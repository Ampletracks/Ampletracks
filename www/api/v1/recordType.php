<?
namespace API;

# define('API_ITEMS_PER_PAGE', 2);

$API_SQL = [
    'getIdList' => '
        SELECT DISTINCT recordType.id
        FROM recordType
        WHERE
            recordType.deletedAt=0
    ',
    'getListData' => '
        SELECT
            recordType.id,
            recordType.name,
            recordType.colour,
            CONCAT("rt_", recordType.apiId) AS apiId,
            dataField.id AS primaryDataFieldId,
            CONCAT("df_", dataField.apiId) AS primaryDataFieldApiId
        FROM
            recordType
            LEFT JOIN dataField ON dataField.id=recordType.primaryDataFieldId AND dataField.recordTypeId=recordType.id AND !dataField.deletedAt
        WHERE recordType.deletedAt=0 AND recordType.id IN (?)
    '
];

$API_ID_MAPPINGS = [
    [ 'recordType', 'id', 'apiId' ],
    [ 'dataField', 'primaryDataFieldId', 'primaryDataFieldApiId' ]
];

require('../../../lib/api/startup.php');

