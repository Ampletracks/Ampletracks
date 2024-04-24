<?
function createAPIKey($userId, $name = '') {
    global $DB;

    $apiKeyId = 0;
    $try = 0; // unlikely, but we might randomly generate an existing code
    while(!$apiKeyId && $try++ < 3) {
        $apiKey = generateKeyString(true);
        $DB->setInsertType('INSERT IGNORE', true);
        $apiKeyId = $DB->insert('userAPIKey', ['userId' => $userId, 'apiKey' => $apiKey, 'name' => $name, 'createdAt' => time()]);
    }

    return [0 => $apiKeyId, 'id' => $apiKeyId, 1 => $apiKey, 'apiKey' => $apiKey];
}

function getAPIKeyUserId($apiKey) {
    global $DB;
    $userId = $DB->getValue('SELECT userId FROM userAPIKey WHERE apiKey = ?', $apiKey);

    return $userId;
}

/**
 * API Keys should use $base64 = true
 * Entity apiIds should use $base64 = false
 */
function generateKeyString($base64) {
    $base64 = (bool)$base64;

    $keyString = openssl_random_pseudo_bytes(20);
    $keyString = sha1($keyString, $base64); // binary if we're base64'ing it later, text if not
    if($base64) {
        $keyString = base64_encode($keyString);
        $keyString = str_replace(['+', '/', '='], ['-', '_', ''], $keyString); // web-safe base64 and get rid of padding '='s
    }

    return $keyString;
}
