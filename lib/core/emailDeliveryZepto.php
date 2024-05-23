<?php

include 'emailDelivery.php';

class emailDeliveryZepto extends emailDelivery{

    // These must all be passed in as options to the constructor
    private $mailToken = '';

    public function __construct($options) {

        parent::__construct($options);

        // The return's below are simply used to short-circuit any subsequent code execution rather than to actually return anything.
        if (empty($options['mailToken']) ) return $this->error($options, 'The email configuration details have not been defined in the site configuration file');
    }

    protected function deliver( $details ) {

        $mailDetails = [
            "template_alias" => $details['template'],
            "to" => $details['recipients']['to'],
            "merge_info" => $details['mergeData'],
            "from" => $details['from'],
            "reply_to" => $details['replyTo'],
        ];

        if (count($details['recipients']['cc'])) $emailDetails['cc'] = $details['recipients']['cc'];
        if (count($details['recipients']['bcc'])) $emailDetails['bcc'] = $details['recipients']['bcc'];

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
    } // Function deliver

} // Class
