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

    // Getting follow-on pages from previous API calls is a special case
    // These will take the form: /api/v1/<entity>/qry_<query_cache_id>...
    if (strpos($API_ID,'qry_')===0) {
        $ENTITY = 'NEXT_PAGE';
        $API_VARS['listId'] = $API_ID;
    } else {
        $API_ENTITY_ID = validateApiId( $API_ID, $ENTITY );
        if (!is_int($API_ENTITY_ID)) {
            apiErrorExit(400, $result );
        }

        $inputValidatorPath .= '/{'.$ENTITY.'Id}';
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

// Handle standard GET list requests
if($API_ENTITY_ID == 0) {
    if($API_METHOD == 'GET') {
        if (!isset($API_ITEMS_PER_PAGE)) $API_ITEMS_PER_PAGE=100;
        if (!isset($API_ID_MAPPINGS)) $API_ID_MAPPINGS = [ [ $ENTITY, 'id', 'apiId' ] ];

        $filters = $inputValidator->getValidInputs('apiFilter_');
        try {
            $responseData = getAPIList($ENTITY, $API_SQL, $API_ID_MAPPINGS, $API_VARS, $filters, $API_ITEMS_PER_PAGE);
        } catch (ApiException $ex) {
            apiErrorExit($ex->getCode(), $ex->getMessage());
        }
        echo json_encode($responseData);
        exit;
    } else if($API_METHOD == 'POST') {
        $WS = array_merge($WS, $inputValidator->getValidInputs());
        $WS['mode'] = 'update';
        unset($WS['id']); // Shouldn't ever be set but may as well make sure
        include(LIB_DIR.'/core/adminPage.php');

        if(!$WS['id']) {
            $allErrors  = inputError('*');
            if(count($allErrors)) {
                apiErrorExit(400, $allErrors[array_key_first($allErrors)][0]);
            } else if(stripos($DB->lastError, 'duplicate entry') !== false) {
                apiErrorExit(400, "Duplicate $ENTITY data");
            } else {
                apiErrorExit(400);
            }
        }

        try {
            $apiId = getAPIId($ENTITY, $WS['id']);
        } catch (ApiException $ex) {
            apiErrorExit($ex->getCode(), $ex->getMessage());
        }
        echo json_encode(['id' => $apiId]);
        exit;
    }
} else {
    // At this point we are dealing with a request like /api/v1/<entity>/<entity_id> or /api/v1/<entity>/<entity_id>/...

    // get rid of the first element of $pathBits - that is the ID and we're done with that now
    array_shift($pathBits);

    // $pathbits will now either be empty, or it will have a sub-entity
    if (count($pathBits) && !empty($pathBits[0])) {

        // OK... so we have a subentity
        // cleanse the subEntity
        $subEntity = strtolower(preg_replace('/[^a-zA-Z_-]/','',$pathBits[0]));

        // See if there is a sub-entity ID after the sub-entity
        array_shift($pathBits);
        $subEntityId = 0;
        if (count($pathBits) && !empty($pathBits[0])) {
            // So we have a sub-entity ID - check this
            $subEntityId = validateApiId( $pathBits[0], $subEntity );
            if (!is_int($subEntityId)) {
                apiErrorExit(404, $subEntityId);
            }
        }

        $handlerName = 'api\handle'.ucfirst($subEntity);
        if (!function_exists($handlerName)) {
            apiErrorExit(404, 'No such endpoint');
        } else {
            try {
                // First do the same lookup we would do for the individual record
                $responseData = getAPIItem($ENTITY, $API_ENTITY_ID, $API_SQL, $API_ID_MAPPINGS, [] );
                // Then pass the result of this to the handler - the handler can grab this by reference to make any changes they want
                $handlerName( $responseData, $API_METHOD, $subEntityId );
            } catch (ApiException $ex) {
                apiErrorExit($ex->getCode(), $ex->getMessage());
            }
        }
    } else {

        // No sub-entity so just a simple GET
        try {
            $responseData = getAPIItem($ENTITY, $API_ENTITY_ID, $API_SQL, $API_ID_MAPPINGS, $API_VARS );
        } catch (ApiException $ex) {
            apiErrorExit($ex->getCode(), $ex->getMessage());
        }
    }

    echo json_encode($responseData);
    exit;

}

