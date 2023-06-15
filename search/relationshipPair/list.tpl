<TEMPLATE NAME="HEADER">
<table class="main">
	<thead>
	<tr>
		<th class="actions">Actions</th>
		<th class="fromRecord">From record</th>
		<th class="relationship">Relationship</th>
		<th class="maximum">Maximum</th>
		<th class="toRecords">To Record</th>
        <th class="numInstances">No. Instances</th>
	</tr>
	</thead>
	<tbody>
</TEMPLATE>

<TEMPLATE NAME="LIST">
	<tr>
		<td class="actions" rowspan="2">
			<a href="admin.php?id=@@id@@">Edit</a>
            <a href="admin.php?id=@@id@@&mode=delete">Delete</a>
		</td>
		<td>@@fromRecordType@@</td>
		<td>@@forwardDescription@@</td>
		<td>up to @@forwardMax@@</td>
		<td><?=pluralize($rowData['toRecordType'],$rowData['forwardMax'])?></td>
		<td rowspan="2">
            @@numInstances@@
        </td>
	</tr>
	<tr>
		<td>@@toRecordType@@</td>
		<td>@@backwardDescription@@</td>
		<td>up to @@backwardMax@@</td>
		<td><?=pluralize($rowData['fromRecordType'],$rowData['backwardMax'])?></td>
	</tr>
    <tr class="divider"><td colspan="99"></td></tr>
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
