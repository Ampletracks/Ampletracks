<?
namespace API;

$requireLogin = false;
require('../../../lib/core/startup.php');
require(LIB_DIR.'/api/startup.php');
require_once(LIB_DIR.'/api/userTools.php');

if(
    ($ENTITY && $ENTITY != 'user') ||
    ($API_ENTITY_ID == 0 && count($API_VARS) > 0)
) {
    errorExit(400);
}

$respData = [];

try {
    if($API_ENTITY_ID == 0) {
        if($API_METHOD == 'GET') {
            $respData = getUserList($_GET);
        } else if($API_METHOD == 'POST') {
            $respData['id'] = createUser($_POST);
        } else {
            errorExit(404);
        }
    } else if(count($API_VARS) == 0) {
        if($API_METHOD == 'GET') {
            $respData = getUserData($API_ENTITY_ID);
        } else if($API_METHOD == 'PATCH') {
            updateUser($API_ENTITY_ID, $_POST);
        } else if($API_METHOD == 'DELETE') {
            deleteUser($API_ENTITY_ID);
        } else {
            errorExit(404);
        }
    } else {
        errorExit(404);
    }
} catch (APIException $aEx) {
    errorExit($aEx->getCode(), $aEx->getMessage());
}

echo json_encode(['status' => 'OK', 'data' => $respData]);
