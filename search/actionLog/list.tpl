<TEMPLATE NAME="HEADER">
<?
	$entityFilter = new formOptionbox('filter_actionLog:entity_eq', array('-- All --'=>''));
	$entityFilter->addLookup("SELECT DISTINCT entity,entity FROM actionLog");

    # use _lk (like) test here as that is the only one that supports checking for emptiness.
	$userTypeFilter = new formOptionbox('filter_actionLog:userType_lk', array(
		'-- Either --'	=>'%',
		'User'		=>'user',
		'Admin'			=>'adminUser',
		'System'			=>'',
	));

	formPlaceholder('filter_actionLog:userType_lk');
	formPlaceholder('filter_actionLog:userId_eq');
?>
<table class="main data-table">
<thead>
	<tr>
		<th>
			Actions<br />
			<input class="filterButton" type="submit" value="Filter" />
		</th>
		<th>
			<?=cms('History List: Date & time column header',0,'Date & time')?>
		</th>
		<th>
			<?=cms('History List: Username column header',0,'User name (ID)')?><br />
			<?formTextbox('filter_user:firstName_ct|filter_adminUser:firstName_ct|filter_user:lastName_ct|filter_adminUser:lastName_ct',10,250)?>
			<?formTextbox('filter_actionLog:userId_eq',1,6)?>
		</th>
		<th>
			<?=cms('History List: Entity column header',0,'Entity')?><br />
			<? $entityFilter->display() ?>
		</th>
		<th>
			<?=cms('History List: Entity ID column header',0,'Entity ID')?><br />
			<?formTextbox('filter_actionLog:entityId_eq',4)?>
		</th>
		<th>
			<?=cms('History List: Activity column header',0,'Activity')?><br />
			<?formTextbox('filter_actionLog:message_ct',10)?>
		</th>
	</tr>
</thead>
</TEMPLATE>

<TEMPLATE NAME="LIST">
<tr>
<td>
	<? if ($rowData['entity'] && !strpos('|caller|scribe|checker|',$rowData['entity'])) {
		if ($rowData['entityId']) { ?>
			<a href="../@@entity@@/admin.php?id=@@entityId@@">view</a>
		<? }
	} ?>
</td>
<td>@@eventDateTime@@</td>
<td>
    <? if ($rowData['userId']==0) { ?>
        System
    <? } else { ?>
        <a href="../user/admin.php?id=@@userId@@">@@userName@@</a> (@@userId@@)
    <? } ?>
</td>
<td><?= htmlspecialchars(ucFirst(fromCamelCase($rowData['entity'])))?></td>
<td>@@entityId@@</td>
<td><?= nl2br(htmlspecialchars($rowData['message']))?></td>
</tr>
</TEMPLATE>

<TEMPLATE NAME="EMPTY">
<tr>
	<td align="center" colspan="999">
	No history data found
	</td>
</tr>
</TEMPLATE>

<TEMPLATE NAME="FOOTER">
</table>
</TEMPLATE>
