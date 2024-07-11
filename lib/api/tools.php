<?
namespace API;

require_once(LIB_DIR.'/api/baseClasses.php');

// Key string types
define('API_KS_API_KEY', 'API Key');
define('API_KS_ENTITY_ID', 'Entity API Id');

function errorExit($code, $message = '') {
    $defaultMessages = [
        400 => 'Bad request',
        401 => 'Unauthenticated',
        403 => 'Forbidden',
        404 => 'Not found',
    ];

    http_response_code($code);
    echo $message ?: ($defaultMessages[$code] ?? '');
    exit;
}

function getAPIIdPrefix($entityType) {
    global $DB, $LOGGER;

    $idPrefix = $DB->getValue('
        SELECT prefix
        FROM apiIdTablePrefix 
        WHERE tableName = ?
    ', $entityType);
    if(!$idPrefix) {
        $LOGGER->log("Bad entityType ->$entityType<-");
        throw new APIException('Failed', 500);
    }

    return $idPrefix;
}

function checkSetApiIds($entityData, $entityType, $realIdCol = 'realId', $apiIdCol = 'id', $removeRealId = true) {
    $idPrefix = getAPIIdPrefix($entityType);
    foreach($entityData as $idx => $entity) {
        if(strlen($entity[$apiIdCol]) <= strlen($idPrefix.'_')) {
            $entity[$apiIdCol] = getAPIId($entityType, $entity[$realIdCol]);
        }
        if($removeRealId) {
            unset($entity[$realIdCol]);
        }
        $entityData[$idx] = $entity;
    }

    return $entityData;
}

function getAPIId($entityType, $id) {
    global $DB, $LOGGER;

    $idPrefix = getAPIIdPrefix($entityType);

    // If we've got here we know $entityType is safe to use
    $idBase = $DB->getValue('
        SELECT apiId
        FROM `'.$entityType.'`
        WHERE id = ?
    ', $id);
    if(!$idBase) {
        $updated = false;
        $try = 0;
        while(!$updated && $try++ < 3) {
            $newApiId = generateKeyString(API_KS_ENTITY_ID);
            $updated = $DB->update($entityType, ['id' => $id, 'apiId' => ''], ['apiId' => $newApiId]);
            if($updated) {
                $idBase = $newApiId;
            }
        }

        if(!$idBase) {
            // There's a tiny chance that someone else set apiId
            // between us not getting it at the top and us trying to set it
            // Do a last check here
            $idBase = $DB->getValue('
                SELECT apiId
                FROM `'.$entityType.'`
                WHERE id = ?
            ', $id);
            if(!$idBase) {
                $LOGGER->log("Failed to generate apiId for ->$entityType|$id<-");
                throw new APIException('Failed', 500);
            }
        }
    }

    return $idPrefix.'_'.$idBase;
}

function createAPIKey($userId, $name = '') {
    global $DB;

    $apiKeyId = 0;
    $try = 0; // unlikely, but we might randomly generate an existing code
    while(!$apiKeyId && $try++ < 3) {
        $apiKey = generateKeyString(API_KS_API_KEY);
        $DB->setInsertType('INSERT IGNORE', true);
        $apiKeyId = $DB->insert('userAPIKey', ['userId' => $userId, 'apiKey' => $apiKey, 'name' => $name, 'createdAt' => time()]);
    }

    return [0 => $apiKeyId, 'id' => $apiKeyId, 1 => $apiKey, 'apiKey' => $apiKey];
}

// Not used?
function getAPIKeyUserId($apiKey) {
    global $DB;

    $userId = $DB->getValue('SELECT userId FROM userAPIKey WHERE apiKey = ?', $apiKey);

    return $userId;
}

if(IS_DEV && !defined('API_KEY_SIGNING_SECRET')) {
    define('API_KEY_SIGNING_SECRET', '#R##6F+p9ZQNPwAhM/J');
}

function signAPIKey($baseAPIKey) {
    return $baseAPIKey.'!!'.hash_hmac('sha1', $baseAPIKey, API_KEY_SIGNING_SECRET, false);
}

/**
 * Returns the passed key if the signature is valid, null if not
 */
function checkSignedAPIKey($signedAPIKey) {
    [$baseAPIKey, $signature] = explode('!!', $signedAPIKey.'!!');
    if(!$baseAPIKey || !$signature) {
        return null;
    }

    if(!hash_equals(hash_hmac('sha1', $baseAPIKey, API_KEY_SIGNING_SECRET, false), $signature)) {
        return null;
    }

    return $signedAPIKey;
}

/**
 * $type: apiKey, entityAPIId
 */
function generateKeyString($type) {
    $base64 = false;
    $signed = false;
    if($type == API_KS_API_KEY) {
        $base64 = true;
        $signed = true;
    }

    $keyString = openssl_random_pseudo_bytes(20);
    $keyString = sha1($keyString, $base64); // binary if we're base64'ing it later, text if not
    if($base64) {
        $keyString = base64_encode($keyString);
        $keyString = str_replace(['+', '/', '='], ['-', '_', ''], $keyString); // web-safe base64 and get rid of padding '='s
    }
    if($signed) {
        $keyString = signAPIKey($keyString);
    }

    return $keyString;
}
