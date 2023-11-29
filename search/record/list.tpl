<TEMPLATE NAME="COMMON">
    <?
    global $fieldsToDisplay,$builtInFieldsToDisplay,$entityName,$filters;

    include_once(LIB_DIR.'/shareLinkTools.php');
    shareLinkJavascript();
    ?>
</TEMPLATE>

<TEMPLATE NAME="HEADER">
    <table class="main data-table record">
        <thead>
            <tr>
                <th class="actions" >Actions</th>
                <? if (isset($builtInFieldsToDisplay['id'])) { ?>
                    <th class="filter recordId">
                        <?=cms('Record List: ID column header',0,'ID')?><br />
                        <? formTextbox('filter_record:id_eq',5,10);?>
                    </th>
                <? } ?>
                <? if (isset($builtInFieldsToDisplay['project'])) { ?>
                    <th class="filter project">
                        <?=cms('Record List: Project column header',0,'Project')?><br />
                        <? global $projectFilter; $projectFilter->display(); formPlaceholder('filter_record:projectId_eq'); ?>
                    </th>
                <? } ?>
                <? if (isset($builtInFieldsToDisplay['labelId'])) { ?>
                    <th class="filter labelId">
                        <?=cms('Record List: Label ID column header',0,'Label ID')?><br />
                        <? formTextbox('filter_label:id_eq',5,10);?>
                    </th>
                <? } ?>
                <? if (isset($builtInFieldsToDisplay['path'])) { ?>
                    <th class="filter path" >
                        <?=cms('Record List: Path column header',0,'Path')?><br />
                        <? formTextbox('filter_record:path_ct',5,10);?>
                    </th>
                <? } ?>
                <? if (isset($builtInFieldsToDisplay['relationships'])) { ?>
                    <th class="relationships">
                        <?=cms('Record List: Relationships',0,'Relationships')?>
                    </th>
                <? } ?>
                <? foreach( $fieldsToDisplay as $fieldId=>$fieldData ) { ?>
                    <th class="filter dataField <?=htmlspecialchars(toCamelCase($filters[$fieldId]->getType()))?>"><?=htmlspecialchars($fieldData['name'])?>
                    <? if (strlen($fieldData['unit'])) echo '<span class="unit">'.htmlspecialchars($fieldData['unit']).'</span>'; ?>
                    <br />
                    <? $filters[$fieldId]->displayFilter();?>
                    </th>
                <? } ?>
            </tr>
        </thead>
        <tbody>
</TEMPLATE>

<TEMPLATE NAME="LIST">
    <?
	global $permissionsEntity;
    $nameField ='answer_'.$rowData['primaryDataFieldId'];
    if(isset($rowData[$nameField])) {
        $name = htmlspecialchars($rowData[$nameField]);
    } else {
        $name = '<i>no name</i>';
    }
    ?>
    <tr>
        <?
			ob_start();
			if (canDo('view',$rowData['id'],$permissionsEntity)) {
				?><a href="admin.php?id=@@id@@">View</a><?
			}
			if (canDo('edit',$rowData['id'],$permissionsEntity)) {
				?><a href="admin.php?id=@@id@@#edit">Edit</a><?
			}
			if (canDo('create',$permissionsEntity)) {
				?><a href="admin.php?parentId=@@id@@">Add Child</a><?
			}
			if (canDo('create',$permissionsEntity)) {
			    ?><a href="admin.php?mode=clone&id=@@id@@">Clone</a><?
			}
			if (canDo('delete',$rowData['id'],$permissionsEntity)) { ?>
				<a deletePrompt="Are you sure you want to delete the following <?=htmlspecialchars($entityName)?>?
				<div class=&quot;deleteWarningBox&quot;><?=$name?></div>" href="admin.php?mode=delete&id=@@id@@">Delete</a>
			<? }
            if (canDo('edit',$rowData['id'],$permissionsEntity)) {
                ?><a href="/label/print.php?recordId=@@id@@">Generate new label</a><?
            }
			if (canDo('view',$rowData['id'],$permissionsEntity)) {
				?><a class="getShareLink" href="admin.php?id=@@id@@&mode=getShareLink">Get Share Link</a><?
			}
			$content = ob_get_clean();
			echo substr_count($content,'href')>1 ? '<td class="actions">'.$content.'</td>' : '<td class="noActionsMenu">'.$content.'</td>';
		?>

        <? if (isset($builtInFieldsToDisplay['id'])) { ?>
            <td class="id">
                <a href="admin.php?id=@@id@@">@@id@@</a>
            </td>
        <? } ?>
        <? if (isset($builtInFieldsToDisplay['project'])) { ?>
            <td class="project">
                @@project@@
            </td>
        <? } ?>
        <? if (isset($builtInFieldsToDisplay['labelId'])) { ?>
            <td class="labelId">
                @@labelId@@
            </td>
        <? } ?>
        <? if (isset($builtInFieldsToDisplay['path'])) { ?>
            <td class="rootId">
                /<?= str_replace(',','/',$rowData['path']); ?>
            </td>
        <? } ?>
        <? if (isset($builtInFieldsToDisplay['relationships'])) { ?>
            <td class="relationships">
                <?
                global $DB;
                $relationships = $DB->getHash('
                    SELECT CONCAT(description," ",recordType.name) AS description, COUNT(*)
                    FROM relationship
                    INNER JOIN relationshipLink ON relationshipLink.id=relationship.relationshipLinkId
                    INNER JOIN recordType ON recordType.id=relationshipLink.toRecordTypeId
                    WHERE relationship.fromRecordId = ?
                    GROUP BY relationshipLink.id
                ',$rowData['id']);
                if (count($relationships)) {
                    echo '<table>';
                    foreach( $relationships as $description=>$count ){
                        echo '<tr><td>'.htmlspecialchars($description).'</td><td>x'.$count.'</td></tr>';
                    }
                    echo '</table>';
                }
                ?>
            </td>
        <? } ?>
        <? foreach( $fieldsToDisplay as $fieldId => $fieldData ) {
            echo '<td class="dataField '.htmlspecialchars(toCamelCase($fieldData['type'])).'">';
            dataField::displayForList($fieldData['typeId'], $rowData['answer_'.$fieldId], $rowData['id'], $fieldId);
            echo '</td>';
        } ?>
    </tr>
</TEMPLATE>

<TEMPLATE NAME="EMPTY">
    <tr class="emptyList">
        <td colspan="999">
            <?=cms('No matching records found')?>
        </td>
    </tr>
</TEMPLATE>

<TEMPLATE NAME="FOOTER">
        </tbody>
    </table>
</TEMPLATE>
