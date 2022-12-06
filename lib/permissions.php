<?
/*
 * 
delete implies list
view implies list
edit implies view and list

Permissions which don't exist
"project create" and "global create" - you can't create a thing which doesn't belong to your project so there is no scope to have "own create" without "deparment create". Equally you can't create a thing which doesn't belong to you so there is no scope to have "project create" without "own create". Therefore "own create" implies "project create" which also implies "global create"

*/

// See if userB has more, less or the same permissions as userA
// Returns "more", "less", "same" or "different"
// "different" means that A has some that B does not have AND B has some that A does not have
function compareUserPermissions( $userB, $userA=0 ) {
    global $USER_ID,$DB;
    if (!$userA) $userA=$USER_ID;
    $permissions = [];
    foreach([$userA,$userB] as $userId) {
        $permissions[] = $DB->getColumn('
            SELECT
                CONCAT(
                    rolePermission.entity,",",
                    impliedLevel.level,",",
                    impliedAction.impliedAction,",",
                    rolePermission.recordTypeId
                )
            FROM
                userRole
                # Join on to role to check it hasnt been delete
                INNER JOIN role ON role.id=userRole.roleId AND role.deletedAt=0
                INNER JOIN rolePermission ON rolePermission.roleId=role.id
                INNER JOIN impliedAction ON impliedAction.action=rolePermission.action
                # in order to the comparison to work we also need to flesh out all the implied levels too
                # ... even if some of the resulting permissions dont exactly make sense
                # e.g. cms,own,edit (since CMS entries dont belong to users)
                INNER JOIN impliedLevel ON impliedLevel.level=rolePermission.level
            WHERE
                userRole.userId=?
                    
        ',$userId);
    }
    $inAButNotB = count(array_diff($permissions[0],$permissions[1]));
    $inBButNotA = count(array_diff($permissions[1],$permissions[0]));

    if ($inAButNotB && $inBButNotA) return 'different';
    if ($inBButNotA) return 'more';
    if ($inAButNotB) return 'less';
    return 'same';
}


function getUserAccessibleRecordTypes( $userId = 0, $action='list' ) {
    global $USER_ID, $DB;
    if (!$userId) $userId=$USER_ID;

    if (isSuperuser($userId)) return $DB->getColumn('
        SELECT id FROM recordType WHERE deletedAt=0
    ');

    return $DB->getColumn('
        SELECT DISTINCT
            rolePermission.recordTypeId
        FROM
            userRole
            # Join on to role to check it hasnt been delete
            INNER JOIN role ON role.id=userRole.roleId AND role.deletedAt=0
            INNER JOIN rolePermission ON rolePermission.roleId=role.id
            INNER JOIN impliedAction ON impliedAction.action=rolePermission.action
        WHERE
            userRole.userId=? AND
            impliedAction.action=?
    ',$userId,$action);
}

function getUserProjects( $userId ) {
    static $projectIds = [];
    if (isset($projectIds[$userId])) return $projectIds[$userId];

    global $DB;
    $projectIds[$userId] = $DB->getColumn('
        SELECT projectId
        FROM userProject
            # Join on to projects so we can check the project hasnt been deleted
            INNER JOIN project ON project.id=userProject.projectId
        WHERE
            project.deletedAt=0 AND
            userId=?
    ',$userId);

    if (!count($projectIds[$userId])) $projectIds[$userId] = [0];
    return $projectIds[$userId];
}

/*
 * entity is one of: 'cms','configuration','dataField','user','relationshipLink','project'
 * OR it is 'recordTypeId:<recordTypeId>'
 * 
 * This function returns a hash of hashes.
 * Outer hash is keyed on action i.e. list,view,edit,delete or create
 * Inner hash is keyed on level i.e. global, project, own
 * 
 */

function isSuperuser( $userId=0 ) {
    global $USER_ID;
    if ($userId==0) $userId = $USER_ID;
    return isset(getUserPermissionsForEntity('superuser',$userId)['superuser']);
}

function getUserPermissionsForEntity( $entity='', $userId=0 ) {
    global $DB, $ENTITY, $USER_ID;

    $emptyPermissions = [
        'list' => [],
        'view' => [],
        'edit' => [],
        'delete' => [],
        'create' => []
    ];

    if ($entity==='') $entity = $ENTITY;
    if ($userId==0) $userId = $USER_ID;

    static $permissions = [];
    static $isSuperuser = [];

    if (!isset($permissions[$userId])) $permissions[$userId]=[];
    else if (isset($permissions[$userId][$entity])) return $permissions[$userId][$entity];

    if (!isset($permissions[$userId][$entity])) $permissions[$userId][$entity] = $emptyPermissions;

    // First see if they are a superuser
    if (!isset($isSuperuser[$userId])) $isSuperuser[$userId]=$DB->getValue('SELECT id FROM userRole WHERE userId=? AND roleId=1',$userId);
    if ($isSuperuser[$userId]) {
        $permissions[$userId][$entity] = [
            'superuser'=>'true',
            'list' => ['global'=>true],
            'view' => ['global'=>true],
            'edit' => ['global'=>true],
            'delete' => ['global'=>true],
            'create' => ['global'=>true]
        ];
        return $permissions[$userId][$entity];
    }

    global $DB;
    $DB->returnHash();
    $queryBase = '
        SELECT DISTINCT
            impliedAction.impliedAction AS action,
            rolePermission.level,
            rolePermission.entity
        FROM
            userRole
            # Join on to role to check it hasnt been delete
            INNER JOIN role ON role.id=userRole.roleId AND role.deletedAt=0
            INNER JOIN rolePermission ON rolePermission.roleId=role.id
            INNER JOIN impliedAction ON impliedAction.action=rolePermission.action
        WHERE
            userRole.userId=?
    ';
    /*
    Don't see the point in limiting which permissions we pull back
    Since.... on most pages we will need to check permissions against all entities in order to be able to
    render the navigation menus - so we will end up doing a lot of small queries.
    ... Mind you... this doesn't hold for recordTypeId: lookups
    */
    $recordTypeId=0;
    if (strpos($entity,'recordTypeId:')===0) {
        $recordTypeId = substr($entity,strpos($entity,':')+1);
        $permissionQuery = $DB->query(
            $queryBase.'AND rolePermission.entity="recordTypeId" AND rolePermission.recordTypeId=?',
            $userId,$recordTypeId
        );
    } else {
        // If we got here and the non-RecordTypeId-permissionsLoaded flag is already set for this user
        // then the absence of permissions for this entity means there just aren't any permissions, not that we haven't loaded them
        if (isset($permissions[$userId]['non-RecordTypeId-permissionsLoaded'])) return $permissions[$userId][$entity];
        $permissions[$userId]['non-RecordTypeId-permissionsLoaded'] = true;

        // in this case we still include the recordTypeId permissions, but the query will return the union
        // of all the recordTypeId permissions for all record types
        // this is principally for answering questions like "can the user list _ANY_ records"
        // so if its not a recordTypeId query then just pull back everything except recordTypeId permissions
        $permissionQuery = $DB->query(
            $queryBase,
            $userId
        );
    }
    
    while ( $permissionQuery->fetchInto($row) ) {
        $entity = $row['entity'];
        if ($recordTypeId) $entity.=':'.$recordTypeId;
       
        if (!isset($permissions[$userId][$entity])) { 
            $permissions[$userId][$entity] = $emptyPermissions;
        }
   
        $permissions[$userId][$entity][$row['action']][$row['level']]=true;
    }
    return $permissions[$userId][$entity];
}

// This function can be called in 3 ways...
//    canDo($action, $ownerId, $projectId, $entity=null, $userId=null)
//          This will check if the user can do the action to the entity based on the ownerId and projectId provided
//          N.B. if using this format to check permissions for a record you must use $entity='recordTypeId:<recordTypeId>'
//    OR
//    canDo($action, $rowData, $entity=null, $userId=null)
//          This will check if the user can do the action to the entity based on the rowData provided
//          The rowData must be a hash including 'ownerId' (or 'userId') and 'deparmentId' fields
//          N.B. if using this format to check permissions for a record you must use $entity='recordTypeId:<recordTypeId>'
//    OR
//    canDo($action, $entityId, $entity=null, $userId=null)
//          This will check if the user can do the action to the entity based on the entity ID provided
//          In this case the function will do another query to lookup the project and owner ID for the entity
//          When checking a record you can pass $entity='record' OR $entity='recordTypeId:<recordTypeId>'
//          If $entity is not passed then the global $ENTITY will be used
//          When checking for "list" permissions there may or may not be an entityId - just pass 0 for the entityId
//          When checking for "create" permissions there is no entityId - just pass 0 for the entityId
//    OR
//    canDo($action, $entity )
//          This only works for create and list actions (i.e. where entityId is not required)
//
// If $projectId is passed this can be either an integer, an array of integers, or a comma separated list of integers
// $ownerId must be just an integer

function canDo( ) {
    global $DB, $ENTITY, $USER_ID;
    
    static $entityOwnershipLookupSql = [
        'cms' => '', // CMS doesn't have owner OR department ID
        'configuration' => '', // configuration doesn't have owner OR department ID
        'dataField' => '
            SELECT recordType.projectId
            FROM dataField
                INNER JOIN recordType ON recordType.id=dataField.recordTypeId
            WHERE
                dataField.deletedAt=0 AND
                dataField.id=?
        ',
        'user' => '
            SELECT user.id AS ownerId, IFNULL(GROUP_CONCAT( userProject.projectId ),0) AS projectId
            FROM user
                LEFT JOIN userProject ON userProject.userId=user.id
            WHERE
                user.deletedAt=0 AND
                user.id=?
        ',
        // This is intentionally truncated to 13 characters
        'relationshipL' => '
            SELECT recordType.projectId
            FROM
                relationshipLink
                INNER JOIN recordType ON recordType.id=relationshipLink.fromRecordTypeId
            WHERE
                recordType.deletedAt=0 AND
                relationshipLink.id=?
        ',
        'project' => '
            SELECT id AS projectId
            FROM project
            WHERE
                project.deletedAt=0 AND id=?
        ',
        'recordType' => '
            SELECT projectId
            FROM recordType
                INNER JOIN project ON project.id=recordType.projectId
            WHERE
                recordType.deletedAt=0 AND project.deletedAt=0
                id=?
        ',
        // Colon on the end of this next one is intentional
        'recordTypeId:' => '
            SELECT projectId, createdBy AS ownerId
            FROM record
            WHERE
                record.deletedAt=0 AND
                # The failure to join on to project and check it hasnt been deleted here is intentional
                # A record whose project has been deleted is just orphaned, not invalid - it should still be displayed
                id=?
        '
    ];
    
    $args = func_get_args();
    $action = array_shift($args);
    $param2 = array_shift($args);
    $entityId=false;
    $entity='';
    $userId=0;
    
    if (is_array($param2)) {
        // We have been called with ($action, $rowData, $entity=null, $userId=null)
        $rowData = $param2;
    } else {
        if (count($args)==0) {
            // We have been called with ($action, $entityId) OR ($action, $entity)
            if (is_numeric($param2)) $entityId = $param2;
            else $entity = $param2;
        } else {
            $param3 = array_shift($args);
            // See if we have been called with ($action, $ownerId, $projectId...) or ($action, $entityId, $entity)
            // $projectId will be either an integer, a comma separated list of integers, or an array of integers
            if (strpos(' 123456789',substr($param3,0)) || is_array($param3)) {
                // Looks like ($action, $ownerId, $projectId...)
                $rowData = [
                    'ownerId' => $param2,
                    'projectId' => $param3,
                ];
            // 
            } else {
                // Looks like ($action, $entityId, $entity)
                $entityId = $param2;
                // Put the entity name back on the args list for later
                array_unshift( $args, $param3 );
            }
        }
    }
    
    if (count($args)) $entity = array_shift( $args );
    if (count($args)) $userId = array_shift( $args );

    if ($entity==='') $entity = $ENTITY;
    if (!$userId) $userId = $USER_ID;

    if ($entity=='record' && $entityId) {
        // If we have been given just a record and ID, look up the record type for this ID
        $recordTypeId = $DB->getValue('SELECT typeId FROM record WHERE id=?',$entityId);
        $entity='recordTypeId:'.$recordTypeId;
    }

    // At this point we should have $action, $entity, $userId
    // echo "-- action=$action entity=$entity userId=$userId --";

    $permissions = getUserPermissionsForEntity( $entity, $userId );

    // Check if they are superuser
    if (isset($permissions['superuser'])) return true;

    // Looking up general "list" permissions for recordType (as opposed to a specific record type) is a special case
    if ($entity=='recordTypeId') {
    }

    // see what permissions the user has in relation to the specified action
    if (!isset($permissions[$action])) return false;
    $permissions = $permissions[$action];
   
    if (!count($permissions)) return false;

    if (isset($permissions['global'])) return true;

    // OK... so we are relying on either project based or own user based permissions
    // so now lets look up the project and owner ID if we weren't given them
    if (
        $action=='create' ||
        ( $action=='list' && !$entityId )
    ) {
        // In this case entityId is irrelevant
        // set the owner and project ID to be this user's user ID and any one of their projects
        // That means they will be allowed to do it if they have global, project or own create permissions
        $rowData = [
            'userId' => $userId,
            'projectId' => getUserProjects( $userId )[0]
        ];
    } else {
        // Use the relevant SQL to load the corresponding ownerId and projectId
        // We use the substr below to chop the :<recordTypeId> off the end
        $sql = $entityOwnershipLookupSql[ substr($entity,0,13) ];
        $DB->returnHash();
        $rowData = $DB->getRow($sql,$entityId);
    }
    
    // At this point rowData will contain projectId and/or ownerId
    $projectIds = [];
    if (isset($rowData['projectId'])) $projectIds = is_array($rowData['projectId']) ? $rowData['projectId'] : explode(',',$rowData['projectId']);
    
    $ownerId = 0;
    if (isset($rowData['ownerId'])) $ownerId = $rowData['ownerId'];
    else if (isset($rowData['userId'])) $ownerId = $rowData['userId'];

    if (isset($permissions['own']) && $ownerId && $ownerId==$userId ) return true;

    // finally try the project permission if they have that
    if (!isset($permissions['project'])) return false;
    
    // need to look up the projects for the user
    $userProjects = getUserProjects( $userId );
   
    if (count(array_intersect($userProjects,$projectIds))) return true;
    
    return false;
}

/*
 * $options can contain...
 *      'projectIdColumn' - defaults to best guess based on entity
 *      'ownerIdColumn' - defaults to best guess based on entity
 *      'entity'
 *          - defaults to $ENTITY
 *          - If entity is "record" then this should be set to 'recordTypeId:<entityId>'
 *      'userId' - defaults to $USER_ID
 *      'prefix' - prefix to be used when adding limits to workspace  defaults to 'limit_'
 */
function addUserAccessLimits( $options=[] ) {
    static $projectColumnLookup = [
        'actionLog'     => '',
        'cms'           => '',
        'configuration' => '',
        'dataField'     => 'recordType.projectId',
        'user'          => 'userProject.projectId',
        'recordTypeId:' => 'record.projectId',
        // This is intentionally truncated to 13 characters
        'relationshipP' => 'record.projectId',
        'project'       => 'project.id',
        'record'        => 'record.projectId',
        'recordType'    => 'recordType.projectId',
        'role'          => '',
    ];
    
    static $ownerColumnLookup = [
        'actionLog'     => '',
        'cms'           => '',
        'configuration' => '',
        'dataField'     => '',
        'user'          => 'user.id',
        'recordTypeId:' => 'record.ownerId',
        // This is intentionally truncated to 13 characters
        'relationshipP' => '',
        'project'       => '',
        'record'        => 'record.ownerId',
        'recordType'    => '',
        'role'          => '',
    ];
    
    global $ENTITY, $USER_ID;
    $entity = isset($options['entity']) ? $options['entity'] : $ENTITY;
    $userId = isset($options['userId']) ? $options['userId'] : $USER_ID;
    $prefix = isset($options['prefix']) ? $options['prefix'] : 'limit_';

    $lookupEntity = substr($entity,0,13);
    $projectIdColumn = isset($options['projectIdColumn']) ? $options['projectIdColumn'] : $projectColumnLookup[$lookupEntity];
    $projectIdColumn = str_replace('.',':',$projectIdColumn);
    $ownerIdColumn = isset($options['ownerIdColumn']) ? $options['ownerIdColumn'] : $ownerColumnLookup[$lookupEntity];
    $ownerIdColumn = str_replace('.',':',$ownerIdColumn);

    $permissions = getUserPermissionsForEntity( $entity, $userId );

    // if they are a superuse then don't impose any limits
    if (isset($permissions['superuser'])) return true;

    // If they have no list permissions in relation to this entity then return false
    if (!isset($permissions['list'])) {
        return false;
    }

    // if they have global list for this entity then don't impose any limits
    if (isset($permissions['list']['global'])) { /* do nothing */ }
    else if (isset($permissions['list']['project'])) {
        ws($prefix.$projectIdColumn.'_in',getUserProjects( $userId ));
    } else {
        // permission level must be "own"
        ws($prefix.$ownerIdColumn.'_eq',$userId);
    }
    return true;
}

// N.B. This only works for create/list actions - i.e. where entityId is not required
function canDoMultiple( $action, $entities, $combinator ) {
    if (!is_array($entities)) $entities = explode(',',$entities);
    
    foreach( $entities as $entity ) {
        $canDo = canDo( $action, $entity );
        if ($combinator=='or' && $canDo) return true;
        else if ($combinator=='and' && !$canDo) return false;
    }

    if ($combinator=='or') return false;
    return true;
}
