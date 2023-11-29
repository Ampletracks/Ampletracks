<?

$INPUTS = array(
    '.*'    => array(
        'filter_recordId_eq'     => 'INT',
        'filter_dataFieldId_eq'  => 'INT',
    )
);

$listSql = '
    SELECT
        recordDataVersion.`data`,
        recordDataVersion.valid,
        recordDataVersion.hidden,
        recordDataVersion.saved,
        recordDataVersion.savedAt,
        IFNULL(dataField.name,"-- deleted --") AS dataFieldName,
        IFNULL(user.firstName,"-- deleted --") AS userFirstName,
        user.lastName AS userLastName,
        recordDataVersion.userId
    FROM
        recordDataVersion
        # This next join required for permissions check
        INNER JOIN record ON record.id=recordDataVersion.recordId
        LEFT JOIN dataField ON dataField.id=recordDataVersion.dataFieldId
        LEFT JOIN user ON user.id=recordDataVersion.userId
    WHERE
        1=1
    ORDER BY
        recordDataVersion.savedAt DESC
';

function extraButtonsBefore() {
    if (ws('filter_recordId_eq')) { ?>
        <a class="btn" href="/record/admin.php?id=<?=(int)ws('filter_recordId_eq')?>" class="button">Back</a>
    <? }
}

$entityName = 'Previous Field Value';
include('../../lib/core/listPage.php');
