<TEMPLATE NAME="COMMON">
<?
    static $numDisplayCols;
	static $colNames;
	global $csvSkipColumns;
	if (!is_array($csvSkipColumns)) $csvSkipColumns=array();
?>
</TEMPLATE>

<TEMPLATE NAME="HEADER">
<?
	global $ENTITY;
	$colNames = $rowData;
	header('Content-Type: text/csv');
#	header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="'.$ENTITY.'Data.csv"');

	$idx = 0;
    $numDisplayCols = $numCols;
	while ($idx < $numCols) {
        if ($rowData[$idx]==='Exclude remaining fields from download') {
            $numDisplayCols = $idx;
            break;
        }
		if (in_array($rowData[$idx],$csvSkipColumns)) {
            $idx++;
            continue;
        }
		echo '"'.ucfirst(preg_replace('/"/','',fromCamelCase($rowData[$idx]))).'",';
		$idx++;
	}
	echo "\n";
?>
</TEMPLATE>

<TEMPLATE NAME="LIST">
<?
    if(function_exists('csvRowFilter') && !csvRowFilter($rowData)) return;

	$idx = 0;
	while ($idx < $numDisplayCols) {
        $colName = $colNames[$idx];
		if (in_array($colName,$csvSkipColumns)) {
            $idx++;
            continue;
        }
		if (preg_match('/Date$/',$colName)) {
            if(is_numeric($rowData[$colName])) {
                if ($rowData[$colName]>0) $rowData[$colName] = date('Y-m-d',$rowData[$colName]);
                else $rowData[$colName] = '';
            }
		} else if (preg_match('/Datetime$/',$colName) || (preg_match('/At$/',$colName) && $rowData[$colName]>86400)) {
			if ($rowData[$colName]>0) $rowData[$colName] = date('Y-m-d H:i:s',$rowData[$colName]);
			else $rowData[$colName] = '';
		}
		echo '"'.preg_replace('/"/','',$rowData[$colName]).'",';
		$idx++;
	}
	echo "\n";
?>
</TEMPLATE>

<TEMPLATE NAME="EMPTY">
</TEMPLATE>

<TEMPLATE NAME="FOOTER">
</TEMPLATE>
