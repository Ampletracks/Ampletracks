<?
$INPUTS = array(
);

$listSql = 'SELECT
    id                           ,
    firstName                    ,
    lastName                     ,
    email                        ,
    mobile                       ,
    lastLoggedInAt               ,
    lastLoginIp                  ,
    deletedAt
FROM user WHERE user.deletedAt=0
';

include( '../../lib/core/listPage.php' );

?>
