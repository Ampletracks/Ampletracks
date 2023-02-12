<TEMPLATE NAME="COMMON">
<?
	global $ENTITY,$fieldsToDisplay,$fieldIds;
?>
</TEMPLATE>

<TEMPLATE NAME="HEADER">
<?
	header('Content-Type: application/json');
    #header('Content-Disposition: attachment; filename="'.$ENTITY.'Data.json"');
?>
[
</TEMPLATE>

<TEMPLATE NAME="LIST">
<?
	global $DB;
	$output = [
		'id'		=> $rowData['id'],
        'recordTypeId'=> $rowData['recordTypeId'],
        'recordType'=> $rowData['recordType'],
		'parentId'  => $rowData['parentId'],
		'path'  	=> substr($rowData['path'],0,-1),
        'data'=> [],
	];
	$recordData = $DB->getHash('SELECT dataFieldId, data FROM recordData WHERE recordId=? AND !hidden AND dataFieldId IN (?)',$rowData['id'],$fieldIds);
	foreach( $fieldsToDisplay as $fieldId=>$fieldData ) {
		if (!isset($recordData[$fieldId])) continue;
        if (empty($fieldData['exportName'])) continue;
		$output['data'][$fieldData['exportName']]=dataField::packForJSON($fieldData['typeId'], $recordData[$fieldId]);
	}
	if ($row>0) echo ",\n";
	echo json_encode($output);
?>
</TEMPLATE>

<TEMPLATE NAME="EMPTY">
</TEMPLATE>

<TEMPLATE NAME="FOOTER">
<? echo "\n]";?>
</TEMPLATE>
