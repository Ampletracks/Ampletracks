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
    global $DB, $originalRecordTypeData;
    // check if a recordType already exists with this name
    $alreadyExists = $DB->getValue('SELECT id FROM recordType WHERE name=? AND !deletedAt AND id<>?',ws('recordType_name'),$id);
    if ($alreadyExists) inputError('recordType_name','A record type with this name already exists - please choose a different name');

    $originalRecordTypeData = $DB->getRow('SELECT * FROM recordType WHERE id=?',$id);
}

function processUpdateAfter( $id, $isNew ) {
    global $DB,$USER_ID,$originalRecordTypeData;
    
    // If the record type name has changed then we might need to update s3Upload paths    
    if (wsset('recordType_name') && isset($originalRecordTypeData['name'])) {
        $recordTypeName = ws('recordType_name');
        if ($recordTypeName != $originalRecordTypeData['name']) {
            // If the name has changed then we need to mark any s3Uploads for any record of this type that have "usesRecordType" set as needing be checked
            $DB->exec('
                UPDATE s3Upload
                INNER JOIN record ON record.id=s3Upload.recordId
                SET s3Upload.needsPathCheck=UNIX_TIMESTAMP()
                WHERE
                    record.recordTypeId=?
                    AND record.deletedAt=0 AND
                    s3Upload.deletedAt=0 AND
                    s3Upload.usesRecordType=1
            ',$id);
        }
    }

    $displayFieldIds = ws('dataFieldIds');
    forceArray($displayFieldIds);
    # $displayFieldIds[] = ws('recordType_primaryDataFieldId');

    // Split out the numeric from the non-numeric entries
    $builtInFieldsToDisplay = [];
    foreach( $displayFieldIds as $idx => $fieldId ) {
        if (!$fieldId) continue;
        if (!is_numeric($fieldId)) {
            if (strpos('|id|project|labelId|path|relationships|primaryDataField',$fieldId)) $builtInFieldsToDisplay[]=$fieldId;
            unset($displayFieldIds[$idx]);
        }
    }
    $builtInFieldsToDisplay = array_filter($builtInFieldsToDisplay);
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
        'id' => cms('Record List: ID column header',0,'ID'),
        'project' => cms('Record List: Project column header',0,'Project'),
        'labelId' => cms('Record List: Label ID column header',0,'Label ID'),
        'path' => cms('Record List: Path column header',0,'Path'),
        'relationships' => cms('Record List: Relationships',0,'Relationships'),
        'primaryDataField' => cms('Record List: Primary data field',0,'Primary data field')
    ];
    $selectedList = [];
    $builtInFieldsToDisplay = explode('|',ws('recordType_builtInFieldsToDisplay'));
    foreach($builtInFieldsToDisplay as $value) {
        if (empty($value)) continue;
        $selectedList[$availableList[$value]] = $value;
        unset($availableList[$value]);
    }

    $availableList = array_flip( $availableList );
        
    $queryh = $DB->query('
        SELECT
            CONCAT(IF(dataField.name="","<no name>",dataField.name)," (ID:",dataField.id,")") AS `option`,
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
    $primaryDataFieldIdSelect->addLookup(['
        SELECT
            CONCAT(IF(dataField.name="","<no name>",dataField.name)," (ID:",dataField.id,")") AS `option`,
            dataField.id AS `value`
        FROM
            dataField
            INNER JOIN dataFieldType ON dataFieldType.id = dataField.typeId
        WHERE
            !dataField.deletedAt AND
            dataField.recordTypeId=? AND 
            dataFieldType.hasValue
        ORDER BY dataField.name ASC
    ',$id]);    
}

$extraScripts = [ '../javascript/coloris/coloris.min.js' ];
$extraStylesheets = [ '../javascript/coloris/coloris.min.css' ];
include( '../../lib/core/adminPage.php' );
