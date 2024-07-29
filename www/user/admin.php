<?
$INPUTS = array(
    'request'   => array(
        'token' => 'TEXT TRIM'
    ),
    'update'    => array(
        'recordTypeIds' => 'INT ARRAY',
        'roleIds' => 'INT ARRAY',
        'projectIds' => 'INT ARRAY',
        'encryptedPassword' => 'TEXT',
        'userDefaultAnswer_question' => 'TEXT TRIM',
        'userDefaultAnswer_answer' => 'TEXT TRIM',
        'userDefaultAnswer_matchType' => 'TEXT TRIM',
    ),
    'deleteDefaultAnswer' => array(
        'userDefaultAnswer_id' => 'INT'
    ),
    'questionNameSearch' => array(
        'ttsSearch' => 'TEXT'
    ),
    'userDefaultSort' => array(
        'id' => 'INT',
        'userDefaultAnswer_id' => 'INT',
        'userDefaultAnswer_orderId' => 'INT'
    ),
    'createAPIKey' => array(
        'name' => 'TEXT',
        'password' => 'TEXT',
    ),
    'deleteAPIKey' => array(
        'apiKeyId' => 'INT',
    ),
);

function processDeleteBefore( $id ) {
    // If this is the instance-on-demand model instance then don't let them delete the model user
    if (!defined('IOD_ROLE') || IOD_ROLE != 'master') return true;
    if (!defined('IOD_MODEL_USER')) return true;

    global $DB;
    $userEmail = $DB->getValue('SELECT email FROM user WHERE id=?',$id);
    if ($userEmail==IOD_MODEL_USER) {
        return [false, 'You cannot delete this user because this is the model user used when creating new instance-on-demand instances']; 
    }

    return true;
}

function processInputs($mode, $id) {
    global $WS, $USER_ID, $DB;

    if ($mode=='userDefaultSort') {
        $newPos=ws('userDefaultAnswer_orderId')*10-5;
        $DB->exec('UPDATE userDefaultAnswer SET orderId=? WHERE userId=? AND id=?',$newPos,$id, ws('userDefaultAnswer_id'));
        reorderUserDefaults($id);
        echo 'OK';
        exit;
    }

    if ($mode=='questionNameSearch') {
        $results = $DB->getColumn('
            SELECT DISTINCT dataField.question
            FROM dataField
                    INNER JOIN dataFieldType ON dataFieldType.id=dataField.typeId
            WHERE
                dataField.deletedAt=0 AND
                dataField.allowUserDefault AND
                dataFieldType.name IN ("Integer","Textbox","Textarea","Select","Email Address","URL","Float","Type To Search","Suggested Textbox") AND
                # Only include record types this user is allowed to edit
                dataField.recordTypeId IN (?) AND
                dataField.question LIKE ?
            ',
            getUserAccessibleRecordTypes($id,'edit',true),
            '%'.ws('ttsSearch').'%'
        );

        echo json_encode($results);
        exit;
    }

    if ($mode == 'createAPIKey') {
        if (!canDo('edit', $id)) {
            echo json_encode(['status' => 'ERROR', 'message' => 'Not allowed']);
            exit;
        } else if($id != $USER_ID) {
            echo json_encode(['status' => 'ERROR', 'message' => 'You may not generate API keys for other users']);
            exit;
        } else {
            $pwHash = $DB->getValue('SELECT password FROM user WHERE id = ?', $USER_ID);
            if(!ws('password') || !password_verify(ws('password'), $pwHash)) {
                echo json_encode(['status' => 'ERROR', 'message' => 'Bad password']);
                exit;
            }
        }
        require_once(LIB_DIR.'/api/tools.php');

        $keyData = \API\createAPIKey($id, ws('name'));
        unset($keyData[0]);
        unset($keyData[1]);
        $keyData['status'] = 'OK';

        echo json_encode($keyData);
        exit;
    }

    if ($mode == 'deleteAPIKey') {
        if (!canDo('edit', $id)) {
            exit;
        }

        global $LOGGER; $LOGGER->log("Deleting:\n".print_r(['id' => ws('apiKeyId'), 'userId' => $id], true));
        $deleted = $DB->update('userAPIKey', ['id' => ws('apiKeyId'), 'userId' => $id], ['deletedAt' => time()]);
        echo $deleted ? 'OK' : 'ERROR';
        exit;
    }

    if ($mode=='request') {
        include(CORE_DIR.'encryptedToken.php');
        $newUserData = decryptEncryptedToken('requestAccount',ws('token'));
        $WS = array_merge($WS,$newUserData);
    }

    global $canEditLogin;
    $canEditLogin=false;
    compareUserPermissions($id);
    // N.B. Making sure user has "user create" permission when $id==0 is done for us automatically by the Core adminPage (see lib/core/adminPage.php)
    if ($id==0 || $id==$USER_ID  || strpos('|same|less|',compareUserPermissions($id))) $canEditLogin=true;

    if (isSuperuser()) {
        $canEditLogin=true;
    } else if ( isSuperuser($id)) {
        // Do not allow non-superuser to edit any aspect of superusers
        // swap to just viewing mode
        ws('mode','view');
        return;
    }

    if ($mode=='deleteDefaultAnswer') {

        if (!ws('userDefaultAnswer_id')) echo "The answer to delete was not specified";
        else if (canDo('edit',$id)) {
            $DB->delete('userDefaultAnswer',[
                'userId'=>$id,
                'id'=>ws('userDefaultAnswer_id')
            ]);
            defaultsChanged($id);
            echo "OK";
        } else {
            echo "You don't have permission to edit this user";
        }
        exit;
    }
    if (!$canEditLogin) {
        unset($WS['user_email']);
        unset($WS['password']);
        unset($WS['encryptedPassword']);
    }

    if (isset($WS['user_email'])) $WS['user_email'] = trim($WS['user_email']);

    if (isset($WS['password'])) {
        if (strlen($WS['password']) && isset($WS['confirmPassword']) && $WS['password']===$WS['confirmPassword']) {
            $WS['user_password']=password_hash($WS['password'],PASSWORD_DEFAULT);
            ws('password','');
            ws('confirmPassword','');
        } else unset($WS['user_password']);
    }

    if (isset($WS['encryptedPassword'])) $WS['user_password']=$WS['encryptedPassword'];
}

