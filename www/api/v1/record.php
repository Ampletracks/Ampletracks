<?
namespace API;

# $API_ITEMS_PER_PAGE = 2;


$API_SQL = [
    'getIdList' => '
        SELECT
            record.id AS id
        FROM
            record
            LEFT JOIN record parent ON parent.id=record.parentId
            INNER JOIN project ON project.id=record.projectId
            INNER JOIN recordType ON recordType.id=record.typeId
            INNER JOIN recordData name ON name.recordId=record.id AND name.dataFieldId=recordType.primaryDataFieldId
            LEFT JOIN user owner ON owner.id=record.ownerId
        WHERE 
            record.deletedAt=0
    ',
    'getListData' => '
        SELECT
            record.id AS id,
            record.apiId AS apiId,
            record.id AS recordInternalId,
            name.data AS name,
            project.id AS projectId,
            project.apiId AS projectApiId,
            parent.id AS parentRecordId,
            parent.apiId AS parentRecordApiId,
            record.path,
            owner.id AS ownerId,
            owner.apiId AS ownerApiId,
            recordType.id AS recordTypeId,
            recordType.apiId AS recordTypeApiId
        FROM
            record
            LEFT JOIN record parent ON parent.id=record.parentId
            INNER JOIN project ON project.id=record.projectId
            INNER JOIN recordType ON recordType.id=record.typeId
            INNER JOIN recordData name ON name.recordId=record.id AND name.dataFieldId=recordType.primaryDataFieldId
            LEFT JOIN user owner ON owner.id=record.ownerId
        WHERE 
            record.deletedAt=0 AND record.id IN (?)
    '
];

$API_ID_MAPPINGS = [
    [ 'record', 'id', 'apiId'],
    [ 'record', 'parentRecordId', 'parentRecordApiId' ],
    [ 'project', 'projectId', 'projectApiId' ],
    [ 'user', 'ownerId', 'ownerApiId' ],
    [ 'recordType', 'recordTypeId', 'recordTypeApiId' ],
];

function processItem( &$record ) {
    global $DB;
    $DB->returnHash();
    $query = $DB->query('
        SELECT
            dataField.apiName AS fieldName,
            dataField.id AS dataFieldId,
            dataField.apiId AS dataFieldApiId,
            dataFieldType.name AS fieldType,
            recordData.data AS value,
            recordData.hidden AS isHidden,
            recordData.valid AS isValid,
            recordData.inherited AS isInherited,
            inheritedFromRecord.id AS inheritedFromRecordId,
            inheritedFromRecord.apiId AS inheritedFromRecordApiId
        FROM
            recordData
            INNER JOIN dataField ON dataField.id=recordData.dataFieldId
            INNER JOIN dataFieldType ON dataFieldType.id=dataField.typeId
            LEFT JOIN record inheritedFromRecord ON inheritedFromRecord.id=recordData.fromRecordId
        WHERE
            # dataField.apiName<>"" AND
            recordData.recordId=?
    ',$record['id']);
    $dataFields = [];
    while( $query->fetchInto($row) ) {
        foreach( $row as $column=>$value ) {
            if (substr($column,0,2)=='is') $row[$column] = (bool)$row[$column];
        }
        $dataFields[] = $row;
    }

    //$dataFields is passed by reference
    checkSetApiIds($dataFields, [
        [ 'dataField', 'dataFieldId', 'dataFieldApiId' ],
        [ 'record', 'inheritedFromRecordId', 'inheritedFromRecordApiId' ],
    ] );

    foreach( $dataFields as $idx=>$dataField ) {
        if (is_null($dataField['inheritedFromRecordId'])) unset($dataFields[$idx]['inheritedFromRecordId']);
    }

    $record = [
        'summary' => $record,
        'dataFields' => $dataFields
    ];
}

function handleRelationship( &$responseData ) {
    global $DB;
    $record = $responseData['data']['summary'];

    $DB->returnHash();
    // As far as permissions go we already know they can access this record because that was checking in tools.php=>getAPIItem()
    // If they couldn't access the record this function wouldn't have been called.
    // Now we need to ensure we only show relationships where they can access the TO record as well
    // So we alias the toRecord to just record so that the limits will work again...

    $sql = '
        SELECT
            relationshipLink.description,
            fromRecord.id AS fromRecordId,
            fromRecord.apiId AS fromRecordApiId,
            record.id AS toRecordId,
            record.apiId AS toRecordApiId,
            fromRecordType.id AS fromRecordTypeId,
            fromRecordType.apiId AS fromRecordTypeApiId,
            toRecordType.id AS toRecordTypeId,
            toRecordType.apiId AS toRecordTypeApiId,
            fromRecordType.name AS fromRecordType,
            toRecordType.name AS toRecordType,
            recordData.data AS toRecordName
        FROM
            relationship
            INNER JOIN relationshipLink ON relationshipLink.id=relationship.relationshipLinkId
            # See comment above for explanation of why this table is not aliased to toRecord
            INNER JOIN record fromRecord ON fromRecord.id=relationship.fromRecordId AND fromRecord.deletedAt=0
            INNER JOIN record ON record.id=relationship.toRecordId AND record.deletedAt=0
            INNER JOIN recordType fromRecordType ON fromRecordType.id=relationshipLink.fromRecordTypeId
            INNER JOIN recordType toRecordType ON toRecordType.id=relationshipLink.toRecordTypeId
            LEFT JOIN recordData ON recordData.recordId=record.id AND recordData.dataFieldId=toRecordType.primaryDataFieldId
        WHERE
            relationship.fromRecordId=?
    ';

    $limits = getUserAccessLimits(['entity' => 'record', 'prefix' => '']);
    addConditions( $sql, $limits );

    $query = $DB->query($sql,$record['id']);

    $responseData=[];
    while( $query->fetchInto($row) ) {
        $responseData[] = $row;
    }

    // $responseData is passed by reference
    checkSetApiIds($responseData, [
        [ 'record', 'fromRecordId', 'fromRecordApiId' ],
        [ 'record', 'toRecordId', 'toRecordApiId' ],
        [ 'recordType', 'fromRecordTypeId', 'fromRecordTypeApiId' ],
        [ 'recordType', 'toRecordTypeId', 'toRecordTypeApiId' ],
    ] );

}

require('../../../lib/api/startup.php');
