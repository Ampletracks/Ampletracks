<?
namespace API;

# define('API_ITEMS_PER_PAGE', 2);


$API_SQL = [
    'idList' => '
        SELECT DISTINCT
            user.id
        FROM user
            LEFT JOIN userProject ON userProject.userId=user.id
        WHERE user.deletedAt=0
    ',
    'getData' => '
        SELECT
            user.id                           ,
            CONCAT("u_", user.apiId) AS apiId ,
            user.firstName                    ,
            user.lastName                     ,
            user.email                        ,
            user.mobile                       ,
            user.lastLoggedInAt AS lastLoginTimestamp,             
            INET_NTOA(user.lastLoginIp) AS lastLoginIp,
            GROUP_CONCAT( DISTINCT IFNULL(CONCAT(role.id,":",IFNULL(role.apiId,"")),"") ) AS roleIds,
            GROUP_CONCAT( DISTINCT IFNULL(CONCAT(project.id,":",IFNULL(project.apiId,"")),"") ) AS projectIds
        FROM user
            LEFT JOIN userProject ON userProject.userId=user.id
            LEFT JOIN project ON project.id=userProject.projectId AND project.deletedAt=0
            LEFT JOIN userRole ON userRole.userId=user.id
            LEFT JOIN role ON role.id=userRole.roleId AND role.deletedAt=0
        WHERE user.deletedAt=0 AND user.id IN (?)
    '
];

function processListItem( &$item ) {
    foreach( ['role','project'] as $thing ) {
        $apiIds = [];
        $thingIds = array_filter(explode(',',$item[$thing.'Ids']));
        foreach( $thingIds as $thingId) {
            list($id,$apiId) = explode(':',$thingId);
            if (!empty($apiId)) {
                $apiIds[] = getAPIIdPrefix($thing).'_'.$apiId;
            } else {
                $apiIds[] = getAPIId($thing, $id);
            }
        }
        $item[$thing.'Ids'] = $apiIds;
    }
}

function processListInputs( &$inputs ) {
    if (isset($inputs['disabledStateFilter'])) {
        if ($inputs['disabledStateFilter']) {
            $inputs['user:disabledAt_ge']=1;
        } else {
            $inputs['user:disabledAt_eq']=0;
        }
        unset($inputs['disabledStateFilter']);
    }
    return $inputs;
}

require('../../../lib/api/startup.php');

