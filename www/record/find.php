<?

$requireLogin = false;
$extraBodyClass = 'public';
include('../../lib/core/startup.php');

include(LIB_DIR.'/labelTools.php');
include(LIB_DIR.'/recordTools.php');
include(LIB_DIR.'/dataField.php');

$error = '';
$errorReturnCode = 400;
$canCreateRecord = false;
$recordId=0;

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    if (strlen($token)<10) {
        $error = 'Invalid token';
    } else {
        include( LIB_DIR.'/shareLinkTools.php');
        $result = checkShareLink( $token );
        if (!$result) {
            $error = checkShareLink('getErrors');
        } else {
            $recordId = $result;
        }
    }
} else {
    if (!isset($_GET['id']) || strlen($_GET['id'])<12) {
        $error = 'Label ID not provided, or not long enough';
    } else if( !preg_match('/^[A-Za-z0-9_-]+$/',$_GET['id']) ) {
        $error = 'Invalid ID provided';
    } else if( strlen($_GET['id'])>20)  {
        $error = 'ID is too long';
    } else {
        $label = new Label($_GET['id']);
        $error = $label->error();

        if (!$error) {
            if ($label->redirectUrl) {
                header("Location: ".$label->redirectUrl);
                exit;
            }
        
            if (!$label->recordId) {
                $error = 'This label has not yet been associated with an item';
                $errorReturnCode = false;
                $canCreateRecord = true;

                $recordTypes = $DB->getHash('SELECT id, name FROM recordType WHERE deletedAt=0');
                $recordTypeSelect = new formOptionbox('record_typeId',['-- Select record type --'=>'']);
                foreach( $recordTypes AS $recordTypeId=>$recordType ) {
                    if (!canDo('create',0,"recordTypeId:$recordTypeId")) continue;
                    $canCreateRecord=true; 
                    $recordTypeSelect->addOption($recordType, $recordTypeId);
                }
            } else {
                $recordId = $label->recordId;
            }
        }
    }
}

// If they are logged in then redirect them to the record editting page
if (!$error && $USER_ID) {
    header('Location: /record/admin.php?id='.$recordId);
    exit;
}

if (!$error) {
    $dataFields = datafield::buildAllForRecord( $recordId, ['where'=>'dataField.displayToPublic'] );
    dataField::loadAnswersForRecord($recordId,'dataField.displayToPublic');
    $previewMessage = $DB->getValue('
        SELECT publicPreviewMessage
        FROM record INNER JOIN recordType ON recordType.id=record.typeId
        WHERE record.id=?
    ',$recordId);
}

if ($error && $errorReturnCode) http_response_code($errorReturnCode);

include(VIEWS_DIR.'/header.php');
?>
<h1><?=cms('Public record preview: header',0,'Sample Details')?></h1>
<? if ($error) { ?>
    <p class="error"><?=cms('Public record preview: error',1,'There was a problem retrieving data about this item')?></p>
    <p class="error"><?=htmlspecialchars($error)?></p>
    <? if ($USER_ID && $canCreateRecord) { ?>
        <form method="post" action="/record/admin.php">
            <? formHidden('labelId',$label->id); ?>
            Create a record now and associate it with this label<br />
            <? $recordTypeSelect->display() ?>
            <input type="submit" value="Create new record" />
        </form>
    <? } ?> 
<? } else { ?>
    <p class="introduction"><?=cms('Public record preview: introduction',1,'The publicly avaiable data relating to this item is shown below')?></p>
    <? if (strlen(trim($previewMessage))) { ?>
        <p class="introduction"><?=nl2br(htmlspecialchars($previewMessage))?></p>
    <? } ?>
    <div class="questionAndAnswerContainer recordData publicView">
        <? foreach( $dataFields as $dataField ) {
            $dataField->displayRow( true );
        } ?>
    </div>
<? } ?>
<br />
<? include(VIEWS_DIR.'/footer.php'); ?>
