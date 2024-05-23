<TEMPLATE NAME="HEADER">
<table class="main data-table">
	<thead>
	<tr>
		<th class="actions">Actions</th>
		<th class="name">Name</th>
        <th class="subject">Subject</th>
        <th class="subject">Default Status</th>
        <th class="subject">Disabled?</th>
	</tr>
	</thead>
	<tbody>
</TEMPLATE>

<TEMPLATE NAME="LIST">
	<tr>
		<td class="actions">
			<a href="admin.php?id=@@id@@">Edit</a>
		</td>
		<td>@@name@@</td>
		<td>@@subject@@</td>
        <td>@@defaultStatus@@</td>
        <td><?=$rowData['disabled']?'Yes':'No'?></td>
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
