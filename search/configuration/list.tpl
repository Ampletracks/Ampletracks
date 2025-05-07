<TEMPLATE NAME="HEADER">

<table class="main data-table" >
    <thead>
	<tr>
		<th class="actions">
			Actions<br />
		</th>
		<th class="name">
		    Name<br />
			<? formTextbox('filter_configuration:name_ct',10,255); ?>
		</th>
		<th class="description">
			Description<br />
			<? formTextbox('filter_configuration:description_ct',10,255); ?>
		</th>
		<th class="value">
		    Value<br />
			<? formTextbox('filter_configuration:value_ct',10,255); ?>
		</th>
	</tr>
    </thead>
    <tbody>
</TEMPLATE>

<TEMPLATE NAME="LIST">
<tr>
<td class="actions">
	<a href="admin.php?id=@@id@@">Edit</a>
</td>
<td class="name">@@name@@</td>
<td class="description">@@description@@</td>
<td class="value">
    <? if ($rowData['isSecret'] || preg_match('/password$/i',$rowData['name'])) { ?>
        <i>hidden</i>
    <? } else { ?>
        @@value@@
    <? } ?>
</td>
</tr>
</TEMPLATE>

<TEMPLATE NAME="EMPTY">
<tr>
	<td align="center" colspan="999">
	No rows found
	</td>
</tr>
</TEMPLATE>

<TEMPLATE NAME="FOOTER">
</tbody>
</table>
</TEMPLATE>
