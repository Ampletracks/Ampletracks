<?

$listSql="
	SELECT
		CONCAT(user.firstName,' ',user.lastName) AS userName,
        DATE_FORMAT(FROM_UNIXTIME(actionLog.time), '%d/%m/%Y %H:%i:%s ') as eventDateTime,
		actionLog.*
	FROM
		actionLog
		LEFT JOIN user ON user.id=actionLog.userId
	WHERE 1=1
	ORDER BY time DESC
";

include( '../../lib/core/listPage.php' );

