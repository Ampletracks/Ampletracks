<?

$listSql = '
    SELECT
        project.*,
        COUNT(record.id) AS numRecords
    FROM
        project
        LEFT JOIN record ON record.projectId=project.id AND record.deletedAt=0
    WHERE
        project.deletedAt=0
    GROUP BY project.id
';

include('../../lib/core/listPage.php');
