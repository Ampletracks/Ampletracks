<?
$jsPath = '../javascript/';

$requireLogin = false;

include('../lib/core/startup.php');

include(VIEWS_DIR.'/header.php');

?>
<style>
    html {
        background-color: white;
        margin:0;
        padding: 0;
    }
    body {
        margin:0;
        padding: 0;
        text-align:center;
        font-family: arial,sans-serif;
    }
    main {
        margin:0;
        padding: 0;
    }
    video {
        width: 80%;
        border: 2px solid black;
    }
    button {
        display: block;
        position: absolute;
        bottom: 10px;
        right: 10px;
        background: #ccc;
        color: #000;
        font-famil:arial;
        padding: 5px;
        border: 0;
        font-weight: bold;
    }
    button.test{
        right: 100px;
        display: none;
    }
    div#success {
        display: none;
        position: absolute;
        font-family: arial;
        font-size: 20px;
        width: 80%;
        margin-left:10%;
        top:50%;
        margin-top:-1em;
        height: 2em;
        line-height:2em;
        background: green;
        color: #fff;
        opacity: 0.8;
        font-weight: bold;
    }
    div#error {
        display: none;
        top:0;
        position: absolute;
        font-family: arial;
        font-size: 18px;
        line-height: 1.5em;
        width: 80%;
        margin-left:10%;
        background: #ab2222;
        color: #fff;
        font-weight: bold;
        opacity: 0.8;
        padding: 5px;
        box-sizing: border-box;
    }
    p.intro {
        margin: 20px;
    }
</style>
    
    <table width="100%" height="100%" cellpadding="0" cellspacing="0"><tr valign="center"><td align="center"><div>
        <h1><?=cms('QR scanner: Title',0,'Label Scanner')?></h1>
        <p class="intro"><?=cms('QR scanner: Intro paragraph',1,'Hold the label you want to scan up to your camera until it is visible clearly in the video window below.')?></p>
        <video muted playsinline id="qr-video"></video>
        <div id="success">QRCode Scanned OK</div>
        <div id="error"></div>

    </div></table>
    
    <script src="<?=$jsPath?>jquery.min.js"></script>
    <script type="module">
        var qrScanner;
        $('#error').on('click',function(){$(this).fadeOut()});
        
        function showError(message) {
            $('#success').hide();
            $('#error').text(message).show();
            window.setTimeout(function(){
                $('#error').fadeOut();
            },5000);
            return false;
        }

        function done() {
            if (qrScanner) qrScanner.destroy();
            qrScanner = null;
        }
        
        <?
            if (defined('LABEL_SITE_ID') && LABEL_SITE_ID>0) $labelBaseUrl = LABEL_QR_CODE_BASE_URL;
            else $labelBaseUrl = 'https://'.SITE_NAME;
        ?>
        console.log(<?=json_encode($labelBaseUrl)?>);

        function foundCode(result,message){
            if (result) {
                var codeError='';
                if (window.parent.checkQRCode) codeError = window.parent.checkQRCode(result);
                if (codeError.length) {
                    showError(codeError);
                } else {
                    $('#error').hide();
                    $('#success').show();
                    window.setTimeout(function(){
                        if (window.parent.gotQRCode) {
                            window.parent.gotQRCode(result);
                            done();
                        } else {
                            if ( result.indexOf(<?=json_encode($labelBaseUrl)?>)!==0 ) {
                                showError('This is not a valid '+<?=json_encode(SITE_NAME)?>+' QR code');
                            } else {
                                window.setTimeout(function(){
                                    $('#success').html('Loading sample details...');
                                    done();
                                    <? if (IS_DEV) { ?>
                                        result = <?=json_encode(SITE_URL)?>+'/record/find/'+result.substring(<?=json_encode(LABEL_QR_CODE_BASE_URL)?>.length);
                                    <? } ?>
                                    top.location.href=result;
                                },300);
                            }
                        }
                    },2000);
                }
            } else {
                if (window.parent.gotQRCode) {
                    window.parent.gotQRCode(message)
                    done();
                } else {
                    showError(message);
                }
            }
        }

        $(window).on('unload',function(){
            if (qrScanner) qrScanner.destroy();
            qrScanner = null;
        });

        import QrScanner from '<?=$jsPath?>qr-scanner/qr-scanner.min.js';
        QrScanner.WORKER_PATH = '<?=$jsPath?>qr-scanner/qr-scanner-worker.min.js';

        QrScanner.hasCamera().then(function(hasCamera){
            if (!hasCamera) foundCode(false,'no camera');
            else {
                var video = $('video');
                qrScanner = new QrScanner(video.get(0), foundCode);
                qrScanner.start();
                
                // Resize the video to fit in the iframe
                video.on('play',function(){
                    // All of this is just to find the size of the iframe we have been loaded into
                    var uniqueishId = Math.floor(Math.random() * 99999) +':'+ Date.now();
                    $('body').attr('id',uniqueishId);
                    $(window.parent.document).find('iframe').each(function(){
                        var self = $(this);
                        if (self.contents().find('body').attr('id') == uniqueishId) {
                            var iframeHeight = self.height();
                            var videoHeight = video.height()
                            // OK.. now we have the height see if this is less than the video height
                            if (iframeHeight < videoHeight) {
                                video.width(video.width()/videoHeight*iframeHeight);
                                video.height(iframeHeight);
                            }
                        }
                    });
                });
            }
        });

        // If this is loaded as an iFrame then remove the header and footer
        $(function() {
            if (window.parent) {
                $('header').hide();
                $('footer').hide();
                
                let iframe = window.parent.document.querySelectorAll("iframe")[0];
                iframe.height=iframe.contentWindow.document.body.scrollHeight+50;
            }
        });
        
    </script>
</body>
