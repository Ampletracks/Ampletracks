<?
include('../lib/core/startup.php');
include(CORE_DIR.'/fileUpload.php');

if (!isset($_REQUEST['spec'])) {
    echo "Missing file specification";
    exit;
}

$spec = $_REQUEST['spec'];

$class = fileUpload::validateFileSpec($spec);
if ($class===false) {
    echo "Invalid URL";
    exit;
}

$class = preg_replace('/[^0-9a-z+=\' _-]/i','',$class);
$classFile = LIB_DIR.'/'.$class.'.php';

if (!file_exists($classFile)) {
    echo "Unknown file type";
    exit;
}

include($classFile);

$class::download($spec);

