<hr class="contentStart">

<div class="layout">
    <div class="content mainContent">
        <div class="questionAndAnswerContainer form-grid">
            <div class="questionAndAnswer half">
                <div class="question">
                    <?=cms('Email Template: Default status',0,'Default status')?>:
                </div>
                <div class="answer">
                    <? formOptionbox('emailTemplate_defaultStatus',[
                        'New' => 'new',
                        'On hold' => 'held'
                    ]);?>
                    <div class="info"><?=cms('Email Template: Default status explanation',0,'You generally want to leave this set to "New". Setting this to "On hold" means emails generated based on this template will not be sent until they have been manually released from the Ampletracks mail queue by an administrator.')?></div>
                </div>
            </div>

            <div class="questionAndAnswer half">
                <div class="question">
                    <?=cms('Email Template: Disabled',0,'Disabled?')?>
                </div>
                <div class="answer">
                    <? formYesNo( 'emailTemplate_disabled',false); ?>
                    <div class="info"><?=cms('Email Template: Disabled explanation',0,'If the template is disabled then any emails generated based on this template will be discarded before they are sent and not recorded in the mail log.')?></div>
                </div>
            </div>
            <div class="questionAndAnswer">
                <div class="question">
                    <?=cms('Email Template: Extra CC',0,'Extra CC address(es)')?>:
                </div>
                <div class="answer">
                    <?
                        formTextbox('emailTemplate_extraCc',250);
                        inputError('emailTemplate_extraCc');
                    ?>
                    <div class="info"><?=cms('Email Template: Extra CC explanation',0,'Optional. Any addresses given here will be CC\'d on all messages based on this template. Use commas to separate multiple addresses.')?></div>
                </div>
            </div>
            <div class="questionAndAnswer">
                <div class="question">
                    <?=cms('Email Template: Extra BCC',0,'Extra BCC address(es)')?>:
                </div>
                <div class="answer">
                    <?
                        formTextbox('emailTemplate_extraBcc',250);
                        inputError('emailTemplate_extraBcc');
                    ?>
                    <div class="info"><?=cms('Email Template: Extra BCC explanation',0,'Optional. As above, use commas to separate multiple addresses.')?></div>
                </div>
            </div>
            <div class="questionAndAnswer">
                <div class="question">
                    <?=cms('Email Template: Subject',0,'Subject')?>:
                </div>
                <div class="answer">
                    <?
                        formTextbox('emailTemplate_subject',250);
                        inputError('emailTemplate_subject');
                    ?>
                    <div class="info"><?=cms('Email Template: Extra BCC explanation',0,'Optional. As above, use commas to separate multiple addresses.')?></div>
                </div>
            </div>
            <div class="questionAndAnswer">
                <div class="question">
                    <?=cms('Email Template: Message body',0,'Message body')?>:
                </div>
                <div class="answer">
                    <?
                        formTextarea('emailTemplate_body',80,10);
                        inputError('recordType_publicPreviewMessage');
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>
