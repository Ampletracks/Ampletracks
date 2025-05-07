<?

$INPUTS = [
    'search' => [
        'searchId' => 'INT SIGNED(SEARCH)',
        'searchType' => 'TEXT',
        'searchCondition' => 'TEXT',
        'negateSearch' => 'INT',
        'subMode' => 'TEXT',
        'existingSearchDescription' => 'TEXT',
        'searchTerm' => 'TEXT',
        'searchFrom' => 'TEXT',
        'searchTo' => 'TEXT',
        'searchValue' => 'TEXT',
        'recordTypeIds' => 'INT ARRAY',
        'dataFieldIds' => 'TEXT ARRAY',
        'search_eq' => 'TEXT',
        'search_ct' => 'TEXT',
        'search_gt' => 'TEXT',
        'search_lt' => 'TEXT',
    ]
];

$requireLogin = false;

include('../../lib/core/startup.php');
include(CORE_DIR.'/search.php');
include(LIB_DIR.'/simplifyLogicExpression.php');

$searchConditions = [
    'Contains' => 'ct',
    'Equals' => 'eq',
    'Greater than' => 'gt',
    'Greater than or equal to' => 'get',
    'Less than' => 'lt',
    'Less than or equal to' => 'le',
    'Between (inclusive)' => 'bt',
    'Starts with' => 'sw',
    'Ends with' => 'ew',
];
$searchConditionLookup = [
    array_flip($searchConditions),
    [
        'ct' => 'Does not contain',
        'eq' => 'Does not equal',
        'gt' => 'Is not greater than',
        'get' => 'Is not greater than or equal to',
        'lt' => 'Is not less than',
        'le' => 'Is not less than or equal to',
        'bt' => 'Is not between (inclusive)',
        'sw' => 'Does not start with',
        'ew' => 'Does not end with',
    ]
];

$searchDescription = '';

$negateSearch = ws('negateSearch') ? 1 : 0;

if (ws('searchType')=='advanced') {
    $searchValue = trim(ws('searchValue'));
    $searchCondition = ws('searchCondition');

    if (!strpos('|gt|ge|lt|le|eq|ct|bt',$searchCondition)) {
        $searchCondition = '';
    }
    if ($searchCondition == 'bt' && !ws('searchTo')) {
        $searchCondition = 'ge';
    }
    if ($searchCondition == 'bt' && !ws('searchFrom')) {
        $searchCondition = 'le';
    }
    if ($searchCondition == 'bt') {
        $searchvalue = [ws('searchFrom'), ws('searchTo')];
    }
} else {
    $searchCondition = 'ct';
    $searchValue = trim(ws('searchTerm'));
}

$searchId=ws('searchId');

$originalDataFieldIds = ws('dataFieldIds');
$originalDataFieldIds = forceArray($originalDataFieldIds);

