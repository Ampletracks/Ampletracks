<?
ini_set('max_execution_time', 120);
define('ACME_RENEWAL_REMAINING_DAYS',30);

$requireLogin = false;

$INPUTS = [
    'verify' => [
        'filename' => 'TEXT',
    ]
];

include('../../lib/core/startup.php');

if(!defined('ACME_ACCOUNT_EMAIL') || !ACME_ACCOUNT_EMAIL) {
    exit;
} else if(!filter_var(ACME_ACCOUNT_EMAIL, FILTER_VALIDATE_EMAIL)) {
	$message = 'Invalid LETS_ENCRYPT_ACCOUNT_EMAIL ('. ACME_ACCOUNT_EMAIL.')';
	$LOGGER->log($message);
	echo $message;
    exit;
}

use LEClient\LEClient;
use LEClient\LEOrder;

$LEDir = DATA_DIR.'/acme/';
if(!is_writable($LEDir)) {
    mkdir($LEDir);
    if(!is_writable($LEDir)) {
        $LOGGER->log("Failed to create Lets Encrypt data directory $LEDir, or directory exists and is not writable");
        exit;
    }
}

$environment = LEClient::LE_PRODUCTION;
if (defined('ACME_USE_STAGING_ENVIRONMENT') && ACME_USE_STAGING_ENVIRONMNET) $environment = LEClient::LE_STAGING;
$client = new LEClient(ACME_ACCOUNT_EMAIL, $environment, false, $LEDir);

$domain = preg_replace('!.*?//(.*?)($|/.*)!', '$1', SITE_URL);

if(ws('mode') == 'renew') {
	$context = stream_context_create(["ssl" => ["capture_peer_cert" => true]]);
	$socketStream = stream_socket_client("ssl://".$domain.":443", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
	$streamParams = stream_context_get_params($socketStream);
	$certInfo = openssl_x509_parse($streamParams['options']['ssl']['peer_certificate']);
	fclose($socketStream);

	if(!is_array($certInfo) || !isset($certInfo['validTo_time_t'])) {
		$LOGGER->log('Error getting SSL certificate for Lets Encrypt renewal');
		exit;
	}

	if($certInfo['validTo_time_t'] - (ACME_RENEWAL_REMAINING_DAYS * 86400) > time()) {
		// No need to renew yet
		exit;
	}
}

$order = $client->getOrCreateOrder($domain, [$domain]);

if(ws('mode') == 'renew') {
	if(!$order->allAuthorizationsValid()) {
		$LOGGER->log('Error - allAuthorizationsValid() failed renewing Lets Encrypt cert');
		exit;
	}

	if(!$order->isFinalized()) {
		$order->finalizeOrder();
	}

	if(!$order->isFinalized()) {
		$LOGGER->log('Error - order not finalized renewing Lets Encrypt cert');
		exit;
	}

	$certSuccess = $order->getCertificate();
	if($certSuccess) {
		$LOGGER->log('Renewed Lets Encrypt certificate');
	} else {
		alert('Failed to renew Lets Encrypt certificate');
		$LOGGER->log('Failed to renew Lets Encrypt certificate');
	}
}

// In apache config...
// RedirectMatch "^/.well-known/acme-challenge/(.*)" "/acme/acmeCheckOrders.php?mode=verify&filename=$1"
if(ws('mode') == 'verify') {
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

$status = "setting up";

if(!$order->allAuthorizationsValid()) {
    // Get the HTTP challenges from the pending authorizations.
    $pending = $order->getPendingAuthorizations(LEOrder::CHALLENGE_TYPE_HTTP);
    // Walk the list of pending authorization HTTP challenges.
    if(!empty($pending)) {
		$status = "verifying domain ownership";
        foreach($pending as $challenge) {
            // Let LetsEncrypt verify this challenge.
            $order->verifyPendingOrderAuthorization($challenge['identifier'], LEOrder::CHALLENGE_TYPE_HTTP);
        }
    }
}

if($order->allAuthorizationsValid()) {
    // Finalize the order first, if that is not yet done.
    if(!$order->isFinalized()) {
		$status = "finalising order";
        $order->finalizeOrder();
    }

    // Check whether the order has been finalized before we can get the certificate. If finalized, get the certificate.
    if($order->isFinalized()) {
		$status = "Complete";
        $order->getCertificate();
    }
}

if (preg_match('/wget/i',$_SERVER['HTTP_USER_AGENT'])) exit;

$title="Let's Encrypt Order Status";

include(VIEWS_DIR.'/header.php');
?>
<h1><?= htmlspecialchars($title) ?></h1>
<p>Using administrative email address: <?=ACME_ACCOUNT_EMAIL?></p>
<p>Current order status: <?=ucfirst($status);?></p>
<? include(VIEWS_DIR.'/footer.php'); ?>


