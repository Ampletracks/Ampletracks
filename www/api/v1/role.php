<?
namespace API;

# define('API_ITEMS_PER_PAGE', 2);

$API_SQL = [
    'getIdList' => '
        SELECT DISTINCT role.id
        FROM role
        WHERE
            role.deletedAt=0
    ',
    'getListData' => '
        SELECT
            role.id,
            CONCAT("r_", role.apiId) AS apiId,
            role.name
        FROM
            role
        WHERE role.deletedAt=0 AND role.id IN (?)
    '
];

require('../../../lib/api/startup.php');

