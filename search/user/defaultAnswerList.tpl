<TEMPLATE NAME="HEADER">
    <div class="info">
        You can provide answers here for data fields which will usually have the same value. These will be used as the default value whenever the question on the form matches the question provided here.<br />
        <ul>
            <li>The order of the defaults below is important - the first match will be used. You can reorder the defaults below by dragging and dropping them.</li>
            <li>All question comparisons are case-insensitive</li>
            <li>Regular expressions should be <a href="https://perldoc.perl.org/perlre" targer="_blank">Perl compatible</a></li>
        </ul>
    </div>
<table class="data-table userDefaultsList">
	<thead>
	<tr>
		<th class="actions">Actions</th>
		<th class="question">Question</th>
		<th class="question">Match type</th>
		<th class="answer">Answer</th>
    </tr>
	</thead>
	<tbody>
</TEMPLATE>

<TEMPLATE NAME="LIST">
<?
global $DB, $projects;
?>
<tr data-id="@@id@@">
		<td class="actions">
			<a deletePrompt="Are you sure you want to delete this default answer" href="admin.php?mode=deleteDefaultAnswer&id=@@userId@@&userDefaultAnswer_id=@@id@@">Delete</a>
		</td>
		<td>@@question@@</td>
        <td><?
            global $defaultAnswerMatchTypeLookup;
            echo $defaultAnswerMatchTypeLookup[$rowData['matchType']];
        ?></td>
        <td>@@answer@@</td>
	</tr>
</TEMPLATE>

<TEMPLATE NAME="EMPTY">
	<tr class="emptyList">
		<td colspan="999">
			No defaults defined yet
		</td>
	</tr>
</TEMPLATE>

<TEMPLATE NAME="FOOTER">
	<tr>
		<td></td>
        <td><?
			formTypeToSearch([
                'name'              => 'userDefaultAnswer_question',
                'url'               => '?mode=questionNameSearch',
                'size'              => 20,
            ]);
		?>
		</td>
        <td><?
			global $defaultAnswerMatchType;
			$defaultAnswerMatchType->display();
		?>
		<td><? formTextbox('userDefaultAnswer_answer',20,250)?></td>
	</tr>
	</tbody>
</table>
</TEMPLATE>
