<?

$INPUTS=[
    '.*' => [
        'token'                 => 'TEXT TRIM'
    ],
    'request' => [
        'firstName'             => 'TEXT TRIM',
        'lastName'              => 'TEXT TRIM',
        'email'                 => 'TEXT TRIM',
        'password'              => 'TEXT TRIM'
    ]
];

$requireLogin=false;
include('../../lib/core/startup.php');
include(LIB_DIR.'email.php');

if (!defined('IOD_ROLE') || IOD_ROLE=='slave' || !$EMAIL) {
    displayError('Instance on demand is not enabled on this server');
    exit;
}

if (ws('token')) {
    include(CORE_DIR.'encryptedToken.php');
    $newUserData = decryptEncryptedToken('iodRequest',ws('token'));
    if (!$newUserData) {
        displayError(decryptEncryptedToken('getErrors'));
        exit;
    }

    // If they already have a server up and running then re-send the welcome email
    $alreadyRunning = $DB->getValue('
        SELECT id
        FROM iodRequest
        WHERE email=? AND status="running"
    ',$newUserData['email']);

    if ($alreadyRunning) {
        $DB->update('iodRequest',['id'=>$alreadyRunning],['status'=>'sendWelcome']);
        $message = cms('Instance on demand already running message',1,'Your server is already up and running - we will re-send the details to your email.');
    } else {
        $pendingRequest = $DB->getValue('
            SELECT id
            FROM iodRequest
            WHERE email=? AND status IN ("new","sendWelcome")
        ',$newUserData['email']);

        // If there is already a pending request then don't create a new one
        if (!$pendingRequest) {

            // Check if we have hit the limit for today
            $todayRequestCount = $DB->getValue('
                SELECT COUNT(*) FROM iodRequest
                WHERE createdAt>UNIX_TIMESTAMP()-86400
            ');
            if (defined('IOD_DAILY_LIMIT') && IOD_DAILY_LIMIT<=$todayRequestCount) {
                displayError('Sorry, but the maximum number of test servers that can be spun up in one day has been reached - please try again tomorrow');
                exit;
            } else {

                // Add the request to the request table
                $DB->insert('iodRequest',[
                    'createdAt' => time(),
                    'userData' => json_encode($newUserData),
                    'email' => $newUserData['email'],
                    'status' => 'new'
                ]);
            }
        }
        $message = cms('Instance on demand request confirmed message',1,'We will start spinning up your server and send you an email when it is ready');
    }

    include(VIEWS_DIR.'iod/success.php');
    exit;
}

if (ws('mode')=='request') {
    $checks = [
        'firstName'             => [2,'You must provide your first name'],
        'lastName'              => [2,'You must provide your last name'],
        'email'                 => [2,'You must provide your email address'],
        'password'              => [10,'You must provide a password that is at least 10 characters long'],
    ];
    foreach( $checks as $var=>$check ) {
        if (strlen(ws($var))<$check[0]) inputError($var,$check[1]);
    }
   
    if (!empty(ws('email')) && !filter_var(ws('email'),FILTER_VALIDATE_EMAIL)) inputError('email','The email supplied is not a valid email address');

    if (!inputError() && defined('LOGIN_RECAPTCHA_SITE_KEY') && !checkRecaptcha()) inputError('captcha','You must complete the CAPTCHA test');

    if (!inputError()) {

        $userParams = [
            'firstName' => ws('firstName'),
            'lastName' => ws('lastName'),
            'email' => ws('email'),
            'password' => password_hash(ws('password'),PASSWORD_DEFAULT)
        ];

        include(CORE_DIR.'encryptedToken.php');
        $token = createEncryptedToken('iodRequest',$userParams);

        $link = SITE_URL.'/iod/index.php?token='.rawurlencode($token);
        
        ws('link',preg_replace('!^https?://!','',$link));

        $name = trim($userParams['firstName'].' '.$userParams['lastName']);
        $recipient = ['name'=>$name, 'email'=>$userParams['email']];

        $sendResult = $EMAIL->add([
            'template' => 'iod/confirm-request',
            'to' => [$recipient],
            'priority' => 'immediate',
            'mergeData' => $WS
        ]);
        if (!$sendResult) {
            inputError('general','There was a problem sending the confirmation email - please try again later');
            $LOGGER->log(implode(' & ',$EMAIL->getErrors()));
        }
        if (!inputError()) {
            include(VIEWS_DIR.'iod/confirm.php');
            exit;
        }
    }

}

$extraScripts = ['https://www.google.com/recaptcha/api.js'];

include(VIEWS_DIR.'iod/request.php');
