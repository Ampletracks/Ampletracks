<TEMPLATE NAME="COMMON">
<?
global $relationshipData;

?>
</TEMPLATE>

<TEMPLATE NAME="HEADER">
</TEMPLATE>

<TEMPLATE NAME="LIST">
<?
    $record = [
        'id' => $rowData['recordId'],
        'type' => $rowData['recordType'],
        'path' => $rowData['path'],
        'recordTypeId' => $rowData['recordTypeId'],
        'labelIds' => array_filter(explode(',',$rowData['labelIds'])),
        'project' => $rowData['project'],
        'inheritedFields' => [],
        'relationships' => [],
        'data' => [],
    ];
    foreach( $rowData['bundle'] as $fieldData ) {
        // Skip NULL values - this indicates the question hasn't been answered
        if (is_null($fieldData['data'])) continue;
        if ($fieldData['inherited']) $record['inheritedFields'][]=$fieldData['exportName'];
        $record['data'][$fieldData['exportName']] = dataField::packForJSON($fieldData['fieldTypeId'], $fieldData['data']);
    }
    if (isset($relationshipData[$rowData['recordId']])) {
        $record['relationships'] = $relationshipData[$rowData['recordId']];
    }

	if ($row>0) echo ",\n";
    echo json_encode($record,JSON_PRETTY_PRINT);
    /*
	foreach( $fieldsToDisplay as $fieldId=>$fieldData ) {
		if (!isset($recordData[$fieldId])) continue;
        if (empty($fieldData['exportName'])) continue;
		$output['data'][$fieldData['exportName']]=dataField::packForJSON($fieldData['typeId'], $recordData[$fieldId]);
	}
	echo json_encode($output);
    */
?>
</TEMPLATE>

<TEMPLATE NAME="EMPTY">
</TEMPLATE>

<TEMPLATE NAME="FOOTER">
</TEMPLATE>
