<?
include('../lib/core/startup.php');
include(CORE_DIR.'/formAsyncUpload.php');

$upload = new formAsyncUpload();

if (!isset($_POST['asyncUploadId'])) $_POST['asyncUploadId']='';
if (!isset($_REQUEST['mode'])) $_REQUEST['mode']='';

list($status, $message) = $upload->processUpload($_REQUEST['mode'],$_POST['asyncUploadId']);

if ($message === false) $message = 'Unexpected error with upload';

echo json_encode(array('status'=>$status,'message'=>$message));