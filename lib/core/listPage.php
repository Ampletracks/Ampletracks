
<?

// ================ LIST ==================

include('deriveEntity.php');
if (!isset($INPUTS)) $INPUTS = array();
if (!isset($INPUTS['.*'])) $INPUTS['.*'] = array();
if (!isset($INPUTS['select'])) $INPUTS['select'] = array();
$INPUTS['.*']['csv'] = 'TEXT';
$INPUTS['.*']['json'] = 'TEXT';
$INPUTS['.*']['previousAction'] = 'TEXT';
$INPUTS['.*']['limit'] = 'INT';
$INPUTS['.*']['scrollPosition'] = 'INT';
$INPUTS['select']['name'] = 'TEXT';
$INPUTS['select']['size'] = 'INT';
$INPUTS['select']['multiple'] = 'TEXT';
$INPUTS['select']['default'] = 'TEXT';

$INPUTS_FROM_FILE = "search/$ENTITY/list.tpl";

include_once('search.php');
include_once('pluralize.php');

include("startup.php");

if (isset($primaryFilterIdField)) applyPrimaryFilter($primaryFilterIdField);

if (function_exists('postStartup')) postStartup(isset($WS['mode'])?$WS['mode']:'');

// These are just to keep intelephense happy
// This also make a handly list of all the hooks that are available
if (0) {
    function canDo(){};
    function postStartup(){};
    function modifyList(){};
    function extraHeaderContent(){};
    function afterList(){};
    function addUserAccessLimits(){};
};

// =============== PERMISSIONS CHECK ===============
if ( function_exists('canDo') ) {
    if (!isset($permissionsEntity)) $permissionsEntity = $ENTITY;
    if (!canDo('list',$permissionsEntity)) {
        displayError('You do not have permission to list '.pluralize($ENTITY));
        exit;
    }
}

if (function_exists('processInputs')) processInputs(isset($WS['mode'])?$WS['mode']:'');

if ($WS['mode']=='select') $outputTemplate='select';
if ($WS['mode']=='json') $outputTemplate='json';
if (!isset($outputTemplate) && isAjaxRequest()) $outputTemplate='json';
if (isset($WS['csv'])) $outputTemplate='csv';
if (isset($WS['json'])) $outputTemplate='json';

if (!isset($outputTemplate)) $outputTemplate = "list";

if (!isset($listSql)) {
    if (function_exists('listSql')) $listSql = listSql();
    else {
        # see if the table has a deletedAt column
        $colNames=$DB->getColumnNames($ENTITY);
        $where = '1=1';
        if (in_array('deletedAt',$colNames)) $where = '!deletedAt';

        $listSql = 'SELECT * FROM `'.$ENTITY.'` WHERE '.$where;
    }
}

if ($outputTemplate!='select' && $outputTemplate!='csv' && !(isset($ignoreLimit) && $ignoreLimit)) {
    $limit = (int)ws('limit');
    if (!$limit) {
        if (isset($rowLimit)) $limit = (int)$rowLimit;
        else $limit = 300;
    }
    if ($limit > 1000) $limit = 1000;
    if (!preg_match('/LIMIT \\d+[\\s\\r\\n]*$/',$listSql)) $listSql .= ' LIMIT '.$limit;
}

if (file_exists(SITE_BASE_DIR.'/search/'.$ENTITY.'/'.$outputTemplate.'.tpl')) $outputTemplateFile = $ENTITY.'/'.$outputTemplate;
else $outputTemplateFile = $outputTemplate;
$list = new search($outputTemplateFile,$listSql);

enrichRowData($WS,'inbound');

if (function_exists('prepareDisplay')) prepareDisplay($list);

$list->addConditions('filter_');
$list->addConditions('having_','HAVING');

if (function_exists('addUserAccessLimits')) addUserAccessLimits(['entity'=>$permissionsEntity]);
$list->addConditions('limit_');

if ($outputTemplate=='csv' || $outputTemplate=='json' || $outputTemplate=='select') {
    $list->display(1);
    if($outputTemplate=='csv' && ws('logDownloadReport')) logAction('Report', 0, 'Downloaded '.ws('logDownloadReport').' report');
    exit;
}

if (function_exists('modifyList')) modifyList($list);

$entityName = isset($entityName)?$entityName:fromCamelCase($ENTITY);

if (!isset($title) || !$title) $title = cms(ucfirst($entityName)." list",0);

if (strlen(ws('scrollPosition'))) {
    setCookie('scrollPosition',ws('scrollPosition'));
}

