<?

$INPUTS = array(
    '.*' => array(
        'recordId'  => 'INT',
        'securityCode'  => 'TEXT',
        'layout'    => 'TEXT',
        'labelId'   => 'INT',
        'x'         => 'INT',
        'y'         => 'INT',
        'fileType'  => 'TEXT'
    )
);

include('../../lib/core/startup.php');
include(LIB_DIR.'/labelTools.php');

if (ws('mode')=='preview') {
    $labelSelect = new formOptionbox('layout', [
        'A4 3x9'=>'3x9',
        'A4 5x13: Avery B7651-50'=>'5x13',
        'A4 8x11: Tiny'=>'Tiny',

    ]);
    $labelSelect->setExtra('onChange="changeLayout(this.value)" class="dontExpand"');

    include(VIEWS_DIR.'/label/preview.php');
    exit;
}

// handle generating a single label
if (ws('recordId')) {
    // get the record type from the
    $recordTypeId = $DB->getValue('SELECT typeId FROM record WHERE id=?',ws('recordId'));
    $permissionsEntity = 'recordTypeId:'.$recordTypeId;
    if (!canDo('edit',ws('recordId'),$permissionsEntity)) {
        displayError('You don\'t have permission to modify this record');
        exit;
    }
    $label = new label();
    // Now associate the new label with the record
    $error = assignLabelToRecord( $label->id, ws('recordId') );
    header(sprintf('Location: ?mode=preview&labelId=%d&securityCode=%s',$label->id,$label->securityCode));
    exit;
}

function scaleImageToFit( $image, $w, $h ) {
    $width_orig = imagesx($image);
    $height_orig = imagesy($image);

    // Create the new image to copy over to
    $image_p = imagecreatetruecolor($w, $h);
    $white  = imagecolorallocate($image_p,255,255,255);
    // fill entire image (quickly)
    imagefilledrectangle($image_p,0,0,$w-1,$h-1,$white);

    $xOffset = 0;
    $ratio_orig = $width_orig/$height_orig;
    if ($w/$h > $ratio_orig) {
        $xOffset = ($w-$h*$ratio_orig)/2;
        $w = $h*$ratio_orig;
    } else {
        $h = $w/$ratio_orig;
    }

    imagecopyresampled($image_p, $image, $xOffset, 0, 0, 0, $w, $h, $width_orig, $height_orig);
    return $image_p;
}

class htmlLabels {
    private $layout;

    function __construct($layout) {
        $this->layout = $layout;

        extract($layout);

        if ($logo) {
            ob_start();
            imagepng($logo);
            $logo = ob_get_clean();
            $logo = 'data:image/png;base64,'.base64_encode($logo);
        }

        ?>
        <script src="/javascript/jquery.min.js"></script>
        <script src="/javascript/html2canvas.min.js"></script>
        <script>
            window.parent.downloadPNG = function() {
                var label = $('div.label');
                var labelId = label.data('labelid');
                label = label.clone().appendTo('body');
                console.log(label.width());
                label.css('transform','scale(4)');
                label.css('border','none');
                html2canvas(label.get(0),{ letterRendering: 1, allowTaint : true, onrendered : function (canvas) { } }).then(canvas => {
                    label.remove();
                    let image = canvas.toDataURL('image/png').replace('image/png', 'image/octet-stream');
                    const a = document.createElement('a');
                    a.setAttribute('download', 'Label_'+labelId);
                    a.setAttribute('href', image);
                    a.click();
                    canvas.remove();
                });
            }

            window.parent.changeLayout = function( layout ) {
                <?
                $url = sprintf('?labelId=%d&securityCode=%s&recordId=%d&x=0&y=0&layout=',ws('labelId'),ws('securityCode'),ws('recordId'));
                echo "console.log(".json_encode($url)."+layout);";
                echo "window.location.href=".json_encode($url)."+layout;";
                ?>
            }

            window.parent.downloadPDF = function() {
                window.open( window.location.href + '&fileType=pdf' );
            }
        </script>
        <style>
            @page { 
                size: A4 portrait;   /* auto is the initial value */ 

                /* this affects the margin in the printer settings */ 
                margin: 0;  
            }

            @media print {
                a.placeholder { display: none; }
            }

            body {
                width: 210mm;
                height: 297mm;
                margin: 0;
            }
            div.label, div.label div{
                position:absolute;
                margin:0;
                padding:0;
                text-align: center;
            }
            div.label,a.placeholder {
                display:block;
                position:absolute;
                border: 1px solid black;
                padding: 0;
                width: <?=$dims[0]?>mm;
                height: <?=$dims[1]?>mm;
            }
            a.placeholder {
                border: 1px dotted #ccc;
                cursor: pointer;
            }
            a.placeholder:hover {
                background-color: #ccc;
            }

            <? if ($logo) { ?>
                div.logo {
                    position: absolute;
                    left: <?=$logoOffset[0]?>mm;
                    top: <?=$logoOffset[1]?>mm;
                    background-image: url(<?=$logo?>);
                    background-size: <?=$logoDims[0]?>mm <?=$logoDims[1]?>mm;
                    width: <?=$logoDims[0]?>mm;
                    height: <?=$logoDims[1]?>mm;
                    background-repeat: no-repeat;
                    background-position: 50%;
                }
            <? } ?>
            div.qrCode {
                left: <?=$qrCodeOffset[0]?>mm;
                top: <?=$qrCodeOffset[1]?>mm;
            }
            div.qrCode img {
                width: <?=$qrCodeSize?>mm;
                height: <?=$qrCodeSize?>mm;
                margin:0;
            }
            <? foreach( $text as $idx=>$t) {
                array_shift($t);
                ?>
                div.content_<?=$idx?> {
                    font-family: <?=array_shift($t)?>;
                    font-weight: <?=array_shift($t)=='B'?'bold':'normal'?>;
                    font-size: <?=array_shift($t)/3?>mm;
                    left:<?=array_shift($t)?>mm;
                    top:<?=array_shift($t)?>mm;
                    width:<?=array_shift($t)?>mm;
                    height:<?=array_shift($t)?>mm;
                }
            <? } ?>
        </style>
        <?
    }

