<TEMPLATE NAME="HEADER">

<table class="main" >
    <thead>
	<tr>
		<th>
			Actions<br />
		</th>
		<th>
		    Name<br />
			<? formTextbox('filter_configuration:name_ct',10,255); ?>
		</th>
		<th>
			Description<br />
			<? formTextbox('filter_configuration:description_ct',10,255); ?>
		</th>
		<th>
		    Value<br />
			<? formTextbox('filter_configuration:value_ct',10,255); ?>
		</th>
	</tr>
    </thead>
    <tbody>
</TEMPLATE>

<TEMPLATE NAME="LIST">
<tr>
<td>
	<a href="admin.php?id=@@id@@">Edit</a>
</td>
<td>@@name@@</td>
<td>@@description@@</td>
<td>@@value@@</td>
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
