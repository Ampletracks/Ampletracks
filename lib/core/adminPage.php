<?

include_once('deriveEntity.php');

if (!isset($INPUTS)) $INPUTS = array();
if (!isset($INPUTS['.*'])) $INPUTS['.*'] = array();
$INPUTS['.*']['id'] = 'INT';
$INPUTS['.*']['submitButton'] = 'TEXT';

if (!isset($INPUTS_FROM_FILE)) {
    $INPUTS_FROM_FILE = array();
} else if (!is_array($INPUTS_FROM_FILE)) {
    $INPUTS_FROM_FILE = array($INPUTS_FROM_FILE);
}
$INPUTS_FROM_FILE[] = "views/$ENTITY/admin.php";

include_once('startup.php');

// These are just to keep intelephense happy
// This also make a handly list of all the hooks that are available
// They are listed here in the order they are called in
if (0) {
    function postStartup($mode,$id){};
    function processInputs($mode,$id){};
    function processUpdateBefore($id){};
    function entityLoadSql($mode,$id){};
    function processDeleteBefore($id,$doShred){};
    function processDeleteAfter($id,$doShred){};
    function processUpdateAfter($id,$newEntity,$actuallyChanged){};
    function prepareDisplay($id,$mode){};
    function extraPageContentAboveButtons(){};
    function extraButtonsBefore(){};
    function extraButtonsAfter(){};
    function extraPageContent(){};
}

if (!isset($WS['mode'])) $WS['mode']='';
$id = (int)ws('id');

$permissionsModeMap = [
    'delete'    => 'delete',
    'edit'      => 'edit',
    'update'    => 'edit',
    'shred'     => 'delete'
];

if (function_exists('postStartup')) postStartup($WS['mode'],$id);
enrichRowData($WS,'inbound');

if (!isset($backHref)) $backHref='list.php?';
if (!strpos($backHref,'?')) $backHref .= '?';

// =============== PERMISSIONS CHECK ===============
if ( function_exists('canDo') ) {
    if (!isset($permissionsEntity)) $permissionsEntity = $ENTITY;

    if (!isset($permissionsMode)) {
        if ( !$id ) $permissionsMode = 'create';
        else if (isset($permissionsModeMap[$WS['mode']])) $permissionsMode = $permissionsModeMap[$WS['mode']];
        else $permissionsMode = 'view';
    }

    if (!canDo($permissionsMode,$id,$permissionsEntity)) {
        // if they're not allowed to edit then see if we can downgrade them to view
        if ($permissionsMode=='edit' && canDo('view',$id,$permissionsEntity)) {
            ws('mode','view');
        } else {
            displayError('You do not have permission to '.$permissionsMode.' this '.fromCamelCase($ENTITY));
            exit;
        }
    }
}

# processInputs may choose to change $WS['mode'] if it wants to - that's why we don't pull ws('mode') into a local variable
if (function_exists('processInputs')) processInputs(ws('mode'),ws('id'));

// =============== UPDATE =================
if ( $WS['mode']=='update' ) {

    // get details of the columns in the table
    // This is used to determine if the lastUpdatedAt column should be updated (for edits)
    // and also to add createdAt and createdBy columns
    $colNames=$DB->getColumnNames($ENTITY);

    //  if we have an ID then we are in edit mode
    $proceed = true;
    $actuallyChanged = false;
    if (function_exists('processUpdateBefore')) {
        $proceed=processUpdateBefore(ws('id'));
        // if the function omits to return a value then null is returned - this probably wants to be treated as OK to proceed
        if (is_null($proceed)) $proceed=true;
    }
    $newEntity = (ws('id')>0)?false:true;
    if (!$proceed || inputError()) {
        if (isAjaxRequest()) {
            if (inputError()) {
                $allErrors  = inputError('*');
                return $allErrors[array_key_first($allErrors)][0];
            }
            exit;
        }
    } else {
        if (!$newEntity) {

            $actuallyChanged = $DB->autoUpdate($ENTITY,$ENTITY.'_','id');
            if ($actuallyChanged) logAction($ENTITY,$WS['id'],'Edited');

        // otherwise we are not in edit mode so create a new item
        } else {
            // if the table has a createdAt column then set it to now
            if (in_array('createdAt',$colNames)) {
                $WS[$ENTITY.'_createdAt'] = time();
            }
            // if the table has a createdBy column then set it to the current user
            if (in_array('createdBy',$colNames)) {
                $WS[$ENTITY.'_createdBy'] = $USER_ID;
            }

            $WS['id'] = $DB->autoInsert($ENTITY,$ENTITY.'_');
            if ($WS['id']>0) {
                $actuallyChanged = true;
                logAction($ENTITY,$WS['id'],'Created');
            } else {
                $proceed = false;
                if (isAjaxRequest()) {
                    echo "Failed to create new ".fromCamelCase($ENTITY);
                    exit;
                }
            }
        }

        // if the update was successful then we need to update the lastUpdatedAt column (if it exists)
        if ($actuallyChanged) {
            # see if the table has a lastUpdatedAt column
            if (in_array('lastUpdatedAt',$colNames) && in_array('id',$colNames)) {
                $DB->update($ENTITY,['id'=>$WS['id']],['lastUpdatedAt'=>time()]);
            }
        }

        if (class_exists('formAsyncUpload') && (!isset($GLOBALS['dontProcessUploads']) || !$GLOBALS['dontProcessUploads'])) {
            formAsyncUpload::setAttributesAll($WS['id']);
            $errors = formAsyncUpload::storeAll();
            if (array_length($errors)) {
                foreach( $errors as $field=>$error ) {
                    inputError($field,$error);
                }
            }
        }
        if (isAjaxRequest()) {
            echo "OK";
            exit;
        }
    }

    if(!empty($API_METHOD)) {
        return;
    }
}

