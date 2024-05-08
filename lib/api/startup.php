<?
namespace API;

require_once(LIB_DIR.'/api/tools.php');

$authHeader = getallheaders()['Authorization'];
[$authType, $apiKey] = explode(' ', $authHeader.' ');

$userDetails = [];
if($authType == 'Bearer') {
    $userDetails = $DB->getRow('
        SELECT user.id, user.email, user.firstName, user.lastName
        FROM user
        INNER JOIN userAPIKey ON userAPIKey.userId = user.id
        WHERE userAPIKey.apiKey = ?
        AND userAPIKey.deletedAt = 0
        LIMIT 1
    ', $apiKey);
}

if($userDetails === null) {
    usleep(random_int(0, 2000000));
    errorExit(401);
}

$USER_ID = $userDetails['id'];
$USER_EMAIL = $userDetails['email'];
$USER_FIRST_NAME = $userDetails['firstName'];
$USER_LAST_NAME = $userDetails['lastName'];

$API_METHOD = $_SERVER['REQUEST_METHOD'];
$API_ID = '';
$ENTITY = '';
$API_ENTITY_ID = 0;
$API_VARS = [];

$apiPath = trim(getenv('API_PATH'));
if(count($_GET)) {
    // strip off the query string
    $apiPath = substr($apiPath, 0, strrpos($apiPath, '?'));
}
$pathBits = explode('/', $apiPath);
// If present, $apiPath will start with '/' so we get an empty value at the start
// If it's empty we get a single empty value. Either way we don't need the first item
array_shift($pathBits);

do { // allow us to jump out as needed
    // Get the entity type and id
    if(count($pathBits) == 0) {
        break;
    }
    $API_ID = $pathBits[0];
    $apiIdBits = explode('_', $API_ID);
    if(count($apiIdBits) != 2) {
        break;
    }

    if(!$apiIdBits[0] != 'qry') {
        $ENTITY = $DB->getValue('
            SELECT tableName 
            FROM apiIdTablePrefix
            WHERE prefix = ?
        ', $apiIdBits[0]);

        if(!$ENTITY) {
            break;
        }
        // As apiIdTablePrefix is effectively a whitelist we should be safe to use $ENTITY directly
        $API_ENTITY_ID = (int)$DB->getValue('
            SELECT id
            FROM `'.$ENTITY.'`
            WHERE apiId = ?
        ', $apiIdBits[1]);
        if(!$API_ENTITY_ID) {
            break;
        }
    } else {
        $ENTITY = 'NEXT_PAGE';
    }

    // Other url variables
    $idx = 1;
    while($idx < count($pathBits)) {
        if(isset($pathBits[$idx + 1])) {
            $API_VARS[$pathBits[$idx]] = $API_VARS[$pathBits[$idx + 1]];
        }
        $idx += 2;
    }
} while (false);
