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
        LEFT JOIN dataField ON dataField.id=recordDataVersion.dataFieldId
        LEFT JOIN user ON user.id=recordDataVersion.userId
    WHERE
        1=1
    ORDER BY
        recordDataVersion.savedAt DESC
';

function extraButtonsBefore() {
    if (ws('filter_recordIdEq')) { ?>
        <a href="/record/admin.php?id=<?=(int)ws('filter_recordIdEq')?>" class="button">Back</a>
    <? }
}

$entityName = 'Previous Field Value';
include('../../lib/core/listPage.php');
