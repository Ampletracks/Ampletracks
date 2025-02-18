<?

$INPUTS = array(
    '.*' => array(
        'filter_record:path_sw' => 'TEXT',
        'filter_record:depth_eq' => 'INT',
        'filter_record:id_in' => 'TEXT',
    )
);

function postStartup() {
    global $DB, $recordTypeId, $entityName, $permissionsEntity;
    
    $recordTypeId = (int)getPrimaryFilter();


    // Get the name of the record type
    $entityName = $DB->getValue('SELECT name FROM recordType WHERE id=?',$recordTypeId);

    if (!$recordTypeId) {
        displayError('You must choose a record type');
    }

    $permissionsEntity = 'recordTypeId:'.$recordTypeId;
    if (!canDo('list',$permissionsEntity )) {
        displayError('You do not have permission to view '.pluralize($entityName));
    }

}

function processInputs() {
    global $DB, $recordTypeId;
    if (ws('mode')=='json') {
        $GLOBALS['ignoreLimit']=true;
    }

    // If they passed in a specific record ID and the type of that record doesn't match the current record type filter
    // then flip the filter to the relevant record type and try again
    $recordIdFilter = (int)ws('filter_record:id_eq');
    if ($recordIdFilter) {
        $targetRecordTypeId = $DB->getValue('SELECT typeId FROM record WHERE id=?',$recordIdFilter);
        if ($targetRecordTypeId != $recordTypeId) {
            // See if they have permissions to see the other record type
            if (canDo('list','recordTypeId:'.$targetRecordTypeId)) {
                //setPrimaryFilter($targetRecordTypeId);
                header("Location: /record/list.php?recordTypeFilterChange={$targetRecordTypeId}&filter_record:id_eq={$recordIdFilter}");
                exit;
            }
        }
    }
}

function listSql(){
    global $DB, $recordTypeId, $fieldsToDisplay, $builtInFieldsToDisplay, $fieldIds, $filters, $primaryDataFieldId;

    include(LIB_DIR.'/dataField.php');
    
    $builtInFieldsToDisplay = explode('|',$DB->getValue('SELECT builtInFieldsToDisplay FROM recordType WHERE id = ?',$recordTypeId));
    $builtInFieldsToDisplay = array_flip($builtInFieldsToDisplay);

    $primaryDataFieldId = $DB->getValue('SELECT recordType.primaryDataFieldId FROM recordType WHERE recordType.id=?',$recordTypeId);

    // If the primary data field is set for display then we need to make sure we load this...
    $extraFieldId=0;
    if (isset($builtInFieldsToDisplay['primaryDataField'])) $extraFieldId=$primaryDataFieldId;

    $DB->returnHash();
    $fieldsToDisplay = $DB->getHash('
        SELECT dataField.id, dataField.name, dataField.exportName, dataField.typeId, dataField.unit, dataField.parameters, dataFieldType.name as type
        FROM dataField
            INNER JOIN dataFieldType ON dataFieldType.id=dataField.typeId
        WHERE !deletedAt AND (displayOnList OR dataField.id=?) AND recordTypeId=?
        ORDER BY dataField.orderId ASC
    ',$extraFieldId,$recordTypeId);
    
    $fieldIds = array_keys($fieldsToDisplay);
    $fields = '';
    $joins = '';
    $filters = array();
    
    foreach ( $fieldsToDisplay as $id=>$fieldData ) {
        $fieldData['id']=$id;
        $filter = DataField::build($fieldData);
        $filters[$id] = $filter;
        $alias = $filter->filterAlias();
        $filterNames = $filter->filterNames();
        foreach( $filter->filterNames() as $filterName ) {
            if (isset($_POST[$filterName])) ws($filterName,$filter->sanitizeFilter($_POST[$filterName]));
        }
        $joins .= "LEFT JOIN recordData $alias ON $alias.recordId=record.Id AND $alias.dataFieldId=".(int)$id." AND $alias.hidden=0\n";
        $fields .= ', `'.$alias.'`.`data` AS answer_'.(int)$id;
    }

    $fields = preg_replace('/,$/','',$fields);
//    if (!strlen($fields)) $fields = 'id';

    $orderBy = 'record.id DESC';
    if (ws('filter_record:path_sw')) $orderBy = 'record.depth ASC, '.$orderBy;
    if (ws('filter_record:id_in')) $orderBy = 'record.depth ASC, '.$orderBy;

    $sql="
        SELECT
            record.id, record.path, record.parentId, record.hiddenFields, record.typeId AS recordTypeId, MAX(label.id) AS labelId,
            recordType.primaryDataFieldId, recordType.name AS recordType,
            project.name AS project
            $fields
        FROM
            record
            INNER JOIN recordType ON recordType.id=record.typeId
            LEFT JOIN project ON project.id=record.projectId
            LEFT JOIN label ON label.recordId=record.id
            $joins
        WHERE
            !record.deletedAt AND
            record.lastSavedAt AND
            record.typeId=$recordTypeId
        GROUP BY record.id
        ORDER BY $orderBy
    ";
    return $sql;
}

function prepareDisplay($list) {
    global $DB, $USER_ID;

    include( LIB_DIR.'/dataFieldImage.php');

    global $hideAddButton;
    $hideAddButton = strtoupper(getConfig('Hide add button on record list')=='YES');

    global $projectFilter;
    // The list of projects to show in the filter depends on their permissions
    $permissions = getUserPermissionsForEntity('recordTypeId')['list'];

    if (isset($permissions['global'])) {
        // give them a list of all projects
        $DB->returnHash();
        $projects = $DB->getHash('SELECT name,id FROM project WHERE deletedAt=0');
    } else {
        $projects = [];
        if (isset($permissions['project'])) {
            // Add all the projects that the user belongs to
            $projects = $DB->getHash('
                SELECT project.name, project.id
                FROM userProject 
                INNER JOIN project ON project.id=userProject.projectId
                WHERE
                    project.deletedAt=0 AND
                    userProject.userId=?
            ',$USER_ID);
        }
        if (isset($permissions['own'])) {
            // Add all of the project of all of the records that they currently own
            $addProjects = $DB->getHash('
                SELECT project.name, project.id
                FROM record
                INNER JOIN project ON project.id=record.projectId
                WHERE
                    project.deletedAt=0 AND
                    record.ownerId=?
            ',$USER_ID);
            $projects = array_merge($projects,$addProjects);
        }
    }
    $projects = array_merge(['-- All --'=>''],$projects);
    $projectFilter = new formOptionbox('filter_record:projectId_eq',$projects);

}

function beforeList() {
    global $recordTypeId, $DB;
    $descendantFilter = ws('filter_record:path_sw');
    if ($descendantFilter) {
        $descendantFilter = preg_replace('/_$/','',$descendantFilter);
        include_once(LIB_DIR.'/recordTools.php');
        $recordDetails = getRecordDetails($descendantFilter);
        $description=ws('filter_record:depth_eq')?'Children of':'Descendents of';
        $description.=" ";
        if (isset($recordDetails['primaryFieldValue'])) echo '<h2>'.$description.' <a href="admin.php?id='.htmlspecialchars($recordDetails['id']).'">'.htmlspecialchars($recordDetails['primaryFieldValue']).'</a></h2>';
    }

}

$primaryFilterIdField = 'record.typeId';

include('../../lib/core/listPage.php');
