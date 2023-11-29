<?

include_once(CORE_DIR.'/encryptedToken.php');

function getShareLink($recordId) {
    global $DB;
    // See if the record has a shareLinkSecret already
    $shareLinkSecret = $DB->getValue('SELECT shareLinkSecret FROM record WHERE id=?',$recordId);
    if (!strlen($shareLinkSecret)) {
        $shareLinkSecret = bin2hex(random_bytes(16));
        $DB->update('record',$recordId,['shareLinkSecret'=>$shareLinkSecret]);
    }

    $token = createEncryptedToken('shareLink',[$recordId,$shareLinkSecret]);
    return SITE_URL.'/record/find.php?token='.$token;
}

# Call this function with $token='getErrors' to retreive the last error;
function checkShareLink($token) {
    global $DB;
    static $lastError = '';

    if ($token=='getErrors') return $lastError==''?decryptEncryptedToken('getErrors'):$lastError;

    $lastError = '';
    $result = decryptEncryptedToken('shareLink',$token);
    if (!$result) return false;
    
    // check the shareLinkSecret
    list( $recordId, $shareLinkSecret ) = $result;
    //retreive the shareLinkSecret
    $desiredShareLinkSecret = $DB->getValue('SELECT shareLinkSecret FROM record WHERE id=? AND deletedAt=0',$recordId);

    if (!strlen($desiredShareLinkSecret)) { $lastError = 'That record no longer exists'; return false; }

    if (!hash_equals($shareLinkSecret,$desiredShareLinkSecret)) { $lastError = 'Invalid token content'; return false; }

    return $recordId;
}

function shareLinkJavascript() {
    ?>
    <script>
    $(function(){
        $('a.getShareLink').on('click',function(){
            let self = $(this);
            var message = $('<div class="notify flashMessage"></div>');
            let error = function() {
                message.append('<div class="error">Sorry - there was an unexpected error whilst retrieving the link</div>');
                $('body').append(message);
            };

            $.get(self.attr('href'), function(data){
                console.log(data);
                if (!data.url) {
                    error();
                } else {
                    navigator.clipboard.writeText(data.url).then( function(){
                        message.append('<div class="success">Share link copied to clipboard</div>');
                        $('body').append(message);
                    });
                };
            }).fail(error);
            return false;
        });
    });
    </script>
    <?
}
