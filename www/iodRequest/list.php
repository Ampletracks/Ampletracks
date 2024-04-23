<?

$listSql = '
    SELECT
        *
    FROM
        iodRequest
    WHERE
        iodRequest.status<>"deleted"
';

$title="Instance On Demand";
$hideAddButton=true;
include('../../lib/core/listPage.php');
