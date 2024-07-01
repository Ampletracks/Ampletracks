<?
namespace API;

$requireLogin = false;
require(LIB_DIR.'/api/startup.php');
require_once(LIB_DIR.'/api/userTools.php');


if ($method == 'GET') {
    $idListSql = "SELECT id FROM user...";
    $entityListSql = 'SELECT * FROM user... WHERE user.id IN (?)';

    if (isset($API_INPUTS['filters'])) {
        $idListSql = addConditions( $idListSql, $API_INPUTS, 'filter_' );
    }

    require(LIB_DIR.'/api/list.php');
} else if ( $API_METHOD == 'PUT' ) {
    $WS = $API_INPUTS;
   
    either one of these two lines...... 
    require(LIB_DIR.'/core/adminPage.php');
    require(LIB_DIR.'/api/edit.php');
}



################################
# Old code below here
################################
$respData = [];

$idListSql = '';
$entityListSql = '';



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
