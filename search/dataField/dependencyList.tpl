<TEMPLATE NAME="HEADER">
<? global $dependencyCombinatorSelect; ?>
	<h2>Dependencies</h2>
	<div class="questionAndAnswer">
		<div class="question">
			<?=cms('Data Field: Dependency combinator',0,'Dependency combinator')?>:
		</div>
		<div class="answer">
			<? $dependencyCombinatorSelect->display() ?>
		</div>
	</div>
</TEMPLATE>

<TEMPLATE NAME="LIST">
<?
	global $dependeeFieldSelect, $dependencyTestSelect, $nonValueDependencyTestSelect, $nonValueQuestions;
	
	if (!$rowData['id'] && !isset($dependeeFieldSelect->options[0])) {
		$dependeeFieldSelect->options = array_merge(array(''=>0), $dependeeFieldSelect->options);
		$dependencyTestSelect->options = array_filter($dependencyTestSelect->options);
		$nonValueDependencyTestSelect->options = array_filter($nonValueDependencyTestSelect->options);
	}

	$rowIdx = '['.(int)$row.']';
	
	if ($row) {
		$previousFieldDependency = 'dependsOn="dependeeDataFieldId['.($row-1).'] gt 0"';
	} else {
		$previousFieldDependency = 'dependsOn="dataField_dependencyCombinator !em"';
	}
?>
<div class="questionAndAnswer" <?=$previousFieldDependency?> >
    <div class="question">
        <?=cms('Data Field: Depends on field',0,'Depends on')?>:
    </div>
    <div class="answer">
		<? formHidden('dependencyId'.$rowIdx,$rowData['id']); ?>
        <? $dependeeFieldSelect->redisplay('dependeeDataFieldId'.$rowIdx,'',$rowData['dependeeDataFieldId']); ?>
        <? $dependencyTestSelect->redisplay(
			'dependencyTest'.$rowIdx,
			'dependsOn1="dependeeDataFieldId'.$rowIdx.' !in '.$nonValueQuestions.'" '.
				'dependsOn2="dependeeDataFieldId'.$rowIdx.' gt 0" '.
				'dependencyCombinator="AND" ',
			$rowData['test']
		); ?>
        <? $nonValueDependencyTestSelect->redisplay(
			'nonValueDependencyTest'.$rowIdx,
			'dependsOn1="dependeeDataFieldId'.$rowIdx.' in '.$nonValueQuestions.'" ',
			$rowData['test']
		); ?>
        <? formTextbox(
			'dependencyTestValue'.$rowIdx,30,4000,
			$rowData['testValue'],
			'dependsOn1="dependencyTest'.$rowIdx.' !cy !em|em|vi|!vi" '.
				'dependsOn2="nonValueDependencyTest'.$rowIdx.' !cy !em|em|vi|!vi" '.
				'dependencyCombinator="OR" '
		); ?>
        <? if ($rowData['id']) { ?>
			<div class="info" dependencycombinator="AND" dependsOn1="dependeeDataFieldId<?=$rowIdx?> gt 0" dependsOn2="dependencyTest<?=$rowIdx?> !em">
				Set the test field to "Delete dependency" to remove this dependency
			</div>
        <? } ?>
        <div class="info" dependsOn="dependencyTest<?=$rowIdx?> !cy !em|em|vi|!vi">
			When dependency relates to a date field enter the date as dd/mm/yyyy or d/m/yyyy<br />
			When dependency relates to a duration field enter the duration in minutes e.g. for 2 hours, 30 mins use 150<br />
			When using "between" test separate the two values with a comma (can be two numbers or two dates)<br />
			When comparing against a "select" type question with multiple answers use...
			<ul>
				<li>equals "<i>value</i>" - to test for exactly one option selected</li>
				<li>equals "|<i>value1</i>|<i>value2</i>|" - to test for an exact combination of options (no more, no less)</li>
				<li>contains all "<i>value1</i>|<i>value2</i>|<i>value3</i>" - to test for all these options (or more)</li>
				<li>contains any "<i>value1</i>|<i>value2</i>|<i>value3</i>" - to test for any one or more of these options</li>
			</ul>
        </div>

    </div>
</div>
</TEMPLATE>
