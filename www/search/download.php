<?

$INPUTS = [
    '.*' => [
        'searchId' => 'INT SIGNED(SEARCH)'
    ],
    'getNextFile' => [
        'downloadBundleId' => 'INT SIGNED(DOWNLOAD)'
    ]
];

$requireLogin = false;

include('../../lib/core/startup.php');
include(LIB_DIR.'/dataFieldImage.php');
include(LIB_DIR.'/dataFieldFileUpload.php');

$searchId=(int)ws('searchId');
$downloadBundleId=(int)ws('downloadBundleId');

if (ws('mode')=='getNextFile' && $downloadBundleId>0) {


    // First get a single download off the queue
    do {
        $downloadBundleEntry = $DB->getRow('
            SELECT downloadBundleEntry.*, dataField.typeId as dataFieldTypeId
            FROM downloadBundleEntry
                LEFT JOIN dataField ON dataField.id=downloadBundleEntry.dataFieldId
                LEFT JOIN recordType ON recordType.id = dataField.recordTypeId
            WHERE
                downloadBundleEntry.downloadBundleId=? AND
                downloadBundleEntry.lastUpdatedAt=0
            ORDER BY recordType.name ASC, downloadBundleEntry.recordId ASC, dataField.id=0 ASC
            LIMIT 1
        ',$downloadBundleId);
        if (!$downloadBundleEntry) break;
        $updated = $DB->update('downloadBundleEntry',['lastUpdatedAt'=>0,'id'=>$downloadBundleEntry['id']],['lastUpdatedAt'=>time()]);
    } while (!$updated);
    if (!$downloadBundleEntry) {
        echo '"END"';
        exit;
    }

    // get data about the record
    $recordData = $DB->getRow('
        SELECT
            recordType.name AS type
        FROM
            record
            INNER JOIN recordType ON recordType.id=record.typeId
        WHERE
            record.id=?
    ',$downloadBundleEntry['recordId']);

    $filename = $recordData['type'].'/record_'.$downloadBundleEntry['recordId'].'/';
    // First handle the record itself
    if ( $downloadBundleEntry['dataFieldId']==0 ) {
        $filename .= 'data.json';
        $downloadUrl = '../record/find.php?mode=json&recordId='.signInput($downloadBundleEntry['recordId'],'PUBLIC_VIEW');
    } else if ( $downloadBundleEntry['dataFieldTypeId']==11 || $downloadBundleEntry['dataFieldTypeId']==12 ) {
        $objectName = $downloadBundleEntry['dataFieldTypeId']==11 ? 'dataFieldFileUpload' : 'dataFieldImage';
        $file = new $objectName( [
            'recordId'  => (int)$downloadBundleEntry['recordId'],
            'dataFieldId' => (int)$downloadBundleEntry['dataFieldId']
        ]);
        $filename .= $file->getDownloadName();
        $downloadUrl = $file->downloadUrl( 'medium' ); // "medium" here means medium longevity for the ephemeral download link
    } else {
        echo '"ERROR - unrecognized field type:'.$downloadBundleEntry['dataFieldTypeId'].' "';
        exit;
    }
    echo json_encode([
        'zipPath'   => $filename,
        'url'       => $downloadUrl
    ]);


    exit;
}

if (ws('mode')=='start' && $searchId>0) {
    $downloadBundleId = $DB->insert('downloadBundle',[
        'searchId'  => $searchId,
        'createdAt' => time(),
    ]);

    $signedDownloadBundleId = signInput($downloadBundleId,'DOWNLOAD');

    $query = $DB->query('
        SELECT
            recordType.name,
            record.id,
            GROUP_CONCAT(DISTINCT uploadField.id) AS uploadFieldIds,
            GROUP_CONCAT(DISTINCT imageField.id) AS imageFieldIds
        FROM
            searchResult
            INNER JOIN record ON record.id=searchResult.recordId
            INNER JOIN recordType on recordType.id=record.typeId AND recordType.includeInPublicSearch>0
            LEFT JOIN dataField AS uploadField ON uploadField.recordTypeId=recordType.id AND uploadField.typeId=11 AND uploadField.displayToPublic>0
            LEFT JOIN dataField AS imageField ON imageField.recordTypeId=recordType.id AND imageField.typeId=12 AND imageField.displayToPublic>0
        WHERE
            searchResult.searchId=?
        GROUP BY record.id
    ',$searchId);

    while( $query->fetchInto( $record ) ) {
        // First insert the record itself
        $DB->insert('downloadBundleEntry',[
            'downloadBundleId'  => $downloadBundleId,
            'recordId'          => $record['id'],
            'dataFieldId'       => 0,
            'complete'          => 0,
            'size'              => 0,
            'lastUpdatedAt'     => 0 
        ]);

        // Now insert images and uploads
        foreach( ['image'=>'Image','upload'=>'FileUpload'] as $uploadType=>$ucUploadType) {
            $dataFields = array_filter(explode(',',$record[$uploadType.'FieldIds']));
            foreach( $dataFields as $dataFieldId ) {
                $objectName = 'dataField'.$ucUploadType;
                $file = new $objectName( [
                    'recordId'  => (int)$record['id'],
                    'dataFieldId' => (int)$dataFieldId
                ]);
                
                $size = $file->size();
                if ($size) {
                    $DB->insert('downloadBundleEntry',[
                        'downloadBundleId'  => $downloadBundleId,
                        'recordId'          => $record['id'],
                        'dataFieldId'       => $dataFieldId,
                        'complete'          => 0,
                        'size'              => $size,
                        'lastUpdatedAt'     => 0 
                    ]);
                }
            }
        }
    }

    $downloadInfo = $DB->getRow('
        SELECT count(*) AS numFiles , sum(downloadBundleEntry.size) AS totalSize
        FROM downloadBundleEntry
        WHERE
            downloadBundleEntry.downloadBundleId=? AND
            lastUpdatedAt=0
    ',$downloadBundleId);

}


include(VIEWS_DIR.'/header.php');
?>

<style>

#progress .completedFile {
    width: 100%;
}
#progress .completedFile .fileCount{
    float: right;
}
#progress .startMessage {
    margin-top: 10px;
    font-weight: bold;
    text-transform: uppercase;
}
#progress .endMessage {
    font-weight: bold;
    text-transform: uppercase;
}

