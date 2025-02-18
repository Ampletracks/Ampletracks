<TEMPLATE NAME="COMMON">
    <?
    global $fieldsToDisplay,$builtInFieldsToDisplay,$entityName,$filters,$primaryDataFieldId;
    ?>
</TEMPLATE>

<TEMPLATE NAME="HEADER">
    <?
    function displayFieldHeader( $fieldId ) {
        global $filters, $fieldsToDisplay;
        $fieldData = $fieldsToDisplay[$fieldId];
        ?>
            <th class="dataField"><?=htmlspecialchars($fieldData['name'])?>
            <? if (strlen($fieldData['unit'])) echo '<span class="unit">'.htmlspecialchars($fieldData['unit']).'</span>'; ?>
            </th>
        <?
    }

    function displayField( $fieldId, &$rowData, $isLink=false ) {
        global $filters, $fieldsToDisplay;
        $fieldData = $fieldsToDisplay[$fieldId];
        echo '<td class="dataField '.htmlspecialchars(toCamelCase($fieldData['type'])).'">';
        if ($isLink) echo '<a href="admin.php?id='.htmlspecialchars($rowData['id']).'">';
        dataField::displayValueStatic($fieldData['typeId'], $rowData['answer_'.$fieldId], $rowData['id'], $fieldId);
        if ($isLink) echo '</a>';
        echo '</td>';
    }
    ?>
    <table class="main data-table record">
        <thead>
            <tr>
                <th width="1%">&nbsp;</th>
                <?
                foreach( $fieldsToDisplay as $fieldId=>$notUsed ) {
                    if ($fieldId==$primaryDataFieldId && isset($builtInFieldsToDisplay['primaryDataField'])) continue;
                    displayFieldHeader( $fieldId );
                }
                ?>
            </tr>
        </thead>
        <tbody>
</TEMPLATE>

<TEMPLATE NAME="LIST">
    <?
    $nameField ='answer_'.$rowData['primaryDataFieldId'];
    if(isset($rowData[$nameField])) {
        $name = htmlspecialchars($rowData[$nameField]);
    } else {
        $name = '<i>no name</i>';
    }
    ?>
    <tr>
        <td>
            <a href="record/find.php?recordId=<?=signInput($rowData['id'],'PUBLIC_VIEW')?>">View</a>
        </td>

        <? foreach( $fieldsToDisplay as $fieldId => $fieldData ) {
            if ($fieldId==$primaryDataFieldId && isset($builtInFieldsToDisplay['primaryDataField'])) continue;
            displayField( $fieldId,$rowData );
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
