<?

include(CORE_DIR.'emailQueue.php');
global $LOGGER,$EMAIL;

$onlySendEmailsTo = trim(getConfig('Only send emails to'));
$onlySendEmailsTo = array_filter(array_map('trim',explode(',',$onlySendEmailsTo)));

$baseOptions = [
    'pauseEmailDelivery' => getConfigBoolean('Pause email delivery'),
    'onlySendEmailsTo' => $onlySendEmailsTo,
    'perminuteEmailThrottle' => (int)getConfig('Email sending throttle per minute'),
    'perhourEmailThrottle' => (int)getConfig('Email sending throttle per hour'),
    'perdayEmailThrottle' => (int)getConfig('Email sending throttle per day'),
];

$emailEngine = getConfig('Email engine');
if ($emailEngine=='SMTP') {
    include(CORE_DIR.'emailDeliverySMTP.php');
    $engine = new emailDeliverySMTP(array_merge($baseOptions,[
        'username' => trim(getConfig('Email SMTP username')),
        'password' => trim(getConfig('Email SMTP password')),
        'server' => trim(getConfig('Email SMTP server')),
        'port' => (int)trim(getConfig('Email SMTP port')),
        'encryptionMechanism' => trim(getConfig('Email SMTP encryption mechanism')),
    ]));
} else {
    include(CORE_DIR.'emailDeliveryNull.php');
    $engine = new emailDeliveryNull();
}

$EMAIL = new emailQueue([
    'deliveryEngine' => $engine,
    'defaultFromName' => getConfig('Email from name'),
    'defaultFromEmail' => getConfig('Email from address'),
    
    'userLookupSql' => [
        'user' => 'SELECT firstName, lastName, CONCAT(firstName," ",lastName) AS name, email FROM user WHERE id=? AND deletedAt=0',
    ],
]);

if (!$EMAIL->ok()) {
    $LOGGER->log('Failed to setup email delivery service: '.implode(' & ',$EMAIL->getErrors()));
    $EMAIL = false;

    if (isset($requireEmail) && $requireEmail) {
        displayError('This page cannot be used because email delivery has not been properly configured for this site');
        exit;
    }
}
