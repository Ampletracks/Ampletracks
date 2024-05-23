<TEMPLATE NAME="HEADER">
<table class="main data-table" >
	<thead>
        <tr>
            <th class="actions">
                <?= cms('Show') ?> <? formOptionbox('limit',array(100=>100,200=>200,300=>300,500=>500,1000=>1000),'class="numRowsSelect"') ?>
            </th>
            <th class="name">
                <?=cms('User Name')?><br />
                <? formTextbox('filter_user:firstName_ct|filter_user:lastName_ct',10,250); ?>
            </th>
            <th class="email">
                <?=cms('User Email')?><br />
                <? formTextbox('filter_user:email_ct',10,250); ?>
            </th>
        </tr>
	</thead>
</form>
    <tbody>
</TEMPLATE>

<TEMPLATE NAME="LIST">
<tr>
    <?
        ob_start();
        if (canDo('edit',$rowData['id'])) {
            ?><a href="admin.php?id=@@id@@">Edit</a><?
        } else if (canDo('view',$rowData['id'])) {
            ?><a href="admin.php?id=@@id@@">View</a><?
        }
        if (canDo('list',0,'actionLog')) {
            ?><a href="../actionLog/list.php?filter_actionLog:userType_eq=user&filter_actionLog:userId_eq=@@id@@">Activity log</a><?
        }
        if (!$rowData['deletedAt'] && canDo('delete',$rowData['id'])) { ?>
            <a deletePrompt="<?=cms('Are you sure you want to delete the following user?',0)?>

@@email@@" href="admin.php?mode=delete&id=@@id@@"><?=cms('Delete',0)?></a>
        <? }
        $content = ob_get_clean();
        echo substr_count($content,'href')>1 ? '<td class="actions">'.$content.'</td>' : '<td class="noActionsMenu">'.$content.'</td>';
    ?>
    <td>@@firstName@@ @@lastName@@</td>
    <td><a href="mailto:@@email@@">@@email@@</td>
</tr>
</TEMPLATE>

<TEMPLATE NAME="EMPTY">
<tr>
	<td align="center" colspan="999">
	No matching users found
	</td>
</tr>
</TEMPLATE>

<TEMPLATE NAME="FOOTER">
</tbody>
</table>
</TEMPLATE>