    function render() {
        // nothing to do here
    }

    function addPage() {
        // nothing to do here
    }

    function addLabel( $label, $col, $row ) {
        extract($this->layout);

        $originX = $leftMargin + $col * ($dims[0]+$spacing[0]);
        $originY = $topMargin + $row * ($dims[1]+$spacing[1]);

        $image = $label->getImageQrCode(1,50);
        $qrCodeImg = "<img class=\"qrCode\" src=\"$image\" />";

        echo '<div style="left:'.$originX.'mm; top:'.$originY.'mm" class="label" data-labelId="'.$label->id.'">';
        echo '<div class="qrCode">'.$qrCodeImg.'</div>';
        echo '<div class="logo"></div>';
        foreach( $text as $idx=>$t) {
            $content = $t[0];
            if ($content=='%%code%%') $content = sprintf('%07d:%s',$label->id,$label->securityCode);

            echo '<div class="content_'.$idx.'">'.$content.'</div>';
        }
        echo '</div>';
    }

    function addPlaceholder($col,$row){
        extract($this->layout);

        $originX = $leftMargin + $col * ($dims[0]+$spacing[0]);
        $originY = $topMargin + $row * ($dims[1]+$spacing[1]);

        $url = sprintf('?labelId=%d&securityCode=%s&recordId=%d&x=%d&y=%d&layout=%s',ws('labelId'),ws('securityCode'),ws('recordId'),$col,$row,ws('layout'));
        echo '<a class="placeholder" href="'.$url.'" style="left:'.$originX.'mm; top:'.$originY.'mm" class="label"></a>';
    }

}

class pdfLabels {

    private $pdf;
    private $layout;

    function __construct($layout) {
        require(LIB_DIR.'fpdf/fpdf.php');
        require(LIB_DIR.'fpdf/memImage.php');
        
        $this->pdf = new PDF_MemImage('P','mm','A4');
        $this->pdf->SetAutoPageBreak(false);
        $this->layout = $layout;
    }

    function addPage() {
        $this->pdf->AddPage();
        $this->pdf->SetMargins(0,0,0);
    }

    function addLabel( $label, $col, $row ) {

        $pdf = &$this->pdf;
        extract($this->layout);

        $originX = $leftMargin + $col * ($dims[0]+$spacing[0]);
        $originY = $topMargin + $row * ($dims[1]+$spacing[1]);

        $qrCodeImage = $label->getImageQrCode(0);

        $pdf->setXY($originX, $originY);

        $pdf->GDImage($qrCodeImage, $originX+$qrCodeOffset[0], $originY+$qrCodeOffset[0], +$qrCodeSize);
                
        if ($drawOutline) {
            $pdf->SetDrawColor(200);
            $pdf->SetLineWidth(0.1);
            $pdf->Cell($dims[0],$dims[1],'',1);
            $pdf->SetDrawColor(0);
        } else {
            $pdf->Cell($dims[0],$dims[1],'',0);
        }

        if (isset($logo) && $logo && isset($logoDims)) {
            $pdf->GDImage($logo, $originX+$logoOffset[0], $originY+$logoOffset[1], $logoDims[0],$logoDims[1] );
        }

        foreach ($text as $t) {
            $content = array_shift($t);
            if ($content=='%%code%%') $content = sprintf('%07d:%s',$label->id,$label->securityCode);
            $pdf->SetFont(array_shift($t),array_shift($t),array_shift($t));
            $pdf->setXY($originX+array_shift($t), $originY+array_shift($t));
            $pdf->MultiCell(array_shift($t),array_shift($t),$content,array_shift($t),'C');
        }

        imagedestroy($qrCodeImage);
    }

