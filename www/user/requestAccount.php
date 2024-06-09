<?

$INPUTS=[
    'request' => [
        'firstName'             => 'TEXT TRIM',
        'lastName'              => 'TEXT TRIM',
        'email'                 => 'TEXT TRIM',
        'mobile'                => 'TEXT TRIM',
        'password'              => 'TEXT TRIM',
        'supportingStatement'   => 'TEXT',
    ]
];

$requireLogin=false;
include('../../lib/core/startup.php');

include(LIB_DIR.'email.php');

if (ws('mode')=='request') {

    $checks = [
        'firstName'             => [2,'You must provide your first name'],
        'lastName'              => [2,'You must provide your last name'],
        'email'                 => [2,'You must provide your email address'],
        'mobile'                => [2,'You must provide your mobile number'],
        'supportingStatement'   => [10,'You must provide a reasonably details supporting statement'],
    ];
    foreach( $checks as $var=>$check ) {
        if (strlen(ws($var))<$check[0]) inputError($var,$check[1]);
    }
   
    if (!empty(ws('email')) && !filter_var(ws('email'),FILTER_VALIDATE_EMAIL)) inputError('email','The email supplied is not a valid email address');

    if (!empty(ws('password')) && strlen(ws('password'))<10) inputError('password','The password must be at least 10 characters long');

    if (!inputError() && defined('LOGIN_RECAPTCHA_SITE_KEY') && !checkRecaptcha()) inputError('captcha','You must complete the CAPTCHA test');

    if (!inputError()) {

        $userParams = [
            'user_firstName' => ws('firstName'),
            'user_lastName' => ws('lastName'),
            'user_email' => ws('email'),
            'user_mobile' => ws('mobile'),
        ];
        if (!empty(ws('password'))) $userParams['encryptedPassword'] = password_hash(ws('password'),PASSWORD_DEFAULT);

        include(CORE_DIR.'encryptedToken.php');
        $token = createEncryptedToken('requestAccount',$userParams);

        $createUserLink = SITE_URL.'/user/admin.php?mode=request&token='.rawurlencode($token);
        
        ws('createUserLink',preg_replace('!^https?://!','',$createUserLink));

        $recipientEmails = array_filter( explode(' ', getConfig('New account request email')));
        if (empty($recipientEmails)) {
            inputError('general','There was a problem sending your application to the site administrator - no administrator email address has been configured');
        } else {
            $recipients = [];
            foreach( $recipientEmails as $email ) {
                $recipients[] = ['email'=>$email];
            }

            $mergeData = [
                'firstName' => ws('firstName'),
                'lastName' => ws('lastName'),
                'email' => ws('email'),
                'mobile' => ws('mobile'),
                'supportingStatement' => ws('supportingStatement'),
            ];

            $sendResult = $EMAIL->add([
                'template' => 'user/account-request',
                'to' => $recipients,
                'priority' => 'high',
                'mergeData' => $mergeData
            ]);

            if (!$sendResult) {
                inputError('general','There was a problem sending your application to the site administrator - please try again later');
                $LOGGER->log(implode(' & ',$EMAIL->errors()));
            }
            if (!inputError()) {
                include(VIEWS_DIR.'user/requestAccountSuccess.php');
                exit;
            }
        }
    }

}

$extraScripts = ['https://www.google.com/recaptcha/api.js'];

include(VIEWS_DIR.'user/requestAccount.php');
