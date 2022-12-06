<div class="questionAndAnswer">
	<div class="question">
		<?=cms('Project: Name',0,'Name')?>:
	</div>
	<div class="answer">
		<? formTextBox('project_name',50,200); ?>
        <? inputError('project_name'); ?>
	</div>
</div>
