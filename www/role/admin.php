<?

$INPUTS = [
    'update' => [
        'permissions' => 'TEXT ARRAY'
    ]
];

global $validPermissions;
$validPermissions = [
    'actionLog'        => ['list',                         'global'             ],
    'cms'              => ['list|view|edit|delete',        'global'             ],
    'configuration'    => ['list|view|edit',               'global'             ],
    'emailTemplate'    => ['list|view|edit',               'global'             ],
    'email'            => ['list|view',                    'global'             ],
    'dataField'        => ['list|view|edit|delete|create', 'global|project'     ],
    'user'             => ['list|view|edit|delete|create', 'global|project|own' ],
    'relationshipLink' => ['list|view|edit|delete|create', 'global|project'     ],
    'project'          => ['list|view|edit|delete|create', 'global|project'     ],
    'recordType'       => ['list|view|edit|delete|create', 'global|project'     ],
];

function processUpdateBefore( $id ) {
    global $DB;
    // check if a role already exists with this name
    $alreadyExists = $DB->getValue('SELECT id FROM role WHERE name=? AND !deletedAt AND id<>?',ws('role_name'),$id);
    if ($alreadyExists) inputError('role_name','A user role with this name already exists - please choose a different name');

    if (!$id && !strlen(trim(ws('role_name')))) inputError('role_name','You must provide a name for this role');
}

function processUpdateAfter($id,$isNew) {
    global $DB;
    if ($id) {
        if (wsset('permissions')) {
            global $validPermissions;

            $validActions = array_unique(explode('|',implode('|',array_column($validPermissions,0))));
            $validLevels = array_unique(explode('|',implode('|',array_column($validPermissions,1))));
            $validEntities = array_keys($validPermissions);
            $validEntities[] = 'recordTypeId';

            $permissions = ws('permissions');
            $DB->delete('rolePermission',['roleId'=>$id]);
            foreach($permissions as $permission) {
                list( $entity, $level, $action ) = explode(',',$permission.',,,');
                $recordTypeId=0;
                if (strpos($entity,'recordTypeId:')===0) {
                    list( $entity, $recordTypeId ) = explode(':',$entity);
                }
                if (in_array($entity,$validEntities) && in_array($level,$validLevels) && in_array($action,$validActions)) {
                    $DB->insert('rolePermission',[
                        'roleId'        => $id,
                        'entity'        => $entity,
                        'level'         => $level,
                        'action'        => $action,
                        'recordTypeId'  => $recordTypeId
                    ]);
                }
            }
        }
    }
}

function prepareDisplay( $id ) {
    global $DB, $validPermissions;
   
    global $impliedActions, $impliedActions_reversed;
    $impliedActions = $DB->getHash('SELECT action, group_concat(impliedAction) FROM impliedAction GROUP BY action');
    $impliedActions_reversed = $DB->getHash('SELECT impliedAction, group_concat(action) FROM impliedAction GROUP BY impliedAction');

    global $recordTypeSelect;
    $recordTypeSelect = $DB->getHash('
        SELECT name, CONCAT("recordTypeId:",id) FROM recordType WHERE deletedAt=0
    ');
    foreach($recordTypeSelect as $recordType) {
        $validPermissions[$recordType] = ['list|view|edit|delete|create', 'global|project|own' ];
    }

    global $currentPermissions;
    $currentPermissions = ws('permissions');
    if (!is_array($currentPermissions)) {
        $currentPermissions = $DB->getColumn('
            SELECT
                CONCAT(
                    entity,
                    IF(entity="recordTypeId" AND recordTypeId>0,CONCAT(":",recordTypeId),""),
                    ",",level,
                    ",",action
                )
            FROM rolePermission
            WHERE roleId=?
        ',$id);
    }
    $currentPermissions = array_filter($currentPermissions);
    $currentPermissions = array_combine($currentPermissions,$currentPermissions);
    $currentPermissions = array_map(function($v){ return substr($v,0,strrpos($v,',')); },$currentPermissions);
}

$extraScripts = ['/javascript/dependentInputs.js'];

include( '../../lib/core/adminPage.php' );