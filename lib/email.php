<?
/*
If any of the required parameters (e.g. ZEPTO_SEND_MAIL_TOKEN) are not defined we use an empty string
This will then cause the emailDelivery object to generate its own internal error so
$EMAIL->ok() will be false and we can then get the error by calling $EMAIL->errors();
*/

include(CORE_DIR.'emailDeliveryZepto.php');
global $LOGGER,$EMAIL;

$EMAIL = new emailDeliveryZepto([
    'mailToken' => defined('ZEPTO_SEND_MAIL_TOKEN')?ZEPTO_SEND_MAIL_TOKEN:'',
    'bounceEmail' => defined('EMAIL_BOUNCE_ADDRESS')?EMAIL_BOUNCE_ADDRESS:'',
    'defaultFromName' => defined('EMAIL_FROM_NAME')?EMAIL_FROM_NAME:'',
    'defaultFromEmail' => defined('EMAIL_FROM_ADDRESS')?EMAIL_FROM_ADDRESS:'',
    'userLookupSql' => [
        'user' => 'SELECT firstName, lastName, CONCAT(firstName," ",lastName) AS fullName, email FROM user WHERE id=? AND deletedAt=0',
    ],
    'emailTemplates' => [
        'requestAccess' => ['firstName','lastName','email','mobile','password','supportingStatement','createUserLink'],
    ]
]);

if (!$EMAIL->ok()) {
    $LOGGER->log('Failed to setup email delivery service: '.implode(' & ',$EMAIL->errors()));
    $EMAIL = false;

    if (isset($requireEmail) && $requireEmail) {
        displayError('This page cannot be used because email delivery has not been properly configured for this site');
        exit;
    }
}