function buildSelectors($userId) {
    global $DB, $USER_ID;

    foreach (['role','recordType','project'] as $thing) {
        global ${"{$thing}Select"};
        $userThingTable = 'user'.ucfirst($thing);

        $limitSql = '';
        $orderSql = '';
        $allowedIds = null;
        if ($thing=='recordType') {
            // There is no point showing recordTypes on this list that the user being editted isn't allowed to see
            // - that would mean the record types would appear on their dropdown filter, but when they selected them
            // they wouldn't be able to see any
            $allowedIds = getUserAccessibleRecordTypes($userId);
        } else if (!isSuperuser()) {
            // If they aren't superuser then they can only give a user either the roles/projects they themselves already have,
            // or roles/projects that the user already has
            $allowedIds = $DB->getColumn("
                SELECT {$thing}.id
                FROM {$userThingTable}
                    INNER JOIN {$thing} ON $thing.id={$userThingTable}.{$thing}Id
                WHERE
                    {$thing}.deletedAt=0 AND {$userThingTable}.userId IN (?)
            ",[$USER_ID,$userId]);
        }
        if (!is_null($allowedIds)) {
            if (!count($allowedIds)) $allowedIds=[0];
            $limitSql = " AND {$thing}.id IN (".implode(',',$allowedIds).')';
        }

        if ($thing=='project') {
            $orderSql = 'ORDER BY userProject.orderId ASC';
        } else {
            $orderSql = "ORDER BY {$thing}.name";
        }

        ${"{$thing}Select"} = new formPicklist( $thing.'Ids', array("
            SELECT
                {$thing}.name AS `option`,
                {$thing}.id AS `value`,
                IF(ISNULL({$userThingTable}.id),0,1) AS `selected`
            FROM
                {$thing}
                LEFT JOIN {$userThingTable} ON {$userThingTable}.{$thing}Id={$thing}.id AND {$userThingTable}.userId=?
            WHERE
                {$thing}.deletedAt=0
                {$limitSql}
                {$orderSql}
        ",$userId));
    }
}

function defaultsChanged($userId) {
    global $DB;
    $DB->update('user',['id'=>$userId],[
        'defaultsLastChangedAt' => time()
    ]);
}

function reorderUserDefaults($userId) {
    global $DB;
    $DB->exec('SET @orderId:=0');
    $updated = $DB->exec('UPDATE userDefaultAnswer SET orderId = @orderId:=@orderId+10 WHERE userId=? ORDER BY orderId ASC', $userId);
    if ($updated) defaultsChanged($userId);
}

function processUpdateBefore($id) {
    if ($id==0 && ws('user_email')) {
        global $DB;
        // Check to see if a user with this email already exists
        $alreadyExists = $DB->getRow('SELECT id FROM user WHERE deletedAt=0 AND email=@@user_email@@');
        if ($alreadyExists) {
            inputError('user_email','A user with this email address already exists');
            return false;
        }
    }
}

function processUpdateAfter($userId) {
    global $DB;

    // Build the selectors so that we can use them to validate the changes
    buildSelectors($userId);

    foreach (['role','recordType','project'] as $thing) {
        $ids = ws($thing.'Ids');
        if (!is_array($ids)) $ids=[];
        global ${"{$thing}Select"};
        $ids = array_intersect( $ids, ${"{$thing}Select"}->options );

        $DB->oneToManyUpdate( 'user'.ucFirst($thing),'userId',$userId,$thing.'Id',$ids );

        if ($thing=='project' && count($ids)) {
            // Update the project order to match that supplied
            $DB->exec('
                UPDATE userProject
                SET orderId=field(projectId,?)
                WHERE userId=?
            ',$ids,$userId);
        }
    }

    if (ws('userDefaultAnswer_answer') && ws('userDefaultAnswer_question') && ws('userDefaultAnswer_matchType')) {
        global $WS;
        ws('userDefaultAnswer_userId',$userId);
        ws('userDefaultAnswer_orderId',9999);
        $DB->autoInsert('userDefaultAnswer');
        reorderUserDefaults($userId);
    }

}

function prepareDisplay($id) {
    global $extraScripts;

    ws('user_password','');

    // (Re)build the selectors to reflect any changes
    buildSelectors($id);

    global $heading,$USER_ID;
    if ($id==$USER_ID) $heading="My settings";

    include(CORE_DIR.'/search.php');
    global $defaultAnswerList;
    $defaultAnswerList = new search('user/defaultAnswerList',['
        SELECT
            id, orderId, question, matchType, answer, userId
        FROM
            userDefaultAnswer
        WHERE
            userDefaultAnswer.userId=?
        ORDER BY orderId ASC
    ',$id]);

    global $defaultAnswerMatchTypeLookup, $defaultAnswerMatchType;
    $defaultAnswerMatchTypeLookup = [
        'exact' => 'Exact match',
        'anywhere' => 'Anywhere in field name',
        'regexp' => 'Regular expression'
    ];
    $defaultAnswerMatchType = new formOptionbox('userDefaultAnswer_matchType',array_flip($defaultAnswerMatchTypeLookup));

    global $defaultAnswerQuestionList;
    $defaultAnswerQuestionList = new formOptionbox('userDefaultAnswer_question');
    $defaultAnswerQuestionList->addLookup('
        SELECT DISTINCT
            dataField.question
        FROM dataField
            INNER JOIN dataFieldType ON dataFieldType.id=dataField.typeId
        WHERE
            dataField.deletedAt=0 AND
            dataFieldType.name IN ("Integer","Textbox","Textarea","Select","Email Address","URL","Float","Type To Search","Suggested Textbox") AND
            # Only include record types this user is allowed to edit
            dataField.recordTypeId IN (?)
    ',getUserAccessibleRecordTypes($id,'edit',true));

    global $apiKeyList;
    $apiKeyList = new search('user/apiKeyList', ['
        SELECT id, userId, name, apiKey, createdAt
        FROM userAPIKey
        WHERE userId = ?
        AND deletedAt = 0
        ORDER BY createdAt ASC
    ', $id]);

    // The following is required to support drag and drop of user defaults for ordering
    $extraScripts = array('/javascript/jquery-ui.justDraggable.min.js');

    $extraScripts = array('/javascript/badPasswordChecker.js');

}

include( '../../lib/core/adminPage.php' );

?>