</style>

<script src="/javascript/fflate.min.js"></script>
<script src="streamFetchToZip.js"></script>
<script>

const numFilesTotal = <?=json_encode( $downloadInfo['numFiles'] ); ?>;
var numFilesDownloaded = 0;

function fetchNextFile(pushFile) {
    $.ajax({
        url     : 'download.php',
        data    : { mode : 'getNextFile', downloadBundleId : <?= json_encode($signedDownloadBundleId); ?> },
        dataType: 'json',
        success : function(data){
            console.log('Got file download data:',data);
            if (data=='END') data=null;
            pushFile(data);
        },
        error : function( jqXHR, textStatus, errorThrown) {
            console.log('Error getting next file download URL: '+textStatus);
            console.log('will retry in 10 seconds');
            window.setTimeout(() => {
                fetchNextFile(pushFile);
            }, 10000);
        }
    });
}

var inProgress;
$(function(){ inProgress=$('#inProgress'); });

// Called after each file is added to the ZIP
function onFileComplete(zipPath) {
  numFilesDownloaded++;
  inProgress.before(`<div class="completedFile"><span class="filename">${zipPath}</span><span class="fileCount">${numFilesDownloaded} of ${numFilesTotal}</span></div>`).html
  if (numFilesDownloaded < numFilesTotal) inProgress.html('Getting details of next file for download');
  else inProgress.html('');
}

var started = false;

