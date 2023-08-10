<div class="questionAndAnswer">
    <div class="question">Last login:</div>
    <div class="answer readOnly">
        <? if ( !ws('user_lastLoggedInAtDateTime') ) { ?>
            Never
        <? } else { ?>
            <?=wsp('user_lastLoggedInAtDateTime')?> from <?=wsp('user_lastLoginIpAddress')?>
        <? } ?>
    </div>
            
</div>
<div class="questionAndAnswer">
    <div class="question">First name:</div>
    <div class="answer"><? formTextbox('user_firstName', 50, 20); ?></div>
</div>
<div class="questionAndAnswer">
    <div class="question">Last name:</div>
    <div class="answer"><? formTextbox('user_lastName', 50, 20); ?></div>
</div>
<div class="questionAndAnswer">
    <div class="question">Email:</div>
    <div class="answer">
        <? if ($canEditLogin) { ?>
            <? formTextbox('user_email', 50); ?>
        <? } else { ?>
            <? wsp('user_email'); ?>
            <div class="info">
                <?=cms('Can\'t edit login info warning',1,'You are not allowed to edit the login details for this user because they have permission to do more stuff than you do') ?>
            </div>
        <? } ?>
    </div>
</div>
<div class="questionAndAnswer">
    <div class="question">Mobile no.:</div>
    <div class="answer"><? formTextbox('user_mobile', 20,20); ?></div>
</div>
<? if ($canEditLogin) { ?>
    <? if (ws('encryptedPassword')) {?>
        <div class="questionAndAnswer">
            <div class="question">Password:</div>
            <div class="answer info">Already provided by user</div>
            <? formHidden('encryptedPassword'); ?>
        </div>
    <? } else { ?>
        <div class="questionAndAnswer">
            <div class="question">Password:</div>
            <div class="answer"><? formTextbox('password', -20,20,null,'autocomplete="new-password"'); ?></div>
        </div>
        <div class="questionAndAnswer">
            <div class="question">Confirm Password:</div>
            <div class="answer"><? formTextbox('confirmPassword', -20); ?></div>
        </div>
    <? } ?>
<? } ?>
<div class="questionAndAnswer">
    <div class="question">Record types:</div>
    <div class="answer">
        <? if (ws('id')>0) { ?>
            <? $recordTypeSelect->display(); ?>
            <div class="info">
                <?=cms('User record types are not permissions note',0,'N.B. The record types selected here don\'t define what the user is allowed to see - that is defined by the permissions assigned to the user\'s roles. This list just gives the user the option of hiding some of the record types if they don\'t use them') ?>
            </div>
        <? } else { ?>
            <?=cms('Save new user before adding record types warning',1,'You must save the new user first before you can select record types because the permissible record types depend on the roles you give the user.')?>
        <? } ?>
    </div>
</div>
<div class="questionAndAnswer">
    <div class="question">Projects:</div>
    <div class="answer">
        <? $projectSelect->display(); ?>
        <? if (!isSuperuser() && ws('id')<>$USER_ID) { ?>
            <div class="info">
                <?=cms('Only assign to others projects you have yourself info',0,'You can only give to others projects which are already assigned to you') ?>
            </div>
        <? } ?>
    </div>
</div>
<div class="questionAndAnswer">
    <div class="question">Roles:</div>
    <div class="answer">
        <? $roleSelect->display(); ?>
        <? if (!isSuperuser() && ws('id')<>$USER_ID) { ?>
            <div class="info">
                <?=cms('Only assign to others roles you have yourself info',0,'You can only give to others roles you have yourself') ?>
            </div>
        <? } ?>
    </div>
</div>
