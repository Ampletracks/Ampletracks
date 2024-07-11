<?
namespace API;

$requireLogin = false;
require('../../../lib/api/startup.php');

require(LIB_DIR.'/api/inputValidator.php');
//$inputSpecIngestionResult = \ApiInputValidator::ingestInputSpecifications('./openApi.json.php');
//if ($inputSpecIngestionResult !== true) {
//    errorExit(500,'API Specification Ingestion failed with errors: ' . join(', ', $inputSpecIngestionResult));
//    exit;
//}

// Validating API Inputs
$inputValidator = new \ApiInputValidator('/project');
$initializationErrors = $inputValidator->errors();
if ($initializationErrors) {
    errorExit(500, "Error: " . join(',', $initializationErrors));
    exit;
}

require_once(LIB_DIR.'/api/projectTools.php');

try {
    if($API_ENTITY_ID == 0) {
        if($API_METHOD == 'GET') {
            $respData = getProjectList($ENTITY, $API_VARS, $inputValidator->getValidInputs());
        }
    }
} catch (ApiException $ex) {
    errorExit($ex->getCode, $ex->getMessage());
}

echo json_encode($respData);
