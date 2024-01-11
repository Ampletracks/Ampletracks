<?

function alert( $message ) {
    global $USER_ID, $DB, $USER_TYPE;

    $backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 1);

    return $DB->insert('systemAlert',array(
        'time'      => time(),
        'userId'    => $USER_ID,
        'userType'  => $USER_TYPE,
        'file'      => $backtrace[0]['file'],
        'lineNumber'  => $backtrace[0]['line'],
        'message'   => $message
    ));
}

function getConfig( $key, $default='' ) {
    static $config;

    if (!is_array($config)) {
        # load in the config for this page
        global $DB, $_SERVER;
        $pathBits = explode('/',$_SERVER['SCRIPT_NAME']);
        array_shift($pathBits);
        $path = '';
        $pathOptions = "'/','";
        foreach( $pathBits as $idx=>$bit ) {
            $path.='/'.$bit;
            $pathOptions .= addSlashes($path)."','";
        }
        $pathOptions = substr($pathOptions,0,-2);
        $config = $DB->getHash("SELECT name,value FROM configuration WHERE path IN($pathOptions)");
    }
    if (!isset($config[$key])) return $default;
    return $config[$key];
}

function logAction($entity, $entityId, $message ) {
    global $USER_ID, $DB;
    return $DB->insert('actionLog',array(
        'time'      => time(),
        'userId'    => $USER_ID,
        'entity'    => $entity,
        'entityId'  => $entityId,
        'message'   => $message
    ));
}

# inputError() => Number of errors
# inputError('') => Display all errors
# inputError('*') => Return all errors as an array
# inputError('fieldName') => Display errors for specified field
# inputError('fieldName','errorMessage') => Record error "errorMessage" against field "FieldName"
# inputError('fieldName',false) => Number of errors against field "FieldName"
function inputError($field=null, $message=null) {
	static $errors = array();
	
	if ($field===null) {
		# we are in error-counting mode
		return count($errors);
	}

    if ($message===false) return( (isset($errors[$field]) && is_array($errors[$field])) ? count($errors[$field]) : 0);

    if ($message===null) {
        if (($field==='*')) return $errors;

		# we are in display mode
        if ($field==='') $fields = array_keys($errors);
        else { 
    		if (!isset($errors[$field])) return;
            $fields = array($field);
        }

		echo '<ul class="error">';
        foreach ($fields as $field) {
    		foreach ($errors[$field] as $error) {
	    		echo '<li>'.cms($error,0).'</li>';
		    }
        }
		echo '</ul>';
	} else if ($message===false) {
        return( isset($errors[$field]) ? count($errors[$field]) : 0);
    } else {
		# we are in setting mode
		if (!isset($errors[$field])) $errors[$field]=array();
		$errors[$field][] = $message;
	}
}

function displayError($message, $showMenu=true, $useCms=true) {
	if (is_array($message) && count($message)==1) $message = array_pop($message);
    
	if ($useCms) {
        if (is_array($message)) {
            foreach( $message as $idx=>$subMessage ) {
                $message[$idx] = cms('ERROR: '.$subMessage,0,$subMessage);
            }
        } else {
		    $error = cms('ERROR: '.$message,1,$message);
        }
    }

    if (is_array($message)) {
        $plainTextError = implode("\n",$error);
    } else {
        $plainTextError = $error;
    }

	if (isAjaxRequest()) {
        echo $plainTextError;
		exit;
	}

    logAction('error', 0, $plainTextError );

    if (is_array($message)) {
		$error = '<ul>';
		foreach( $message as $subMessage ) {
			$error .= '<li>'.$subMessage.'</li>';
		}
		$error .= '</ul>';
	}

    include(VIEWS_DIR.'/error.php');

	exit;
}

function matchCase($string, $toMatch) {
	# check for all caps
	if (preg_match('/^[\\W0-9A-Z]+$/',$toMatch)) return strtoupper($string);
	# check for uc first
	if (preg_match('/\\b[A-Z][a-z]/',$toMatch)) return ucfirst($string);

	return $string;	
}

