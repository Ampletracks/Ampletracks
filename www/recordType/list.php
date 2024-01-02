<?

function postStartup() {
    include(LIB_DIR.'/recordTypeLabelImage.php');
}

$listSql = '
    SELECT
        recordType.id,
        recordType.name,
        recordType.colour,
        dataField.name AS primaryDataField
    FROM
        recordType
        # check that the primary data field is set and valid
        LEFT JOIN dataField ON dataField.id=recordType.primaryDataFieldId AND dataField.recordTypeId=recordType.id AND !dataField.deletedAt
    WHERE
        !recordType.deletedAt
';

function extraButtonsAfter(){ ?>
    <a class="btn" href="importExport.php">Import</a>  
<?}

include('../../lib/core/listPage.php');
