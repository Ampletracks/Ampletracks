<?

$INPUTS = array(
	'export'	=> array(
		'id'	=> 'INT'
	)
);
include( '../../lib/core/startup.php' );

$dataStructureSpec = array(
	'tableName'			=> 'recordType',
	'importProcess'		=> function(&$row){
		$row['name'] = 'Import '.date('d/m/Y H:i:s').' '.$row['name'];
	},
	'parentIdColumn'	=> '',
	'idColumn'			=> 'id',
	'lookup'			=> array( 'primaryDataFieldId'=>'dataField' ),
	'subElements'		=> array(
		'dataField'	=> array(
			'tableName'			=> 'dataField',
			'parentIdColumn'	=> 'recordTypeId',
			'idColumn'			=> 'id',
			'subElements'		=> array(
				'dataFieldDependency' => array(
					'tableName'			=> 'dataFieldDependency',
					'parentIdColumn'	=> 'dependeeDataFieldId',
					'idColumn'			=> 'id',
					'lookup'			=> array( 'dependentDataFieldId'=>'dataField' ),
					'subElements'		=> array(
					)
				)
			)
		),
	)
);

function xmlDump( $dataStructureSpec, $parentId ) {
	global $DB, $lookup;
	static $objectRef = 0;
	
	if (!isset($dataStructureSpec['lookup'])) $dataStructureSpec['lookup']=array();
	
	$tableName = $dataStructureSpec['tableName'];
	$parentIdColumn = $dataStructureSpec['parentIdColumn'];
	$idColumn = $dataStructureSpec['idColumn'];
	$filter = isset($dataStructureSpec['filter'])?$dataStructureSpec['filter']:'';
    if (strlen(trim($filter))) $filter = "AND $filter";

	// This next line is intended only for the top level item where there is no parentId
	if (!$parentIdColumn) $parentIdColumn = $idColumn;

	$DB->returnHash();
	$objectDataQuery = $DB->query("SELECT * FROM $tableName WHERE $parentIdColumn = ? $filter",$parentId);
	while ($objectDataQuery->fetchInto($row)) {
		echo "<$tableName\n";
		$objectRef++;
		echo "\t__objectRef=\"$objectRef\"\n";
		if (!isset($lookup[$tableName])) $lookup[$tableName] = array();
		if (isset($row[$idColumn])) $lookup[$tableName][$row[$idColumn]] = $objectRef;
		// dump the columns as attributes
		foreach( $row as $column=>$value ) {
			// don't include the ID or the parentId column
			if ($column === $idColumn) continue;
			if ($column === $parentIdColumn) continue;
			if (isset($dataStructureSpec['lookup'][$column])) {
				// doesn't make any sense if a lookup column is empty or zero
				if (!$value) continue;
				$value = "__objectRef:".$dataStructureSpec['lookup'][$column].":$value";
			}
			// strip out any invalid UTF8 character sequences that have crept in
			// 
			//$value = htmlspecialchars($value);
            $value = htmlspecialchars($value);
            $value = mb_encode_numericentity ($value, array (0x80, 0xffff, 0, 0xffff), 'UTF-8');
			$value = str_replace("\r",'',$value);
			$value = str_replace("\n",'__newline__',$value);
			echo "\t$column=\"".$value."\"\n";
		}
		
		echo ">\n";
		
		// Now dump the sub-elements
		if (!isset($dataStructureSpec['subElements'])) $dataStructureSpec['subElements']=array();
		foreach ($dataStructureSpec['subElements'] as $subElementDataStructureSpec) {
			xmlDump($subElementDataStructureSpec,$row[$idColumn]);
		}
		echo "</$tableName>\n";
	}
	
}

function importXml( $dataStructureSpec, $xml, $parentId = null ) {
	global $lookup,$DB,$referenceUpdates;

	if (!isset($dataStructureSpec['lookup'])) $dataStructureSpec['lookup']=array();
	
	$tableName = $dataStructureSpec['tableName'];
	$idColumn = $dataStructureSpec['idColumn'];
	if(!isset($referenceUpdates[$tableName.':'.$idColumn])) $referenceUpdates[$tableName.':'.$idColumn]=array();
	
	$objectName = $xml->getName();
	if ($tableName !== $objectName) {
		echo "Skipping unexpected object $objectName\n";
		return;
	}
	echo "Creating object: $tableName with id...";

	$row = array();
	$objectReferenceUpdates = array();
	$objectRef = 0;
	foreach ( $xml->attributes() as $attribute=>$value ) {
		$value = (string)$value;
		if ($attribute==='__objectRef') {
			$objectRef = $value;
			continue;
		}
		if (isset($dataStructureSpec['lookup'][$attribute])) {
			if ($value) $objectReferenceUpdates[$attribute]=$value;
			# Don't add this to the insert data
			# just use the default value - we will update this later
			continue;
		}
		$row[$attribute] = html_entity_decode(str_replace("__newline__","\n",$value));
	}
	
	if (strlen($dataStructureSpec['parentIdColumn'])) $row[ $dataStructureSpec['parentIdColumn'] ] = $parentId;
	if (isset($dataStructureSpec['importProcess'])) $dataStructureSpec['importProcess']($row);
	$id = $DB->insert( $tableName, $row );
	if ($objectRef) $lookup[$objectRef] = $id;
	
	echo " ...$id\n";
	if (count($objectReferenceUpdates)) {
		echo count($objectReferenceUpdates)." reference updates required for this object\n";
		$referenceUpdates[$tableName.':'.$idColumn][$id] = $objectReferenceUpdates;
	}
	
	foreach ( $xml->children() as $child ) {
		$childName = $child->getName();
		if (!isset($dataStructureSpec['subElements'][$childName])) {
			echo "Ignoring unexpected child $childName\n";
			continue;
		}
		echo "Creating child $childName\n";
		importXml( $dataStructureSpec['subElements'][$childName], $child, $id );
	}

	return $id;
}