# Given a message referring to the singular it pluralizes it depending on the value of count
# It looks for the magic phrases enclosed in double square brackets and pluralizes them if neccessary
# e.g. pluralize('There [[is an error]] in the data you provided for this entry. [[This is]] shown in red below. Please correct [[this]] and then check the entry again',$numErrors);

function pluralizeText($message, $count) {	
	if ($count==1) return preg_replace('/\\[\\[|\\]\\]/','',$message);

	static $setup = 0;
	$setup |= include_once('pluralize.php');
	
	static $lookup = 0;
	$lookup || $lookup = array(
		'is'		=> 'are',
		'was'		=> 'were',
		'will be'	=> 'will be',
		'has been'	=> 'have been',
		'have been'	=> 'have been'
	);
	
	static $lookFor = 0;
	$lookFor || $lookFor = implode('|',array_keys($lookup));

	$message = preg_replace_callback(
		"/\\[\\[($lookFor) (?:a|an|1)?\\W+(\\w+)\\]\\]/",
		function($matches) use(&$count,&$lookup) {
			return $lookup[$matches[1]].' '.($count?$count:'no').' '.pluralizeNouns($matches[2]);
		},
		$message
	);

	$message = preg_replace_callback(
		"/\\[\\[(this)(\\s+(?:$lookFor))?\\]\\]/i",
		function($matches) use(&$count,&$lookup) {
			if (!isset($matches[2]) || !strlen($matches[2])) return matchCase('these ',$matches[1]);
			return matchCase('these ',$matches[1]).$lookup[trim($matches[2])];
		},
		$message
	);

	$message = preg_replace_callback(
		"/\\[\\[(\\w+)\\]\\]/i",
		function($matches) use(&$count) {
			return pluralizeNouns($matches[1]);
		},
		$message
	);
	
	return $message;
}

function timezoneSelect($name,$display=true) {
    $timezoneSelect = new formOptionbox($name,array('-- Select --'=>''));
    $timezones = timezone_identifiers_list();
    $timezoneSelect->addOptions(array_combine($timezones,$timezones));
    
    if ($display) $timezoneSelect->display();
    return $timezoneSelect;
}

function timezoneWarning($which='time', $display=1) {
    $return = sprintf('<span class="%s" timezone="%s" timezoneOffset="%s"></span>',$which,date('T'),date('Z')/-60);
    if ($display) echo $return;
    return $return;
}

function formatDateTime( $unixTime, $empty='') {
    if (!$unixTime) return $empty;
    return rawOutput(sprintf('<span class="dateTime" timezone="%s" timezoneOffset="%s">%s</span>',date('T'),date('Z')/-60,date('D M d, Y h:i A',$unixTime)));
}

function formatDate( $unixTime, $empty='') {
    if (!$unixTime) return $empty;
    return date('M d, Y',$unixTime);
}

function formatTime( $unixTime ) {
    $format = 'h:i A';
    if ($unixTime<86400) return gmdate($format,$unixTime);
    return date($format,$unixTime);
}

function formatCurrencyAmount( $amt, $currency ) {
	return ($amt<0?'-':'') . ($currency=='GBP'?'&pound;':'$') . number_format(abs($amt),2,'.',',' );
}

function formatPrice( $price, $currency ) {
    $symbolLookup = array(
        'EUR'   => '&euro;',
        'GBP'   => '&pound;',
        'USD'   => '&dollar;',
    );
    if (isset($symbolLookup[$currency])) $symbol = $symbolLookup[$currency];
    else $symbol = '';
    // pass a null price if you want just the currency symbol on its own
    $minus=$price<0?'-':'';
    return $minus.$symbol.sprintf('%0.2f',abs($price));
}

function makeLinksOpenInNewWindow( $markup ) {
    return preg_replace( '/(<a\\s[^>]*)href="/i','$1 target="_blank" href="',$markup);
}

function tabIndex($display = false) {
    static $tabIndex = 0;
    $tabIndex++;
    $markup = " tabindex=\"$tabIndex\" ";
    if ($display) echo $markup;
    return $markup;
}

