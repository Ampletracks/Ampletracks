<?
namespace API;

// Use code to suggest an http return code for this exception
class APIException extends \Exception {}

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
            $newApiId = generateKeyString(false);
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