$id=0;
if ( isset($WS['id']) && $WS['id'] ) {

    $id = (int)$WS['id'];

    // =============== LOAD =================
    // load existing data for this item
    // Do this before deleting just in case the processDelete function wants to use some data about the entity

    if (function_exists('entityLoadSql')) $entityLoadSql = entityLoadSql($WS['mode'],$id);
    if (!isset($entityLoadSql) || !strlen($entityLoadSql)) { $entityLoadSql = "SELECT * FROM $ENTITY WHERE id='@@id@@'"; }
    
    $DB->returnHash();
    $entityData = $DB->getRow($entityLoadSql);
    // If we are in update mode and the update just failed then don't ovewrite existing Workspace data
    // Otherwise the fields will be reset to the data from the database rather than what the user just changed
    // This looks odd because errors may then be flagged against the original data (which was OK) not the
    // erroneous data that the user now provided.
    // The third parameter to loadRow determines whether existing values will be overwritten
    // ... set this to false if there has been an update error
    $DB->loadRow($entityLoadSql,[
        'overwrite' => !($WS['mode']=='update' && inputError()),
        'prefix' => "{$ENTITY}_"
    ]);

    if (class_exists('formAsyncUpload') && (!isset($GLOBALS['dontProcessUploads']) || !$GLOBALS['dontProcessUploads'])) {
        formAsyncUpload::setAttributesAll($id);
    }

    // =============== DELETE =================

    if ( $WS['mode']=='delete' || $WS['mode']=='shred' ) {

        $proceed=true;
        $ajaxMessage=null;
        if ( function_exists('processDeleteBefore') ) {
            $proceed = processDeleteBefore( $id, $WS['mode']=='shred' );
            // if the function omits to return a value then null is returned - this probably wants to be treated as OK to proceed
            if (is_array($proceed)) {
                list($proceed,$ajaxMessage) = $proceed;
            }
            if (is_null($proceed)) $proceed=true;
        }
        if ($proceed) {
            logAction($ENTITY,$id,$WS['mode']=='delete'?'Deleted':'Shredded');

            # see if the table has a deletedAt column
            $colNames=$DB->getColumnNames($ENTITY);
            if (in_array('deletedAt',$colNames)) {
                # don't actually delete it - just mark it as deleted
                # If mode is shred then set the delete date way back in the past so that this gets picked up immediately by the tidyUp script
                $updates = array('deletedAt'=>$WS['mode']=='delete'?time():100);
                if (in_array('deletedBy',$colNames)) {
                    $updates['deletedBy'] = $GLOBALS['USER_ID'];
                }
                if (in_array('deletedByType',$colNames)) {
                    $updates['deletedByType'] = $GLOBALS['USER_TYPE'];
                }
                $DB->update($ENTITY,array('id'=>$id),$updates);
            } else {
                $DB->exec("DELETE FROM $ENTITY WHERE id=?",$id);
            }

            if (class_exists('formAsyncUpload')) formAsyncUpload::deleteAll();

            if ( function_exists('processDeleteAfter') ) processDeleteAfter( $id, $WS['mode']=='shred' );

            if (isAjaxRequest()) echo $ajaxMessage ?? "OK";
        } else {
            if (isAjaxRequest()) echo $ajaxMessage ?? "Not deleted";
        }

        if (!isAjaxRequest()) {
            header('Location: '.$backHref.'&previousAction=delete');
        }

        exit;
    }
} else {
    $id = 0;
}

