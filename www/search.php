<?

$INPUTS = [
    'search' => [
        'subMode' => 'TEXT',
        'searchTerm' => 'TEXT',
        'recordTypeIds' => 'INT ARRAY',
        'searchId' => 'INT SIGNED(SEARCH)'
    ]
];

include('../lib/core/startup.php');
include(CORE_DIR.'/search.php');

$searchTerm = trim(ws('searchTerm'));
$searchId='';

if (!empty($searchTerm) && ws('mode')=='search') {
    include_once(LIB_DIR.'/dataField.php');

    $subMode = ws('subMode');

    // If no specific dataField has been specified then use the ones that are flagged for use in general search
    $dataFieldIds = ws('dataFieldIds');
    $dataFieldIds =  array_filter(forceArray($dataFieldIds));
    $dataFieldIdsQuery = ['
        SELECT id
        FROM
            dataField 
        WHERE
            dataField.deletedAt=0 AND
            dataField.displayToPublic>0
    '];
    if (count($dataFieldIds)) {
        $dataFieldIdsQuery[0].=' AND dataField.id IN (?) AND dataField.useForAdvancedSearch>0';
        $dataFieldIdsQuery[1] = $dataFieldIds;
    } else {
        $dataFieldIdsQuery[0].=' AND dataField.useForGeneralSearch>0';
    }
    $dataFieldIds = $DB->getColumn($dataFieldIdsQuery);
    
    // If no record types specified then select all record types
    // Validate the list of ID's if one is provided
    $recordTypeIds = ws('recordTypeIds');
    $recordTypeIds = array_filter(forceArray($recordTypeIds));
    
    $dataFieldsQuery = ['
        SELECT dataField.*
        FROM
            recordType
            INNER JOIN dataField ON dataField.recordTypeId=recordType.id AND dataField.deletedAt=0
        WHERE
            recordType.deletedAt=0 AND
            recordType.includeInPublicSearch>0 AND
            dataField.id IN (?)
    ',$dataFieldIds];
    if (count($recordTypeIds)) {
        $dataFieldsQuery[0].=' AND recordType.id IN (?)';
        $dataFieldsQuery[] = $recordTypeIds;
    }

    $dataFieldsQuery = $DB->query( $dataFieldsQuery );

    $searchId=ws('searchId');

    // Check if they want to restart the result set
    if (!in_array($subMode,['remove','filter','add'])) {
        $subMode='new';
        $searchId=false;
    }
    global $USER_ID;
    // If we do have a search ID then validate this
    // make sure it exists and belongs to the same user
    // Update the last used time while we're at it
    if (empty($searchId) || !$DB->update('search',['id'=>$searchId,'userId'=>$USER_ID],['lastUsedAt'=>time()])) {
        // No search ID, or it was invalid, or it expirerd
        // so create a new one
        $searchId = $DB->insert('search',[
            'userId'        => $USER_ID,
            'lastUsedAt'    => time(),
        ]);
    }

    ws('searchId',signInput($searchId,'SEARCH'));
 
    $unionQueries = [];
    $joins = '';

    if ($subMode=='remove' || $subMode=='filter') {
        // In these cases we can limit the searcing down to the records already in the result set by doing an inner join
        $joins = " INNER JOIN searchResult ON searchResult.searchId=\"$searchId\" AND searchResult.recordId=recordData.recordId ";
    }

    while ($dataFieldsQuery->fetchInto($row)) {
        $dataField = dataField::build($row);
        $searchSql = $dataField->searchSql( $searchTerm,'recordData.data','recordData.dataFieldId' );
        if (empty($searchSql)) continue;
        $unionQueries[] = "SELECT recordData.recordId FROM recordData $joins WHERE $searchSql AND hidden=0";
    }

    // Clear the searchBuild table for this search
    $DB->delete('searchBuild',['searchId'=>$searchId]);

    // Now run the search query into the searchBuild Table
    // See this conversation for rationale for using unions, "distinct" and "union all"
    // https://chatgpt.com/share/67af1ae8-81fc-8004-b21b-768dad90bd81

    $insertSql = '
        INSERT INTO searchBuild (searchId, recordId)
        SELECT DISTINCT '.$searchId.',recordId FROM (
            '.implode(' UNION ALL ',$unionQueries).'
        ) AS `data`
    ';
    $numResults = $DB->exec($insertSql);

    $numOriginalResults = 0;
    if ($subMode!='new') {
        $numOriginalResults = $DB->getValue('SELECT COUNT(*) FROM searchResult WHERE searchId=?',$searchId);
    }

    if ($subMode=='remove') {
        $DB->exec('
            DELETE searchResult
            FROM searchResult
            INNER JOIN searchBuild ON searchBuild.searchId=searchResult.searchId AND searchBuild.recordId=searchResult.recordId
            WHERE searchResult.searchId=?
        ',$searchId);
    } else if ($subMode=='filter') {
        $DB->exec('
            DELETE searchResult
            FROM searchResult
            LEFT JOIN searchBuild ON searchBuild.searchId=searchResult.searchId AND searchBuild.recordId=searchResult.recordId
            WHERE searchResult.searchId=? AND ISNULL(searchBuild.searchId)
        ',$searchId);
    } else {
        $DB->exec('
            INSERT INTO searchResult (searchId,recordId)
            SELECT searchId,recordId
            FROM searchBuild
            WHERE searchBuild.searchId=?
            ON DUPLICATE KEY UPDATE searchResult.searchId = searchResult.searchId
        ',$searchId);
    }

    //$DB->delete('searchBuild',['searchId'=>$searchId]);
    $numResults = $DB->getValue('SELECT COUNT(*) FROM searchResult WHERE searchId=?',$searchId);

    if ($subMode!='new') {
        $difference = $numResults - $numOriginalResults;
        $activity = sprintf("%d records %s result set",abs($difference),$difference>0?'added to':'removed from');
        if ($difference==0 && $subMode =='add') $activity= "No new records found";
        addUserNotice($activity,'success');
    }
}

$subModeSelect = new formOptionbox('subMode',[
    "add matching records to existing search results" => "add",
    "remove matching records from existing search results" => "remove",
    "narrow down existing search results" => "filter",
    "start new search" => "new"
]);
$subModeSelect->setExtra('id="subModeSelect"');
include(VIEWS_DIR.'/header.php');
?>

<form action="" method="post">
<?formHidden('mode','search'); ?>
<?formHidden('searchId'); ?>
Search: <? formTextbox('searchTerm',20,100); ?>
Searching: All Record Types <a href="#">Change</a><br />
<? if (ws('searchId')) { ?>
    <div class="extendSearch">
        <label for="subModeSelect">Extend existing search:</label><? $subModeSelect->display(); ?>
    </div>
<? } ?>
<input type="submit" value="Search" />
<? if ($searchId) { ?>
    <a class="btn" href="search.php">Clear Search</a><br />
<? } ?>
<?
if ($searchId) {
    // Get a list of all the record types in the result set
    $recordTypes = $DB->getHash('
        SELECT
            recordType.id,
            recordType.name,
            COUNT(*) as numResults
        FROM
            searchResult
            INNER JOIN record ON record.id=searchResult.recordId
            INNER JOIN recordType on recordType.id=record.typeId AND recordType.includeInPublicSearch>0
        WHERE
            searchResult.searchId=?
        GROUP BY recordType.id
    ',$searchId);
    $maxRows = 300;
    foreach ($recordTypes as $recordTypeId=>$recordTypeInfo) {
        echo "<h2>".htmlspecialchars($recordTypeInfo['name'])."</h2>";
        if ($recordTypeInfo['numResults']>$maxRows) echo "<div class=\"rowsLimited warning\">Only showing most recent $maxRows rows out of a total of {$recordTypeInfo['numResults']}</div>";
        $fieldsToDisplay = $DB->getHash('
            SELECT dataField.id, dataField.name, dataField.exportName, dataField.typeId, dataField.unit, dataField.parameters, dataFieldType.name as type
            FROM dataField
                INNER JOIN dataFieldType ON dataFieldType.id=dataField.typeId
            WHERE deletedAt=0 AND displayToPublic>0 AND displayOnPublicList>0 AND recordTypeId=?
            ORDER BY dataField.orderId ASC
        ',$recordTypeId);

        $fields = $joins = '';
        foreach ( $fieldsToDisplay as $id=>$fieldData ) {
            $fieldData['id']=$id;
            $field = DataField::build($fieldData);
            $alias = $field->filterAlias();
            $joins .= "LEFT JOIN recordData $alias ON $alias.recordId=record.Id AND $alias.dataFieldId=".(int)$id." AND $alias.hidden=0\n";
            $fields .= ', `'.$alias.'`.`data` AS answer_'.(int)$id;
        }
        $fields = preg_replace('/,$/','',$fields);

        $sql="
            SELECT
                record.id, record.path, record.parentId, record.hiddenFields, record.typeId AS recordTypeId, MAX(label.id) AS labelId,
                recordType.primaryDataFieldId, recordType.name AS recordType,
                project.name AS project
                $fields
            FROM
                searchResult
                INNER JOIN record ON record.id=searchResult.recordId
                INNER JOIN recordType ON recordType.id=record.typeId
                LEFT JOIN project ON project.id=record.projectId
                LEFT JOIN label ON label.recordId=record.id
                $joins
            WHERE
                searchResult.searchId=$searchId AND
                !record.deletedAt AND
                record.lastSavedAt AND
                record.typeId=$recordTypeId
            GROUP BY record.id
            ORDER BY record.id DESC
            LIMIT $maxRows
        ";

        $search = new Search('record/search',$sql);
        $search->display(true);
        echo "<hr />";
    }
}
?>
</form>
<?
include(VIEWS_DIR.'/footer.php');