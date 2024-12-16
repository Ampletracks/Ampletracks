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
    ORDER BY cms.label ASC
';

include( '../../lib/core/listPage.php' );

?>
