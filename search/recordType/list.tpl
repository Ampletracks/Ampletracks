<TEMPLATE NAME="HEADER">
<table class="main">
	<thead>
	<tr>
		<th class="actions">Actions</th>
		<th class="logo">Logo</th>
		<th class="name">Name</th>
        <th class="primaryDataField">Primary Data Field</th>
		<th class="numRecords">No. Records</th>
	</tr>
	</thead>
	<tbody>
</TEMPLATE>

<TEMPLATE NAME="LIST">
<?
global $DB;

$labelImage = new recordTypeLabelImage( $rowData['id'] );
?>
	<tr>
		<td class="actions">
			<a href="admin.php?id=@@id@@">Edit</a>
            <a href="../record/admin.php?record_typeId=@@id@@">Create New Record</a>
            <a href="../dataField/list.php?recordTypeFilterChange=@@id@@">Data Fields</a>
            <a href="importExport.php?id=@@id@@&mode=export">Export</a>
			<a deletePrompt="Are you sure you want to delete the following record type?
			<div class=&quot;deleteWarningBox&quot;>@@name@@</div>" href="admin.php?mode=delete&id=@@id@@">Delete</a>
		</td>
		<td><? $labelImage->display() ?></td>
		<td>@@name@@</td>
        <? if (!strlen($rowData['primaryDataField'])) { ?>
            <td class="error">
                <div class="error"><?=cms('Record Type List: Primary data field not defined',1,'The primary data field is not defined or has been deleted. This is a serious issue and will cause all sorts of problems.')?></div>
            </td>
        <? } else { ?>
            <td>
                @@primaryDataField@@
            </td>
        <? } ?>
		<td>
            <a href="../record/list.php?recordTypeFilterChange=@@id@@"><?=$DB->getValue('SELECT COUNT(*) FROM record WHERE record.lastSavedAt AND !record.deletedAt AND record.typeId=?',$rowData['id'])?></a>
        </td>
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
</TEMPLATE>
