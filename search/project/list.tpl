<TEMPLATE NAME="HEADER">
<?
	global $DB,$projects; 

	$projects = $DB->getHash('SELECT CONCAT(name," (ID: ",id,")"),id FROM project WHERE deletedAt=0 ORDER BY name ASC');
?>
<style>
	div.alertable-message div.confirmDelete {
		white-space: initial;
	}
</style>
<table class="main data-table">
	<thead>
	<tr>
		<th class="actions">Actions</th>
		<th class="name">Name</th>
		<th class="numRecords">Number of Records</th>
    </tr>
	</thead>
	<tbody>
</TEMPLATE>

<TEMPLATE NAME="LIST">
<?
global $DB, $projects;
?>
	<tr>
		<td class="actions">
			<a href="admin.php?id=@@id@@">Edit</a>
			<a href="admin.php" confirm="#deleteProject_@@id@@">Delete</a>
            <a href="../record/admin.php?record_typeId=@@id@@">Create</a>
		</td>
		<td>@@name@@</td>
        <td>
            <?
            if ($rowData['numRecords']==0) {
                echo "-";
            } else {
                foreach($rowData['bundle'] as $data) {
                    $canList = canDo('list', 0, $rowData['id'], 'recordTypeId:'.$data['recordTypeId']);
                    if (!$canList) continue;
                    echo '<div class="recordCount">';
                    echo '<span class="count">';
                    echo htmlspecialchars($data['numRecords']);
                    echo '</span><span class="type">';
                    echo '<a href="../record/list.php?filter_record:projectId_eq='.htmlspecialchars($rowData['id']).'&recordTypeFilterChange='.htmlspecialchars($data['recordTypeId']).'">';
                    echo htmlspecialchars($data['recordType']);
                    echo '</a>';
                    echo '</span></div>';
                }
            }
            ?>
        </td>
	</tr>
    <div style="display: none;">
        <div class="confirmDelete" id="deleteProject_@@id@@">
            <div class="title">
                <? if ($rowData['numRecords']>0) { ?>
                    Reassign Records 
                <? } else { ?>
                    Confirm Delete
                <? } ?>
            </div>
            <? formHidden('mode','delete'); ?>
            <? formHidden('id',$rowData['id']); ?>
            <div class="message">
                <? if ($rowData['numRecords']>0) { ?>
                    Please select the project you would like all of this project's existing samples to be assigned to:<br />
                    <?
                        formOptionbox('recipientProjectId',array_diff($projects,[$rowData['id']]));
                    ?>
                <? } else { ?>
                    Are you sure you want to delete the project called "@@name@@"
                <? } ?>
            </div>
        </div>
    </div>
</TEMPLATE>

<TEMPLATE NAME="EMPTY">
	<tr class="emptyList">
		<td colspan="999">
			No projects defined yet
		</td>
	</tr>
</TEMPLATE>

<TEMPLATE NAME="FOOTER">
	</tbody>
</table>
</TEMPLATE>
