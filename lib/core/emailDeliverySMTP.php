<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once LIB_DIR.'/PHPMailer/Exception.php';
require_once LIB_DIR.'/PHPMailer/PHPMailer.php';
require_once LIB_DIR.'/PHPMailer/SMTP.php';

class emailDeliverySMTP {
    private $errors = [];

    // These must all be passed in as options to the constructor
    private $username;
    private $password;
    private $server;
    private $port;
    private $encyrptionMechanism;

    public function __construct($options) {
        if (empty($options['username'])) return $this->error('The SMTP username must be provided');
        if (!isset($options['password'])) return $this->error('The SMTP username must be provided');
        if (empty($options['server'])) return $this->error('The SMTP server name must be provided');
        if (empty($options['port'])) return $this->error('The SMTP port must be provided');
        if (empty($options['encryptionMechanism'])) return $this->error('The SMTP encryption mechanism must be specified');
        if (!in_array($options['encryptionMechanism'],['SMPTS','STARTTLS'])) return $this->error('The SMTP encryption mechanism must either SMTPS or STARTTLS');

        $reflection = new ReflectionClass($this);
        $vars = $reflection->getProperties(ReflectionProperty::IS_PRIVATE);
        foreach( $vars as $var ) {
            $var = $var->getName();
            if (isset($options[$var])) $this->$var = $options[$var];
        }

    }

    public function getErrors() {
        return $this->errors;
    }

    private function error( $error ) {
        $this->errors[] = $error;
        return false;
    }

    public function deliver($details) {

        $this->errors = [];

        $mail = $mail = new PHPMailer(true); 
        //Server settings
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER;                   //Enable verbose debug output
        $mail->isSMTP();                                            //Send using SMTP
        $mail->Host       = $this->server;                          //Set the SMTP server to send through
        $mail->SMTPAuth   = true;                                   //Enable SMTP authentication
        $mail->Username   = $this->username;                        //SMTP username
        $mail->Password   = $this->password;                        //SMTP password
        $mail->SMTPSecure = $this->encyrptionMechanism=='SMTPS' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $this->port;                            //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

        try {
            //Recipients
            $mail->setFrom($details['from']['email'], $details['from']['name']);
            if (isset($details['replyTo']) && strlen($details['replyTo']['email']??'')) {
                $mail->addReplyTo($details['replyTo']['email'], $details['replyTo']['name']);
            }
            foreach( array('to'=>'addAddress','cc'=>'addCC','bcc'=>'addBCC') as $type=>$method) {
                if (!isset($details['recipients'][$type])) {
                    $details['recipients'][$type] = [];
                }
                foreach( $details['recipients'][$type] as $recipient ) {
                    if (isset($recipient['name'])) {
                        $mail->$method($recipient['email'],$recipient['name']);
                    } else {
                        $mail->$method($recipient['email']);
                    }
                }
            }

            if (isset($details['attachments'])) {
                foreach($details['attachments'] as $name=>$attachment) {
                    if (preg_match('/^[0-9]+$/',$name)) {
                        $mail->addAttachment($attachment);
                    } else {
                        $mail->addAttachment($attachment,$name);
                    }
                }
            }
        } catch (Exception $e) {
            $this->errors[] = $mail->ErrorInfo;
        }

        if (count($this->errors)) return false;
        
        //Content
        $mail->isHTML(true);                                  //Set email format to HTML
        $mail->Subject = $details['subject'];
        $mail->Body    = $details['body'];

        $mail->CharSet = 'UTF-8';
        if (function_exists('config_mailSetup')) config_mailSetup($mail);

        try {
            $mail->Send();
        } catch (Exception $e) {
            $this->errors[] = $mail->ErrorInfo;
        }
        return count($this->errors) ? false:true;
    }
}
