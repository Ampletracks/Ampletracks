<?

$listSql = '
    SELECT
        project.*,
        recordType.name AS recordType,
        COUNT(record.id) AS numRecords,
        recordType.id AS recordTypeId
    FROM
        project
        LEFT JOIN record ON record.projectId=project.id AND record.deletedAt=0 AND record.lastSavedAt>0
        LEFT JOIN recordType ON recordType.id=record.typeId
    WHERE
        project.deletedAt=0
    GROUP BY project.id,recordType.id
';

function prepareDisplay($list) {
    $list->bundleArray(3);
}

include('../../lib/core/listPage.php');
