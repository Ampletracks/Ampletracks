<?

namespace API;

// We don't need the core to handle login in this case because we handle authentication separately
$requireLogin = false;
require(dirname(__FILE__).'/../core/startup.php');


require_once(LIB_DIR.'/api/tools.php');

$authHeader = @getallheaders()['Authorization'];
[$authType, $signedAPIKey] = explode(' ', $authHeader.' ');
$apiKey = checkSignedAPIKey($signedAPIKey);

$userDetails = null;
if($apiKey) { // will be null if checkSignedAPIKey() failed
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
}

if($userDetails === null) {
    usleep(random_int(0, 2000000));
    apiErrorExit(401,'No authentication data provided');
}

$USER_ID = $userDetails['id'];
$USER_EMAIL = $userDetails['email'];
$USER_FIRST_NAME = $userDetails['firstName'];
$USER_LAST_NAME = $userDetails['lastName'];

$API_METHOD = strtoupper($_SERVER['REQUEST_METHOD']);
$API_ID = ''; // The ID of the entity as used in any API interactions - this is not the internal ID of the object
$API_ENTITY_ID = 0; // This is the internal database ID of the entity.
$API_VARS = [];
if (!isset($ENTITY)) $ENTITY = '';
$inputValidatorPath = '/'.$ENTITY;

$apiPath = trim(getenv('API_PATH'));
if(count($_GET)) {
    // strip off the query string
    $apiPath = substr($apiPath, 0, strrpos($apiPath, '?'));
}
$pathBits = explode('/', $apiPath);

// If present, $apiPath will start with '/' so we get an empty value at the start
// If it's empty we get a single empty value. Either way we don't need the first item
array_shift($pathBits);

// Path will look like this
//      www.ampletracks.com/api/v1/<entity>/
// The Apache redirect will send this to
//      www/api/v1/<entity>.php
//      with the rest of the path in $pathBits

do { // allow us to jump out as needed
    if (count($pathBits) == 0) {
        // Nothing else to do here as there is no path e.g. user has called /api/v1/user/
        break;
    }

    // Get the entity type and id
    // If the path is longer then the next bit should ALWAYS be an object ID of the form <prefix>_<API ID>
    $API_ID = $pathBits[0];
    $apiIdBits = explode('_', $API_ID);
    if (count($apiIdBits) != 2) {
        apiErrorExit(400,"The object ID is invalid");
    }

    // Getting follow-on pages from previous API calls is a special case
    // These will take the form: /api/v1/<entity>/qry_<query_cache_id>...
    if ($apiIdBits[0] != 'qry') { // this ISN'T a follow-on page request....

        // From this point on we're expecting to find an API entity ID next in the path
        // Validate that the API is looks right
        // Do a lookup on this and resolve this to the internal ID
        $entityCheck = $DB->getValue('
            SELECT tableName 
            FROM apiIdTablePrefix
            WHERE
                prefix = ? AND
        ', $apiIdBits[0], $ENTITY);

        if (!$entityCheck) {
            apiErrorExit(400,empty($ENTITY)?'No API object type specified':'No such API object type:'.$ENTITY);
        } else if ($entityCheck!==$ENTITY) {
            apiErrorExit(400,"The object ID doesn't match the requested object type");
        }

        $inputValidatorPath .= '/{'.$ENTITY.'Id}';

        // As apiIdTablePrefix is effectively a whitelist we should be safe to use $ENTITY directly
        $API_ENTITY_ID = (int)$DB->getValue('
            SELECT id
            FROM `'.$ENTITY.'`
            WHERE apiId = ?
        ', $apiIdBits[1]);
        if(!$API_ENTITY_ID) {
            apiErrorExit(404,'No '.ucfirst($ENTITY).' found with id: '.$apiIdBits[1]);
        }

    } else {
        $ENTITY = 'NEXT_PAGE';
        $API_VARS['listId'] = $API_ID;
    }

    // Other url variables
    $idx = 1;
    while($idx < count($pathBits)) {
        if(isset($pathBits[$idx + 1])) {
            $API_VARS[$pathBits[$idx]] = $pathBits[$idx + 1];
        }
        $idx += 2;
    }
} while (false);

require(LIB_DIR.'/api/inputValidator.php');

if ($ENTITY != 'NEXT_PAGE') {
    // Validating API Inputs
    $inputValidator = new \ApiInputValidator($inputValidatorPath);
    if ($initializationErrors = $inputValidator->errors()) {
        apiErrorExit(500, "Error: " . join(',', $initializationErrors));
        exit;
    }
    $inputErrors = $inputValidator->validateInput();
    if ($inputErrors) {
        foreach( $inputErrors as $field => $error ) {
            if (is_numeric($field)) $field = 'input body';
            $errorMsg = 'Error in '.$field.': '.$error."\n";
        }
        apiErrorExit(400, "Error: " . $errorMsg);
        exit;
    }
}

// Handle standar GET list requests
if($API_ENTITY_ID == 0) {
    if($API_METHOD == 'GET') {
        if (!isset($API_ITEMS_PER_PAGE)) $API_ITEMS_PER_PAGE=100;
        if (!isset($API_ID_MAPPINGS)) $API_ID_MAPPINGS = [ $ENTITY => ['id','apiId']];

        $filters = $inputValidator->getValidInputs('apiFilter_');
        try {
            $responseData = getAPIList($ENTITY, $API_SQL, $API_ID_MAPPINGS, $API_VARS, $filters, $API_ITEMS_PER_PAGE);
        } catch (ApiException $ex) {
            apiErrorExit($ex->getCode(), $ex->getMessage());
        }
        echo json_encode($responseData);
        exit;
    }
}

