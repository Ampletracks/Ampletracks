<TEMPLATE NAME="HEADER">
<table class="main data-table">
	<thead>
	<tr>
		<th class="actions" >Actions</th>
		<th class="name" >Name</th>
	</tr>
	</thead>
	<tbody>
</TEMPLATE>

<TEMPLATE NAME="LIST">
	<tr>
		<td class="actions">
			<a href="admin.php?id=@@id@@">Edit</a>
			<a deletePrompt="Are you sure you want to delete the role: @@name@@" href="admin.php?id=@@id@@&mode=delete">Delete</a>
		</td>
		<td>@@name@@</td>
	</tr>
</TEMPLATE>

<TEMPLATE NAME="FOOTER">
	</tbody>
</table>
</TEMPLATE>
