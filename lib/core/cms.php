<?

$CMS_CACHE = false;
$CMS_PAGE_ID = 0;

function cms($label,$markupOption=1,$defaultContent='', $stripTags=false) {
	global $CMS_PAGE_ID, $CMS_CACHE, $DB;

	# for lookups make everything lowercase to avoid case problems
	$lcLabel = strtolower($label);

	# use markupOption=-1 for inclusion in javascript strings (as is)
	# use markupOption=-2 for inclusion in javascript strings (markup escaped)
	
	$allowMarkup = ($markupOption==1 or $markupOption==-1)?1:0;
	$jsEscape = $markupOption==-1 or $markupOption==-2;

	# Work out the page ID if we don't already know it
	if (!$CMS_PAGE_ID) {
		$page = $_SERVER["SCRIPT_NAME"];
		$CMS_PAGE_ID = $DB->getValue('SELECT id FROM cmsPage WHERE page=?',$page);
		# if this fails then we need to add the page into the page table
		if (!$CMS_PAGE_ID) {
			$CMS_PAGE_ID = $DB->insert('cmsPage',array('page'=>$page));
		}
	}

	# Pre-populate the Cache if we haven't already done so
	if (!is_array($CMS_CACHE)) {
		$CMS_CACHE = $DB->getHash('
			SELECT cms.lookup,cms.content
			FROM
				cms
				INNER JOIN cmsPageLabel ON cmsPageLabel.cmsId=cms.id
			WHERE
				cmsPageLabel.pageId=?
		',$CMS_PAGE_ID);
	}
	
	# Look for the label in the cache
    $lookup = md5($lcLabel).$allowMarkup;
	if (isset($CMS_CACHE[$lookup])) {
		$content = $CMS_CACHE[$lookup];
	} else {
	
		# not in md5 cache so look up the value from the cms table
		list( $cmsId, $content ) = $DB->getRow('SELECT id,content FROM cms WHERE lookup=?',$lookup);

		# if it's not in the CMS table then create it
        # If no specific default value has been used then use the label as the default default value
        if (!strlen($defaultContent)) $defaultContent=$label;
		if (!$cmsId) {
			$cmsId = $DB->insert('cms',array(
				'label'			    => $lcLabel,
				'allowMarkup'	    => $allowMarkup,
				'content'		    => $defaultContent,
                'defaultContent'    => $defaultContent,
                'lookup'            => $lookup
			));
			$content = $defaultContent;
		}

		# add it to the cache
		$CMS_CACHE[$lookup] = $content;
		
		# We need to remember the fact that this label appeared on this page
		$DB->insert('cmsPageLabel',array('pageId'=>$CMS_PAGE_ID,'cmsId'=>$cmsId));
	}

    if ($stripTags) $content = strip_tags($content);
	if (!$allowMarkup) $content = htmlspecialchars($content);
    else {
        # All external links should open in new windo
        $content = makeLinksOpenInNewWindow($content);
    }
	if ($jsEscape) $content = addslashes($content);
    if ($content == '_empty_') $content = '';
	return $content;
}
