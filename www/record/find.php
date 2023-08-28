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
                if (!canDo('create',0,"recordType:$recordTypeId")) continue;
                $canCreateRecord=true; 
                $recordTypeSelect->addOption($recordType, $recordTypeId);
            }

            // Get a list of recent records the user might want to associate this label with
            $accessibleRecordTypes = getUserAccessibleRecordTypes( 0,'edit');
            if (count($accessibleRecordTypes)) {
                $assignableRecords = $DB->getHash('
                    SELECT
                        record.id,
                        recordType.name AS type,
                        recordData.data AS name,
                        MAX(recordAccessLog.accessedAt) AS lastAccess,
                        IF(ISNULL(label.id),0,1) AS hasLabel
                    FROM
                        recordAccessLog
                        INNER JOIN record ON record.id=recordAccessLog.recordId
                        INNER JOIN recordType ON recordType.id=record.typeId
                        INNER JOIN recordData ON recordData.recordId=recordAccessLog.recordId AND recordData.dataFieldId=recordType.primaryDataFieldId
                        LEFT JOIN label ON label.recordId=record.id
                    WHERE
                        record.typeId IN (?) AND
                        record.deletedAt=0 AND
                        record.lastSavedAt>0
                    GROUP BY record.id
                    ORDER BY lastAccess DESC
                    LIMIT 100
                ',$accessibleRecordTypes);
                $filteredAssignableRecords = [];
                $count = 20;
                foreach( $assignableRecords as $recordId=>$recordData) {
                    if (canDo('edit',$recordId,'record')) {
                        $filteredAssignableRecords[$recordId] = $recordData;
                    }
                    if (!$count--) break;
                }
                $assignableRecords = $filteredAssignableRecords;
            }

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
    <? if ($USER_ID) { ?>
        <? if ( $canCreateRecord) { ?>
            <h2>Create new record for this label</h2>
            <form method="post" action="/record/admin.php">
                <? formHidden('labelId',$label->id); ?>
                <p>Create a record now and associate it with this label</p>
                <? $recordTypeSelect->display() ?>
                <input type="submit" value="Create new record" />
            </form>
        <? } ?>
        <? if ( !empty($assignableRecords) ) {
            echo '<h2>Assign label to recently viewed record</h2>';
            echo '<table><thead><tr><th>Type</th><th>Name</th><th>Has existing label?</th><th style="width:1%"></th></tr></thead><tbody>';
            foreach( $assignableRecords as $recordId => $recordData ) {
                printf('<tr><td>%s</td><td>%s</td><td>%s</td><td><a href="/record/admin.php?id=%d&mode=update&labelId=%d" class="btn">Assign</a></td></tr>',
                    $recordData['type'],
                    $recordData['name'],
                    $recordData['hasLabel']?'yes':'no',
                    $recordId,
                    $label->id
                );
            }
            echo '</tbody></table>';
        } ?>
    <? } ?> 
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
