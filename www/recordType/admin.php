<?

$INPUTS = array(
    'update'    => array(
        'dataFieldIds'  => 'TEXT ARRAY',
        'recordType_primaryDataFieldId' => 'INT',
    )
);

function processInputs( $mode, $id ) {
    if ($mode=='export') {
        exit;
    }

    include( CORE_DIR.'/formAsyncUpload.php');
    global $labelImageUpload;
    $labelImageUpload = new formAsyncUpload('labelImage',LIB_DIR.'/recordTypeLabelImage.php');
}

function processUpdateBefore( $id ) {
    global $DB;
    // check if a recordType already exists with this name
    $alreadyExists = $DB->getValue('SELECT id FROM recordType WHERE name=? AND !deletedAt AND id<>?',ws('recordType_name'),$id);
    if ($alreadyExists) inputError('recordType_name','A record type with this name already exists - please choose a different name');
}

function processUpdateAfter( $id, $isNew ) {
    global $DB,$USER_ID;
    
    $displayFieldIds = ws('dataFieldIds');
    forceArray($displayFieldIds);
    $displayFieldIds[] = ws('recordType_primaryDataFieldId');

    // Split out the numeric from the non-numeric entries
    $builtInFieldsToDisplay = [];
    foreach( $displayFieldIds as $idx => $fieldId ) {
        if (!$fieldId) continue;
        if (!is_numeric($fieldId)) {
            if (strpos('|id|project|labelId|path|relationships',$fieldId)) $builtInFieldsToDisplay[]=$fieldId;
            unset($displayFieldIds[$idx]);
        }
    }
    $builtInFieldsToDisplay = array_filter($builtInFieldsToDisplay);
    sort($builtInFieldsToDisplay);
    $builtInFieldsToDisplay = implode('|',$builtInFieldsToDisplay);
    $DB->update('recordType',['id'=>$id],['builtInFieldsToDisplay'=>$builtInFieldsToDisplay]);
    ws('recordType_builtInFieldsToDisplay',$builtInFieldsToDisplay);

    $DB->exec('
        UPDATE dataField SET displayOnList = id IN (?) WHERE !dataField.deletedAt AND dataField.recordTypeId=?
    ',$displayFieldIds,$id);

    // If the recordType is new then add it to the user who just created it
    if ($id && $isNew) {
        global $USER_ID;
        $DB->insert('userRecordType',array(
            'userId'        => $USER_ID,
            'recordTypeId'  => $id
        ));
    }
}

function prepareDisplay( $id ) {
    global $displayFieldsSelect, $DB;
    $availableList = [
        cms('Record List: ID column header',0,'ID') => 'id',
        cms('Record List: Project column header',0,'Project') => 'project',
        cms('Record List: Label ID column header',0,'Label ID') => 'labelId',
        cms('Record List: Path column header',0,'Path') => 'path',
        cms('Record List: Relationships',0,'Relationships') => 'relationships',
    ];
    $selectedList = [];
    $builtInFieldsToDisplay = explode('|',ws('recordType_builtInFieldsToDisplay'));
    foreach( $availableList as $option=>$value ) {
        if (in_array($value,$builtInFieldsToDisplay)) {
            $selectedList[$option] = $value;
            unset($availableList[$option]);
        }
    }
    $queryh = $DB->query('
        SELECT
            dataField.name AS `option`,
            dataField.id AS `value`,
            dataField.displayOnList AS `selected`
        FROM
            dataField
            INNER JOIN dataFieldType ON dataFieldType.id = dataField.typeId
        WHERE
            !dataField.deletedAt AND
            dataField.recordTypeId=? AND
            dataFieldType.hasValue
        ORDER BY dataField.name ASC
    ',$id);
    while ( $queryh->fetchInto( $row ) ) {
        if( $row['selected'] ) $selectedList[$row['option']] = $row['value'];
        else $availableList[$row['option']] = $row['value'];
    }
    $displayFieldsSelect = new formPicklist( 'dataFieldIds', $availableList, $selectedList);
    
    global $primaryDataFieldIdSelect;
    $primaryDataFieldIdSelect = new formOptionbox( 'recordType_primaryDataFieldId');
    $primaryDataFieldIdSelect->addLookup(array('
        SELECT dataField.name,dataField.id
        FROM
            dataField
            INNER JOIN dataFieldType ON dataFieldType.id = dataField.typeId
        WHERE
            !dataField.deletedAt AND
            dataField.recordTypeId=? AND 
            dataFieldType.hasValue
        ORDER BY dataField.name ASC
    ',$id));    
}

$extraScripts = [ '../javascript/coloris/coloris.min.js' ];
$extraStylesheets = [ '../javascript/coloris/coloris.min.css' ];
include( '../../lib/core/adminPage.php' );
