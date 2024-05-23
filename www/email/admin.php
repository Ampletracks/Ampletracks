<?

$INPUTS = array(
    'downloadAttachment' => array(
        'attachmentId' => 'INT'
    )
);

$entityLoadSql = '
    SELECT
        email.*,
        emailTemplate.name AS template
    FROM email
        INNER JOIN emailTemplate ON emailTemplate.id=email.emailTemplateId
    WHERE email.id="@@id@@"
';

# $stylesheet=array();
# $script=array();
function processInputs() {
    global $DB, $emailDetails;

    include(LIB_DIR.'/email.php');

    if (!ws('id')) {
        displayError('An email ID must be provided');
        exit;
    }

    $emailDetails = $EMAIL->getSubjectAndBody( ws('id'));

    if (is_array($emailDetails)) {
        $emailDetails['body'] = preg_replace_callback('/(<[^<>]+src\\s*=\\s*)(["\'])(.+?)\\2/',function($matches) use($emailDetails){
            $imageFilename = $emailDetails['imageDir'].'/'.$matches[3];
            if (!file_exists($imageFilename)) return $matches[0];
            $dataUri = 'data: '.mime_content_type($imageFilename).';base64,'.base64_encode(file_get_contents($imageFilename));
            return $matches[1].$matches[2].$dataUri.$matches[2];
        },$emailDetails['body']);
    }
    if (ws('mode')=='resend' && ws('id')) {
        if (
            $DB->update('email',array('id'=>ws('id'),'status'=>'sent'),array(
                'status'                => 'new',
                'lastSendAttemptedAt'   =>  0,
                'sendAttempts'          => 0,
                'lastError'             => '',
            ))
        ) logAction('email',ws('id'),'Email resend triggered');
    }
    else if (ws('mode')=='sendNow' && ws('id')) {
        if (
            $DB->exec('
                UPDATE email SET sendAfter=UNIX_TIMESTAMP(), priority="high"
                WHERE id=? AND status NOT IN ("failed","sent","held")
            ',ws('id'))
        ) logAction('email',ws('id'),'Email set to send now');
    }
    else if (ws('mode')=='hold' && ws('id')) {
        if (
            $DB->exec('
                UPDATE email SET status="held"
                WHERE id=? AND status NOT IN ("failed","sent","held")
            ',ws('id'))
        ) logAction('email',ws('id'),'Email put on hold');
    }
    else if (ws('mode')=='release' && ws('id')) {
        if (
            $DB->update('email',array('id'=>ws('id'),'status'=>'held'),array(
                'status'            => 'new',
            )) 
        ) logAction('email',ws('id'),'Email hold released');
    }
    else if (ws('mode')=='downloadAttachment' && ws('id')) {
        if(isset($emailDetails['attachments']) && !empty($emailDetails['attachments']) && isset($emailDetails['attachments'][ws('attachmentId')])) {
            $attachment = $emailDetails['attachments'][ws('attachmentId')];
            $attachment = explode('|',$attachment);
            $downloadName = (isset($attachment[1]) && !empty($attachment[1]))?$attachment[1]:basename($attachment[0]);

            header('Content-type: application/pdf');
            header('Content-disposition: inline; filename="'.$downloadName.'"');
            readfile($attachment[0]);
            exit;
        } else {
            displayError('No such attachment Found');
            die();
        }
    }
}

function extraButtonsAfter() {
    global $WS, $emailDetails;
    if ($WS['email_status']=='sent') {
        if (is_array($emailDetails)) { ?>
            <a href="admin.php?mode=resend&id=<?=wsp('id')?>" class="btn">Resend</a>
        <? }
    } else {
        if ($WS['email_sendAfter']>time()) { ?>
            <a href="admin.php?mode=sendNow&id=<?=wsp('id')?>" class="btn">Send ASAP</a>
        <? }
        if ($WS['email_status']!='held') { ?>
            <a href="admin.php?mode=hold&id=<?=wsp('id')?>" class="btn">Hold</a>
        <? } else { ?>
            <a href="admin.php?mode=release&id=<?=wsp('id')?>" class="btn">Release</a>
        <? } 
    }
}
function extraPageContent() {
    global $WS, $EMAIL, $emailDetails;

    $addresses = $EMAIL->getAddresses( ws('id'));

    ?>
    <br />
    <h2>Email Preview</h2>
    <? if (is_array($emailDetails)) {
        ?>
        <table style="border: 1px solid black; width:800px;">
            <? foreach( array('from'=>'From','replyTo'=>'Reply To','to'=>'To','cc'=>'CC','bcc'=>'BCC') as $var=>$label ) {
                // from and reply to are not an array of arrays - so make them into one (assuming they're not empty)
                if ( strpos('|from|replyTo|',$var) && count($addresses[$var])) $addresses[$var] = [$addresses[$var]];
                if (count($addresses[$var])) { ?>
                    <tr><th style="text-align:right;vertical-align:top;"><?=$label?>:</th><td>
                        <? foreach( $addresses[$var] as $address ) {
                            echo htmlspecialchars($address['name']).' &lt;'.htmlspecialchars($address['email']).'&gt;<br />';
                        } ?>
                    </td></tr>
                <? }
            } ?>
            <tr><th style="text-align:right;vertical-align:top;">Subject:</th><td><?=htmlspecialchars($emailDetails['subject'])?></td></tr>
            <? if(isset($emailDetails['attachments']) && !empty($emailDetails['attachments'])){ ?>
            <tr><th style="text-align:right;vertical-align:top;">Attachments:</th>
                <td>
                    <ul>
                    <? foreach($emailDetails['attachments'] AS $attachmentId => $attachment) {
                        $attachment = explode('|',$attachment);
                        $attachment = (isset($attachment[1]) && !empty($attachment[1]))?$attachment[1]:basename($attachment[0]);
                        echo '<li><a href="?mode=downloadAttachment&attachmentId='.$attachmentId.'&id='.(int)$WS['id'].'">'.htmlspecialchars($attachment).'</a></li>';
                    }
                    ?>
                    </ul>
                </td>
            </tr>
            <? } ?>
            <tr><td colspan="2" style="border-top:1px solid black">
                <div id="emailContent">
                    <?=$emailDetails['body']?>
                </div>
                <iframe width="800" height="800" id="emailContentIframe"></iframe>
                <script>
                    var ifrm = document.getElementById('emailContentIframe');
                    ifrm = ifrm.contentWindow || ifrm.contentDocument.document || ifrm.contentDocument;
                    ifrm.document.open();
                    ifrm.document.write($('#emailContent').hide().html());
                    ifrm.document.close();
                </script>
            </td>
        </table>

        <script>
            function previewEmail() {
                console.log('x');
                var preview = window.open('','emailPreview','resizable=yes,scrollbars=yes,status=no,width=800, height=800');
                preview.document.write($('[name=emailTemplate_content]').val());
            }
        </script>
        <? if ($WS['email_status']=='sent') { ?>
            <div class="info">
                N.B. This preview is generated using the current email template, if the template has been changed since this email was sent, then the content shown above may not be an exact copy of the email that was sent.
            </div>
        <? } ?>
    <? } else { ?>
        <i>Preview no longer available</i>
    <? } ?>
<? }

function prepareDisplay() {
    global $EMAIL, $prioritySelect;
    $prioritySelect = new formOptionbox('email_priority', $EMAIL->getPriorities(true));
    $prioritySelect->removeOption('immediate');
}

$heading = 'Email details';
include( '../../lib/core/adminPage.php' );


?>