// =============== NOT DELETING =================
if (ws('mode')=='update' && function_exists('processUpdateAfter')) processUpdateAfter($id,$newEntity,$actuallyChanged);

if (ws('mode')=='update' && !inputError() && preg_match('/^save.*close$/i',ws('submitButton'))) {
    header('Location: '.$backHref.'&previousAction=update');
    exit;
}

if ($id && !isset($WS["{$ENTITY}_id"])) {
    if(empty($API_METHOD)) {
        include(VIEWS_DIR.'/header.php');
        echo "<h2>No ".fromCamelCase($ENTITY)." found with id ".$id."</h2>";
        include(VIEWS_DIR.'/footer.php');
        exit;
    } else {
        return;
    }
}

if (function_exists('prepareDisplay')) prepareDisplay($id,$WS['mode']);

if (!isset($ENTITYName)) $ENTITYName = fromCamelCase($ENTITY);
if (!isset($title)) $title = ucfirst(fromCamelCase($ENTITY))." administration";
if (!isset($heading)) {
    $heading = cms( (ws('id')?'Edit existing':'Add new')." $ENTITYName", 0 );
    if (ws('id') && ws($ENTITY.'_name')) $heading .= ': '.htmlspecialchars( ws($ENTITY.'_name') );
}

if (!isset($extraBodyClasses)) $extraBodyClasses=array();
$extraBodyClasses = array_merge( $extraBodyClasses, array('admin','checkExit'));

if(ws('mode') == 'update') {
    if ($proceed) {
        addUserNotice(cms('Changes saved',0), 'success');
    } else {
        addUserNotice(cms('There was a problem saving the changes',0), 'warning');
    }
}

if(!empty($API_METHOD)) {
    return;
}

include(VIEWS_DIR.'/header.php');
?>
<script>
    cms = {};
    // Add in the CMS for the "are you want to leave this page" warning
    cms.checkExitStart = '<?=addSlashes(cms('Are you sure you want to leave this page - all the changes since',0))?>';
    cms.checkExitEnd = '<?=addSlashes(cms('will be lost',0))?>';
</script>

<h1><?= $heading ?></h1>

<form method="post" action="admin.php" id="dataEntryForm" enctype="multipart/form-data"><? /* Need enctype for file uploads */ ?>

    <? if ($numErrors = inputError()){
        $errorMessage = $numErrors>1?'There are some errors with the data you provided. These are detailed below.':'There is an error with the data you provided. This is shown below.';
        echo '<div class="errorSummary error">';
        echo cms($errorMessage,0);
        echo '</div>';
    } ?>

    <?
        formHidden('id');
        formHidden('mode','update');
        formHidden('displayMode','update');

        include(VIEWS_DIR.'/'.$ENTITY.'/admin.php');
    ?>

    <? if (function_exists('extraPageContentAboveButtons')) extraPageContentAboveButtons(); ?>

    <ul class="btn-list bottom">
        <input type="button" class="btn" value="<?=cms('Button: Back',0,'Back')?>" onClick="window.location.href='<?=htmlspecialchars($backHref)?>'"/>
        <? if (function_exists('extraButtonsBefore')) extraButtonsBefore() ?>
        <? if (ws('id') && canDo('actionLog','list')) { ?>
            <input type="button" class="btn" value="<?=cms('Button: History',0,'History')?>" onClick="window.location.href='/actionLog/list.php?filter_actionLog:entity_eq=<?=htmlspecialchars($ENTITY)?>&filter_actionLog:entityId_eq=<?=htmlspecialchars(ws('id'))?>'"/>
        <? } ?>
        <? if (ws('mode')!='view') { ?>
            <button class="save btn" name="submitButton" type="submit" value="Save"><?=cms('Button: Save',0,'Save')?></button>
            <button class="saveAndClose btn" name="submitButton" type="submit" value="Save & Close"><?=cms('Button: Save & Close',0,'Save & Close')?></button>
        <? } ?>
        <? if (function_exists('extraButtonsAfter')) extraButtonsAfter() ?>
    </ul>

</form>

<? if (isset($extraView)) include(VIEWS_DIR.$extraView); ?>
<? if (function_exists('extraPageContent')) extraPageContent() ?>

<? include(VIEWS_DIR.'footer.php'); ?>
<? if (ws('mode')=='view') { ?>
<script>
    $('#dataEntryForm').find('textarea,select,input').not('input:button').prop('disabled',true);
</script>
<? } ?>
