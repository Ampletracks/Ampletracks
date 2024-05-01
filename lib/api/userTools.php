<?
namespace API;

function getUserList($_filters = []) {
    global $DB;

    if(!canDo('list', 'user')) {
        throw new APIException('Forbidden', 403);
    }

    $filters = [];
    // specify all filters relate to the user table for makeConditions()
    foreach($_filters as $test => $val) {
        if(strpos('user:', $test) !== 0) {
            $test = 'user:'.$test;
        }
        $filters[$test] = $val;
    }

    $apiIdPrefix = getAPIIdPrefix('user');
    $filterConditions = makeConditions($filters);
    if($filterConditions) {
        $filterConditions = "AND $filterConditions";
    }
    $DB->returnHash();
    $userList = $DB->getRows('
        SELECT
            user.id AS realId,
            CONCAT(?, "_", user.apiId) AS id,
            user.firstName,
            user.lastName
        FROM user
        WHERE deletedAt = 0
        '.$filterConditions.'
    ', $apiIdPrefix);

    foreach($userList as $idx => $userData) {
        if(strlen($userData['id']) == strlen($apiIdPrefix.'_')) {
            $userList[$idx]['id'] = getAPIId('user', $userData['realId']);
        }
        unset($userList[$idx]['realId']);
    }

    return $userList;
}

function createUser($userDetails) {
    global $DB;

    if(!canDo('create', 'user')) {
        throw new APIException('Forbidden', 403);
    }

    $userId = 0;
    $try = 0;
    while(!$userId && $try++ < 3) {
        $userDetails['apiId'] = generateKeyString(false);
        $userId = $DB->insert('user', $userDetails);
    }
    if(!$userId) {
        return null;
    }

    return getAPIId('user', $userId);
}

function getUserData($userId) {
    global $DB;

    if(!canDo('view', $userId, 'user')) {
        throw new APIException('Forbidden', 403);
    }

    $apiIdPrefix = getAPIIdPrefix('user');
    $DB->returnHash();
    $userData = $DB->getRow('
        SELECT
            user.id AS realId,
            CONCAT(?, "_", user.apiId) AS id,
            user.firstName,
            user.lastName,
            user.email,
            user.mobile,
            user.lastLoggedInAt,
            user.lastLoginIp,
            user.createdAt,
            user.recordTypeFilter,
            user.defaultsLastChangedAt,
            user.fontScale
        FROM user
        WHERE id = ?
    ', $apiIdPrefix, $userId);

    if(strlen($userData['id']) == strlen($apiIdPrefix.'_')) {
        $userData['id'] = getAPIId('user', $userData['realId']);
    }
    unset($userData['realId']);

    return $userData;
}

function updateUser($userId, $updateData) {
    global $DB;

    if(!canDo('edit', $userId, 'user')) {
        throw new APIException('Forbidden', 403);
    }

    $DB->update('user', ['id' => $userId], $updateData);
}

function deleteUser($userId) {
    global $DB;

    if(!canDo('delete', $userId, 'user')) {
        throw new APIException('Forbidden', 403);
    }

    $DB->update('user', ['id' => $userId], ['deletedAt' => time()]);
}
