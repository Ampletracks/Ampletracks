<?

$INPUTS=array(
    '.*'    => array(
        'confirmMode'                   => 'TEXT',
        'sure'                          => 'TEXT',
        'check'                         => 'INT',
    ),
);

function listSql() {

    if (strlen(trim(ws('filter_emailAddress:address_ct')))) {
        $extraTables = 'INNER JOIN emailRecipient ON emailRecipient.emailId=email.id INNER JOIN emailAddress ON emailAddress.id=emailRecipient.emailAddressId ';
        $grouping = 'GROUP BY email.id';
    } else {
        global $WS;
        unset($WS['filter_emailAddress:address_ct']);
        $extraTables = '';
        $grouping = '';
    }
    $listSql = "
        SELECT
            email.id,
            email.status,
            email.sendAfter,
            email.priority,
            email.lastSendAttemptedAt,
            IFNULL(emailTemplate.name,'--deleted--') AS template
        FROM
            email
            INNER JOIN emailTemplate ON email.emailTemplateId=emailTemplate.id
            $extraTables
        WHERE
            email.deletedAt=0
        $grouping
        ORDER BY email.status='sent' ASC, email.sendAfter DESC, email.id DESC
        LIMIT 1000
    ";

    return $listSql;
}

function processInputs($mode) {
    global $DB, $beforeList; 

    if (ws('mode')=='pause') {
        setConfig('Pause email delivery','yes');
    }
    else if (ws('mode')=='resume') {
        setConfig('Pause email delivery','no');
    }

    if (ws('filter_email:lastSendAttemptedAt_on')) ws('filter_email:status_eq','sent');

    $mode = ws('sure')?ws('confirmMode'):ws('mode');

    // Take the " matching" off the end of the mode
    $mode = substr(strtolower($mode),0,-9);
    if ($mode && strpos('|hold|release|delete',$mode)) {
        $sql = listSql();

        if (strpos('|hold|',$mode)) {
           ws('filter_email:status_in',array('new','retry')); 
        } else if (strpos('|release|',$mode)) {
           ws('filter_email:status_eq','on hold'); 
        }
        ws('filter_email:deletedAt_eq',0);

        addConditions($sql,'filter_');

        $actioned = false;
        if (ws('sure')=='Continue') {
            $emailIds = $DB->getColumn($sql);
            if (count($emailIds)<>ws('check')) {
                $beforeList = '<div class="error">The number of emails affected has changed since you started the process - please re-check and click continue</div>';
            } else {
                $actioned = true;
                if (strpos('|hold|',$mode)) {
                    $DB->update('email',array('id'=>$emailIds),array('status'=>'on hold'));
                } else if (strpos('|release|',$mode)) {
                    $DB->update('email',array('id'=>$emailIds),array('status'=>'new'));
                } else {
                    $DB->update('email',array('id'=>$emailIds),array('deletedAt'=>time()));
                }
            }
        }

        if (!$actioned) {
            if (!isset($beforeList)) $beforeList='';
            // Count the number of affected rows
            $numRows = $DB->count($sql);
            if ($numRows) {
                $beforeList .= '
                    <script>
                        vex.dialog.alert("You are about to '.htmlspecialchars($mode).' '.$numRows.' emails. The first page of these is shown below. Please check this and then, if you wish to proceed, click \\"Continue\\" at the top of the list.");
                    </script>
                    <input type="hidden" name="confirmMode" value="'.ws('mode').'" />
                    You are about to '.htmlspecialchars($mode).' '.$numRows.' emails. The first page of these is shown below. Are you sure you want to continue?<br />
                    <input type="submit" name="sure" Value="Continue"/>
                    <input type="hidden" name="check" Value="'.$numRows.'"/>
                ';
            } else {
                $beforeList .= '<div class="error">The filters you applied didn\'t result in there being any emails to '.htmlspecialchars($mode).'</div>';
            }
        }
    }
}

function prepareDisplay() {
    global $templateFilter;
    $templateFilter = new formOptionbox('filter_emailTemplate:typeId_eq',array('Any'=>''));
    $templateFilter->addLookup('
        SELECT DISTINCT emailTemplate.name, emailTemplate.id
        FROM emailTemplate
        INNER JOIN email ON email.emailTemplateId = emailTemplate.id
        WHERE
        email.sendAfter > (UNIX_TIMESTAMP() - (86400 * 30)) AND
        email.deletedAt = 0
        ORDER BY name ASC
    ');
    $templateFilter->setExtra('style="max-width: 110px" onChange="$(\'#filterTTName\').val(\'\')"');

    global $title;
    $title = "Email Queue";
}

function extraButtonsBefore($location) {
    ?>
    <? if (canDo('list', 'emailTemplate')) { ?>
        <a href="../emailTemplate/list.php?scrollPosition=0" class="btn"><?=cms('Email Templates', 0, 'Email Templates')?></a>
    <? } ?>
    <? if (strtolower(getConfig('Pause email delivery')=='no')) { ?>
        <a href="list.php?mode=pause" class="btn"><?=cms('Pause Email Delivery',0,'Pause Delivery')?></a>
    <? }
}

function beforeList() {
    if (strtolower(getConfig('Pause email delivery')=='yes')) { ?>
    <div class="emailDeliveryPause warning">
        <?=cms('Email delivery paused warning',1,'All email delivery is currently paused. All new emails will be held on the queue until email delivery is resumed');?>
        <a href="list.php?mode=resume" class="button"><?=cms('Resume Email Delivery')?></a>
    </div>
    <? }
}

$noAddButton = true;
include( '../../lib/core/listPage.php' );

?>
