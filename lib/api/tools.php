<?
namespace API;

require_once(LIB_DIR.'/api/baseClasses.php');

// Key string types
define('API_KS_API_KEY', 'API Key');
define('API_KS_ENTITY_ID', 'Entity API Id');

function apiErrorExit($code, $message = '') {
    $defaultMessages = [
        400 => 'Bad request',
        401 => 'Unauthenticated',
        403 => 'Forbidden',
        404 => 'Not found',
    ];

    http_response_code($code);
    echo json_encode([
        'code' => $code,
        'message' => $message ?: ($defaultMessages[$code] ?? '')
    ]);
    exit;
}

function getAPIIdPrefix($entityType) {
    global $DB, $LOGGER;

    //  Whenever adding a new one here be sure to add an `apiId` column and corresponding index to the relevant table definition
    $prefixLookup = [
        'user'          => 'u',
        'recordType'    => 'rt',
        'relationship'  => 'rl',
        'role'          => 'ro',
        'dataField'     => 'df',
        'record'        => 'r',
        'project'       => 'p',
    ];

    $idPrefix = $prefixLookup[$entityType] ?? null;

    if(!$idPrefix) {
        $LOGGER->log("Bad entityType ->$entityType<-");
        throw new APIException('Failed', 500);
    }

    return $idPrefix;
}

function checkSetApiIds(&$entities, $entityMap, $removeOriginal = true) {
    foreach($entities as $idx => $entity) {
        foreach( $entityMap as $mapping ) {
            // If the output column is not specified then assume that the original ID column should be overwritten
            if (count($mapping)==3) $mapping[] = $mapping[1];
            list( $entityType, $realIdCol, $apiIdCol, $apiOutputCol ) = $mapping;
            $apiId=null;
            if(strlen($entity[$apiIdCol])<5) {
                if (!empty($entity[$realIdCol])) {
                    $apiId = getAPIId($entityType, $entity[$realIdCol]);
                }
            } else {
                $apiId = $entity[$apiIdCol];
                if (!empty($apiId)) $apiId = getAPIIdPrefix($entityType).'_'.$apiId;
            }
            $entities[$idx][$apiOutputCol] = $apiId;
            if($removeOriginal) {
                if ($realIdCol != $apiOutputCol) unset($entities[$idx][$realIdCol]);
                if ($apiIdCol != $apiOutputCol) unset($entities[$idx][$apiIdCol]);
            }
        }
    }

    return $entities;
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
            $updated = $DB->update($entityType, ['id' => $id, 'apiId' => null], ['apiId' => $newApiId]);
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

function getAPIList($entity, $sql, $apiIdMapping, $apiVars, $filters, $itemsPerPage) {

    if (function_exists('api\processListInputs')) processListInputs($filters);

    if(!canDo('list', $entity)) {
        throw new ApiException('Forbidden', 403);
    }

    $page = 1;
    if($entity == 'NEXT_PAGE') {
        $listIdOrSql = $apiVars['listId'];
        $page = $apiVars['page'];
    } else {
        $listIdOrSql = $sql['getIdList'];
        $limits = getUserAccessLimits(['entity' => $entity, 'prefix' => '']);
        $allFilters = array_merge($filters, $limits);
        addConditions( $listIdOrSql, $allFilters );
    }
    $startIdx = ($page - 1) * $itemsPerPage;

    $idStreamer = new IdStreamer($listIdOrSql, $entity, $startIdx);
    $ids = [];
    foreach($idStreamer->getIds($itemsPerPage) as $id) {
        $ids[] = $id;
    }

    global $DB;
    $DB->returnHash();

    $items = $DB->getRows($sql['getListData'], $ids);
    $items = checkSetApiIds($items, $apiIdMapping );
    if (function_exists('api\processListItem')) {
        foreach( $items as $idx=>$item ) {
            processListItem($items[$idx]);
        }
    }

    $numRecords = $idStreamer->getNumIds();
    $numPages = ceil($numRecords / $itemsPerPage);
    $nextPageUrl = $page < $numPages ? '/api/v1'.$idStreamer->getPageUrl($page + 1) : '';
    return [
        'data' => $items,
        'metadata' => [
            'numRecords' => $numRecords,
            'numPages' => $numPages,
            'nextPageUrl' => $nextPageUrl,
            'pageNumber' => $page,
        ],
    ];
}

function getAPIItem( $entity, $entityId, $sql, $apiIdMapping ) {
    global $DB;

    if (isset($sql['getItem'])) {
        $query = $sql['getItem'];
        $queryParams = $entityId;
    } else {
        $query = $sql['getListData'];
        $queryParams = [$entityId];
    }

    $limits = getUserAccessLimits(['entity' => $entity, 'prefix' => '']);
    addConditions( $query, $limits );
    $DB->returnHash();
    $item = $DB->getRow($query,$queryParams);

    if (function_exists('api\processItem')) {
        processItem($item);
    // If we used the getListData SQL instead of the getItem then look for a processListItem function
    } else if (!isset($sql['getItem'])) {
        if (function_exists('api\processListItem')) {
            processListItem($items[$idx]);
        }
    }

    if (!$item) {
        throw new ApiException(ucfirst($entity).' not found', 404);
    }

    return [
        'data' => $item,
    ];

}

function validateApiId( $apiId, $entity ) {
    global $DB;

    $apiIdBits = explode('_', $apiId);
    if (count($apiIdBits) != 2) return "The $entity ID is invalid";
    
    // Do a lookup on this and resolve this to the internal ID
    $entityCheck = getAPIIdPrefix( $entity );

    if (!$entityCheck) {
        return empty($entity)?'No API object type specified':'No such API object type:'.$entity;
    } else if ($entityCheck!==$apiIdBits[0]) {
        return "The object ID doesn't match the requested object type";
    }

    // As getAPIIdPrefix is effectively a whitelist we should be safe to use $entity directly
    $entityId = (int)$DB->getValue('
        SELECT id
        FROM `'.$entity.'`
        WHERE apiId = ?
    ', $apiIdBits[1]);
    if(!$entityId) return 'No '.ucfirst($entity).' found with id: '.$apiId;

    return $entityId;
}