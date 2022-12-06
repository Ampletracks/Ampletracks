<?

$listSql = '
    SELECT *
    FROM role
    WHERE
        # dont list the special superuser role
        id > 1 AND
        deletedAt=0
';

include('../../lib/core/listPage.php');