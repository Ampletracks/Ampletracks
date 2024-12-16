<TEMPLATE NAME="HEADER">
<table class="main data-table">
	<thead>
	<tr>
		<th>
			Actions
		</th>
		<th>
		    Label<br />
			<? formTextbox('filter_cms:label_ct',30,255); ?>
		</th>
		<th>
			Content<br />
			<? formTextbox('filter_cms:content_ct',30,255); ?>
		</th>
		<th>
		    WYSIWYG
		</th>
	</tr>
	</thead>
	<tbody>
</TEMPLATE>

<TEMPLATE NAME="LIST">
<tr>
<td class="actions">
	<a href="admin.php?mode=edit&id=@@id@@" />Edit</a>
	<a deletePrompt="Are you sure you want to delete this cms" href="admin.php?mode=delete&amp;id=@@id@@">Delete</a>
</td>
<td>@@label@@</td>
<td><?=$rowData['allowMarkup']?$rowData['content']:htmlspecialchars($rowData['content'])?></td>
<td><?=$rowData['allowMarkup']?'Y':'N'?></td>
</tr>
</TEMPLATE>

<TEMPLATE NAME="EMPTY">
<tr>
	<td align="center" colspan="999">
	<?=cms('No rows found');?>
	</td>
</tr>
</TEMPLATE>

<TEMPLATE NAME="FOOTER">
</tbody>
</table>
</TEMPLATE>
