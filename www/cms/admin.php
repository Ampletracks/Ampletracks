<?

$INPUTS = array(
    '.*' => array(
        'lookup' => 'TEXT'
	)
);

function processInputs() {
    global $DB, $WS, $USER_ROLES;

    if (ws('lookup')) {
        $id = $DB->getValue('SELECT id FROM cms WHERE lookup=?',ws('lookup'));
        $WS['id'] = $id;
    }
}

function extraPageContent() {
	global $DB;
	
	$pages = $DB->getColumn('
		SELECT
			cmsPage.page
		FROM
			cmsPageLabel
			INNER JOIN cmsPage ON cmsPage.id=cmsPageLabel.pageId
		WHERE
			cmsPageLabel.cmsId="@@id@@"
	');
	echo '<h2>Pages where this occurs...</h2>';
	echo '<ul><li>'.implode('</li><li>',$pages).'</li></ul>';
}

include( '../../lib/core/adminPage.php' );


?>
