<?
ini_set('max_execution_time', 120);
define('ACME_RENEWAL_REMAINING_DAYS',30);
define('ACME_SYSTEM_DATA_STATE_KEY','acmeCheckState');
define('DAILY_RENEWAL_ATTEMPT_LIMIT',10);

$requireLogin = false;

$INPUTS = [
    'verify' => [
        'filename' => 'TEXT',
    ]
];

include('../../lib/core/startup.php');

use LEClient\LEClient;
use LEClient\LEOrder;

// First check if the ACME functionality is enabled and properly configured
if(!defined('ACME_ACCOUNT_EMAIL') || !ACME_ACCOUNT_EMAIL) {
    exit;
} else if(!filter_var(ACME_ACCOUNT_EMAIL, FILTER_VALIDATE_EMAIL)) {
	$message = 'Invalid LETS_ENCRYPT_ACCOUNT_EMAIL ('. ACME_ACCOUNT_EMAIL.')';
	$LOGGER->log($message);
	echo $message;
    exit;
}

function loadState($justInitialized=false) {
    $state = systemData('acmeCheckState',null,false); // false at the end means don't use system data cache
    // if this comes back empty it means the state entry is missing from the database so initialise it
    if (empty($state)) {
        // If it comes back empty after we just initialized it this means the database write failed
        if ($justInitialized) return [null,null];
        systemData(ACME_SYSTEM_DATA_STATE_KEY,'0:0');
        return loadState(true);
    }

    return explode(':',$state);
}

function randomDelayDone($timeSinceLastRun) {
    // We want to be pretty much certain that this script will run once every 24h
    // This script runs once a minute so we need to be certain that after 24*60 goes we will have returned true at least once
    // So the probability of NOT returning true after 24*60 goes must be 0.001
    // So that means chance of getting false AND false AND false... 24*60 times must be 0.001
    // Let p be chance of returning false => p^(24*60) = 0.001
    // p = 0.001 ^ (1/(24*60)) = 0.99521443520218
    if ($timeSinceLastRun>86400 || mt_rand(0,10000)>9952) return true;
    return false;
}

function saveState($runsToday) {
    systemData(ACME_SYSTEM_DATA_STATE_KEY,$runsToday.':'.time());
}

function certDoesntNeedRenewing( $crtFile ) {
	static $error = false;

	$cert = file_get_contents($crtFile);	
	if (!$cert) return 'Couldn\'t read existing certificate file';
	$certInfo = openssl_x509_parse($cert);
	if(!is_array($certInfo) || !isset($certInfo['validTo_time_t'])) {
		return 'Couldn\'t read existing certificate details';
	}

	if($certInfo['validTo_time_t'] - (ACME_RENEWAL_REMAINING_DAYS * 86400) > time()) {
		// No need to renew yet
		return false;
	}
	return true;
}

function getCertificate( $client,$domain ) {
    try {
        $order = $client->getOrCreateOrder($domain, [$domain]);
    } catch  (Exception $e) {
        $responsData = $e->getResponseData();
        $error = $e->getMessage();
        if (isset($responsData['body']) && isset($responsData['body']['detail'])) {
            $error = $responsData['body']['detail'];
        }
        return ['error',$error];
    }

    $status = 'setting up';
    $error = '';

    if(!$order->allAuthorizationsValid()) {
        // Get the HTTP challenges from the pending authorizations.
        $pending = $order->getPendingAuthorizations(LEOrder::CHALLENGE_TYPE_HTTP);
        // Walk the list of pending authorization HTTP challenges.
        if(!empty($pending)) {
            $status = 'verifying domain ownership';
            foreach($pending as $challenge) {
                // Let LetsEncrypt verify this challenge.
                $order->verifyPendingOrderAuthorization($challenge['identifier'], LEOrder::CHALLENGE_TYPE_HTTP);
            }
        }
    }

    if($order->allAuthorizationsValid()) {
        // Finalize the order first, if that is not yet done.
        if(!$order->isFinalized()) {
            $status = 'finalising order';
            $order->finalizeOrder();
        }

        // Check whether the order has been finalized before we can get the certificate. If finalized, get the certificate.
        if($order->isFinalized()) {
            $status = 'Complete';
            if (!$order->getCertificate()) {
                $error = 'problem downloading Let\'s Encrypt certificate';
            } else {
                // If the order finalized and successfully created a certificate then copy the ceritificate and key file for safe keeping
                // but ONLY if both certificate and key files are valid
                global $acmeDir;
                $safeDir = $acmeDir.'/safe';
                if (!is_dir($safeDir)) {
                    mkdir( $safeDir );
                    chmod( $safeDir, 0744);
                }
                $certFile = $acmeDir.'/fullchain.crt';
                $keyFile = $acmeDir.'/private.pem';

                if (file_exists($certFile) && file_exists($keyFile)) {
                    $safeCertFile = $safeDir.'/fullchain.crt';
                    $safeKeyFile = $safeDir.'/private.pem';
                    if (
                        !file_exists($safeCertFile) ||
                        !file_exists($safeKeyFile) ||
                        filemtime($certFile) > filemtime($safeCertFile) ||
                        filemtime($keyFile) > filemtime($safeKeyFile)
                    ) {
                        $key = file_get_contents($keyFile);
                        $cert = file_get_contents($certFile);
                        $keyIsValid = openssl_pkey_get_private($key);
                        $certIsValid = openssl_x509_parse($cert);
                        if ($certIsValid !== false && $keyIsValid !== false ) {
                            copy( $certFile, $safeDir.'/fullchain.crt' );
                            copy( $keyFile, $safeDir.'/private.pem' );
                        }
                    }
                }
            }
        } else {
            $error = 'order not finalized whilst renewing Let\'s Encrypt cert';
        }
    } else {
        $error = 'allAuthorizationsValid() failed renewing Let\'s Encrypt cert';
    }

    return [ $status, $error ];
}
list( $runsToday, $lastRunTime ) = loadState();

