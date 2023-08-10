<?php

class emailDeliveryZepto {

    // These must all be passed in as options to the constructor
    private $mailToken = '';
    private $userLookupSql;
    private $emailTemplates;
    private $bounceEmail;
    private $defaultFromName;
    private $defaultFromEmail;

    // These can optionally be passed in as options to the constructor
    private $errorHandler = false;

    // These are computed/used internally
    private $userTypeRegex;
    private $warnings=[];
    private $errors=[];
    private $configuredOk = false;

    public function __construct($options) {

        // The return's below are simply used to short-circuit any subsequent code execution rather than to actually return anything.
        if (empty($options['userLookupSql'])) return $this->error($options, 'The user lookup queries have note been defined');
        if (empty($options['emailTemplates'])) return $this->error($options, 'The list of valid email templates have note been defined');
        if (empty($options['bounceEmail'])) return $this->error($options, 'The email bounce address has not been defined in the site configuration file');
        if (empty($options['mailToken']) ) return $this->error($options, 'The email configuration details have not been defined in the site configuration file');
        if (empty($options['defaultFromName'])) return $this->error($options, 'The email configuration details have not been defined in the site configuration file');
        if (empty($options['defaultFromName'])) return $this->error($options, 'The email configuration details have not been defined in the site configuration file');

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

    public function warnings($raw=false) {
        if ($raw) return $this->warnings;
        return array_map('htmlspecialchars',$this->warnings);
    }

    public function errors($raw=false) {
        if ($raw) return $this->errors;
        return array_map('htmlspecialchars',$this->errors);
    }

    private function error($options, $error) {
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
    from => an object containing keys: "email" and "name" - if not specified then EMAIL_FROM_NAME and EMAIL_FROM_ADDRESS will be used
    replyTo => an object containing keys: "email" and "name" - if not specified then EMAIL_FROM_NAME and EMAIL_FROM_ADDRESS will be used

    attachments => an array of objects each containing keys: "pathname" and "displayFilename"
    mergeData => an object keyed on merge field name containing merge field data

    This will return true if email is sent and false if there was an error.
    To get details of any error call errors() method immediately after your call to sendEmail

    The email will send as long as there is at least one "to" address. If there are any other bogus addresses (in to, cc or bcc) then the email will still send and the function will return true.
    If you want to get warnings of addresses that were ignored then you can call the warnings() method immediately after your call to sendEmail - this will return an array of warnings.
    */

    public function send( $options ) {

        $this->warnings = [];
        $this->errors = [];
        if (!$this->configuredOk) return $this->error($options,'Email sending has not been configured');
        if (!isset($options['priority'])) return $this->error($options,'The priority for the email was not set');
        if (!isset($options['to'])) return $this->error($options,'The recipient of the email was not defined');
        if (!isset($options['template'])) return $this->error($options,'The template for the email was not defined');

        $replyToName = $fromName = $this->defaultFromName;
        $replyToEmail = $fromEmail = $this->defaultFromEmail;

        if (isset($options['from']) && isset($options['from']['name'])) $fromName = $options['from']['name'];
        if (isset($options['from']) && isset($options['from']['email'])) $fromEmail = $options['from']['email'];
        if (isset($options['replyTo']) && isset($options['replyTo']['name'])) $replyToName = $options['replyTo']['name'];
        if (isset($options['replyTo']) && isset($options['replyTo']['email'])) $replyToEmail = $options['replyTo']['email'];

        global $DB;
        $mergeData = $options['mergeData'] ?? [];

        $to = $cc = $bcc = [];

        foreach(['to','cc','bcc'] as $type) {
            $$type = [];
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
                    $toAdd = [ 'address' => $recipient ];

                } else {

                    // Now we check for an object containing userType and userId
                    if (isset($recipient['userType']) && isset($recipient['userId'])) {
                        if (isset($this->userLookupSql[$recipient['userType']])) {
                            $DB->returnHash();
                            $newRecipient = $DB->getRow($this->userLookupSql[$recipient['userType']],$recipient['userId']);
                            if (empty($newRecipient)) $warnings[] = 'Couldn\'t find '.$recipient['userType'].' with ID '.$recipient['userId'].' in "'.$type.'" address list';
                            else $recipient = $newRecipient;
                        } else {
                            $warnings[] = 'Unrecognised user type ('.$recipient['userType'].') in "'.$type.'" address list';
                        }
                    }

                    // Now we check for an object containing address and email
                    if (empty($recipient)) {
                        // do nothing
                    } else if (isset($recipient['email'])) {
                        $toAdd = ['address' => $recipient['email']];
                        if (isset($recipient['name'])) $toAdd['name'] = $recipient['name'];
                    } else {
                        $warnings[] = 'The following "'.$type.'" recipient was ignored because it was not a recognized structure : '.json_encode($recipient);
                    }
                }

                if (!empty($toAdd)) {
                    if (filter_var($toAdd['address'], FILTER_VALIDATE_EMAIL)){
                        $$type[] = [ 'email_address' => $toAdd ];
                    } else {
                        $warnings[] = 'The following "'.$type.'" address was ignored because it was invalid: '.$toAdd['address'];
                    }
                }
            }
        }

        if (empty($to)) return $this->error($options,'No valid recipient address was specified for the email');

        $mailDetails = [
            "template_alias" => $options['template'],
            "to" => $to,
            "merge_info" => $mergeData,
            "from" => [
                "address" => $fromEmail,
                "name" => $fromName
            ],
            "reply_to" => [
                "address" => $replyToEmail,
                "name" => $replyToName
            ],
        ];

        if (count($cc)) $emailDetails['cc'] = $cc;
        if (count($bcc)) $emailDetails['bcc'] = $bcc;

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.zeptomail.eu/v1.1/email/template",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($mailDetails),
            CURLOPT_HTTPHEADER => [
                'accept: application/json',
                'authorization: Zoho-enczapikey '.$this->mailToken,
                'cache-control: no-cache',
                'content-type: application/json',
            ],
        ));

        $response = curl_exec($curl);
        $curlError = curl_error($curl);

        if ($curlError) {
            $this->errors[] = $curlError;
            return false;

        } else {
            $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            if ($status > 299) {
                $decodedResponse = @json_decode( $response, true );
                if (empty($decodedResponse['details']['message'])) {
                    $this->errors[] = 'Unrecognised response from Zepto: '.$response;
                } else {
                    $this->errors[] = $decodedResponse['details']['message'];
                }
                return false;
            }
        }

        curl_close($curl);
        return true;
    } // Function send

} // Class
