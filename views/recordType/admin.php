<hr class="contentStart">

<div class="layout">
    <div class="content mainContent">
        <div class="questionAndAnswerContainer form-grid">
            <div class="questionAndAnswer half">
                <div class="question">
                    <?=cms('Record Type: Name',0,'Name')?>:
                </div>
                <div class="answer">
                    <? formTextBox('recordType_name',50,200); ?>
                    <? inputError('recordType_name'); ?>
                </div>
            </div>

<? if (ws('id')) { ?>
            <div class="questionAndAnswer half">
                <div class="question">
                    <?=cms('Record Type: Primary data field',0,'Primary data field')?>:
                </div>
                <div class="answer">
                    <? global $primaryDataFieldIdSelect; $primaryDataFieldIdSelect->display()  ?>
                    <? inputError('recordType_primaryDataFieldId'); ?>
                </div>
            </div>

            <div class="questionAndAnswer">
                <div class="question">
                    <?=cms('Record Type: Fields to show',0,'Fields to show on listing')?>:
                </div>
                <div class="answer">
                    <?
                        global $displayFieldsSelect;
                        $displayFieldsSelect->display();
                        inputError('dataFieldIds');
                    ?>
                </div>
            </div>
<? } ?>

            <div class="questionAndAnswer">
                <div class="question">
                    <?=cms('Record Type: Public preview message',0,'Public preview message')?>:
                </div>
                <div class="answer">
                    <?
                        formTextarea('recordType_publicPreviewMessage',80,5);
                        inputError('recordType_publicPreviewMessage');
                    ?>
                    <div class="info"><?=cms('Record Type: Public preview message explanation',0,'The text supplied here is displayed when a user who isni\'t logged in scans the QR code on a label which is linked to this kind of record')?></div>
                </div>
            </div>

            <div class="questionAndAnswer">
                <div class="question">
                    Label image
                </div>
                <div class="answer">
                    <? $labelImageUpload->display() ?>
                    <? inputError('labelImage'); ?>
                </div>
            </div>
        </div>
    </div>
</div>
