<? if (ws('id')) { ?>
    <div class="questionAndAnswer">
        <div class="question">
            Label
        </div>
        <div class="answer">
            <?= wsp('cms_label') ?>
        </div>
    </div>
    <div class="questionAndAnswer">
        <div class="question">
	        Content
        </div>
        <div class="answer">
            <? formTextarea('cms_content',80,4,null,$WS['cms_allowMarkup']?'class="htmlEdit"':'');?>
        </div>
    </div>
    <div class="questionAndAnswer">
        <div class="question">
            Original
        </div>
        <div class="answer">
            <?= wsp('cms_defaultContent');?>
        </div>
    </div>
<? } else { ?>
    _Label: <? formTextbox('cms_label',40,255)?><br />
	_Markup allowed?: <? yesNo('cms_allowMarkup',false); # formPlaceholder('cms_allowMarkup')?><br />
	<small>You must save the label first before you can edit the content</small>
<? } ?>
