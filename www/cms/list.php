<?

$listSql = '
	SELECT
		cms.id,
		cms.label,
		cms.content,
		cms.allowMarkup
	FROM
		cms
	WHERE 1=1
';

include( '../../lib/core/listPage.php' );

?>