if (!isset($extraBodyAttributes)) $extraBodyAttributes=array();
$extraBodyAttributes = array_merge( $extraBodyAttributes, array('rememberScrollPosition' => "yes"));
if (!isset($extraBodyClasses)) $extraBodyClasses=array();
$extraBodyClasses = array_merge( $extraBodyClasses, array('list',$ENTITY));

include(VIEWS_DIR.'/header.php');
?>

<? if (ws('previousAction')) { ?>
    <div class="notify">
        <? if (ws('previousAction')=='delete') {
            echo cms('The selected record was deleted');

        // If the previous action contains a space it is taken to be a sentence to display
        } else if (strpos(ws('previousAction'),' ')) {
            echo nl2br(htmlspecialchars(ws('previousAction'))).' at '.date('H:i:s');

        // Otherwise it is assumed to be just a general save
        } else {
            echo cms('Changes saved');
        } ?>
    </div>
<? }?>

<form id="filterForm" action="list.php" method="post">
    <header class="data-records-header">
        <h1><?=$title?></h1>

        <? if (!isset($hideButtons) || !$hideButtons) { ?>
            <ul class="btn-list no-border">
                <? if(function_exists('extraButtonsBefore')) extraButtonsBefore('top'); ?>
                <? if (!isset($hideDownloadButton) || !$hideDownloadButton) { ?>
                    <? if (file_exists(SITE_BASE_DIR.'/search/'.$ENTITY.'/json.tpl')) { ?>
                        <li>
                            <button class="btn" type="submit" name="json" ><?=cms('Button: Export JSON', 0, 'Export JSON')?></button>
                        </li>
                    <? } ?>
                    <li>
                        <button class="btn" type="submit" name="csv" ><?=cms('Button: Export CSV', 0, 'Export CSV')?></button>
                    </li>
                <? } ?>
                <? if (!function_exists('canDo') || canDo('create',0,$permissionsEntity)) {
                    if((!isset($hideAddButton) || !$hideAddButton) && file_exists(SITE_BASE_DIR."/www/$ENTITY/admin.php")) { ?>
                        <li>
                            <button class="addButton btn" type="button" onClick="window.location.href='admin.php'"/><?=cms("Button: Add $entityName", 0, "Add $entityName")?></button>
                        </li>
                    <? }
                } ?>
                <? if(function_exists('extraButtonsAfter')) extraButtonsAfter('top'); ?>
            </ul>
        <? } ?>

        <? if(function_exists('extraHeaderContent')) { ?>
            <? /* TODO change float to something better */ ?>
            <div style="float: right; margin: 30px 10px 0px 0px">
                <? extraHeaderContent() ?>
            </div>
        <? } ?>
    </header>
    <hr>

    <?
    if(function_exists('beforeList')) beforeList();
    if(isset($beforeList)) echo $beforeList;
    ?>

    <p id="rowsDisplayedTopPlaceholder"></p>

    <div class="table-container">
        <?
        $list->display(1);
        $displayRows = isset($numRowsOverride) && $numRowsOverride ? $numRowsOverride : $list->numRows();
        if(!isset($rowCountPrefix)) $rowCountPrefix = $list->resultWasLimited() ? 'Only first ':'All ';
        $rowCountMarkup = $rowCountPrefix.$displayRows.' '.htmlspecialchars(fromCamelCase(pluralize($entityName,$displayRows))).' displayed';

        if($displayRows>0) {
            echo '<p class="rowCount bottom">'.$rowCountMarkup.'</p>';
            echo '<p class="rowCount top" moveTo="#rowsDisplayedTopPlaceholder">'.$rowCountMarkup.'</p>';
        }
        ?>
    </div>

    <? if(function_exists('afterList')) afterList() ?>

    <? if(!isset($hideButtons) || !$hideButtons) { ?>
        <div class="buttons bottom">
            <? if(function_exists('extraButtonsBefore')) extraButtonsBefore('bottom'); ?>
            <? if (!function_exists('canDo') || canDo('create',0,$permissionsEntity)) {
                if((!isset($hideAddButton) || !$hideAddButton) && file_exists(SITE_BASE_DIR."/www/$ENTITY/admin.php")) { ?>
                    <input type="button" value="<?=cms("Button: Add $entityName",0,"Add $entityName")?>" class="add" onClick="window.location.href='admin.php'"/>
                <? }
            }

            if(function_exists('extraButtonsAfter')) extraButtonsAfter('bottom');
            ?>
        </div>
    <? } ?>
</form>
<? if(function_exists('extraPageContent')) extraPageContent() ?>

<? include(VIEWS_DIR.'/footer.php'); ?>
