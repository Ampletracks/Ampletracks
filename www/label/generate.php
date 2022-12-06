<?

$INPUTS = array(
    '.*' => array(
        'recordId' => 'INT'
    )
);

include('../../lib/core/startup.php');

include(LIB_DIR.'/labelTools.php');

$label = new label();
$image = $label->getImageQrCode(1,50);

$qrCode = "<img class=\"qrCode\" src=\"$image\" />";

?>
<style>
    div.label {
        border: 1px solid black;
        padding: 10px; 
        overflow: hidden;
        display:inline-block;
    }
    img.qrCode {
        width: 200px;
        height: 200px;
        float: left;
        margin:0;
    }
</style>
<div class="label">
    <?=$qrCode?>
    <div class="logo"></div>
    <div></div>
    Sample ID : Security Code',0,'C');
    sprintf('%07d:%s',$label->id,$label->securityCode)
    If found please visit:
</div>

