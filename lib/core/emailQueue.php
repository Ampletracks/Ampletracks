<?php

class emailQueue {

    // These must all be passed in as options to the constructor
    private $userLookupSql;
    private $defaultFromName;
    private $defaultFromEmail;
    private $deliveryEngine;

    // These can optionally be passed in as options to the constructor
    private $errorHandler = false;
    private $defaultReplyToName = '';
    private $defaultReplyToEmail = '';
    private $pauseEmailDelivery = false;
    private $onlySendEmailsTo = [];
    private $perMinuteEmailThrottle = 0;
    private $perHourEmailThrottle = 0;
    private $perDayEmailThrottle = 0;

    // These are computed/used internally
    private $userTypeRegex;
    protected $warnings=[];
    protected $errors=[];
    protected $configuredOk = false;

    public function __construct($options) {

        // The return's below are simply used to short-circuit any subsequent code execution rather than to actually return anything.
        if (!is_object($options['deliveryEngine'])) return $this->error($options, 'The email delivery engine was not specified');
        // Check the delivery engine is happy n.b. single equality below is intentional
        if ($errors = $options['deliveryEngine']->getErrors()) {
            $this->errors = array_map(function($item) { return 'Email delivery engine error:' . $item; }, $errors);
            return false;
        }
        if (empty($options['userLookupSql'])) return $this->error($options, 'The user lookup queries for email delivery have note been defined');
        if (empty($options['defaultFromName'])) return $this->error($options, 'The email configuration details (email from name) have not been defined in the site configuration file');
        if (empty($options['defaultFromEmail'])) return $this->error($options, 'The email configuration details (email from address) have not been defined in the site configuration file');

        $reflection = new ReflectionClass($this);
        $vars = $reflection->getProperties(ReflectionProperty::IS_PRIVATE);
        foreach( $vars as $var ) {
            $var = $var->getName();
            if (isset($options[$var])) $this->$var = $options[$var];
        }

        $this->userTypeRegexp = '\\Q'.implode('\\E|\\Q',array_keys($this->userLookupSql)).'\\E';

        $this->configuredOk = true;
    }

    public function ok() {
        return $this->configuredOk;
    }

    public function getWarnings($raw=false) {
        if ($raw) return $this->warnings;
        return array_map('htmlspecialchars',$this->warnings);
    }

    public function getPriorities($forOptionbox=false) {
        // If it's for an optionbox, then don't let people choose "immediate"
        if ($forOptionbox) return ['High'=>'high','Medium'=>'medium','Low'=>'low'];
        return ['immediate','high','medium','low'];
    }
    public function getErrors($raw=false) {
        if ($raw) return $this->errors;
        return array_map('htmlspecialchars',$this->errors);
    }

    protected function error($options, $error) {
        if ($this->errorHandler) ($this->errorHandler)($options,$error);
        $this->errors[] = $error;
        return false;
    }

    /*
    This method is called with an options object
    Keys marked below with an asterisk* are mandatory

    template* => The name of the email template to use

    priority* => A case insensitive string containing either "immediate" "high" "medium" or "low"
    sendAfter => unixTimestamp (not applicable to "immediate" priority emails)
    to* => array of to recipients. Each recipient can either be...
            - An email address as a string
            - an object containing keys: "email" and "name"
            - a string of the form: "<userType>:<userId>"
            - an object containing keys: "userType" and "userId"
    cc => as above for "to"
    bcc => as above for "to"
    from => an object containing keys: "email" and "name" - if not specified then defaults supplied on object creation will be used
    replyTo => an object containing keys: "email" and "name" - if not specified then  defaults supplied on object creation will be used

    attachments => an array of objects each containing keys: "pathname" and "displayFilename"
    mergeData => an object keyed on merge field name containing merge field data

    This will return true if email is sent and false if there was an error.
    To get details of any error call getErrors() method immediately after your call to sendEmail

    The email will send as long as there is at least one "to" address. If there are any other bogus addresses (in to, cc or bcc) then the email will still send and the function will return true.
    If you want to get warnings of addresses that were ignored then you can call the warnings() method immediately after your call to sendEmail - this will return an array of warnings.
    */

