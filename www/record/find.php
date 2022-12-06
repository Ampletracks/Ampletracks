<?

$requireLogin = false;
$extraBodyClass = 'public';
include('../../lib/core/startup.php');

include(LIB_DIR.'/labelTools.php');
include(LIB_DIR.'/recordTools.php');
include(LIB_DIR.'/dataField.php');

$error = '';
$errorReturnCode = 400;

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
        } else {
            $dataFields = datafield::buildAllForRecord( $label->recordId, ['where'=>'dataField.displayToPublic'] );
        }
    }
}

// If they are logged in then redirect them to the record editting page
if (!$error && $USER_ID) {
    header('Location: /record/admin.php?id='.$label->recordId);
    exit;
}

if (!$error) {
    $recordId=$label->recordId;
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
<? } else { ?>
    <p class="introduction"><?=cms('Public record preview: introduction',1,'The publicly avaiable data relating to this item is shown below')?></p>
    <? if (strlen(trim($previewMessage))) { ?>
        <p class="introduction"><?=nl2br(htmlspecialchars($previewMessage))?></p>
    <? } ?>
    <div class="questionAndAnswerContainer recordData">
        <? foreach( $dataFields as $dataField ) {
            $dataField->displayRow( true );
        } ?>
    </div>
<? } ?>
<? include(VIEWS_DIR.'/footer.php'); ?>
