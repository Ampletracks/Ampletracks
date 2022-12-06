<?

include('../lib/core/startup.php');

if (!defined('SCANNER_COMMS_FILE_LIFETIME')) define('SCANNER_COMMS_FILE_LIFETIME',86400);
if (!defined('SCANNER_COMMS_DIR')) define('SCANNER_COMMS_DIR','scannerComms');


?><html>
<head>
    <script src="/javascript/jquery.min.js"></script>
    <style>
        body {
            text-align: center;
            font-family: arial, sans-serif;
            margin: 0;
        }
        iframe.scanQRCode {
            width: 100%;
            height: 500px;
            border: none;
        }
        
        @media (min-width: 800px) {
            body {
                width:800px;
                margin: 0 auto;
            }
        }
        
        div.title {
            font-size: 20px;
            font-weight: bold;
            height:0;
            visibility: hidden;
            background-color: red;
            color: black;
            transition: background-color 1s ease;
            transition: color 1s ease;
        }
        
        body.lookupRecord div.title.lookupRecord, body.attachLabel div.title.attachLabel {
            padding: 20px 10px;
            visibility: visible;
            height: initial;
            background-color: white;
        }
        
        body.lookupRecord div.title.lookupRecord {
            background-color: white;
        }
        
        body.attachLabel div.title.attachLabel {
            background-color: green;
            color: white;
        }

        body.modeAttachLabel iframe.scanQRCode {
            height: 200px;
        }
        
        body.modeAttachLabel div.title.attachLabel {
            font-size: 15px;
            background-color: inherit;
            color: black;
            padding-top: 0;
        }
    </style>
</head>
<body class="<?=ws('mode')=='attachLabel'?'modeAttachLabel attachLabel':'lookupRecord'?>">

<? if (ws('mode')=='attachLabel') { ?>
    <div class="title attachLabel">
        Please scan an unused label here, or via the scanner page on your mobile.
    </div>
<? } else { ?>
    <div class="title lookupRecord">
        Scan a label to lookup an existing item.
    </div>
    <div class="title attachLabel">
        Please scan a new label to attach to the item you are editting.
    </div>
<? } ?>
<iframe class="scanQRCode" src="scanQRCode.php">
</iframe>

<script>
    var commsFilename = '/<?=SCANNER_COMMS_DIR.'/'.$commsFilename?>';
    var commsFilenameExpiresAt = '<?=$commsFilenameExpiresAt?>';
    var backgrounded = false;
    var requestInProgress = false;
    var lastStatus = false;
    
    window.setInterval(function(){
        if (backgrounded) return;
        if (requestInProgress) return;
        requestInProgress=true;
        
        $.ajax({
            type    : 'GET',
            url     : commsFilename,
            headers : {'Cache-Control': 'max-age=0'},
            success : processStatus,
            dataType: 'json',
            error   : function(stuff){
                console.log('If you see a 404 error below this is expected and not an error - it just means the server has no messages for us right now');
                console.log('no comms');
                requestInProgress=false;
            }
        });
    },1000);
    
    document.addEventListener("visibilitychange", function() {
        backgrounded = document.hidden;
    });
    
    function processStatus(data){
        console.log(data);
        if (lastStatus==data.status) return;
        requestInProgress=false;
        if (data.status=='complete') {
            lastStatus=data.status;
            $('body').removeClass('attachLabel').addClass('lookupRecord');
            <? if (ws('mode')=='attachLabel') { ?>
                $('iframe').remove();
                window.parent.gotQRCode();
            <? } else { ?>
                $('iframe').get(0).contentWindow.location.reload();
            <? } ?>
        } else {
            $('body').removeClass('lookupRecord').addClass('attachLabel');
            console.log('got message',data);
        }
    }

    function gotQRCode(code) {
        $.post('scanner.php',{
            'mode'  : 'checkCode',
            'code'  : 'code'
        },function(data) {
            if (data.status=='OK') {
                if (window.parent) window.parent.gotQRCode(code);
            }
        },'json');
    }
    
</script>
</body>
</html>