<div class="questionAndAnswer">
    <div class="question">
        Name:
    </div>
    <div class="answer">
        <b><?= wsp('configuration_name')?></b>
    </div>
</div>

<div class="questionAndAnswer">
    <div class="question">
        Description:
    </div>
    <div class="answer">
        <?= wsp('configuration_description')?>
    </div>
</div>

<div class="questionAndAnswer">
    <div class="question">
        Value:
    </div>
    <div class="answer">
        <?= formTextbox('configuration_value',30,4096)?>
    </div>
</div>

