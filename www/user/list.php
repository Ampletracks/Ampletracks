<?
$INPUTS = array(
);

$listSql = '
SELECT
    user.id                           ,
    user.firstName                    ,
    user.lastName                     ,
    user.email                        ,
    user.mobile                       ,
    user.lastLoggedInAt               ,
    user.lastLoginIp                  ,
    user.deletedAt
FROM user
    LEFT JOIN userProject ON userProject.userId=user.id
WHERE user.deletedAt=0
GROUP BY user.id
';

include( '../../lib/core/listPage.php' );

?>
