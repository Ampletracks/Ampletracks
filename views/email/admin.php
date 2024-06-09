<div class="questionAndAnswer">
    <div class="question">Status:</div>
    <div class="answer"><?=wsp('email_status')?></div>
</div>
<div class="questionAndAnswer">
    <div class="question">Template:</div>
    <div class="answer"><?=wsp('email_template')?></div>
</div>
<? if (strlen(ws('email_lastError'))) { ?>
    <div class="questionAndAnswer">
        <div class="question">Last error:</div>
        <div class="answer"><? formTextbox('user_firstName', 50, 20); ?></div>
    </div>
<? } ?>

<? if ($WS['email_status']=='sent') { ?>
    <div class="questionAndAnswer">
        <div class="question">Priority:</div>
        <div class="answer"><?=ucfirst(ws('email_priority'))?></div>
    </div>
    <div class="questionAndAnswer">
        <div class="question">Send after:</div>
        <div class="answer"><?=date('d/m/Y H:i',$WS['email_sendAfter'])?></div>
    </div>
    <div class="questionAndAnswer">
        <div class="question">Sent at:</div>
        <div class="answer"><?=date('d/m/Y H:i',$WS['email_lastSendAttemptedAt'])?></div>
    </div>
<? } else { ?>
    <div class="questionAndAnswer">
        <div class="question">Priority:</div>
        <div class="answer"><? $prioritySelect->display(); formPlaceholder('email_priority'); ?></div>
    </div>
    <div class="questionAndAnswer">
        <div class="question">Send after:</div>
        <div class="answer"><?=formDate('email_sendAfter')?></div>
    </div>
    <? if ($WS['email_lastSendAttemptedAt']) { ?>
        <div class="questionAndAnswer">
            <div class="question">Last attempted at:</div>
            <div class="answer"><?=date('d/m/Y H:i',$WS['email_lastSendAttemptedAt'])?></div>
        </div>
        <div class="questionAndAnswer">
            <div class="question">Last error:</div>
            <div class="answer"><?=wsp('email_lastError', true, true)?></div>
        </div>
    <? } ?>
    
<? } ?>