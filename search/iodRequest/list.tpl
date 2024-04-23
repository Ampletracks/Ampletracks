<TEMPLATE NAME="HEADER">
<table class="main">
	<thead>
	<tr>
		<th class="actions">Actions</th>
		<th class="createdAt">Creation Date</th>
        <th class="status">Termination Date</th>
		<th class="email">Created For</th>
        <th class="status">Status</th>
        <th class="status">URL</th>
	</tr>
	</thead>
	<tbody>
</TEMPLATE>

<TEMPLATE NAME="LIST">
	<tr>
		<td class="actions">
			<a href="admin.php?id=@@id@@">Edit</a>
			<a deletePrompt="Are you sure you want to delete the IOD request for this user?
			<div class=&quot;deleteWarningBox&quot;>@@email@@</div>" href="admin.php?mode=delete&id=@@id@@">Delete</a>
		</td>
		<td>
            @@createdAtDateTime@@
        </td>
		<td>
            @@deleteAtDateTime@@
        </td>
		<td>
            @@email@@
        </td>
		<td>
            @@status@@
        </td>
		<td>
            <a target="_blank" href="https://@@subDomain@@.<?=IOD_DOMAIN?>/">@@subDomain@@.<?=IOD_DOMAIN?></a>
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
