<?
namespace API;

require_once(LIB_DIR.'/api/baseClasses.php');

define('API_PER_PAGE_USER', 50);

//class APIQuery_user implements APIQuery {
//    private $userRecords = [];
//    private $currentPage = 1;
//    private $userIdStreamer = null;
//
//    public function __construct($filtersOrListId, $page) {
//        $this->currentPage = max((int)$page, 1);
//
//        if(!canDo('list', 'user')) {
//            throw new APIException('Forbidden', 403);
//        }
//
//        if(is_array($filtersOrListId)) {
//            $filters = [];
//            // specify all filters relate to the user table for makeConditions()
//            foreach($_filters as $test => $val) {
//                if(strpos('user:', $test) !== 0) {
//                    $test = 'user:'.$test;
//                }
//                $filters[$test] = $val;
//            }
//
//            $queryOrFileId = 'SELECT id FROM user WHERE 1 = 1';
//            $filterConditions = makeConditions($filters);
//            if($filterConditions) {
//                $filterConditions = "AND $filterConditions";
//            }
//        } else {
//            $queryOrFileId = $filtersOrListId;
//        }
//
//        $startIdx = ($this->currentPage - 1) * API_PER_PAGE_USER;
//
//        $this->userIdStreamer = new IdStreamer($queryOrFileId, 'user', $startIdx);
//        $userIds = [];
//        foreach($this->userIdStreamer->getIds(API_PER_PAGE_USER) as $userId) {
//            $userIds[] = $userId;
//        }
//
//        $this->loadRecordsById($userIds);
//    }
//
//    private function loadRecordsById(array $ids) {
//        global $DB;
//
//        $ids = array_filter($ids, function ($id) { return is_int($id); });
//        $DB->returnHash();
//        $userRecords = $DB->getRows('
//            SELECT
//                user.id AS realId,
//                CONCAT(?, "_", user.apiId) AS id,
//                user.firstName,
//                user.lastName,
//                user.email,
//                user.mobile,
//                user.lastLoggedInAt,
//                user.lastLoginIp,
//                user.createdAt,
//                user.recordTypeFilter,
//                user.defaultsLastChangedAt,
//                user.fontScale
//            FROM user
//            WHERE id IN (?)
//        ', $ids);
//
//        foreach($userRecords as $idx => $userData) {
//            if(strlen($userData['id']) == strlen($apiIdPrefix.'_')) {
//                $userRecords[$idx]['id'] = getAPIId('user', $userData['realId']);
//            }
//            unset($userRecords[$idx]['realId']);
//        }
//
//        $this->userRecords = $userRecords;
//    }
//
//    public function getUserRecords() {
//        return $this->userRecords;
//    }
//
//    public function getNextListPageUrl($pageNum) {
//        if(!$this->userIdStreamer->getIds(1)) {
//            return '';
//        }
//        return '/user/listId/'.$this->userIdStreamer->getIdFileId().'/page/'.$this->currentPage + 1;
//    }
//
//    public function formatRecordForOutput() {
//        return [
//            'userRecords' => $this->userRecords,
//            'nextPageUrl' => $this->getNextListPageUrl(),
//        ];
//    }
//}

// probably unused now
$__userIdStreamer = null;

// probably unused now
function getUserList($filtersOrListId, $listId = null, $page = 1) {
    // Test data until the auto-data formatting is done
    return [
        [
            'id' => 'u_852a125a7030cebf953c19e03ec1363846dff79c',
            'firstName' => 'Andrew',
            'lastName' => 'Wilson',
        ],
    ];

    // vvv NOT USED vvv
    global $DB;

    if(!canDo('list', 'user')) {
        throw new APIException('Forbidden', 403);
    }

    if(is_array($filtersOrListId)) {
        $filters = [];
        // specify all filters relate to the user table for makeConditions()
        foreach($_filters as $test => $val) {
            if(strpos('user:', $test) !== 0) {
                $test = 'user:'.$test;
            }
            $filters[$test] = $val;
        }

        $queryOrFileId = 'SELECT id FROM user WHERE 1 = 1';
        $filterConditions = makeConditions($filters);
        if($filterConditions) {
            $filterConditions = "AND $filterConditions";
        }
    } else {
        $queryOrFileId = $filtersOrListId;
    }

    $startIdx = ($page - 1) * API_PER_PAGE_USER;

    $__userIdStreamer = new IdStreamer($queryOrFileId, 'user', $startIdx);
    $userIds = [];
    foreach($__userIdStreamer->getIds(API_PER_PAGE_USER) as $userId) {
        $userIds[] = $userId;
    }

    //$userQuery = new APIQuery_user();
    //$userQuery->loadRecordsById($userIds);

    //return $userQuery->getUserRecords();
    return $userIds;
}

// probably unused now
function getNextListPageUrl($pageNum) {
    global $__userIdStreamer;

    if(
        gettype($__userIdStreamer) != 'object' ||
        getClass($__userIdStreamer) != 'IdStreamer'
    ) {
        global $LOGGER;
        $LOGGER->log('getNextListPageUrl() called before $__userIdStreamer initiallised');
        throw new APIException('Internal Error', 500);
    }
    $pageNum = max((int)$pageNum, 1);
    return '/user/listId/'.$__userIdStreamer->getIdFileId()."/page/$pageNum";
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