    public function add( $options ) {

        global $DB;

        $this->warnings = [];
        $this->errors = [];
        if (!$this->configuredOk) return $this->error($options,'Email sending has not been configured');
        if (!isset($options['priority'])) return $this->error($options,'The priority for the email was not set');
        if (!isset($options['to'])) return $this->error($options,'The recipient of the email was not defined');
        if (!isset($options['template'])) return $this->error($options,'The template for the email was not defined');

        // Check that the template is defined in the database
        $emailTemplateData = $DB->getRow('SELECT id,extraCc,extraBcc,disabled,defaultStatus FROM emailTemplate WHERE name=?',$options['template']);
        if (!$emailTemplateData) return $this->error($options,'Unrecognised email template: '.$options['template']);

        // If the email has been disabled then don't go any further
        if ($emailTemplateData['disabled']) return true;

        $fromDetails = [];
        if (isset($options['from'])) $fromDetails['from'] = $options['from'];
        if (isset($options['replyTo'])) $fromDetails['replyTo'] = $options['replyTo'];

        global $DB;
        $mergeData = $options['mergeData'] ?? [];

        $to = $cc = $bcc = [];
        $seenMainRecipient = false;

        foreach(['to','cc','bcc'] as $type) {
            $$type = [];
            $extra = trim($emailTemplateData['extra'.ucfirst($type)] ?? '');
            if (!empty($extra)) {
                $extra = array_filter(array_map('trim',explode(',',$extra)));
                foreach( $extra as $recipient ) {
                    if (!empty($recipient) && filter_var($recipient, FILTER_VALIDATE_EMAIL) ) $$type[] = [ 'email' => $recipient ];
                }
            }
            if (!isset($options[$type])) continue;
            $recipients = $options[$type];
            if (!is_array($recipients)) $recipients=[$recipients];
            foreach ($recipients as $recipient) {
                $toAdd = false;
                if (is_string($recipient) && preg_match('/^('.$this->userTypeRegexp.'):(\\d+)$/',$recipient,$matches)) {
                    $recipient = ['userType'=>$matches[1], 'userId'=>$matches[2]];
                }

                // If it is still a string at this point then it must be an email address
                if (is_string($recipient)){
                    $toAdd = [ 'email' => $recipient ];

                } else {

                    // Now we check for an object containing userType and userId
                    if (isset($recipient['userType']) && isset($recipient['userId'])) {
                        if (isset($this->userLookupSql[$recipient['userType']])) {
                            $DB->returnHash();
                            $newRecipient = $DB->getRow($this->userLookupSql[$recipient['userType']],$recipient['userId']);
                            if (empty($newRecipient)) $this->warnings[] = 'Couldn\'t find '.$recipient['userType'].' with ID '.$recipient['userId'].' in "'.$type.'" address list';
                            else $recipient = $newRecipient;
                            // If this is the first "To" lookup we've done then add the results of the SQL query to the mergeData
                            if ($type=='to' && !$seenMainRecipient) {
                                $seenMainRecipient = true;
                                $mergeData = array_merge($mergeData,$recipient);
                            }
                        } else {
                            $this->warnings[] = 'Unrecognised user type ('.$recipient['userType'].') in "'.$type.'" address list';
                        }
                    }

                    // Now we check for an object containing address and email
                    if (empty($recipient)) {
                        // do nothing
                    } else if (isset($recipient['email'])) {
                        $toAdd = ['email' => $recipient['email']];
                        if (isset($recipient['name'])) $toAdd['name'] = $recipient['name'];
                    } else {
                        $this->warnings[] = 'The following "'.$type.'" recipient was ignored because it was not a recognized structure : '.json_encode($recipient);
                    }
                }

                if (!empty($toAdd)) {
                    if (filter_var($toAdd['email'], FILTER_VALIDATE_EMAIL)){
                        $$type[] = $toAdd;
                    } else {
                        $this->warnings[] = 'The following "'.$type.'" address was ignored because it was invalid: '.$toAdd['email'];
                    }
                }
            }
        }

        if (empty($to)) return $this->error($options,'No valid recipient address was specified for the email');

        // If email delivery is currently paused then downgrade any immediate emails to high so they get queued rather than sent
        if ($options['priority']=='immediate' && $this->pauseEmailDelivery) $options['priority']=='high';

        // Create a new ID for the email
        $emailDetails = [
            'status'=>$emailTemplateData['defaultStatus'],
            'emailTemplateId'=>$emailTemplateData['id'],
            'fromDetails'=>json_encode($fromDetails),
            'priority'=>$options['priority'],
            'sendAfter'=>isset($options['sendAfter']) ? (int)$options['sendAfter'] : time()
        ];
        $emailId = $DB->insert('email',$emailDetails);
        if (!$emailId) return $this->error($options,'Unexpected error adding email to the queue');

        // Write the mergeData to a file
        $mergDataFilename = $this->getMergeDataFilename( $emailTemplateData['id'], $emailId );
        file_put_contents( $mergDataFilename, json_encode($mergeData) );

        // Save the recipients
        $alreadySeenRecipients = array();
        foreach( array('to','cc','bcc') as $addressType ) {
            foreach( $$addressType as $recipient ) {
                if (!isset($recipient['name'])) $recipient['name']='';
                $emailAddressId = $DB->getEntityId( $created, 'emailAddress', array( 'email' => $recipient['email'], 'name' => $recipient['name']));

                if (isset($alreadySeenRecipients[$emailAddressId])) continue;
                $alreadySeenRecipients[$emailAddressId] = true;
                $DB->insert('emailRecipient',[
                    'emailId'           => $emailId,
                    'type'              => $addressType,
                    'emailAddressId'    => $emailAddressId
                ]);
            }
        }

        if ($options['priority']=='immediate') return $this->unpackAndSend($emailId);

        return true;
    }