if (is_null($runsToday)) {
    // This indicates there was a database error getting the state
    // In this case we want to fail here rather than risk hammering LetsEncrypt (unless we're running from a browser)
    if ($calledFromBrowser) {
        list( $runsToday, $lastRunTime ) = [0,0];
    } else {
        $LOGGER->log('Unexpected database error whilst trying to load state of ACME certificate renewal system');
        exit;
    }
}

$timeSinceLastRun = time()-$lastRunTime;
$twelveHours = 3600 * 12;
if ( $timeSinceLastRun > $twelveHours ) {
  $runsToday=0;
}
$calledFromBrowser = isset($_SERVER['HTTP_USER_AGENT']) && !preg_match('/wget/i',$_SERVER['HTTP_USER_AGENT']);

$acmeDir = DATA_DIR.'/acme/';
if(!is_writable($acmeDir)) {
    mkdir($acmeDir);
    if(!is_writable($acmeDir)) {
        $message = "Failed to create ACME data directory $acmeDir, or directory exists and is not writable";
        $LOGGER->log($message);
        echo $message;
        exit;
    }
}

$crtFile = $acmeDir.'/certificate.crt';
$certificatePresent = file_exists($crtFile);

$environment = LEClient::LE_PRODUCTION;
if (defined('ACME_USE_STAGING_ENVIRONMENT') && ACME_USE_STAGING_ENVIRONMENT) $environment = LEClient::LE_STAGING;
$client = new LEClient([ACME_ACCOUNT_EMAIL], $environment, false, $acmeDir);

$domain = preg_replace('!.*?//(.*?)($|/.*)!', '$1', SITE_URL);

// In apache config...
// RedirectMatch "^/.well-known/acme-challenge/(.*)" "/acme/acmeCheckOrders.php?mode=verify&filename=$1"
if(ws('mode') == 'verify') {
    $order = $client->getOrCreateOrder($domain, [$domain]);

    $filename = ws('filename');
    if(empty($filename)) {
        exit;
    }

    $pending = $order->getPendingAuthorizations(LEOrder::CHALLENGE_TYPE_HTTP);
    // Walk the list of pending authorization HTTP challenges.
    if(!empty($pending)) {
        foreach($pending as $challenge) {
            if($filename == $challenge['filename']) {
                echo $challenge['content'];
                break;
            }
        }
    }
    exit;
}

$proceed = false;
$saveState = true;
$certDoesntNeedRenewingError = false;
// If we are running interactively then we press ahead no matter what
if ( $calledFromBrowser ) $proceed=true;
// If we are in the setup phase then press ahead no matter what
else if ( !$certificatePresent ) $proceed=true;
// If we have successfully run today then no need to continue
else if ($runsToday==9999) $proceed=false;
// If we are in a normal running state then don't do anything until the random delay has passed
else if ($runsToday==0 && !randomDelayDone($timeSinceLastRun)) $proceed = $saveState = false;
// If the random delay has passed see if the certificate needs renewing
// Single = below is intentional
else if ( $certDoesntNeedRenewingError = certDoesntNeedRenewing($crtFile) ) {
    $runsToday==9999;
    $proceed=false;
}
else $proceed=true;

if (!$calledFromBrowser) {
	if (strlen($certDoesntNeedRenewingError)) {
		echo $certDoesntNeedRenewingError;
		$LOGGER->log($certDoesntNeedRenewingError);
	}

    if ($saveState) saveState($runsToday);
    if (!$proceed) exit;

    // Don't hit Lets encrypt too much in one day
    if ($runsToday !=9999 && $runsToday > DAILY_RENEWAL_ATTEMPT_LIMIT) {
        $LOGGER->log("ACME certificate renewal process exceeded daily run limit");
        exit;
    }
}

$runsToday++;
// Do the business
list($status,$error) = GetCertificate($client,$domain);
if (!$error) $runsToday=9999;
else {
    $error = 'ERROR - '.$error.' '.$domain;
}

# If we are called from the browser and it all goes OK, then no need for the automated process to check again later
# However, If we are called from the browser and there is an error don't record it
if (!$calledFromBrowser || !$error) {
    saveState($runsToday);
}

if (!$calledFromBrowser) {
	if ($error) $LOGGER->log("ACME certificate renewal process error: $error");
    exit;
}

$title="Let's Encrypt Order Status";

include(VIEWS_DIR.'/header.php');
?>
<h1><?= htmlspecialchars($title) ?></h1>
<div class="questionAndAnswer">
    <div class="question">
        Using administrative email address:
    </div>
    <div class="answer readOnly">
        <?=ACME_ACCOUNT_EMAIL?>
    </div>
</div>
<div class="questionAndAnswer">
    <div class="question">
        Current order status:
    </div>
    <div class="answer readOnly">
        <?=htmlspecialchars(ucfirst($status));?>
    </div>
</div>
<? if ($error) { ?>
    <div class="questionAndAnswer">
        <div class="question">
            Error:
        </div>
        <div class="answer readOnly">
            <?=htmlspecialchars($error);?>
        </div>
    </div>
<? } ?>

<? include(VIEWS_DIR.'/footer.php'); ?>
