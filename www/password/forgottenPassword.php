<?
$INPUTS = [
    'sendLink' => [
        'email' => 'TEXT',
    ],
    '' => [
        'code' => 'TEXT',
    ],
    'reset' => [
        'code' => 'TEXT',
        'password' => 'TEXT',
        'confirmPassword' => 'TEXT',
    ],
];

$requireLogin = false;
include('../../lib/core/startup.php');

if(ws('mode') == '' && ws('code')) {
    ws('mode', 'startReset');
}

$show = 'sendForm';
$error = '';

if(ws('mode') == 'startReset' || ws('mode') == 'reset') {
    [$userId, $email] = checkPasswordResetCode(ws('code'));
    if(!$userId) {
        $error = $email;
    } else {
        $show = 'resetForm';
        if(ws('mode') == 'reset') {
            if(!ws('password')) {
                inputError('password', 'Please enter a new password');
            } else if(ws('password') != ws('confirmPassword')) {
                inputError('confirmPassword', 'Passwords don\'t match');
            } else {
                $DB->update('user',
                    ['id' => $userId],
                    ['password' => password_hash(ws('password'), PASSWORD_DEFAULT)]
                );
                $show = 'resetSuccess';
            }
        }
    }
} else if(ws('mode') == 'sendLink') {
    $linkError = sendPasswordResetLink(ws('email'));
    $show = 'linkSent';
}

include(VIEWS_DIR.'/password/forgottenPassword.php');