// Called repeatedly during each file's download
function onDownloadProgress(zipPath, downloadedBytes, percent, totalSize) {
  if (!started) {
    started = true;
    inProgress.before(`<div class="startMessage">Buidling zip file...</div>`);
  };
  totalSize |= '?';
  var progressMessage = `Downloading: ${numFilesDownloaded+1} of ${numFilesTotal} ${zipPath} ${downloadedBytes}/${totalSize}`;
  if (percent != null) {
    progressMessage += ` ${percent.toFixed(1)}%`;
    console.log(`[${zipPath}] ${downloadedBytes} bytes downloaded (${percent.toFixed(1)}% of ${totalSize} total).`);
  } else {
    console.log(`[${zipPath}] ${downloadedBytes} bytes downloaded (unknown total).`);
  }
  inProgress.html(progressMessage);
}

$('body').on('click','#startDownloadButton',function(){
    inProgress.empty();
    inProgress.siblings().remove();
    // Trigger the streaming ZIP creation
    streamFetchToZip(fetchNextFile, onFileComplete, onDownloadProgress)
    .then(function(){
      inProgress.before(`<div class="endMessage">Download complete</div>`);
    })
    .catch(console.error);
});

</script>
<div id="downloadInfo">
    <h1>Download</h1>
    When you click the button below, a zip file will be downloaded.<br />
    This zip file will contain <?=$downloadInfo['numFiles']?> files.<br />
    The total size of the files in the zip file will be at least <?=formatBytes($downloadInfo['totalSize'])?>.<br />
    <button id="startDownloadButton">Start Download</button>
    <div id="progress">
        <div id="inProgress"></div>
    </div>
</div>
<div id="unsupportedBrowserMessage" style="display:none; border: 1px solid #ccc; padding: 1em; border-radius: 5px; max-width: 700px; margin: 2em auto; ">
        <h2 style="margin-top: 0;">This feature is not supported in your browser</h2>
        <p>It appears that your web browser does not support a technical feature required for this part of the site to function. Specifically, this page relies on the <strong>File API</strong> and related browser capabilities that allow large zip files to be assembled directly on your device using JavaScript.</p>

        <p>This approach has been chosen for several reasons:</p>
        <ul>
          <li>It enables users to begin downloading files immediately, without needing to wait for a server to compile and prepare the download.</li>
          <li>It avoids the need for the server to generate and temporarily store large zip archives, which in some cases may be tens or even hundreds of gigabytes in size.</li>
          <li>It allows users to see progress as each record and file is downloaded and added to the archive, providing better feedback and control over the process.</li>
        </ul>

        <p>The more traditional alternative — preparing zip files on the server — presents a significant technical and financial challenge. Because we cannot predict in advance how many users might request large downloads at the same time, the server would need to maintain sufficient temporary storage to support many simultaneous downloads. In a busy period, this could require hundreds of gigabytes or even terabytes of working space, much of which would be unused most of the time.</p>

        <p>Provisioning infrastructure at that scale is not only inefficient, but also expensive — particularly for a research-focused platform with limited resources. By moving the download process to the user's browser, we can provide a faster, more scalable, and much more cost-effective service.</p>

        <p>Unfortunately, not all browsers support the required client-side technologies. If you'd like to check whether your browser supports the necessary features, you can consult the compatibility table here:</p>

        <p><a href="https://caniuse.com/native-filesystem-api" target="_blank" rel="noopener noreferrer">Can I use: File API — browser support information</a></p>
      </div>
</div>
<script>
  (function () {
    const fileApiSupported = (
      typeof Blob !== 'undefined' &&
      typeof File !== 'undefined' &&
      typeof URL !== 'undefined' &&
      typeof URL.createObjectURL === 'function'
    );

    if (!fileApiSupported) {
      // Insert the warning message into the page
      $('#unsupportedBrowserMessage').show();
      downloadContainer = $('#downloadInfo').get(0);
      downloadContainer.style.opacity = '0.5';
      downloadContainer.style.pointerEvents = 'none';
      downloadContainer.style.userSelect = 'none';
      downloadContiner.setAttribute('aria-disabled', 'true');
    } 
  })();
</script>



<?
include(VIEWS_DIR.'/footer.php');