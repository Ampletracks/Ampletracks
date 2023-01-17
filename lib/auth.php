<?

$USER_ID=0;
session_start();

$persistLoginCookieSigningSecret = 'persistLogin_kdsnldkjnl'.SECRET;
$persistLoginCookieName = md5(SITE_NAME.'persistLogin'.SECRET);
                         
if ( isset($_REQUEST['mode']) && $_REQUEST['mode']=='logout' ) {
    session_destroy();
    // remove the persistLogin cookie
    setcookie( $persistLoginCookieName, '', time(), '/', '', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']=='on'), true );
    unset($_SESSION['USER_ID']);
}

// Check the persitLogin cookie if present
$persistLogin = false;
if (isset($_COOKIE[$persistLoginCookieName])) {
    $bits = explode(':',$_COOKIE[$persistLoginCookieName]);
    if (count($bits)==5) {
        $sig = array_pop($bits);
        $desiredSig = hash_hmac('sha256',implode(':',$bits),$persistLoginCookieSigningSecret);
        if (
            hash_equals( $desiredSig, $sig ) && // Signature matches
            $bits[3] > time() && // Not expired
            $bits[2] > time()-86400*7 // Not allowed to be issued more than 7 days ago
        ) {
            // Make sure the email address matches
            $username = $DB->getValue('SELECT email FROM user WHERE id=?',$bits[0]);
            if (hash_equals(md5($username),$bits[1])) {
                $persistLogin=true;
            }
        }
    }
}


if (
    $persistLogin ||
    (
        isset($_REQUEST['mode']) && $_REQUEST['mode']=='login' &&
        isset($_REQUEST['username']) && strlen(isset($_REQUEST['username'])) &&
        isset($_REQUEST['password']) && strlen(isset($_REQUEST['password']))
    )
) {
    global $showCaptcha;
    $showCaptcha = false;
    
    if (!$persistLogin) $username = $_REQUEST['username'];
    
    $hashedUsername = substr(hash('sha256',getLocalSecret().SECRET.$username),0,32);
    // See if the account is currently locked due to failed logins and just requires a captcha
    if (defined('LOGIN_RECAPTCHA_SECRET_KEY') && LOGIN_RECAPTCHA_SECRET_KEY) {
        if (!defined('LOGIN_LOCK_TIMEOUT')) define('LOGIN_LOCK_TIMEOUT',1800);
        if (!defined('LOGIN_ATTEMPTS_BEFORE_CAPTCHA')) define('LOGIN_ATTEMPTS_BEFORE_CAPTCHA',3);

        # Remove any login locks that have timed out
        $DB->exec('DELETE FROM failedLogin WHERE lastFailedAt<UNIX_TIMESTAMP()-?',LOGIN_LOCK_TIMEOUT);


        # See if we have a login lock record for this user
        $numAttempts = $DB->getValue('SELECT attempts FROM failedLogin WHERE hashedUsername=?',$hashedUsername);
        if ($numAttempts>=LOGIN_ATTEMPTS_BEFORE_CAPTCHA) {
            # If CAPTCHA supplied then see if it is valid
            $captcha=isset($_POST['g-recaptcha-response'])?$_POST['g-recaptcha-response']:'';
            if (strlen($captcha) && checkRecaptcha($captcha)) {
                    # OK let them continue
            } else {
                $showCaptcha = true;
                if ($numAttempts>LOGIN_ATTEMPTS_BEFORE_CAPTCHA) {
                    inputError('login',"There was a problem with the CAPTCHA result - please try again.");
                } else {
                    inputError('login',"Due to recent login activity on your account we need you to prove that you're not a robot. If you are not a robot AND the username and password you just supplied are correct then you will be logged in.");
                }
            }
        }
    }

    if (!$showCaptcha) {
                
        // Get the password from the database
        $DB->returnHash();
        $userData = $DB->getRow('SELECT password, email AS EMAIL, id AS ID, firstName AS FIRST_NAME, lastName AS LAST_NAME FROM user WHERE !user.deletedAt AND email=?',$username);
        // Prevent timing attacks on by filling in bogus password and ID if lookup failed
        if (!is_array($userData) || !count($userData)) $userData = array('ID'=>0,'password'=>'$2y$10$57MkiQmrjryJjJLoiAhvgO..EjizJWq7X/jgWCmSZZrFuEN24sL5i');

        if ($persistLogin || (password_verify($_REQUEST['password'],$userData['password']) && $userData['ID'])) {
            
            // User has just logged in
            unset($userData['password']);
            foreach($userData as $key=>$value) {
                $key = 'USER_'.$key;
                $_SESSION[$key]= $value;
            }
                
            // clear out the record of any failed logins
            if ($hashedUsername) $DB->delete('failedLogin',array('hashedUsername'=>$hashedUsername));

            // Update the last login time
            $DB->update('user',['id'=>$userData['ID']],[
                'lastLoggedInAt'    => time(),
                'lastLoginIp'       => inet_aton($_SERVER['REMOTE_ADDR'])
            ]);

            // If they clicked the "persist login" checkbox then give them a longer-lived cookie
            if (isset($_REQUEST['persistLogin'])) {
                list($time,$sig) = explode(':',$_REQUEST['persistLogin'].':::');
                $desiredSig = hash_hmac('sha256',$time,$persistLoginCookieSigningSecret.'_token');
                if ($time>time()-600 && hash_equals( $desiredSig,$sig)) {
                    $expiry = time()+86400*7;
                    $cookie = implode(':',array($_SESSION['USER_ID'],md5($_SESSION['USER_EMAIL']),time(),$expiry));
                    $cookie .= ':'.hash_hmac('sha256',$cookie,$persistLoginCookieSigningSecret);
                    setcookie( $persistLoginCookieName, $cookie, $expiry, '/', '', $_SERVER['HTTPS']=='on', true );
                }
            }
        }
    }
    
    if (!isset($_SESSION['USER_ID'])) {

        // make a note of the failed login
        if ($hashedUsername) {
            $updated = $DB->exec('UPDATE failedLogin SET lastFailedAt=UNIX_TIMESTAMP(), attempts=attempts+1 WHERE hashedUsername=?',$hashedUsername);
            if (!$updated) {
                $DB->insert('failedLogin', array(
                    'lastFailedAt' 		=> time(),
                    'attempts'			=> 1,
                    'hashedUsername'	=> $hashedUsername
                ));
            }
        }

        inputError('login','Incorrect username or password - please try again');
        // record this failed login
        
    }
    
}

if (!isset($_SESSION['USER_ID'])) {
    if (isset($requireLogin) && !$requireLogin) {
        $USER_ID=0;
        $USER_EMAIL='';
        $USER_FIRST_NAME='';
        $USER_LAST_NAME='';
    } else {
        $persistLoginToken = time().':'.hash_hmac('sha256',time(),$persistLoginCookieSigningSecret.'_token');
        include(VIEWS_DIR.'/login.php');
        exit;
    }
} else {
    foreach( array('ID','EMAIL','FIRST_NAME','LAST_NAME') as $key ) {
        $key = 'USER_'.$key;
        $$key = $_SESSION[$key];
    }
}

