<?

$INPUTS = [
    '.*' => [
        'recordId' => 'INT SIGNED(PUBLIC_VIEW)'
    ]
];

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
$mode="";

if (isset($_GET['token'])) {
    $mode="token";
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
} else if (ws('recordId')>0 && getConfigBoolean('Enable public search')) {
    $mode="public";
    $recordId = $DB->getValue('
        SELECT record.id
        FROM
            record
            INNER JOIN recordType ON recordType.id=record.typeId
        WHERE
            record.id=? AND
            record.deletedAt=0 AND
            recordType.includeInPublicSearch>0
    ',ws('recordId'));
    if (!$recordId) $error="No matching record found";
} else {
    $mode="label";
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

                $recentRecordSelect = null;
                if($USER_ID) {
                    $recentRecordSelect = new formOptionbox('id');
                    foreach( ['withoutLabel','withLabel'] as $which ) {
                        $recentRecordQuery = $DB->query('
                            SELECT record.id, CONCAT( FROM_UNIXTIME( MAX(userRecordAccess.accessedAt),"- %d/%m/%Y %H:%i - "),recordType.name,": ",name.data," (ID:",record.id,")") AS name
                            FROM record
                            INNER JOIN recordType ON recordType.id=record.typeId
                            INNER JOIN userRecordAccess ON userRecordAccess.recordId = record.id AND userRecordAccess.userId = ?
                            INNER JOIN recordData AS name ON name.recordId = record.id
                            INNER JOIN dataField ON dataField.id = name.dataFieldId AND dataField.orderId = 1
                            LEFT JOIN label ON label.recordId=record.id
                            # to keep things sensible only go back 30 days
                            WHERE
                                record.lastSavedAt>0 AND record.deletedAt=0 AND
                                userRecordAccess.accessedAt > UNIX_TIMESTAMP()-86400*30
                                '.($which=='withLabel'?'AND !ISNULL(label.id)':'AND ISNULL(label.id)').'
                            GROUP BY record.id
                            ORDER BY MAX(userRecordAccess.accessedAt) DESC
                        ', $USER_ID);
                        $editableRecords = [];
                        $recordData = [];
                        while($recentRecordQuery->fetchInto($recordData) && count($editableRecords) < 5) {
                            if(canDo('edit', $recordData['id'])) {
                                $editableRecords[$recordData['name']] = $recordData['id'];
                            }
                        }
                        if (count($editableRecords)) {
                            $recentRecordSelect->addOption($which=='withLabel'?'Recently viewed with existing label...':'Recently viewed records without label...','');
                            $recentRecordSelect->addOptions($editableRecords);
                        }
                    }
                    $recentRecordSelect->setExtra('id="recentRecordSelect"');
                }
            } else {
                $recordId = $label->recordId;
            }
        }
    }
}

// If they are logged in then redirect them to the record editting page
if (!$error && $USER_ID && $mode!='public') {
    header('Location: /record/admin.php?id='.$recordId);
    exit;
}

if (!$error) {
    $dataFields = datafield::buildAllForRecord( $recordId, ['where'=>'dataField.displayToPublic>0'] );
    dataField::loadAnswersForRecord($recordId,'dataField.displayToPublic');
    $previewMessage = $DB->getValue('
        SELECT publicPreviewMessage
        FROM record INNER JOIN recordType ON recordType.id=record.typeId
        WHERE record.id=?
    ',$recordId);
}

if ($error && $errorReturnCode) http_response_code($errorReturnCode);

if (ws('mode')=='json') {
    include_once(LIB_DIR.'/kvToStruct.php');
    include_once(LIB_DIR.'/api/tools.php');
    $exportData = [];
    foreach( $dataFields as $dataField ) {
        $key = $dataField->exportName;
        if (!$dataField->hasValue()) continue;
        if (!method_exists($dataField,'exportAnswer')) continue;
        $exportData[$key] = $dataField->exportAnswer();
    }
    $exportData = kvToStruct($exportData);
    $exportData['__apiId'] = API\getAPIId('record', $recordId);
    echo json_encode($exportData,JSON_PRETTY_PRINT);
    exit;
}

include(VIEWS_DIR.'/header.php');

echo "<h1>";
if ($USER_ID && $mode!='public') {
    echo cms('Associate label: header',0,'Associate Label');
} else {
    echo cms('Public record preview: header',0,'Sample Details');
}
echo "</h1>";

if ($USER_ID && $mode!='public') {
    if($canCreateRecord) { ?>
        <form method="post" action="/record/admin.php">
            <? formHidden('labelId',$label->id); ?>
            Create a record now and associate it with this label<br />
            <? $recordTypeSelect->display() ?>
            <input type="submit" value="Create new record" />
        </form>
    <? }
    if(isset($recentRecordSelect) && $recentRecordSelect instanceof formOptionbox && count($recentRecordSelect->getOptions()) > 1) {
        ?>
        <form method="post" action="/record/admin.php" id="associateLabelForm">
            <? formHidden('labelId', $label->id); ?>
            Associate this label with a recently viewed record<br>
            <? $recentRecordSelect->display(); ?>
            <input type="submit" value="Associate" />
        </form>
        <script>
            $(function () {
                $('#associateLabelForm').on('submit', function () {
                    if(!$('#recentRecordSelect').val()) {
                        window.alert('Please choose a recently viewed record');
                        return false;
                    }
                });
            });
        </script>
        <?
    }
} else {
    if ($error) { ?>
        <p class="error"><?=cms('Public record preview: error',1,'There was a problem retrieving data about this item')?></p>
        <p class="error"><?=htmlspecialchars($error)?></p>
    <? } else { ?>
        <p class="introduction"><?=cms('Public record preview: introduction',1,'The publicly avaiable data relating to this item is shown below')?></p>
        <? if (strlen(trim($previewMessage))) { ?>
            <p class="introduction"><?=nl2br(htmlspecialchars($previewMessage))?></p>
        <? } ?>
        <div class="questionAndAnswerContainer recordData publicView">
            <? foreach( $dataFields as $dataField ) {
                $dataField->displayRow( true, false );
            } ?>
        </div>
    <? }
} ?>
<br />
<? include(VIEWS_DIR.'/footer.php'); ?>