if (ws('mode')=='export' && ws('id')) {

	$recordTypeId = (int)ws('id');
    if( !canDo('view', $recordTypeId, 'recordType') ) {
        displayError('You do not have permission to export this record type');
        exit;
    }

	$lookup = array();
	$lookupErrors = array();

	ob_start();
	xmlDump( $dataStructureSpec, $recordTypeId );
	$xml = ob_get_contents();
	ob_end_clean();
	$xml = preg_replace_callback(
		'/__objectRef:(.*?):(.*?)"/',
		function($matches) use($lookup,$lookupErrors) {
			if (!isset($lookup[$matches[1]][$matches[2]])) $lookupErrors[]=$matches[1].' = '.$matches[2];
			else return $lookup[$matches[1]][$matches[2]].'"';
			return '"';
		},
		$xml
	);

	$recordTypeName = SITE_NAME.'-'.$DB->getValue('SELECT name FROM recordType WHERE id=?',$recordTypeId);
	$safeRecordTypeName = preg_replace('/[^0-9a-z_-]+/i','_',$recordTypeName);
	if (count($lookupErrors)) {
		displayError('LOOKUP ERRORS!');
	} else {
		// next "if" for debugging purposes
		if (0) {
			header('Content-Type: text/plain');
		} else {
			header('Content-Type: text/xml');
			header("Content-Disposition: attachment; filename=RecordTypeStructure-{$safeRecordTypeName}_".date('Ymd').'.xml');
		}
		echo $xml;
	}
	
	exit;
}

if (ws('mode')=='import') {

	if (!isset($_FILES) || !is_array($_FILES) || !isset($_FILES['file']) || $_FILES['file']['name']==='' ) {
		displayError("There was an unexpected error uploading this file");
	}
	if (isset($_FILES['file']['error']) && $_FILES['file']['error']==2) {
		displayError('The file was too big - please try again with a smaller file',0);
	} else if (!isset($_FILES['file']['size']) || !$_FILES['file']['size'] ) {
		fatalError("There was an unexpected error uploading this file (zero length)");
	}
	
	$fh = fopen($_FILES['file']['tmp_name'],'r');
	if (!is_resource($fh)) {
		displayError("There was an unexpected error uploading this file (file open failed)");
	}
	fclose($fh);
	
	// OK so... now we have a file - read in the XML
	$xml = simplexml_load_file($_FILES['file']['tmp_name']);

	$lookup = array();
	$referenceUpdates = array();
	$lookupErrors = array();	
    ob_start();
	include( VIEWS_DIR.'/header.php');
	echo '<h1>Importing</h1>';
	echo 'Import debug info:<br /><textarea rows="20" cols="100">';
	$newRecordTypeId = importXml( $dataStructureSpec, $xml );
	foreach( $referenceUpdates as $tableData=>$updates ) {
		list($tableName,$idColumn) = explode(':',$tableData,2);
		if (!count($updates)) continue;
		echo count($updates)." $tableName records require reference updates\n";
		foreach( $updates as $id=>$updateCols ) {
			echo "Running update on $tableName where $idColumn = $id\n";
			foreach( $updateCols as $col=>$ref ) {
				if (!isset($lookup[$ref])) $lookupErrors[] = "Couldn't find $col object with objectRef=$ref when updating $tableName\n"; 
				$updateCols[$col] = $lookup[$ref];
			}
			$DB->update($tableName, array($idColumn=>$id),$updateCols);
		}
	}
	echo "</textarea>";

	if (count($lookupErrors)) {
        ob_end_flush();
		echo "WARNING: IMPORT LOOKUP ERRORS\n";
		echo "<ul><li>".implode('</li><li>',$lookupErrors)."</li></ul>";
	} else {
        ob_end_clean();
    }
	
	if ($newRecordTypeId) {
		$DB->insert('userRecordType',array('userId'=>$USER_ID,'recordTypeId'=>$newRecordTypeId));
	}

    if (!count($lookupErrors)) {
        header('Location: list.php');
    }
	exit;

}

include(VIEWS_DIR.'/header.php');
?>

<h1>Record Structure Import</h1>

<form enctype="multipart/form-data" method="POST" action="">
	<input type="hidden" name="MAX_FILE_SIZE" value="10485760" />
	<input type="hidden" name="mode" value="import" />
	<input type="file" name="file" />
	<input type="submit" value="Import" />
</form>

<? include(VIEWS_DIR.'/footer.php'); ?>