    protected function getMergeDataFilename( $templateId, $emailId ) {
        return sprintf('%s/emailMergeData/%d_%d.json',DATA_DIR,$templateId,$emailId);
    }

    public function getSubjectAndBody( $emailId ) {
        global $DB;

        $emailData = $DB->getRow('SELECT * FROM email WHERE id=?',$emailId);
        if (!$emailData) return $this->error('Couldn\'t find email with ID: '.$emailId);

        $mergDataFilename = $this->getMergeDataFilename( $emailData['emailTemplateId'], $emailId );
        if (!file_exists( $mergDataFilename )) return $this->error('Couldn\'t find merge data for email ID: '.$emailId);

        $mergeData = file_get_contents( $mergDataFilename );
        $mergeData = @json_decode( $mergeData, true );

        $templateData = $DB->getRow('SELECT * FROM emailTemplate WHERE id=?',$emailData['emailTemplateId']);
        $subject = $templateData['subject'];
        $body = $templateData['body'];

        $mergeDataCallback = function($matches) use($mergeData) {
            static $context='subject';
            if (!is_array($matches)) return $context = $matches;

            $lookup = strip_tags($matches[1]);

            if (!isset($mergeData[$lookup])) {
                return '';
            }
            $value = $mergeData[$lookup];

            if (is_array($value)) {
                if (count($value)==1) $value = array_pop($value);
                else if (!count($value)) $value = '';
            };

            if ($context=='subject') {
                if (is_array($value)) $value = implode(',',$value);
                return preg_replace('/[\\r\\n]+/',' ',$value);
            }
            else {
                if (is_array($value)) {
                    foreach( $value as $id=>$val ) { $value[$id]=htmlspecialchars($val); }
                    return '<ul><li>'.implode('</li><li>',$value).'</li></ul>';
                }
                return nl2br(htmlspecialchars($value));
            }
        };

        // Switch the merge function into html mode
        $mergeDataCallback('subject');
        $subject = preg_replace_callback('/\{\{([^\{]+)\}\}/',$mergeDataCallback,$subject);

        // Switch the merge function into html mode
        $mergeDataCallback('html');
        $body = preg_replace_callback('/\{\{([^\{]+)\}\}/',$mergeDataCallback,$body);

        return [
            'subject'   => $subject,
            'body'      => $body,
        ];
    }

