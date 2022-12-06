<?
$INPUTS = array(
    'update'    => array(
        'recordTypeIds' => 'INT ARRAY',
        'roleIds' => 'INT ARRAY',
        'projectIds' => 'INT ARRAY',
    )
);

function processInputs($mode, $id) {
    global $WS, $USER_ID;

    global $canEditLogin;
    $canEditLogin=false;
    compareUserPermissions($id);
    if ($id==0 || $id==$USER_ID  || strpos('|same|less|',compareUserPermissions($id))) $canEditLogin=true;

    if (isSuperuser()) {
        $canEditLogin=true;
    } else if ( isSuperuser($id)) {
        // Do not allow non-superuser to edit any aspect of superusers
        // swap to just viewing mode
        ws('mode','view');
        return;
    }

    if (!$canEditLogin) {
        unset($WS['user_email']);
        unset($WS['password']);
    }

    if (isset($WS['user_email'])) $WS['user_email'] = trim($WS['user_email']);

    if (isset($WS['password'])) {
        if (strlen($WS['password']) && isset($WS['confirmPassword']) && $WS['password']===$WS['confirmPassword']) {
            $WS['user_password']=password_hash($WS['password'],PASSWORD_DEFAULT);
        } else unset($WS['user_password']);
    }
}

function buildSelectors($userId) {
    global $DB, $USER_ID;

    foreach (['role','recordType','project'] as $thing) {
        global ${"{$thing}Select"};
        $userThingTable = 'user'.ucfirst($thing);

        $limitSql = '';
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
        ",$userId));
    }
}

function processUpdateAfter($id) {
    global $DB;

    // Build the selectors so that we can use them to validate the changes
    buildSelectors($id);

    foreach (['role','recordType','project'] as $thing) {
        $ids = ws($thing.'Ids');
        if (!is_array($ids)) $ids=[];
        global ${"{$thing}Select"};
        $ids = array_intersect( $ids, ${"{$thing}Select"}->options );

        $DB->oneToManyUpdate( 'user'.ucFirst($thing),'userId',$id,$thing.'Id',$ids );
    }

}

function prepareDisplay($id) {
    ws('user_password','');

    // (Re)build the selectors to reflect any changes
    buildSelectors($id);
}

include( '../../lib/core/adminPage.php' );

?>
