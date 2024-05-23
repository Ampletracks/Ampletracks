<TEMPLATE NAME="HEADER">
<table class="main data-table">
	<thead>
	<tr>
		<th class="persist">Date</th>
		<th class="persist">User</th>
		<th class="persist">Value</th>
		<th class="persist">Saved?</th>
		<th class="persist">Valid?</th>
		<th class="persist">Hidden?</th>
	</tr>
	</thead>
	<tbody>
</TEMPLATE>

<TEMPLATE NAME="LIST">
<?
global $DB;
?>
	<tr>
		<td>@@savedAtDateTime@@</td>
		<td><a href="mailto://@@userEmail@@">@@userFirstName@@ @@userLastName@@</a></td>
		<td>@@data@@</td>
		<td><?=$rowData['saved']?'Yes':'No'?></td>
		<td><?=$rowData['valid']?'Yes':'No'?></td>
		<td><?=$rowData['hidden']?'Yes':'No'?></td>
	</tr>
</TEMPLATE>

<TEMPLATE NAME="EMPTY">
	<tr class="emptyList">
		<td colspan="999">
			No value supplied for this field yet
	</tr>
</TEMPLATE>

<TEMPLATE NAME="FOOTER">
	</tbody>
</table>
</TEMPLATE>
