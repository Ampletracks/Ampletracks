<?php
/* -----------------------------------------------------------------
   Configuration & bootstrap
   ----------------------------------------------------------------- */

$INPUTS = [
    'start|upload' => [
        'recordId'   => 'INT SIGNED(s3UploadRecordId)',
        'dataFieldId'=> 'INT SIGNED(s3UploadDataFieldId)',
    ],
    '.*' => [
        'uploadId'  => 'INT SIGNED(s3UploadId)',
        'part'     => 'INT',
    ]
];
$requireLogin = false;

include('../../lib/core/startup.php');     // your project bootstrap

$uploadId= ws('uploadId');

// Before we go any further enforce restrictions on non-logged in users
if (!$USER_ID) {
    // Public users can only access the displayExisting and download modes
    if (!in_array($mode, ['displayExisting', 'download'], true)) {
        jsonError('You must be logged in to use this API.', 401);
    }
    // Both of these modes require an uploadId
    if (!$uploadId) {
        jsonError('Missing or invalid uploadId.', 400);
    }

    // Check that the site, the recordType, and the field are all public
    $allowed = true;
    if (!getConfigBoolean('Enable public search')) {
        $allowed = false;
    } else {
        $allowed = $DB->getRow('
            SELECT
                s3Upload.id
            FROM s3Upload
                INNER JOIN record ON record.id = s3Upload.recordId
                INNER JOIN dataField ON dataField.id = s3Upload.dataFieldId
                INNER JOIN recordType ON recordType.id = record.typeId
            WHERE
                s3Upload.deletedAt = 0 AND
                record.deletedAt = 0 AND
                dataField.deletedAt = 0 AND
                recordType.deletedAt = 0 AND
                recordType.includeInPublicSearch > 0 AND
                dataField.displayToPublic > 0 AND
                s3Upload.id = ?
        ');
    }
    if (!$allowed) {
        jsonError('You do not have permission to access this upload.', 403);
    }
} else {
    // For logged in users we need to check their permissions
    // but we can't do that until we know all of: uploadId, recordId and dataFieldId
    // so we do those checks later
}

require_once LIB_DIR.'/vendor/autoload.php';   // Composer (AWS SDK)
include( LIB_DIR.'/s3Tools.php' );

/* --- Config values --- */
$bucket     = getConfig('S3 upload bucket name');
$endpoint   = getConfig('S3 upload endpoint');
$publicKey  = getConfig('S3 upload public key');
$secretKey  = getConfig('S3 upload secret key');
$region     = getConfig('S3 region');

/* Ensure all required configurations are present */
if ( empty($bucket) || empty($endpoint) || empty($publicKey) || empty($secretKey) || empty($region) ) {
    jsonError('Missing required S3 configuration parameters.', 500);
}

/* -----------------------------------------------------------------
   Utility helpers
   ----------------------------------------------------------------- */
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

function jsonError(string $msg, int $http = 400): never
{
    global $DB, $uploadId;
    if ($uploadId) {
        $DB->update('s3Upload', ['id' => $uploadId], [
            'status' => 'error',
            'errors' => $msg,
        ]);
    }
    // Set the HTTP response code and return JSON error
    http_response_code($http);
    echo json_encode(['error' => $msg], JSON_UNESCAPED_SLASHES);
    exit;
}

function safeFileName(string $name): string
{
    return preg_replace('/[^\w\-.]+/', '_', $name) ?: 'file';
}

function s3Client(): S3Client
{
    static $client;
    if ($client) return $client;

    global $region, $endpoint, $publicKey, $secretKey;

    $client = new S3Client([
        'version'           => 'latest',
        'region'            => $region,     // still required for signing
        'endpoint'          => $endpoint,   // custom or AWS default
        'signature_version' => 'v4',
        'credentials' => [
            'key'    => $publicKey,
            'secret' => $secretKey,
        ],
        'http' => ['verify' => false]       // set to true for real AWS
    ]);
    return $client;
}

/* -----------------------------------------------------------------
   Request routing & common variables
   ----------------------------------------------------------------- */

$mode = ws('mode');

if ($mode !== 'upload') header('Content-Type: application/json');

if (!in_array($mode, ['start', 'signParts', 'complete', 'updateProgress', 'displayExisting','delete', 'download','upload'], true)) {
    jsonError('Invalid or missing mode.');
}

if ($mode !== 'start' && $mode !== 'upload') {
    if (!$uploadId) {
        jsonError('Missing uploadId.');
    }

    // In this case we need to load data from the s3Upload table
    list( $s3UploadId, $path, $size, $uploadedCreatedAt, $recordId, $dataFieldId ) = $DB->getRow('
        SELECT s3UploadId, path, size, createdAt, recordId, dataFieldId
        FROM s3Upload
        WHERE id = ? AND deletedAt = 0
    ', $uploadId);

    if ( !$s3UploadId || !$recordId || !$dataFieldId) jsonError('Unknown upload ID.', 404);

} else {
    // For "start" mode, we need to load recordId and dataFieldId from the request
    $recordId   = ws('recordId');
    $dataFieldId= ws('dataFieldId');

    if (!$recordId || !$dataFieldId) jsonError('Missing recordId or dataFieldId.');
}

// Now we have all the details we need to check permissions
if ($USER_ID) {
    // Do canDo check based on the mode being requested
    $allowed = false;
    if ( $mode === 'displayExisting' || $mode === 'download') {
        $allowed = canDo('view', $recordId, 'record');
    } elseif ($mode === 'delete') {
        // If they are allowed to edit the record then they can delete the file too
        // ... but keep this separate just in case we want to change it later
        $allowed = canDo('edit', $recordId, 'record');
    } else { // ( $mode === 'start' || $mode === 'upload' || $mode === 'signParts' || $mode === 'complete' || $mode === 'updateProgress')
        $allowed = canDo('edit', $recordId, 'record');
    }
    if (!$allowed) {
        jsonError('You do not have permission to access this upload.', 403);
    }
} else {
    // For public users, we already checked permissions earlier
}

/* Fetch field parameters (not needed for "complete") */
$fieldParams = [];
if ($mode !== 'complete') {
    $row = $DB->getRow('
        SELECT parameters
        FROM dataField
        WHERE id = ? AND deletedAt = 0
    ', $dataFieldId);
    if (!$row) jsonError('Unknown data field.', 404);
    $fieldParams = unserialize($row['parameters']) ?: [];
    if (!is_array($fieldParams)) jsonError('Invalid field parameters.', 500);
    $pathTemplate = $fieldParams['storagePath'] ?: '<unique_id>';
}

/* -----------------------------------------------------------------
   MODE: delete – Mark an upload as deleted
   ----------------------------------------------------------------- */
if ($mode === 'delete') {

    // Mark the upload as deleted in the database
    $DB->update('s3Upload', ['id' => $uploadId], [
        'deletedAt' => time(),
        'deletedBy' => $USER_ID,
        'status'    => 'needsDelete',
    ]);

    // Updated the recordData to remove the reference to this upload
    $updates = [
        'data' => '',
        'hidden'       => 0,
        'valid'        => 1,
        'inherited'    => 0,
        'fromRecordId' => 0
    ];
    $updated = $DB->update('recordData', ['data' => $uploadId, 'recordId' => $recordId, 'dataFieldId' => $dataFieldId], $updates);

    if ($updated) {
        // Create a new recordDataVersion entry to mark the deletion
        $DB->insert('recordDataVersion', array_merge($updates,[
            'recordId'     => $recordId,
            'dataFieldId'  => $dataFieldId,
            'userId'       => $USER_ID,
            'savedAt'      => time(),
            'saved'        => 1,
        ]));
    }

    echo json_encode(['ok' => true], JSON_UNESCAPED_SLASHES);
    exit;
}
/* -----------------------------------------------------------------
   MODE: displayExisting – Generate an HTML summary of the upload
   ----------------------------------------------------------------- */
if ($mode === 'displayExisting') {

    // Fetch the upload details from the database
    $upload = $DB->getRow('
        SELECT 
            s3UploadId, 
            path, 
            size, 
            progress, 
            status, 
            originalFilename, 
            createdAt, 
            uploadCompletedAt, 
            newPath
        FROM s3Upload
        WHERE id = ? AND deletedAt = 0
    ', $uploadId);

    if (!$upload) {
        jsonError('Upload not found.', 404);
    }

    $uploadDuration = $upload['uploadCompletedAt'] - $upload['createdAt'];
    if ($uploadDuration > 3600) {
        $durationText = round($uploadDuration / 3600, 2) . ' hours';
    } elseif ($uploadDuration > 60) {
        $durationText = round($uploadDuration / 60, 2) . ' minutes';
    } else {
        $durationText = $uploadDuration . ' seconds';
    }

    // Generate the HTML summary
    $html = '<p><strong>Original Filename:</strong> ' . htmlspecialchars($upload['originalFilename']) . '</p>';
    $html .= '<p><strong>Storage Path:</strong> ' . htmlspecialchars($upload['path']) . '</p>';
    // Add pending move information if newPath is not empty
    if (!empty($upload['newPath'])) {
        $html .= '<p><strong>Pending move to new location:</strong> ' . htmlspecialchars($upload['newPath']) . '</p>';
    }
    $html .= '<p><strong>Size:</strong> ' . formatBytes($upload['size']) . '</p>';
    $html .= '<p><strong>Uploaded At:</strong> ' . date('Y-m-d H:i:s', $upload['createdAt']) . ' (upload took ' . $durationText . ')</p>';

    // Return the HTML
    echo $html;
    exit;
}

/* -----------------------------------------------------------------
   MODE: download – Serve the file for download
   ----------------------------------------------------------------- */
if ($mode === 'download') {

    // Fetch the upload details from the database
    $upload = $DB->getRow('
        SELECT path, originalFilename
        FROM s3Upload
        WHERE id = ? AND deletedAt = 0
    ', $uploadId);

    if (!$upload) {
        jsonError('Upload not found or already deleted.', 404);
    }

    $path = $upload['path'];
    $filename = $upload['originalFilename'];

    try {
        // Generate a pre-signed URL for downloading the file
        $cmd = s3Client()->getCommand('GetObject', [
            'Bucket' => $bucket,
            'Key'    => $path,
            'ResponseContentDisposition' => 'attachment; filename="' . safeFileName($filename) . '"',
        ]);

        $request = s3Client()->createPresignedRequest($cmd, '+15 minutes');
        $presignedUrl = (string)$request->getUri();

        // Redirect the client to the pre-signed URL
        header('Location: ' . $presignedUrl);
        exit;
    } catch (AwsException $e) {
        jsonError('Failed to generate download URL: ' . $e->getMessage(), 500);
    }
}

/* -----------------------------------------------------------------
Safe JSON body parsing
----------------------------------------------------------------- */
$rawBody = file_get_contents('php://input');

try {
    $payload = $rawBody !== ''
        ? json_decode($rawBody, true, 64, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING)
        : [];
} catch (JsonException $e) {
    jsonError('Malformed JSON: '.$e->getMessage());
}

if (!is_array($payload)) $payload = [];   // extra defence

/* -----------------------------------------------------------------
   MODE: start  –  CreateMultipartUpload
   ----------------------------------------------------------------- */
if ($mode === 'start') {

    // Chop the filename down to 255 chars because that's all we store in the database
    $filename = substr($payload['filename'] ?? '',0,255);
    $size     = (int)($payload['size'] ?? 0);
    if (!$filename || !$size) jsonError('filename and size required.');

    /* Min / max checks */
    $minBytes = !empty($fieldParams['minSize']) ? $fieldParams['minSize']*1048576 : 0;
    $maxBytes = !empty($fieldParams['maxSize']) ? $fieldParams['maxSize']*1048576 : 0;
    if ($minBytes && $size < $minBytes) jsonError('File below minimum size.');
    if ($maxBytes && $size > $maxBytes) jsonError('File exceeds maximum size.');
    $uploadedCreatedAt = time();

    $uploadId = $DB->insert('s3Upload', [
        'dataFieldId'       => $dataFieldId,
        'recordId'          => $recordId,
        'status'            => 'inProgress',
        'createdAt'         => $uploadedCreatedAt,
        'progress'          => 0,
        'size'              => $size,
        'originalFilename'  => $filename,
        'lastCheckedAt'     => time(),
        'numAttempts'       => 0,
        'newPath'           => '',
        'errors'            => '',
    ]);

    $apiId = API\getAPIId('s3Upload',$uploadId);
    $usage = [];

    if (!$apiId || !$uploadId ) {
        jsonError('Failed to create new upload record.', 500);
    }

    $path = S3Tools\buildS3FilePath( $pathTemplate, $recordId, $filename, $uploadedCreatedAt, $apiId, $usage );

    try {
        $resp = s3Client()->createMultipartUpload([
            'Bucket'      => $bucket,
            'Key'         => $path,
            'ContentType' => $payload['type'] ?? 'application/octet-stream',
            'ACL'         => 'private',
        ]);
    } catch (AwsException $e) {
        jsonError('AWS error: '.$e->getMessage(), 500);
    }

    $s3UploadId = $resp['UploadId'] ?? '';
    if (!$s3UploadId) {
        jsonError('Failed to create S3 upload.', 500);
    }

    // Now that we have the path and we know what it uses, we can update the record
    if (!$DB->update('s3Upload', [ 'id' => $uploadId ],[
        'path' => $path,
        'usesProject'     => $usage['usesProject']     ? 1 : 0,
        'usesRecord'      => $usage['usesRecord']      ? 1 : 0,
        'usesRecordType'  => $usage['usesRecordType']  ? 1 : 0,
        'usesOwner'       => $usage['usesOwner']       ? 1 : 0,
        's3UploadId'      => $s3UploadId
    ])) {
        jsonError('Failed to update upload record.', 500);
    }

    echo json_encode([
        'uploadId'  => signInput($uploadId, 's3UploadId')
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

/* -----------------------------------------------------------------
   MODE: signParts  –  presign each UploadPart
   ----------------------------------------------------------------- */
if ($mode === 'signParts') {

    $part = ws('part');

    if (!$uploadId || !$path || !$part ) {
        jsonError('uploadId, path and part required.');
    }

    $urls = [];
    try {
        $cmd  = s3Client()->getCommand('UploadPart', [
            'Bucket'     => $bucket,
            'Key'        => $path,
            'UploadId'   => $s3UploadId,
            'PartNumber' => $part,
        ]);
        
        $req  = s3Client()->createPresignedRequest($cmd, '+15 minutes');
        $url = (string)$req->getUri();
    } catch (AwsException $e) {
        jsonError('AWS error (part '.$part.'): '.$e->getMessage(), 500);
    }

    echo json_encode(['url' => $url], JSON_UNESCAPED_SLASHES);
    exit;
}

/* -----------------------------------------------------------------
   MODE: complete  –  CompleteMultipartUpload
   ----------------------------------------------------------------- */
if ($mode === 'complete') {

    $parts     = $payload['parts']     ?? [];

    // Validate the parts array
    $partsError = false;
    foreach ($parts as $part) {
        if (!isset($part['ETag']) || !isset($part['PartNumber'])) {
            $partsError = true;
            break;
        }
        // S3 limits ETag to 255 chars and part number to 10000
        // See: https://docs.aws.amazon.com/AmazonS3/latest/API/API_CompleteMultipartUpload.html
        if (strlen($part['ETag']) > 255 || $part['PartNumber'] > 10000) {
            $partsError = true;
            break;
        }

        if (array_diff_key($part, ['ETag' => true, 'PartNumber' => true])) {
            $partsError = true;
            break;
        }
    }
    if ($partsError) {
        jsonError('Malformed parts array.');
    }

    if (!$s3UploadId || !$path || !is_array($parts) || !count($parts)) {
        jsonError('uploadId, path and parts[] required.');
    }

    usort($parts, fn($a, $b) => $a['PartNumber'] <=> $b['PartNumber']);

    try {
        s3Client()->completeMultipartUpload([
            'Bucket'          => $bucket,
            'Key'             => $path,
            'UploadId'        => $s3UploadId,
            'MultipartUpload' => ['Parts' => $parts]
        ]);
    } catch (AwsException $e) {
        jsonError('AWS error on complete: '.$e->getMessage(), 500);
    }

    // Check the file size
    $result = s3Client()->headObject([
        'Bucket' => $bucket,
        'Key'    => $path,
    ]);
    if ($result['@metadata']['statusCode'] !== 200) {
        jsonError('Failed to fetch file size from S3.', 500);
    }
    $s3Size = $result['ContentLength'] ?? 0;
    if ($s3Size != $size) {
        jsonError('File size mismatch: expected '.$size.', got '.$s3Size.'.', 500);
    }

    // Update the database record
    if (!$DB->update('s3Upload', [ 'id' => $uploadId ], [
        'status'                => 'ok',
        'progress'              => 100,
        'uploadCompletedAt'     => time(),
        'errors'                => '',
    ])) {
        jsonError('Failed to update upload record.', 500);
    }

    // Now we can store replace any existing s3Upload file references in the recordData with the new one
    // But get the old one first
    $oldUploadId = $DB->getValue('
        SELECT data
        FROM recordData
        WHERE recordId = ? AND dataFieldId = ?
    ', $recordId, $dataFieldId);
    if ($oldUploadId) {
        // Mark the old upload as deleted
        $DB->update('s3Upload', [ 'id' => $oldUploadId ], [
            'deletedAt' => time(),
            'deletedBy' => $USER_ID,
            'status'   => 'needsDelete',
        ]);
    }

    /*
    Make a record of the this version of the data in the recordDataVersion table
    */
    $updateData = [
        'data'         => $uploadId,
        'hidden'       => 0,
        'valid'        => 1,
        'inherited'    => 0,
        'fromRecordId' => 0
    ];

    $DB->update('recordData',[ 'recordId' => $recordId, 'dataFieldId' => $dataFieldId], $updateData);

    $updateData = array_merge($updateData, [
        'recordId'     => $recordId,
        'dataFieldId' => $dataFieldId,
        'userId'       => $USER_ID,
        'savedAt'      => time(),
        'saved'        => 1,
    ]);

    $DB->insert('recordDataVersion', $updateData);

    echo json_encode(['ok' => true], JSON_UNESCAPED_SLASHES);
    exit;
}

/* -----------------------------------------------------------------
   MODE: updateProgress – Update the progress of an ongoing upload
   ----------------------------------------------------------------- */
if ($mode === 'updateProgress') {
    $progress = (int)($payload['progress'] ?? 0);

    if (!$uploadId || $progress < 0 || $progress > 100) {
        jsonError('uploadId and valid progress (0-100) are required.');
    }

    // Update the progress in the database
    $DB->update('s3Upload', ['id' => $uploadId], [
        'progress' => $progress,
        'lastCheckedAt' => time(),
    ]);

    echo json_encode(['ok' => true], JSON_UNESCAPED_SLASHES);
    exit;
}

/* -----------------------------------------------------------------
   MODE: upload – Upload a file to S3
   ----------------------------------------------------------------- */
// everything beyond here is for the upload page
if ($mode !== 'upload') exit;

include(LIB_DIR.'/dataField.php'); 

// Load in more data about the record
$DB->loadRow(['
    SELECT
        recordType.name AS recordTypeName,
        IFNULL( title.data,"") AS recordTitle
    FROM record
        INNER JOIN recordType ON record.typeId = recordType.id
        LEFT JOIN recordData title ON title.recordId = record.id AND title.dataFieldId = recordType.primaryDataFieldId
    WHERE record.id = ?
',$recordId]);

// Instantiate the dataField object
$dataField = dataField::build($dataFieldId);
$dataField->setup( $recordId );

dataField::loadAnswersForRecord( $recordId, 'dataField.id='.(int)$dataFieldId );

include( VIEWS_DIR.'/header.php' );
?>

<h1><?=wsp('recordTypeName')?>: <?=wsp('recordTitle')?></h1>
<h2>File upload for: <? $dataField->displayLabel(); ?></h2>
<div class="questionAndAnswer s3Upload form-row" dependencycombinator="and">
    <div class="answer">
    <? $dataField->displayInput(); ?>
    </div>
</div>

<?
include( VIEWS_DIR.'/footer.php' );