if (!empty($searchValue) && ws('mode')=='search') {
    include_once(LIB_DIR.'/dataField.php');

    $subMode = ws('subMode');

    $dataFieldIdsQuery = ['
        SELECT
            dataField.id,
            CONCAT( recordType.name, " ",IF(LENGTH(dataField.publicName),dataField.publicName,dataField.question)) AS name
        FROM
            dataField 
            INNER JOIN recordType ON recordType.id=dataField.recordTypeId
        WHERE
            dataField.deletedAt=0 AND
            dataField.displayToPublic>0
    '];

    $dataFieldIds = $originalDataFieldIds;

    // Clean and validate dataFieldIds
    if (count($dataFieldIds)) {
        // some of the entries in $dataFieldIds may be comma separated lists
        // To handle these we join all values into one long string then split on comma
        $dataFieldIds = array_filter(explode(',', implode(',', $dataFieldIds)), function($value) {
            return ctype_digit($value) && (int)$value > 0;
        });
    }

    if (count($dataFieldIds)) {
        $dataFieldIdsQuery[0].=' AND dataField.id IN (?) AND dataField.useForAdvancedSearch>0';
        $dataFieldIdsQuery[1] = $dataFieldIds;
    // If no specific dataField has been specified then use the ones that are flagged for use in general search
    } else {
        $dataFieldIdsQuery[0].=' AND dataField.useForGeneralSearch>0';
        $searchDescription =  'Any general search field';
    }

    $dataFieldInfo = $DB->getHash($dataFieldIdsQuery);
    $dataFieldIds = array_keys($dataFieldInfo);    

    if (empty($searchDescription)) {
        $searchDescription =  implode(',',array_values($dataFieldInfo));
        // If there is more than one field then add the words "any of these fields" on the front
        if (count($dataFieldInfo)>1) {
            $searchDescription = 'any of these fields ['.$searchDescription.']';
        }
    }

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

    // Check if they want to restart the result set
    if (!in_array($subMode,['remove','filter','add'])) {
        $subMode='new';
        $searchId=false;
        ws('existingSearchDescription','');
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
        $searchSql = $dataField->searchSql( $searchValue, $searchCondition, 'recordData.data','recordData.dataFieldId' );
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

    $searchDescription .= ' {{'.$searchConditionLookup[$negateSearch][$searchCondition].'}} ';

    if ($searchCondition == 'bt') {
        $searchDescription .= htmlspecialchars($searchValue[0]).' and '.htmlspecialchars($searchValue[1]);
    } else {
        $searchDescription .= is_numeric($searchValue) ? $searchValue : '"'.$searchValue.'"';
    }

    $existingSearchDescription = ws('existingSearchDescription');

    if ($subMode=='remove') {
        $DB->exec('
            DELETE searchResult
            FROM searchResult
            INNER JOIN searchBuild ON searchBuild.searchId=searchResult.searchId AND searchBuild.recordId=searchResult.recordId
            WHERE searchResult.searchId=?
        ',$searchId);
        if (!empty($existingSearchDescription)) {
            $searchDescription = '('.$existingSearchDescription.') {{AND NOT}} ('.$searchDescription.')';
        }
    } else if ($subMode=='filter') {
        $DB->exec('
            DELETE searchResult
            FROM searchResult
            LEFT JOIN searchBuild ON searchBuild.searchId=searchResult.searchId AND searchBuild.recordId=searchResult.recordId
            WHERE searchResult.searchId=? AND ISNULL(searchBuild.searchId)
        ',$searchId);
        if (!empty($existingSearchDescription)) {
            $searchDescription = '('.$existingSearchDescription.') {{AND}} '.$searchDescription;
        }
    } else {
        $DB->exec('
            INSERT INTO searchResult (searchId,recordId)
            SELECT searchId,recordId
            FROM searchBuild
            WHERE searchBuild.searchId=?
            ON DUPLICATE KEY UPDATE searchResult.searchId = searchResult.searchId
        ',$searchId);
        if (!empty($existingSearchDescription)) {
            $searchDescription = '('.$existingSearchDescription.') {{OR}} '.$searchDescription;
        }
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
    "start new search" => "new",
    "add matching records to existing search results" => "add",
    "remove matching records from existing search results" => "remove",
    "narrow down existing search results" => "filter",
]);
$subModeSelect->setExtra('id="subModeSelect"');

$recordTypeSelect = new formOptionbox('recordTypeIds','
    SELECT DISTINCT recordType.name, recordType.id
    FROM
        dataField
        INNER JOIN recordType ON recordType.id=dataField.recordTypeId AND recordType.includeInPublicSearch>0
        INNER JOIN dataFieldType ON dataFieldType.id=dataField.typeId
    WHERE
        dataField.useForAdvancedSearch AND
        dataField.deletedAt=0 AND
        dataField.displayToPublic>0 AND
        dataFieldType.hasValue>0
');
$recordTypeSelect->setMultiple(true);

$searchFields = $DB->query('
    SELECT
        IF(LENGTH(dataField.publicName),dataField.publicName,dataField.question) AS name,
        MIN(dataField.id) AS id,
        GROUP_CONCAT(DISTINCT dataField.id SEPARATOR ",") AS ids,
        GROUP_CONCAT(DISTINCT recordType.id SEPARATOR "|") AS recordTypeIds,
        dataFieldType.name AS type
    FROM
        dataField
        INNER JOIN recordType ON recordType.id=dataField.recordTypeId AND recordType.includeInPublicSearch>0
        INNER JOIN dataFieldType ON dataFieldType.id=dataField.typeId
    WHERE
        dataField.useForAdvancedSearch AND
        dataField.deletedAt=0 AND
        dataField.displayToPublic>0 AND
        dataFieldType.hasValue>0
    GROUP BY name
');

$searchConditionSelect = new formOptionbox('searchCondition',$searchConditions);

if (!empty( $searchDescription )) {
    $searchDescriptionMarkup = htmlspecialchars(simplifyLogicExpression($searchDescription));
    $searchDescriptionMarkup = preg_replace('/\{\{([^\}]+)\}\}/','<b>$1</b>',$searchDescriptionMarkup);
}

ws('searchTerm',$searchValue);
ws('searchValue',$searchValue);

$extraScripts[] = '/javascript/dependentInputs.js';
include(VIEWS_DIR.'/header.php');
?>

<form action="" method="post">
<?formHidden('mode','search'); ?>
<?formHidden('searchType','basic',null,'id="searchType"'); ?>
<?formHidden('searchId'); ?>
<?formHidden('existingSearchDescription',$searchDescription); ?>
<div id="basicSearch">
    Search for: <? formTextbox('searchTerm',20,100); ?>
    <a  href="#" id="advancedSearchButton">Advanced Search Options</a><br />
</div>
<div id="advancedSearch">
    <a class="btn small" href="#" id="basicSearchButton" style="float:right">Back to basic Search</a>
    <h2>Record Types</h2>
    <div id="advancedSearchRecordTypeSelectContainer">
        <div id="advancedSearchRecordTypeSelect">
            <? $recordTypeSelect->displayCheckboxes(); ?>
        </div>
        <div class="info">Selecting no record types is the same as selecting all record types</div>
    </div>

    <h2>Search Fields</h2>
    <div id="advancedSearchQuestionSelectContainer">
        <div id="advancedSearchQuestionSelect">
            <?
            $typeLookups = [];
            while( $searchFields->fetchInto($row) ) {
                if (!isset($typeLookups[$row['type']])) $typeLookups[$row['type']] = [];
                $typeLookups[$row['type']][] = $row['ids'];
                ?>
                <span class="checkbox" dependencyCombinator="or" dependsOn1="recordTypeIds[] cy <?=$row['recordTypeIds']?>" dependsOn2="recordTypeIds[] em">
                    <input
                        id="searchField_<?=$row['id']?>"
                        type="checkbox"
                        name="dataFieldIds[]"
                        <? if (in_array($row['ids'],$originalDataFieldIds)) echo 'checked="checked"'; ?>
                        value="<?=htmlspecialchars($row['ids'])?>"
                    >
                    <label for="searchField_<?=$row['id']?>"><?=htmlspecialchars($row['name'])?></label>
                </span>
                <?
            }
            ?>
        </div>
        <div class="info">Selecting no fields is the same as selecting all fields</div>
    </div>
    <h2>Search Condition</h2>
    <div id="advancedSearchConditionsContainer">
        <div id="form-row"> 
            <div class="question">Test</div>
            <div class="answer">
                <? $searchConditionSelect->display() ?>
            </div>
        </div>
        <div id="form-row" dependsOn="searchCondition !eq bt">
            <div class="question">Value</div>
            <div class="answer">
                <? formTextbox('searchValue',20,100); ?>
            </div>
        </div>
        <div id="form-row" dependsOn="searchCondition eq bt">
            <div class="question">From:</div>
            <div class="answer">
                <? formTextbox('searchFrom',20,100); ?>
            </div>
        </div>
        <div id="form-row" dependsOn="searchCondition eq bt"> 
            <div class="question">To:</div>
            <div class="answer">
                <? formTextbox('searchTo',20,100); ?>
            </div>
        </div>
        <div dependsOn="searchCondition !eq ct" class="info">Omit units for measurements (unit conversion not currently supported)</div>
        <? if (isset($typeLookups['Date'])) {?>
            <? // Display the date help check only if they selected at least one date field ?>
            <div dependencyCombinator="and" dependsOn="searchCondition !eq ct" dependsOn="dataFieldIds[] cy <?=implode("|",$typeLookups['Date'])?>" class="info">Use YYYY-MM-DD for dates and YYYY-MM-DD hh:mm for date and time. You can also use any string supported by strtotime.</div>
        <? } ?>
    </div>
</div>
<script>
    $(function() {
        $('#searchType').val('basic');
        $('#advancedSearchButton').click(function() {
            $('#searchType').val('advanced');
            $('#advancedSearch').toggle();
            $('input[name=searchValue]').val($('input[name=searchTerm]').val());
            $('#basicSearch').hide();
            return false;
        });
        $('#basicSearchButton').click(function() {
            $('#searchType').val('basic');
            $('#advancedSearch').hide();
            $('#basicSearch').show();
            return false;
        });
        <? if (ws('searchType')=='advanced') { ?>
            $('#searchType').val('advanced');
            $('#basicSearch').hide();
        <? } else { ?>
            $('#advancedSearch').hide();
        <? } ?>
    });
</script>

<? if (ws('searchId')) { ?>
    <div class="extendSearch">
        <label for="subModeSelect">Extend existing search:</label><? $subModeSelect->display(); ?>
    </div>
<? } ?>
<input type="submit" value="Search" />
<? if ($searchId && $numResults>0) { ?>
    <a class="btn" href="index.php">Clear Search</a><br />
<? } ?>
</form>

<? if ($searchId && $numResults>0) { ?>
<form action="download.php" method="POST" id="downloadForm" style="display: inline;" target="_blank" >
    <? formHidden('mode','start'); ?>
    <? formHidden('searchId'); ?>
    <input type="submit" value="Download All Results" />
</form>
<? } ?>

<? if ($searchDescription) { ?>
    <div class="searchDescription">Searchresults for: <?=$searchDescriptionMarkup?></div>
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
<?
include(VIEWS_DIR.'/footer.php');
