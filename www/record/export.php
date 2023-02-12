<?

$CHUNK_SIZE = 2;

include('../../lib/core/startup.php');
include(CORE_DIR.'/search.php');
include(LIB_DIR.'/dataField.php');

function findRelatedRecords($recordIds=null) {
    global $DB;

    static $allRecordIds;
    if (!isset($allRecordIds)) {
        $allRecordIds = $DB->getHash('SELECT id,typeId FROM record WHERE id IN (?)',$recordIds);
    } else if (is_null($recordIds)) {
        $return = [];
        foreach( $allRecordIds as $recordId=>$recordTypeId ) {
            if (!isset($return[$recordTypeId])) $return[$recordTypeId] = [];
            $return[$recordTypeId][] = $recordId;
        }
        unset($allRecordIds);
        return $return;
    }

    $sql="
        SELECT
            record.id,
            record.path,
            record.typeId AS recordTypeId,
            relationship.id AS relationshipId,
            relationship.toRecordId as relatedRecordId,
            relatedRecord.typeId AS relatedRecordTypeId,
            relationship.reciprocalRelationshipId
        FROM
            record
            LEFT JOIN relationship ON relationship.fromRecordId=record.id
            LEFT JOIN record relatedRecord ON relatedRecord.id=relationship.toRecordId
        WHERE
            record.id IN (?) AND
            record.lastSavedAt AND
            record.deletedAt=0
        ORDER BY record.id
    ";

    $queryh = $DB->query($sql,$recordIds);

    $extraRecordIds = [];
    $lastRecordId = null;

    while( $queryh->fetchInto($row) ) {
        if (is_null($lastRecordId) || $row['id']!=$lastRecordId) {
            $lastRecordId = $row['id'];
            foreach(explode(',',$row['path']) as $extraRecordId ) {
                if (!$extraRecordId) continue;
                if (!isset($allRecordIds[$extraRecordId])) {
                    $allRecordIds[$extraRecordId] = $row['recordTypeId'];
                    $extraRecordIds[] = $extraRecordId;
                }
            }
        }
        if (!is_null($row['relatedRecordId']) && !isset($allRecordIds[$row['relatedRecordId']])) {
            $allRecordIds[$row['relatedRecordId']] = $row['relatedRecordTypeId'];
            $extraRecordIds[] = $row['relatedRecordId'];
        }
    }
    if (count($extraRecordIds)) findRelatedRecords($extraRecordIds);
}

header('Content-Type: application/json');
#header('Content-Disposition: attachment; filename="'.$ENTITY.'Data.json"');

findRelatedRecords([301]);
$recordIdsByRecordType = findRelatedRecords();

// First check the user has permission to see all the records
foreach( $recordIdsByRecordType as $recordTypeId => $recordIds ) {
    $recordIdChunks = array_chunk($recordIds,$CHUNK_SIZE);

    $limits = addUserAccessLimits([
        'entity'=>'recordTypeId:'.$recordTypeId,
        'destination'=>'return'
    ]);

    foreach( $recordIdChunks as $recordIds ) {
    }
}

foreach( $recordIdsByRecordType as $recordTypeId => $recordIds ) {

    $recordIdChunks = array_chunk($recordIds,$CHUNK_SIZE);

    echo "{\n'records':[\n";
    foreach( $recordIdChunks as $recordIds ) {
        global $relationshipData;
        $relationshipData = [];
        // Load all of the relationships for all of the records in this chunk
        $DB->returnHash();
        $query = $DB->query('
            SELECT
                relationship.fromRecordId,
                relationship.toRecordId,
                relationshipLink.description,
                relationshipLink.relationshipPairId
            FROM relationship
                INNER JOIN relationshipLink ON relationshipLink.id=relationship.relationshipLinkId
            WHERE
                fromRecordId IN (?)
        ',$recordIds);
        while( $query->fetchInto($row) ) {
            $fromRecordId = $row['fromRecordId'];
            unset($row['fromRecordId']);
            if (!isset($relationshipData[$fromRecordId])) $relationshipData[$fromRecordId] = [];
            $relationshipData[$fromRecordId][] = $row;
        }
        $sql = '
           SELECT
                record.id AS recordId, record.path, record.typeId AS recordTypeId, (SELECT GROUP_CONCAT(label.id) FROM label WHERE label.recordId=record.id) AS labelIds,
                recordType.primaryDataFieldId, recordType.name AS recordType,
                project.name AS project,
                dataField.id,
                dataField.typeId AS fieldTypeId,
                dataField.exportName,
                recordData.data,
                recordData.inherited
            FROM
                record
                INNER JOIN recordType ON recordType.id=record.typeId
                LEFT JOIN project ON project.id=record.projectId
                LEFT JOIN dataField on dataField.recordTypeId=record.typeId AND dataField.deletedAt=0
                LEFT JOIN recordData ON recordData.recordId=record.id AND recordData.hidden=0 AND recordData.dataFieldId=dataField.id
            WHERE
                # We dont want all the null record data, but if we just did !IS_NULL(recordData.data) then we would
                # completely miss out records which had no answers
                # So.... we want to remove all NULLs except for one.
                # We can use the primaryDataFieldId for this since this will always point to one of the dataFields
                # The primary dataField might not have a value - but thats OK - we will filter any nulls out from there on rendering the data
                (1 OR dataField.id=recordType.primaryDataFieldId OR !ISNULL(recordData.data)) AND
                record.deletedAt=0 AND
                record.lastSavedAt AND
                record.id IN (?)
            ORDER BY record.id
        ';
        $search = new search('record/export',[$sql,$recordIds]);
        $search->bundleArray(5);
        $search->display();

    }
}
echo "\n]}\n";