function generatePassword($numWords=3,$dictionaryFile='') {
	if ($dictionaryFile==='') {
		$dictionaryFile = SITE_BASE_DIR.'/config/passwordDictionary.txt';
	}
	$fh = fopen($dictionaryFile,'r');
	$maxSeek = filesize($dictionaryFile)-20;
	if ($maxSeek<1) return '';
	$password = '';
	for ($wordCount=1; $wordCount<=$numWords; $wordCount++) {
		$seekPos = mt_rand(0,$maxSeek);
		fseek($fh, $seekPos);
		fgets($fh);
		$counter = 0;
		do {
			$word = trim(fgets($fh));
			$counter++;
		} while ($counter<10 && !feof($fh) && !strlen($word));
		$password .= ucfirst($word);
	}
	return $password;
}

if(!function_exists('hash_equals')) {
  function hash_equals($str1, $str2) {
    if(strlen($str1) != strlen($str2)) {
      return false;
    } else {
      $res = $str1 ^ $str2;
      $ret = 0;
      for($i = strlen($res) - 1; $i >= 0; $i--) $ret |= ord($res[$i]);
      return !$ret;
    }
  }
}

function checkRecaptcha($response=null) {
    if (is_null($response)) {
        $response = isset($_REQUEST['g-recaptcha-response'])?$_REQUEST['g-recaptcha-response']:'';
    }
    if (!strlen($response)) return false;
    global $_SERVER;
    $postData = array(
        'secret'    => LOGIN_RECAPTCHA_SECRET_KEY,
        'response'  => $response,
        'remoteip'  => $_SERVER['REMOTE_ADDR'],
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://www.google.com/recaptcha/api/siteverify");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    $verdict = curl_exec($ch);
    $verdict = json_decode($verdict);
    curl_close($ch);

    return (isset($verdict->success) && $verdict->success == true);
}

function getPasswordResetSignature($userId, $expiry) {
    return substr(hash('sha256', $userId.SIGNING_SECRET_1.$expiry.SIGNING_SECRET_2), 0, 10);
}

function checkPasswordResetCode($code) {
    global $DB;

    [$userId, $expiry, $signature] = explode('x', $code);

    $error = '';
    if(
        !$userId || !$expiry || !$signature ||
        $signature != getPasswordResetSignature($userId, $expiry)
    ) {
        $error = 'bad link';
    } else if($expiry < time()) {
        $error = 'expired';
    }

    if($error) {
        return [null, $error];
    }

    $checkResult = $DB->getRow('SELECT id, email FROM user WHERE id = ?', $userId);
    return $checkResult ?: [null, 'bad link'];
}

function sendPasswordResetLink($email) {
    global $DB, $WS;

    $userId = $DB->getValue('SELECT id FROM user WHERE email = ?', $email);
    if(!$userId) {
        return 'No user';
    }

    $expiryHours = getConfig('Password reset link expiry');
    $expiry = time() + 60 * 60 * $expiryHours;

    $signedId = $userId.'x'.$expiry.'x'.getPasswordResetSignature($userId, $expiry);
    ws('signedId', $signedId);
    ws('expiry', date('d F Y \\a\\t H:i', $expiry));
    ws('resetLink', SITE_URL.'/password/forgottenPassword.php?code='.$signedId);

    /*DEBUG*/
    //global $LOGGER; $LOGGER->log("reset link ->{$WS['resetLink']}<-");
    //return false;

    include(LIB_DIR.'email.php');
    $sendResult = $EMAIL->send([
        'template' => 'password-reset',
        'to' => [
            ['userType' => 'user', 'userId' => $userId],
        ],
        'priority' => 'medium',
        'mergeData' => $WS
    ]);
    if(!$sendResult) {
        global $LOGGER;
        $LOGGER->log("Error sending password reset to $email [$userId]:".print_r($EMAIL->errors(true), true));
        return 'send error';
    }

    return false;
}

class Autoloader
{
    public static function register()
    {
        spl_autoload_register(function ($class) {
            if (strpos($class,'\\')===false) return false;
            list( $lib, $class ) = explode('\\',$class,2);
            $file = LIB_DIR.'/'.$lib.'/src/'.str_replace('\\', '/', $class).'.php';
            if (file_exists($file)) {
                require $file;
                return true;
            }
            return false;
        });
    }
}
Autoloader::register();