    public function getAddresses( $emailId ) {
        global $DB;

        // First From and Reply To
        $addresses = [
            'from' => [
                'name' => $this->defaultFromName,
                'email' => $this->defaultFromEmail
            ],
            'replyTo' => []
        ];

        if ( !empty($this->defaultReplyToEmail) ) {
            $addresses['replyTo'] = [
                'name' => $this->defaultReplyToName,
                'email' => $this->defaultReplyToEmail
            ];
        }

        $fromDetails = $DB->getValue('SELECT fromDetails FROM email WHERE id=?',$emailId);
        $fromDetails = empty($fromDetails) ? [] : json_decode($fromDetails);

        foreach(['from','replyTo'] as $which) {
            if (isset($fromDetails[$which])) {
                if (isset($fromDetails[$which]['name']) && strlen($fromDetails[$which]['name'])) {
                    $addresses[$which]['name'] = $fromDetails[$which]['name'];
                }
                if (isset($fromDetails[$which]['email']) && strlen($fromDetails[$which]['email'])) {
                    $addresses[$which]['email'] = $fromDetails[$which]['email'];
                }
            }
        }

        // Then To CC and BCC
        $addresses['to'] = [];
        $addresses['cc'] = [];
        $addresses['bcc'] = [];

        $addressData = $DB->getRows('
            SELECT
                emailRecipient.type,
                emailAddress.name,
                emailAddress.email
            FROM
                emailRecipient
                INNER JOIN emailAddress ON emailAddress.id=emailRecipient.emailAddressId
            WHERE
                emailRecipient.emailId=? 
        ',$emailId);
        foreach( $addressData as $recipient ) {
            $addresses[$recipient['type']][] = [
                'name' => $recipient['name'],
                'email' => $recipient['email']
            ];
        }

        return $addresses;
    }

    public function unpackAndSend( $emailId, $debug ) {
        global $DB;

        if ($debug) {
            echo "\n\n==========================\nUnpacking and sending email: $emailId\n\n";
        }

        $subjectAndBody = $this->getSubjectAndBody( $emailId );
        if ($debug) {
            dump($subjectAndBody,false);
        }

        if (!$subjectAndBody) return false;

        $addresses = $this->getAddresses( $emailId );
        if ($debug) {
            echo "Email address details are:\n";
            dump($addresses,false);
        }

        // If "Only send emails to" configuration parameter is set then remove any addresses not specified in that list
        $skippedEmails = [];
        $onlySendEmailsTo = (array)$this->onlySendEmailsTo;
        if (count($onlySendEmailsTo)) {
            foreach( ['to','cc','bcc'] as $type ) {
                foreach($addresses[$type] as $idx=>$recipient) {
                    $reverseRecipient = strrev($recipient['email']);
                    $okToSend = false;
                    foreach( $onlySendEmailsTo as $email) {
                        if (strpos( $reverseRecipient, strrev($email) )===0) {
                            $okToSend = true;
                            break;
                        }
                    }
                    if (!$okToSend) {
                        $skippedEmails[] = $recipient['email'];
                        if ($debug) echo "SKIPPING RECIPIENT ".htmlspecialchars($recipient['email'])." because they aren't in the \"Only send email to\" config setting\n";
                        unset( $addresses[$type][$idx] );
                    }
                }
                // re-index the array so we don't leave any gaps if things have been removed
                // Not essential but probably worth doing to avoid possible future bugs
                $addresses[$type] = array_values($addresses[$type]);
            }
        }

        $emailLogFile = SITE_BASE_DIR.'/log/email_'.date('Ymd').'.log';
        $log = @fopen($emailLogFile,'a');

        if (count($skippedEmails)) {
            $logLine = 'Following addresses removed from next email because they weren\'t in "Only send emails to" configuration setting: '.implode(', ',array_unique( $skippedEmails ));
            if ($log) fputs($log,'INFO '.$logLine);
        }

        $numRecipients = 0;
        foreach( ['to','cc','bcc'] as $type ) {
            $numRecipients += count($addresses[$type]);
        }

        if ($numRecipients==0) {
            $logLine = sprintf('No recipients %sto send email to: %s',count($skippedEmails)?'left ':'',$subjectAndBody['subject']);
            if ($debug) echo "$logLine\n";
            if ($log) fputs($log,'INFO '.$logLine);
            return true;
        }

        $emailDetails = [
            'body' => $subjectAndBody['body'],
            'subject' => $subjectAndBody['subject'],
            'from' => $addresses['from'],
        ];
        if (isset($addresses['replyTo'])) $emailDetails['replyTo'] = $addresses['replyTo'];
        unset($addresses['from']);
        unset($addresses['replyTo']);

        $emailDetails['recipients'] = $addresses;

        if ($debug) echo "About do deliver email\n";
        $sentOK = $this->deliveryEngine->deliver($emailDetails);

        $logLine =
            date('H:i:s').
            ' from:'.$emailDetails['from']['name'].' <'.$emailDetails['from']['email'].'>'.
            ' to:'.implode(',',array_column($emailDetails['recipients']['to'],'email')).
            ' cc:'.implode(',',array_column($emailDetails['recipients']['cc'],'email')).
            ' bcc:'.implode(',',array_column($emailDetails['recipients']['bcc'],'email')).
            ' '.$subjectAndBody['subject']."\n"
        ;
        if (!$sentOK) {
            $errors = $this->deliveryEngine->getErrors();
            $this->errors = array_merge(
                $this->errors,
                array_map(function($item) { return 'Email delivery engine error:' . $item; }, $errors)
            );
            if ($log) {
                fputs($log,'ERROR '.$logLine);
                fputs($log,'ERROR '.implode("; ",$this->errors));
            }
            if ($debug) {
                echo "$logLine\n";
                dump($this->errors,false);
            }
            $maxDeliveryRetries=5;
            $deliveryRetryTime=600; // Seconds
            $DB->exec('
                UPDATE email SET
                    sendAtempts = sendAttempts+1,
                    lastSentAttemptedAt = UNIX_TIMESTAMP(),
                    # Retry delivery after 10 minutes
                    sendAfter = UNIX_TIMESTAMP()+?,
                    status = IF(sendAttempts>?,"error",status),
                    lastError = ?
                WHERE id=?
            ',$maxDeliveryRetries, $deliveryRetryTime, implode("\n",$this->errors), $emailId);
            return false;
        } else {
            if ($log) fputs($log,'OK '.$logLine);
            if ($debug) echo "$logLine\n";
            $DB->exec('
                UPDATE email SET
                    sendAttempts = sendAttempts+1,
                    lastSendAttemptedAt = UNIX_TIMESTAMP(),
                    status="sent"
                WHERE id=?
            ',$emailId);
            return true;
        }
    } // Function send


    function processQueue( $debug=false ) {

        global $DB;

        if ($debug) $debug("Waiting for database lock for email queue processing\n");

        // Use MySQL named locks to prevent more than one copy of this running at a time
        // Set this to 9 seconds so this doesn't end up in the slow query log
        $gotLock = $DB->getValue('SELECT GET_LOCK(?,9)',__FILE__);
        if (!$gotLock) {
            $message = "Another process is currently handling email delivery";
            if ($debug) $debug($message);
            return false;
        } else {
            if ($debug) $debug("Got lock");
        }

        $emailsToSend = 1000;

        // See how many emails have been sent in the last day and the last hour.
        list($dayEmailCount,$hourEmailCount, $minuteEmailCount) = $DB->getRow('
            SELECT COUNT(*), SUM(IF(IFNULL(lastSendAttemptedAt,0)>UNIX_TIMESTAMP()-3600,1,0)), SUM(IF(IFNULL(lastSendAttemptedAt,0)>UNIX_TIMESTAMP()-60,1,0))
            FROM email
            WHERE
                status="sent" AND
                lastSendAttemptedAt>UNIX_TIMESTAMP()-86400
        ');
        if ($debug) $debug("Emails sent in last 24h: $dayEmailCount\nEmails sent in last hour: $hourEmailCount\nEmails sent in last 60s: $minuteEmailCount");

        foreach(['day','hour','minute'] as $timeframe ) {
            $ucTimeframe = ucfirst( $timeframe );
            $throttle = $this->{"per{$ucTimeframe}EmailThrottle"};
            if (!$throttle) continue;
            $count = ${"{$timeframe}EmailCount"};
            if ((int)$throttle==0) continue;
            if ($count>=$throtle) {
                $message = "Per-{$timeframe} email delivery throttle of $throttle reached.";
                if ($debug) $debug($message);
                return $message;
            }
            $allowanceLeft = $throttle - $count;
            if ($allowanceLeft < $emailsToSend) $emailsToSend = $allowanceLeft;
        }

        $query = $DB->query('
            SELECT email.id, emailTemplate.disabled
            FROM
                email
                INNER JOIN emailTemplate ON emailTemplate.id=email.emailTemplateId
            WHERE
                sendAfter<UNIX_TIMESTAMP() AND
                status="new"
            ORDER BY
                FIELD(priority,"immediate","high","medium","low") ASC, sendAfter ASC
            LIMIT 
        '.$emailsToSend);

        while($query->fetchInto($row)) {
            if ($debug) {
                $debug("\nConsidering sending the following email...");
                $debug(print_r($row,true));
            }
            $id = $row['id'];
            // If the template has been disabled since the email was created then remove the email
            if ($row['disabled']) {
                if ($debug) $debug("Deleting email {$id} because the template is now disabled");
                $DB->delete('email',['id'=>$id]);
                continue;
            }
            if ($debug) $debug("Sending email id: {$id}");
            $this->unpackAndSend( $id, $debug );
        }

        return true;
    } // Function processQueue

} // Class
