<?
    $dataFields = DataField::getAllTypes();
    $typesWithValue = array();
    foreach( $dataFields as $typeId=>$dataField ) {
        if ($dataField->hasValue()) $typesWithValue[]=$typeId;
    }
    $onlyShowWhenFieldHasValue = sprintf('<div dependsOn="dataField_typeId in %s">',implode('|',$typesWithValue));
?>
<p>
    Belongs to record type: <? wsp('recordType_name') ?>
</p>
<? if (inputError()) { ?>
    <div class="error">
        There was an error with the data you submitted. Please check the fields below.
    </div>
<? } ?>
<h2>Basics</h2>
<div class="questionAndAnswer">
	<div class="question">
		<?=cms('Data Field: Type',0,'Type')?>:
	</div>
	<div class="answer">
		<? $GLOBALS['dataFieldTypeSelect']->display(); formPlaceHolder('dataField_typeId'); ?>
        <?= $dataFieldHelp ?>
	</div>
</div>

<div class="questionAndAnswer">
	<div class="question">
		<?=cms('Data Field: Position',0,'Position')?>:
	</div>
	<div class="answer">
		<? $GLOBALS['positionSelect']->display(); formPlaceHolder('dataField_orderId'); ?>
	</div>
</div>

<div class="questionAndAnswer">
	<div class="question">
		<?=cms('Data Field: Name',0,'Name')?>:
	</div>
	<div class="answer">
		<? formTextBox('dataField_name',50,200); ?>
	</div>
</div>

<?=$onlyShowWhenFieldHasValue?>
    <div class="questionAndAnswer">
        <div class="question">
            <?=cms('Data Field: Export name',0,'Export name')?>:
        </div>
        <div class="answer">
            <? formTextBox('dataField_exportName',50,200); ?>
            <div class="info">
                This is name used when exporting records to JSON. If this is empty then this field will not be included in the JSON export.
                <a confirm="#exportNameHelp">Advanced usage...</a>
                <div id="exportNameHelp" style="display: none"><? include(VIEWS_DIR.'/dataField/exportNameHelp.html'); ?></div>
                
            </div>
        </div>
    </div>
    <div class="questionAndAnswer">
        <div class="question">
            <?=cms('Data Field: API name',0,'API name')?>:
        </div>
        <div class="answer">
            <? formTextBox('dataField_apiName',50,200); ?>
            <div class="info">
                This is name used when exporting records via the API. If this is empty then this field will not be included when records are accessed via the API.
            </div>
        </div>
    </div>
</div>

<div class="questionAndAnswer">
	<div class="question" dependsOn="dataField_typeId lt 3">
		<?=cms('Data Field: Title',0,'Title')?>:
	</div>
	<div class="question" dependsOn="dataField_typeId gt 2">
		<?=cms('Data Field: Question',0,'Question')?>:
	</div>
	<div class="answer">
		<? formTextBox('dataField_question',50,200); ?>
	</div>
</div>

<h2>Options</h2>

<div class="questionAndAnswer">
	<div class="question">
		<?=cms('Data Field: Display to public',0,'Display to the public')?>:
	</div>
	<div class="answer">
		<? formYesNo('dataField_displayToPublic',false,true) ?>
	</div>
</div>
<? if (getConfigBoolean('Enable public search')) { ?>
    <div class="questionAndAnswer" dependsOn="dataField_displayToPublic eq 1">
        <div class="question">
            <?=cms('Data Field: Field name on public view',0,'Field name on public view')?>:
        </div>
        <div class="answer">
            <? formTextBox('dataField_publicName',50,200) ?>
            <div class="info">
                If this is empty then the "question" defined above will be displayed as the field label in the public view.
            </div>
        </div>
    </div>
    <div class="questionAndAnswer" dependsOn="dataField_displayToPublic eq 1">
        <div class="question">
            <?=cms('Data Field: Use for general search',0,'Use for general search')?>:
        </div>
        <div class="answer">
            <? formYesNo('dataField_useForGeneralSearch',false,true) ?>
        </div>
    </div>
    <div class="questionAndAnswer" dependsOn="dataField_displayToPublic eq 1">
        <div class="question">
            <?=cms('Data Field: Allow for advanced search',0,'Allow for advanced search')?>:
        </div>
        <div class="answer">
            <? formYesNo('dataField_useForAdvancedSearch',false,true) ?>
        </div>
    </div>
<? } ?>

<?=$onlyShowWhenFieldHasValue; ?>
    <div class="questionAndAnswer">
        <div class="question">
            <?=cms('Data Field: Required',0,'Required')?>:
        </div>
        <div class="answer">
            <? formYesNo('dataField_optional',false,false,true); ?>
        </div>
    </div>
    <div class="questionAndAnswer" dependsOn="dataField_typeId !in 18">
        <div class="question">
            <?=cms('Data Field: Allow user default',0,'Allow user default')?>:
        </div>
        <div class="answer">
            <? formYesNo('dataField_allowUserDefault',false,false,false); ?>
        </div>
    </div>
    <div class="questionAndAnswer" dependsOn="dataField_typeId !in 18">
        <div class="question">
            <?=cms('Data Field: Inheritance',0,'Inheritance')?>:
        </div>
        <div class="answer">
            <? $GLOBALS['inheritanceSelect']->display(); formPlaceholder('dataField_inheritance') ?>
        </div>
    </div>
    <div class="questionAndAnswer" dependsOn="dataField_typeId !in 11|12|18">
        <div class="question">
            <?=cms('Data Field: Save invalid answers',0,'Save invalid answers')?>:
        </div>
        <div class="answer">
            <? $GLOBALS['saveInvalidAnswersSelect']->display(); formPlaceholder('dataField_saveInvalidAnswers') ?>
        </div>
    </div>
    <div class="questionAndAnswer" dependsOn="dataField_typeId !in 5|7|8|9|10|11|12|16|18">
        <div class="question">
            <?=cms('Data Field: Unit',0,'Unit')?>:
        </div>
        <div class="answer">
            <? formTextbox('dataField_unit',10,250) ?>
            <div class="note"><?=cms('Data Field: Unit is optional message',1,'Optional - leave empty if there are no units for this field'); ?></div>
        </div>
    </div>
</div>
<?
    foreach( $dataFields as $typeId=>$dataField ) {
        printf('<div dependsOn="dataField_typeId eq %d">',$typeId);
        // If the data field needs to know the record type ID then tell it
        // e.g. for graph fields which need to present a dropdown where the user can select the upload field the graph relates to
		if (method_exists($dataField, 'setRecordTypeId')) $dataField->setRecordTypeId(ws('dataField_recordTypeId'));
        $dataField->displayDefinitionForm('fieldParameters_');
        echo '</div>';
    }


    $dependeeFieldList->display();
?>


<script>
    $(function(){ $('#dataEntryForm').addClass('renameInvisibleFieldsOnSubmit'); });
</script>
