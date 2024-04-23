<hr class="contentStart">

<div class="layout">
    <div class="content mainContent">
        <div class="questionAndAnswerContainer form-grid">
            <div class="questionAndAnswer half">
                <div class="question">
                    <?=cms('IOD Admin: Creation date',0,'Creation date')?>:
                </div>
                <div class="answer readOnly">
                   <? wsp('iodRequest_createdAtDateTime'); ?> 
                </div>
            </div>

            <div class="questionAndAnswer half">
                <div class="question">
                    <?=cms('IOD Admin: Termination date',0,'Termination date')?>:
                </div>
                <div class="answer">
                    <? formDate('iodRequest_deleteAt'); ?>
                </div>
            </div>

            <div class="questionAndAnswer">
                <div class="question">
                    <?=cms('IOD Admin: Created for',0,'Created for')?>:
                </div>
                <div class="answer readOnly">
                    <? wsp('iodRequest_email') ?>
                </div>
            </div>

            <div class="questionAndAnswer half">
                <div class="question">
                    <?=cms('IOD Admin: Status',0,'Status')?>:
                </div>
                <div class="answer readOnly">
                    <? wsp('iodRequest_status'); ?>
                </div>
            </div>

            <div class="questionAndAnswer half">
                <div class="question">
                    <?=cms('IOD Admin: URL',0,'URL')?>:
                </div>
                <div class="answer readOnly">
                    https://<? wsp('iodRequest_subDomain'); ?>.<?=IOD_DOMAIN?>
                </div>
            </div>

        </div>
    </div>
</div>