    function addPlaceholder(){
        // Nothing to do here
    }

    function render(){
        $this->pdf->Output();
    }
}

$layout = ws('layout');
if (!$layout) $layout = '3x9';

$fqdn = preg_replace('!^https?://!','',SITE_URL);
$fqdn = preg_replace('!/+$!','',$fqdn);

if ($layout=='3x9') {
    $layout = [
        'name'          => '3x9',
        'cols'          => 3,
        'rows'          => 9,
        'topMargin'     => 15,
        'leftMargin'    => 7.75,
        'dims'          => [65,29.6],
        'spacing'       => [0,0],
        'logoDims'      => [33,7],
        'logoOffset'    => [28, 3.5],
        'qrCodeOffset'  => [2, 2.3],
        'qrCodeSize'    => 26,
        'drawOutline'   => false,
        'text' => [
            ['Sample ID : Security Code','Arial','',6,24,12.8,36,5,0],
            ['%%code%%','Courier','',10.5,27.5,15.3,34,4.5,'TB'],
            ['If found please visit:','Arial','',6,20.5,19.6,37,5,0],
            [$fqdn,'Arial','B',strlen($fqdn)>14?7.5:8.5,23,23,37,20,0],
        ]
    ];
} else if ($layout=='Tiny') {
    $layout = [
        'name'          => 'Tiny',
        'cols'          => 8,
        'rows'          => 11,
        'topMargin'     => 15,
        'leftMargin'    => 7.75,
        'dims'          => [24,24],
        'spacing'       => [0,0],
        'qrCodeOffset'  => [6, 6],
        'qrCodeSize'    => 12,
        'drawOutline'   => true,
        'text' => [
        ]
    ];
} else {
    $layout = [
        'name'              => '5x13',
        'cols'              => 5,
        'rows'              => 13,
        'topMargin'         => 12,
        'leftMargin'        => 5,
        'dims'              => [40.5,21.1],
        'spacing'           => [0,0],
        'logoDims'          => [15,4],
        'logoOffset'        => [19.4, 2],
        'qrCodeOffset'      => [0.6, 0.6],
        'qrCodeSize'        => 16.5,
        'drawOutline'       => false,
        'text' => [
            ['Sample ID : Security Code','Arial','',4.1,17,8.4,20,5,0],
            ['%%code%%','Courier','',5.8,16,10.4,22,3.2,0],
            ['If found please visit:','Arial','',4.1,15,12.8,20,5,0],
            [$fqdn,'Arial','B',strlen($fqdn)>14?7.5:8.5,1,16.5,36,6,0],
        ]
];
}

$layout['fqdn'] = $fqdn;

$logoImage = SITE_BASE_DIR.'/data/images/labelLogo.png';
$logo = false;
if (isset($layout['logoDims']) && file_exists($logoImage)) {
    $logo = imagecreatefrompng($logoImage);
    // convert to black and white
    //imagefilter($logo, IMG_FILTER_GRAYSCALE);
    //imagefilter($logo, IMG_FILTER_CONTRAST, -1000);
    $logo = scaleImageToFit( $logo, $layout['logoDims'][0]*100,$layout['logoDims'][1]*100 );
}
$layout['logo'] = $logo;

$labelId=(int)ws('labelId');
if ($labelId) {
    $x = (int)ws('x');
    $y = (int)ws('y');
    $labelId = (int)ws('labelId');
    $securityCode = ws('securityCode');
    $label = new label($labelId,$securityCode);
    $labels = [
        "$x,$y" => $label
    ];
} else {
    $label = false;
}

// remember that column and row numbers are zero-based
$skipCols=[];
$skipRows=[];
$drawOutline=false;

if (ws('fileType')=='pdf') $output = new pdfLabels($layout);
else $output = new htmlLabels($layout);

$output->addPage();
for ($col=0; $col<$layout['cols']; $col++) {
    //if (in_array($col,$skipCols)) continue;
    for ($row=0; $row<$layout['rows']; $row++) {
        //if (in_array($row,$skipRows)) continue;
        $lastOne = false;

        if (isset($labels)) {
            if (isset($labels["$col,$row"])) $output->addLabel( $labels["$col,$row"], $col, $row );
            else $output->addPlaceholder( $col, $row );
        } else {
            if (ws('fileType')=='pdf') $label = new label();
            else $label = new label('dummy');
            $output->addLabel( $label, $col, $row );
        }

//        if ($lastOne) break 2;
    }
}

$output->render();
