<TEMPLATE NAME="HEADER">
<table class="main data-table">
	<thead>
	<tr>
		<th class="actions"><?=cms('Data field list: Actions',0,'Actions');?></th>
		<th class="name"><?=cms('Data field list: Name',0,'Name');?></th>
		<th class="type"><?=cms('Data field list: Type',0,'Type');?></th>
		<th class="inheritance"><?=cms('Data field list: Inheritance',0,'Inheritance');?></th>
		<th class="displayOnList"><?=cms('Data field list: Display on list',0,'Display on list');?></th>
		<th class="displayToPublic"><?=cms('Data field list: Display to public',0,'Display to public');?></th>
	</tr>
	</thead>
	<tbody>
</TEMPLATE>

<TEMPLATE NAME="LIST">
	<?
		global $exampleDataFieldId;
		$exampleDataFieldId = $rowData['id'];
	?>
	<tr data-id="@@id@@">
		<?
			ob_start();
			if (canDo('edit',$rowData['id'])) {
				?><a href="admin.php?id=@@id@@">Edit</a><?
			} else if (canDo('view',$rowData['id'])) {
				?><a href="admin.php?id=@@id@@">View</a><?
			}
			if (canDo('delete',$rowData['id'])) { ?>
				<a deletePrompt="Are you sure you want to delete the following data field?
				<div class=&quot;deleteWarningBox&quot;>@@name@@</div>" href="admin.php?mode=delete&id=@@id@@">Delete</a>
			<? }
			$content = ob_get_clean();
			echo substr_count($content,'href')>1 ? '<td class="actions">'.$content.'</td>' : '<td class="noActionsMenu">'.$content.'</td>';
		?>
		<td class="name">@@name@@</td>
		<td class="type">@@type@@</td>
		<td class="type">@@inheritance@@</td>
		<td class="displayOnList">
			<?
			if (canDo('edit',$rowData['id'])) {
				if ($rowData['canDisplayOnList']) {
					formCheckbox('','1',$rowData['displayOnList'],'class="displayOnList"');
				}
			} else {
				if ($rowData['canDisplayOnList']) echo $rowData['displayOnList']?'yes':'no';
			}
			?>
		</td>
		<td class="displayToPublic"><?=$rowData['displayToPublic']?'Yes':'No'?></td>
	</tr>
</TEMPLATE>

<TEMPLATE NAME="EMPTY">
	<tr class="emptyList">
		<td colspan="999">
			No record types defined yet
		</td>
	</tr>
</TEMPLATE>

<TEMPLATE NAME="FOOTER">
	</tbody>
</table>
<?
global $exampleDataFieldId;
if (canDo('edit',$exampleDataFieldId)) { ?>
	<p class="info">Drag and drop rows to change question order.</p>
<? } ?>
</TEMPLATE>
