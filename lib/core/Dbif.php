<?
/**
 * Database Interface
 * 
 * An object oriented MySQL Database Interface
 * 
 * @author Ben Jefferson
 * @version 1.0
 * @package Ampletracks
 * 
 */

/**
* DbifDefaultErrorHandler
*
* 	 The main dbif object can be passed an error handler object
* 	 The error handle must have a handleError method
*
*	 If no error handler is provided then an instance of this default error handler will be used
*/
class DbifDefaultErrorHandler {
	function __construct() {
	}
	
	/**
     * @param int $errorCode The error will be one of these values
	 *	1 => No query handle defined to execute query
	 *	2 => Error establishing connection to database	
	 *	3 => Database reported SQL error in query
	 *	4 => Error retreiving table meta data
	 *	5 => Number of placeholders doesn't macth number of value supplied
	 * @param string $basicMessage Contains an error message with a description of the type of error but no details
     * @param string $detailedMessage Contains the details of the error
	*/
	function handleError($errorCode, $basicMessage, $detailedMessage) {
		// Ideally the programmer will have defined their own error handler
		// this function should only be used as a worst-case fallback
		// in which case the safest thing to do is probably just to exit here
		exit;
	}
}


/**
 * Class DbifQuery
 * 
 * Handles database query interactions, providing methods for fetching
 * rows, columns, and other information from the result set.
 */
class DbifQuery {

	var $queryHandle = 0;
	var $creator = 0;
	var $resultType;
	
    /**
	 * DbifQuery constructor
	 *
	 * @param resource $queryHandle The MySQLi query result resource
	 * @param object $creator The instance that created the DbifQuery object
	 */
	function __construct( $queryHandle, $creator ) {
		$this->queryHandle = $queryHandle;
		$this->creator = $creator;
		$this->resultType = $creator->getResultType();
	}

	/**
     * Retrieves the number of columns in the result set
     *
     * @return int|false The number of columns, or false if there is no query handle
     */
    function numCols( ) {
		if (!$this->queryHandle) {
			$this->creator->errorHandler->handleError(1,"System error - no query handle","No query handle for numCols");
			return false;
		}
		return mysqli_num_fields( $this->queryHandle );
	}

    /**
     * Retrieves the number of rows in the result set
     *
     * @return int|false The number of rows, or false if there is no query handle
     */
    function numRows( ) {
		if (!$this->queryHandle) {
			$this->creator->errorHandler->handleError(1,"System error - no query handle","No query handle for numRows");
			return false;
		}
		return mysqli_num_rows( $this->queryHandle );
	}

	/**
     * Seeks to the specified row in the result set
     *
     * @param int $rows The number of rows to skip
     * @return void
     */
    function skipRows( $rows ) {
		if (!$this->queryHandle) {
			$this->creator->errorHandler->handleError(1,"System error - no query handle","No query handle for skipRows");
			return false;
		}
		mysqli_data_seek( $this->queryHandle,  $rows );
	}

	/**
     * Fetches a row from the result set into the provided array
     *
     * @param array &$rowData A reference to the array in which to store the fetched row
     * @param int|bool $resultType (optional) The type of result to be fetched, default value is false
     * @return array|false The fetched row as an array, or false if there is no query handle
     */
    function fetchInto( &$rowData, $resultType = false ) {
		if ($resultType===false) $resultType = $this->resultType;
		if (!is_array( $rowData )) $rowData = array();
		if (!$this->queryHandle) {
			$this->creator->errorHandler->handleError(1,"System error - no query handle","No query handle for fetchInto");
			return false;
		}
		$rowData = mysqli_fetch_array( $this->queryHandle,  $resultType );
		return( $rowData );
	}

	/**
     * Retrieves an array of column names from the result set
     *
     * @return array|false An array of column names, or false if there is no query handle
     */
    function getColumns() {
		if (!$this->queryHandle) {
			$this->creator->errorHandler->handleError(1,"System error - no query handle","No query handle for getColumns");
			return false;
		}
		$numFields = (($___mysqli_tmp = mysqli_num_fields($this->queryHandle)) ? $___mysqli_tmp : false);
		$idx = 0;
		$columns=array();
		while ( $idx < $numFields ) {
			$colData = mysqli_fetch_field_direct( $this->queryHandle, $idx);
			if (!is_object($colData) || !isset($colData->name) ) {
				$columns[$idx] = '';
			} else {
				$columns[$idx] = $colData->name;
			}
			$idx++;
		}
		return $columns;

	}

    /**
     * Retrieves an array of column names from the result set
     *
     * @return array An array of column names
     */
	function getColumnNames( ) {
		return $this->creator->getColumnData( $this->queryHandle, 0 );
	}

	/**
     * Retrieves an array of column lengths from the result set
     *
     * @return array An array of column lengths
     */
    function getColumnLengths( ) {
		return $this->creator->getColumnData( $this->queryHandle, 1 );
	}

	/**
     * Retrieves an array of column data types from the result set
     *
     * @return array An array of column data types
     */
    function getColumnTypes( ) {
		return $this->creator->getColumnData( $this->queryHandle, 2 );
	}

	/**
     * Retrieves an array of column flags from the result set
     *
     * @return array An array of column flags
     */
    function getColumnFlags( ) {
		return $this->creator->getColumnData( $this->queryHandle, 3 );
	}

	/**
     * Frees the result set associated with the query handle
     *
     * @return bool True if the result set was freed, false otherwise
     */
    function free( ) {
		return ((mysqli_free_result( $this->queryHandle ) || (is_object( $this->queryHandle ) && (get_class( $this->queryHandle ) == "mysqli_result"))) ? true : false);
	}

}


 /*
 * Class Dbif
 * 
 * Database interface class that simplifies interaction with MySQL databases. This class provides methods for
 *   - connnecting to the database
 *   - queryinig the database with simple queries to get values, rows, columns and hashes back
 *   - executing inserts
 *   - running arbitrary update SQL
 *
 * Example Usage
 *
 * <code>
 * 	require_once('Dbif.php');
 * 
 * 	// This is not essential - Dbif will use a default (silent) error handler if none is supplied
 * 	class MyErrorHandler extends DbifDefaultErrorHandler {
 * 		function handleError($code, $basicMessage, $detailedMessage) {
 * 			// Log error message and redirect user to error page
 * 			echo $basicMessage." - ".$detailedMessage;
 * 		}
 * 	}
 * 
 * 	$errorHandler = new MyErrorHandler();
 * 	
 * 	$dbName = 'test';
 * 	$dbUsername = 'username';
 * 	$dbPassword = 'password';
 * 	$dbHost = 'localhost';
 * 	
 * 	// Connect to the database
 * 	$db = new Dbif( $dbName, $dbUsername, $dbPassword, $dbHost, $errorHandler );
 * 
 * 	// Check we managed to connect
 * 	if (!$db->connected()) die("Couldn't connect to database");
 * 	
 * 	// Use exec to run arbitrary update SQL 
 * 	$db->exec('CREATE TEMPORARY TABLE theTable (col1 int unsigned not null primary key auto_increment, col2 int unsigned not null, col3 varchar(255))');
 * 
 * 	// Insert some rows into the table
 * 	for( $i=10; $i; $i-- ) {
 * 		$db->insert('theTable',array(
 * 			'col1'	=> '',
 * 			'col2'	=> 123+$i,
 * 			'col3'	=> 'wibble'.$i
 * 		));
 * 	}
 * 			
 * 	$whereValue = 5;
 * 	$col3Update = "new 'value'";
 * 	
 * 	// run a query and get the results one row at a time
 * 	$query = $db->query('SELECT col1, col2, col3 FROM theTable WHERE col1>?',$whereValue);
 * 	while( $query->fetchInto($row) ) {
 * 		echo "$row[0],$row[1],$row[2]<br />\n";
 * 	}
 * 	$query->free();
 * 
 * 
 * 	// Perform an update using exec
 * 	$rowsAffected = $db->exec('UPDATE theTable SET col3=? where col1=?',$col3Update,$whereValue);
 * 	echo "Update affected $rowsAffected rows<br />\n";
 * 	
 * 	// Get a single value back
 * 	$value = $db->getValue('SELECT col2 FROM theTable WHERE col1=?',$whereValue);
 * 	echo "Got back value $value<br />\n";
 * 	
 * 	// Do another insert
 * 	// The fourth parameter here details which values are SQL functions and thus should not be quoted or escaped
 * 	$insert_id = $db->insert( 'theTable', array( '','UNIX_TIMESTAMP()','value3' ), 'col1,col2,col3', 'col2' );
 * 	
 * 	// Get back a column
 * 	$col = $db->getColumn('SELECT col3 FROM theTable');
 * 	echo "Col3 contains: ".implode(',',$col)."<br />";
 * 	
 * 	// Get a hash
 * 	$hash = $db->getHash('SELECT col3,col1 FROM theTable WHERE col1>?',0);
 * 	foreach( $hash as $key=>$value ) {
 * 		echo "$key = $value<br />\n";
 * 	}
 * 	
 * 	// Get a hash of arrays...
 * 	$hash = $db->getHash('SELECT col3,col1,col2 FROM theTable WHERE col1>?',0);
 * 	foreach( $hash as $key=>$value ) {
 * 		echo "$key = $value<br />\n";
 * 	}
 * 
 * 	// Close the connection to the database
 * 	$db->close();
 * </code>
 */

class Dbif {

    /**
     * @var object|null $errorHandler Error handler object.
     */
    var $errorHandler;
   
    /**
     * @var bool|mysqli $dbHandle Database connection handle.
     */
    var $dbHandle = false;

    // Allows $DB->insert to be used for INSERT IGNORE, or REPLACE
    var $insertType = 'INSERT';
    var $nextInsertType = 'INSERT';
    
    /**
     * @var string $resultType Stores the current mode for pulling back row arrays
     */
    private $resultType = MYSQLI_BOTH;

    /**
     * @var string $nextResultType This allows for temporarily overriding the result type just for the next query
     */
	private $nextResultType=false;

    // dbName is required for calls to mysql_list_fields
    var $dbName;

    /**
     * @var string $lastError The last error returned from MySQL
     */
	var $lastError = '';

    /**
     * @var string|null $changeMonitor If set then the checkSql method of this object is passed a copy of this db handle and any SQL that updates the database
     */
	var $changeMonitor;

    /**
     * Dbif constructor.
     *
     * @param string $dbName Database name.
     * @param string $username Database username.
     * @param string $password Database password.
     * @param string $host Database host.
     * @param string|object $errorHandler Error handler object or an empty string.
     */
	function __construct( $dbName, $username='', $password='', $host='', $errorHandler='' ) {
	
		// Set up the error handler if none is provided
		if (!is_object($errorHandler)) {
			$errorHandler = new DbifDefaultErrorHandler();
		}
		$this->errorHandler = $errorHandler;
		
		// Defaul the host
		if ($host=='') $host='localhost';
		$this->dbHandle = false;
		$this->dbName = false;
		
		// Connect to the DB
        try {
	        $this->dbHandle = @mysqli_connect($host, $username, $password);
        } catch( Exception $e) {
			$this->lastError = "Couldn't connect to host '$host' using username '$username'";
            return;
		}
        try {
            mysqli_select_db($this->dbHandle, $dbName);
        } catch( Exception $e) {
			$this->lastError = "Couldn't select database '$dbName'";
			((is_null($___mysqli_res = mysqli_close($this->dbHandle))) ? false : $___mysqli_res);
			$this->dbHandle = false;
            return;
		}
		$this->dbName = $dbName;
        // make sure we use UTF8
        ((bool)mysqli_set_charset( $this->dbHandle, "utf8"));
	}

    function escapeAndQuote($value) {
        return '"'.mysqli_real_escape_string($this->dbHandle, $value).'"';
    }

    /**
     * Get the error handler.
     *
     * @return object|null Error handler object.
     */
	function getErrorHandler() {
		return $this->errorHandler;
	}

    /**
     * Check if the connection to the database is established.
     *
     * @return bool|mysqli True if connected, otherwise false.
     */
	function connected() {
		return $this->dbHandle;
	}
	
    /**
     * Set the result type for queries to return indexed arrays.
     *
     * @param bool $nextQueryOnly Whether to apply this setting only to the next query.
     * @return int The result type constant.
     */
	function returnArray($nextQueryOnly=true) {
		if ($nextQueryOnly) return( $this->nextResultType= MYSQLI_NUM );
		return( $this->resultType = MYSQLI_NUM );
	}
	
    /**
     * Set the result type for queries to return associative arrays.
     *
     * @param bool $nextQueryOnly Whether to apply this setting only to the next query.
     * @return int The result type constant.
     */
	function returnHash($nextQueryOnly=true) {
		if ($nextQueryOnly) return( $this->nextResultType= MYSQLI_ASSOC );
		return( $this->resultType = MYSQLI_ASSOC );
	}
	
    /**
     * Set the result type for queries to return both indexed and associative arrays
     *
     * @param bool $nextQueryOnly Whether to apply this setting only to the next query.
     * @return int The result type constant.
     */
	function returnBoth($nextQueryOnly=true) {
		if ($nextQueryOnly) return( $this->nextResultType= MYSQLI_BOTH );
		return( $this->resultType = MYSQLI_BOTH );
	}

	function getResultType($resetNext = true) {
		if($this->nextResultType !== false) {
			$resultType = $this->nextResultType;
			if($resetNext) $this->nextResultType = false;
		} else $resultType = $this->resultType;
		return $resultType;
	}

	function setChangeMonitor( $changeMonitor ) {
		$this->changeMonitor = $changeMonitor;
	}

	function lastError() {
		return $this->lastError;
	}

	// This is an internal function used to replace placeholders with corresponding fields
	// and escape data in the process
	function buildSql( $fields ) {
		global $WS;
		
		$sqlStr = array_shift($fields);
		
		// the sql string and fields are sometimes passed as a single array
		// we detect this and unpack it here
		while (is_array($sqlStr)) {
			$fields = $sqlStr;
			$sqlStr = array_shift($fields);
		}

		// substitute workspace fields in place of @@x@@ tags
		$bits = explode('@@',$sqlStr);
		$sqlStr = '';
		while( count($bits) ) {
			$sqlStr .= array_shift( $bits );
			if (count($bits)) {
				$thisBit = array_shift( $bits );
				$needsQuotes = !strpos(' \'"',substr($sqlStr,-1));
				
				if ($needsQuotes) $sqlStr .= '"';
				if (isset($WS[$thisBit])) {
                    $thisWSBit = $WS[$thisBit]; // Assign first as forceArray() works on a reference and we don't want to alter $WS
                    $thisWSBit = forceArray($thisWSBit);
                    foreach($thisWSBit as $key => $val) $thisWSBit[$key] = mysqli_real_escape_string($this->dbHandle, $val);
                    $quoteChar = $needsQuotes ? '"' : substr($sqlStr,-1);
                    $sqlStr .= implode($quoteChar.','.$quoteChar, $thisWSBit);
                }
				if ($needsQuotes) $sqlStr .= '"';
			}
		}

		// substitute in the fields if some have been supplied
		if (count($fields)) {
			$bits = explode('?',$sqlStr);
			$newSql = array_shift( $bits );
			foreach( $bits as $idx => $bit ) {
				if (!isset($fields[$idx])) {
					// Flag an error if there is no matching record for this field
					$this->errorHandler->handleError(5,"Number of placeholders doesn't match number of values supplied",$sqlStr);
					return false;
				}
				$data = $fields[$idx];

				if (is_array($data)) {
					# if this is an empty array then just use the mysql value NULL
					# if "x IN (NULL)" will always return false and will not error
                    
					if (!count($data)) $newSql .= 'NULL';
					else {
						foreach( $data as $subIdx=>$value ) {
							$newSql .=  "'".mysqli_real_escape_string($this->dbHandle, $value)."',";
						}
						# take the final comma of the end
						$newSql = substr($newSql,0,-1);
					}
				} else {
					# now for ordinary values...
					$newSql .= "'".mysqli_real_escape_string($this->dbHandle, $data)."'";
				}
				
				$newSql .= $bit;
			}
			$sqlStr = $newSql;
		}
		
		return $sqlStr;
	}
	
	# This is for UDPATE, INSERT AND DELETE queries
	# it returns the number of rows affected by the query
	# OR the insert ID if the sql starts with the word 'INSERT' and the table has an auto-increment field
	function exec() {

		$sqlStr = $this->buildSql( func_get_args() );
		
		$queryHandle = mysqli_query( $this->dbHandle ,  $sqlStr);
		if (!$queryHandle) {
			$this->lastError = 'Error ('.mysqli_errno($this->dbHandle).': '.mysqli_error($this->dbHandle).") executing SQL '$sqlStr'";
			$this->errorHandler->handleError(3,"Error in SQL",$this->lastError);
			return false;
		}

		// if there is a change monitor function then call that
		if (isset($this->changeMonitor)) {
			$this->changeMonitor->checkSql($this,$sqlStr);
		}

		$insertCheckRegexp = '/^([\s\r\n]|\s*#[^\r\n]*[\r\n])*INSERT/si';

		if (preg_match($insertCheckRegexp, $sqlStr)) {
			$insertId = ((is_null($___mysqli_res = mysqli_insert_id( $this->dbHandle ))) ? false : $___mysqli_res);
			if ($insertId) return $insertId;
		}
		
		return mysqli_affected_rows( $this->dbHandle );
	}

    /**
     * Execute a query and return the result.
     *
     * @return DbifQuery|bool A DbifQuery object on success or false on failure.
     */
	function query( ) {
		$sqlStr = $this->buildSql( func_get_args() );

		$queryHandle = mysqli_query( $this->dbHandle ,  $sqlStr);
		if (!$queryHandle) {
			$this->lastError = 'Error ('.mysqli_errno($this->dbHandle).': '.mysqli_error($this->dbHandle).") executing SQL '$sqlStr'";
			$this->errorHandler->handleError(3,"Error in SQL",$this->lastError);
            return FALSE;
		}
		return new DbifQuery( $queryHandle, $this );
	}

    function replace( $tableName, $keyData, $otherData, $returnId=false, $idColumn='id' ) {
        $updated = $this->update( $tableName, $keyData, $otherData );
        if ($updated) {
            if ($returnId) {
                $getIdSql = $this->conditionsFromHash($keyData,'SELECT `'.$idColumn.'` FROM `$tableName` WHERE ').' LIMIT 1';
                return $this->getValue( $getIdSql );
            } else return 'updated';
        } else {
            $this->setInsertType('REPLACE', true);
            if ($id = $this->insert( $tableName, array_merge( $keyData, $otherData ) )) return $returnId?$id:'inserted';
            else return false;
        }
    }
    
    function setInsertType( $type, $justNextOne=true ) {
        $type = strtoupper($type);
        if (!in_array($type,array('INSERT','INSERT IGNORE','REPLACE'))) $type='INSERT';
        
        $this->nextInsertType = $type;
        if ($justNextOne) return $type;
        $this->insertType = $type;
    }
    
	// This function builds and exequtes and INSERT statement
	// $columnNames should be a comma separated list of column names or an array of column names
	// If $values is a hash and $columns is empty then the column names will be taken from the hash keys
	// $allowUnquoted can be used to specify columns whose value should not be quoted
	// $allowUnquoted can be an array of a comma separated list
	// i.e. if one of the values is a mysql function like UNIX_TIMESTAMP();
	// N.B. Column names must not consist entirely of numbers
	function insert( $table, $values, $columnNames='', $allowUnquoted='' ) {
		$valueData = array();

		if ($columnNames=='') $columnNames = array_keys($values);
		if (!is_array($columnNames)) $columnNames = explode(',',$columns);

		if ($allowUnquoted=='') $allowUnquoted = array();
		else if (!is_array($allowUnquoted)) $allowUnquoted = explode(',',$allowUnquoted);
		$allowUnquoted = array_flip($allowUnquoted);
		
		$idx=-1;
		foreach( $values as $key=>$value) {
			$idx++;
			$columnName = $columnNames[$idx];
			
			if ( count($allowUnquoted) && isset($allowUnquoted[$columnName])) {
				$valueData[] = $value;
			} else {
				if (is_array($value)) $value = implode(',',$value);
				$valueData[] = "'".mysqli_real_escape_string($this->dbHandle, $value)."'";
			}
		}
		$sqlStr = $this->nextInsertType." INTO `$table` ";
        // Reset the next insert to be the current default type
        $this->nextInsertType = $this->insertType;
        
		$columnList = '`'.implode('`,`',$columnNames).'`';
		if (preg_match('/[^\\d,]/',$columnList)) {
			$sqlStr .= "(".$columnList.") ";
		}
		$sqlStr .= "VALUES (".implode(",",$valueData).")";

		$queryHandle = mysqli_query( $this->dbHandle ,  $sqlStr);
		if (!$queryHandle) {
			$this->lastError = 'Error ('.mysqli_errno($this->dbHandle).': '.mysqli_error($this->dbHandle).") executing SQL '$sqlStr'";
			$this->errorHandler->handleError(3,"Error in SQL",$this->lastError);
            return false;
		} else {
			# if there is a change monitor object then call that
			if (isset($this->changeMonitor)) {
				$this->changeMonitor->checkSql($this,$sqlStr);
			}
		}
		return( ((is_null($___mysqli_res = mysqli_insert_id( $this->dbHandle ))) ? false : $___mysqli_res) );
	}

	function autoInsert( $tableName, $columnSpec='', $extras=null, $defaults=null, $duplicateCheck=false ) {

		$insertData = $this->buildColumnHash( $tableName, $columnSpec, $extras, $defaults );
		if ($duplicateCheck && count($insertData)) {
			$checkArgs = array();
			$checkSql = "SELECT 1 FROM `$tableName` WHERE ";
			foreach( $insertData as $columnName=>$value ) {
				$checkSql .= "$columnName=? AND ";
				$checkArgs[] = $value;
			}
			array_unshift($checkArgs, substr($checkSql,0,-4));
			$checkResult = $this->getValue( $checkArgs );
			if ($checkResult) return 0;
		}

		return( $this->insert( $tableName, $insertData ) );
	}

	private function conditionsFromHash( $where_data, $sql="" ) {
		if ( !is_array($where_data) || !count($where_data) ) return array( $sql.' 1=1' );

		$values = array('');

		foreach( $where_data as $key=>$value ) {
			if (is_array($value)) {
				$sql .= "`$key` IN (?) AND ";
    			$values[] = $value;
			} else if (is_null($value)) {
				$sql .= "ISNULL(`$key`) AND ";
			} else {
				$sql .= "`$key` = ? AND ";
       			$values[] = $value;
            }
		}
		$values[0] = substr($sql,0,-4);
		return $values;
	}
	
	function duplicateData( $table_name, $where_data, $update_data, $dest_table=null, $callback=null, $destDb=null ) {
		if (is_null($dest_table)) $dest_table = $table_name;
		$queryData = $this->conditionsFromHash( $where_data );
		$queryData[0] = "SELECT * FROM `$table_name` WHERE ".$queryData[0];
		$query = $this->query( $queryData );
		$idx = 0;
		while ( $query->fetchInto( $row, MYSQLI_ASSOC ) ) {
			$newRow = array_merge($row, $update_data);
			if (is_null($destDb)) $destDb=$this;
			$id = $destDb->insert($dest_table,$newRow);
			if (is_callable($callback)) $callback( $row, $id, $idx );
			$idx++;
		}
	}
	
	function map( $sql, $function, $noReturn=false ) {
		if (!is_callable($function)) return array();
		$query = $this->query($sql);
		$return = array();
		$idx = 0;		
		while ( $query->fetchInto( $row, MYSQLI_ASSOC ) ) {
			$idx++;
			if ($noReturn) $function($row,$idx);
			else $return[] = $function($row,$idx);
		}
		return $return;
	}

    function delete( $table_name, $where_data ) {

        $sql = "DELETE FROM `$table_name` WHERE ";
        $values = array('');
        $where_data = $this->conditionsFromHash( $where_data );
        $sql .= array_shift($where_data);
        $values = array_merge($values, $where_data);
        $values[0] = $sql;
        return $this->exec( $values );
    }
	
	function update( $table_name, $where_data, $update_data ) {
	
        // If $where_data is a whole number then we assume that $where_data is ['id'=>$where_data]
        if (is_numeric($where_data) && $where_data==(int)$where_data) $where_data = ['id'=>$where_data];
		if (!count($where_data) || !count($update_data)) return false;
		
		$sql = "UPDATE `$table_name` SET ";
		$values = array('');
		foreach( $update_data as $key=>$value ) {
			$sql .= "`$key` = ?, ";
			$values[] = $value;
		}

		$sql = substr($sql,0,-2)." WHERE ";

		$where_data = $this->conditionsFromHash( $where_data );
		$sql .= array_shift($where_data);
		$values = array_merge($values, $where_data);
		$values[0] = $sql;
		
		return $this->exec( $values );		
	}
	
	function autoUpdate( $table_name, $column_spec='', $where_field='id', $where_value=null, $extras=null, $defaults=null ) {
		global $WS;
		if ($where_value===null && isset($WS[$where_field])) $where_value=$WS[$where_field];
		$update_data = $this->buildColumnHash( $table_name, $column_spec, $extras, $defaults );
		if (!count($update_data)) return false;

		return $this->update( $table_name, array($where_field => $where_value), $update_data );
	}
	
#	columnspec provides details of what data to insert into the database
#		This can either be a field prefix which will identify CGI fields (the prefix is stripped off)
#			e.g. if column_spec=new_row_ and CGI fields include new_row_foo and new_row_blah then the SQL will look something like
#			INSERT INTO table_name ( foo, blah ) VALUES ( <value of CGI['new_row_foo']>, <value of CGI['new_row_blah']> );
#		Othewise this can be an ordinary array specify names of fields to be added
#			(it is assumed that the field and column names are identical) e.g. column_spec=array('foo','blah') gives
#			INSERT INTO table_name ( foo, blah ) VALUES ( <value of CGI['foo']>, <value of CGI['blah']> );
#		Or... it can be a hash mapping CGI field names to column names
#			e.g. column_spec=array( 'wibble' => 'foo', 'blort' => 'blah ) gives
#			INSERT INTO table_name ( foo, blah ) VALUES ( <value of CGI['wibble']>, <value of CGI['blort']> );
#			N.B. entirely numeric CGI field names are NOT ALLOWED e.g. column_spec=array( '1' => 'foo', 'wibble' => 'blah )
#		Or... if it is not given at all or an empty string is passed ('') then it looks for all cgi fields that start "<table_name>_"
#			and uses these.
#
#	extras is a hash keyed on column name that defines extra data to be inserted
#		- this is ignored if a non-array value is passed
#		- it is also ignored if column_spec is not specified
#	defaults is a hash keyed on column name that provides default values should the appropriate CGI fields not exist.
#	BEWARE: input taken from $_WORKSPACE or $defaults is screened to check if it starts '%' - if it is this is escaped with a second '%'
#		this stops users entering strings starting with '%' and having them run as arbitrary SQL
#		if you want to use a function like UNIX_TIMESTAMP then put that in $extra - this is not filtered in this way
#		by the same virtue take care when putting things in $extra that start with '%'!

	function buildColumnHash( $table_name, $column_spec='', $extras=null, $defaults=null, $allow_unquoted='' ) {
		global $WS, $SEARCH_SPEC_LOOKUP_TABLE;
		if ( !is_array($defaults) ) $defaults = array();
		# extra fields are a nonsense if we are going to interogate the database for ALL columns
		if ( !is_array($extras) || $column_spec=='' ) $extras=array();
		$data = $extras;
		if ( $column_spec == '' ) {
			$column_spec = $table_name.'_';
		}

		if ( !is_array($column_spec) ) {
#			echo "column_spec = $column_spec<BR />\n";
			# column_spec must be a field name prefix
			reset( $WS );
			foreach ( $WS as $key=>$value ) {
				if ( strpos($key,$column_spec)===0 && !(substr( $key, -3, 1) == '_' && isset($SEARCH_SPEC_LOOKUP_TABLE[substr( $key, -2)]) ) ) {
					$col = substr($key,strlen($column_spec));
#					echo "inserting data into column $col<BR />\n";
					if ( $allow_unquoted && substr( $value, 0, 1 ) == '%' ) $value = '%'.$value;
					# sanitize the column name
					$col = preg_replace( '/[^a-zA-Z0-9_]/','',$col );
					$col = substr( $col, 0, 64);
					$data[$col] = $value;
				}
			}
		} else {
			# column spec is some kind of array/hash
			reset( $column_spec );
            foreach ($column_spec as $key=>$value) {
				if ( ereg('^[0-9]+$',$key) ) {
					# this is part of a plane old array
					# there is no mapping between CGI field name and column name to be done
					$key = $value;
				}
				if ( isset($_WORKSPACE[$key]) ) {
					$data[$value] = $_WORKSPACE[$key];
				} elseif ( isset( $defaults[$key] ) ) {
					$data[$value] = $defaults[$key];
				} else {
					$data[$value] = '';
				}
				if ( substr( $data[$value], 0, 1 ) == '%' ) $data[$value] = '%'.$data[$value];
			}
		}
		return($data);
	}

	function tableExists( $tableName ) {
		$tableLookup = array_flip( $this->getTables() );
		return isset( $tableLookup[ $tableName ] );
	}

	function getTempTableName( $suggestion = '' ) {
		if ( $suggestion == '' ) $suggestion = 'tmp_'.time();
		$tableLookup = array_flip( $this->getTables() );
		$idx = 0;
		while ( isset( $tableLookup[ $suggestion.'_'.$idx ] ) ) { $idx++; }
		return $suggestion.'_'.$idx;
	}

	function lookup( $table, $value, $lookupColumn='description', $keyColumn='id' ) {
		list( $value ) = $this->getRow("SELECT `$lookupColumn` FROM `$table` WHERE `$keyColumn`='".mysqli_real_escape_string($this->dbHandle, $value)."' LIMIT 1");
		return $value;
	}

	function close( ) {
		if (is_resource($this->dbHandle)) ((is_null($___mysqli_res = mysqli_close($this->dbHandle))) ? false : $___mysqli_res);
	}

	# if the SQL returns one column this returns a hash in the form col1 => col1
	# if the SQL returns two columns this returns a hash in the form col1 => col2
	# if the SQL returns more than two columns this returns a hash in the form col1 => array(col2,col3...)

	function getHash( ) {
		$resultType = $this->getResultType(false); // Get this now as if it's using nextResultType it'll be blanked when the query runs
		$sqlStr = $this->buildSql( func_get_args() );
		$queryh = $this->query($sqlStr);
		$numCols = $queryh->numCols();

		$hash = array();
		$row = array();
		$setKeys = true;
		while( $queryh->fetchInto($row) ) {
			if($setKeys) {
				$key0 = 0;
				$key1 = 1;
				// Using numerical keys doesn't work if returnHash() has been called before this
				if($resultType === MYSQLI_ASSOC) {
					$rowKeys = array_keys($row);
					$key0 = $rowKeys[0];
					$key1 = isset($rowKeys[1]) ? $rowKeys[1] : null;
				}
				$setKeys = false;
			}
			if ( $numCols > 2 ) {
				$key = $row[$key0];
				unset( $row[$key0] );
				$hash[$key] = $row;
			} else if ( $numCols==2 ) {
				$hash[$row[$key0]] = $row[$key1];
			} else {
				$hash[$row[$key0]] = $row[$key0];
			}
		}
		$queryh->free();
		reset($hash);
		return $hash;
	}

	function getTableRows( $tableName ) {
		$tableName = str_replace('`','',$tableName);
		return $this->getValue("SELECT COUNT(*) FROM `$tableName`");
	}
	
	function getColumnNames( $tableName ) {
		return $this->getColumnData( $tableName, 0 );
	}

	function getColumnLengths( $tableName ) {
		return $this->getColumnData( $tableName, 1 );
	}

	function getColumnTypes( $tableName ) {
		return $this->getColumnData( $tableName, 2 );
	}

	function getColumnFlags( $tableName ) {
		return $this->getColumnData( $tableName, 3 );
	}

	function getColumnData( $tableName, $which ) {
		// see if the "tableName" is in fact a query handle
		if ( !is_resource($tableName) ) {
			$sqlStr = "SELECT EXTRA, COLUMN_NAME, COLUMN_TYPE, COLLATION_NAME, CHARACTER_MAXIMUM_LENGTH, NUMERIC_PRECISION, DATA_TYPE, COLUMN_KEY, IS_NULLABLE FROM `information_schema`.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? ORDER BY ORDINAL_POSITION ASC";
			$columnDataQuery = $this->query($sqlStr,$this->dbName, $tableName);
			
			$columnSpec = array();
			while ($columnDataQuery->fetchInto($colData)) {
				switch ($which) {
					case 0:
							$columnSpec[] = $colData['COLUMN_NAME'];
							break;
					case 1:
							$columnSpec[] = $colData['CHARACTER_MAXIMUM_LENGTH'] + $colData['NUMERIC_PRECISION'];
							break;
					case 2:	
							$columnSpec[] = $colData['DATA_TYPE'];
							break;
					case 3:
							$flags = '';
							if ($colData['COLUMN_KEY']=='MUL') $flags .= 'multiple_key';
							else if ($colData['COLUMN_KEY']=='PRI') $flags .= 'primary_key';
							else if ($colData['COLUMN_KEY']=='UNI') $flags .= 'unique_key';
							if ($colData['DATA_TYPE']=='blob') $flags .= 'blob';
							else if ($colData['DATA_TYPE']=='enum') $flags .= 'enum';
							else if ($colData['DATA_TYPE']=='set') $flags .= 'set';
							else if ($colData['DATA_TYPE']=='timestamp') $flags .= 'timestamp';
							if (strpos($colData['COLUMN_TYPE'],'unsigned')!==false) $flags .= 'unsigned';
							if (strpos($colData['COLUMN_TYPE'],'zerofill')!==false) $flags .= 'zerofill';
							if (strpos($colData['EXTRA'],'auto_increment')!==false) $flags .= 'auto_increment';
							if (strpos(rev($colData['COLLATION_NAME']),'nib_')===0) $flags .= 'binary';
							if (!$colData['IS_NULLABLE']) $flags .= 'not_null';
							$columnSpec[] = $flags;
				}
			}
			return($columnSpec);
		}

		$handle = $tableName;

		if (!is_object($handle)) {
			$dbError = mysqli_error( $this->dbHandle );
			$this->lastError="Error getting table column names in Dbif->getColumnNames probably table '$tableName' doesn't exist. DB error message: $dbError";
			$this->errorHandler->handleError(4,"System error communicating with database",$this->lastError);
			return(0);
		}

		$numFields = mysqli_num_fields( $handle );

		$columnSpec = array();
		$idx = 0;
		while ( $idx < $numFields ) {
			$colData = mysqli_fetch_field_direct( $handle,  $idx );
			if (!is_object($colData)) {
				$columnSpec[] = false;
			} else {
				switch ($which) {
					case 0:
							$columnSpec[] = isset($colData->name) ? $colData->name : false;
							break;
					case 1:
							$columnSpec[] = isset($colData->length) ? $colData->length : 0;
							break;
					case 2:	
							if (!isset($colData->type) || is_null($colData->type)) {
								$type = false;
							} else {
								$type = '';
								if ( $colData->type == MYSQLI_TYPE_STRING || $colData->type == MYSQLI_TYPE_VAR_STRING ) $type='string';
								if ( in_array($colData->type, array(MYSQLI_TYPE_TINY, MYSQLI_TYPE_SHORT, MYSQLI_TYPE_LONG, MYSQLI_TYPE_LONGLONG, MYSQLI_TYPE_INT24))) $type .= "int ";
								if ( in_array($colData->type, array(MYSQLI_TYPE_FLOAT, MYSQLI_TYPE_DOUBLE, MYSQLI_TYPE_DECIMAL, defined("MYSQLI_TYPE_NEWDECIMAL") ? constant("MYSQLI_TYPE_NEWDECIMAL") : -1))) $type .= "real ";
								if ( $colData->type == MYSQLI_TYPE_TIMESTAMP) $type .= "timestamp ";
								if ( $colData->type == MYSQLI_TYPE_YEAR) $type .= "year ";
								if ( $colData->type == MYSQLI_TYPE_DATE || $colData->type == MYSQLI_TYPE_NEWDATE) $type .= "date ";
								if ( $colData->type == MYSQLI_TYPE_TIME) $type .= "time ";
								if ( $colData->type == MYSQLI_TYPE_SET) $type .= "set ";
								if ( $colData->type == MYSQLI_TYPE_ENUM) $type .= "enum ";
								if ( $colData->type == MYSQLI_TYPE_GEOMETRY) $type .= "geometry ";
								if ( $colData->type == MYSQLI_TYPE_DATETIME) $type .= "datetime ";
								if ( in_array($colData->type, array(MYSQLI_TYPE_TINY_BLOB, MYSQLI_TYPE_BLOB, MYSQLI_TYPE_MEDIUM_BLOB, MYSQLI_TYPE_LONG_BLOB))) $type .= "blob ";
								if ( $colData->type == MYSQLI_TYPE_NULL) $type .= "null ";
								$type = substr((string)$type, 0, -1);
								if ($type== "") $type="unknown";
							}
							$columnSpec[] = $type;
							break;
					case 3:
							if (!isset($colData->flags)) $flags = false;
							else {
								if ( $colData->flags & MYSQLI_NOT_NULL_FLAG)       $flags .= "not_null ";
								if ( $colData->flags & MYSQLI_PRI_KEY_FLAG)        $flags .= "primary_key ";
								if ( $colData->flags & MYSQLI_UNIQUE_KEY_FLAG)     $flags .= "unique_key ";
								if ( $colData->flags & MYSQLI_MULTIPLE_KEY_FLAG)   $flags .= "unique_key ";
								if ( $colData->flags & MYSQLI_BLOB_FLAG)           $flags .= "blob ";
								if ( $colData->flags & MYSQLI_UNSIGNED_FLAG)       $flags .= "unsigned ";
								if ( $colData->flags & MYSQLI_ZEROFILL_FLAG)       $flags .= "zerofill ";
								if ( $colData->flags & 128)                        $flags .= "binary ";
								if ( $colData->flags & 256)                        $flags .= "enum ";
								if ( $colData->flags & MYSQLI_AUTO_INCREMENT_FLAG) $flags .= "auto_increment ";
								if ( $colData->flags & MYSQLI_TIMESTAMP_FLAG)      $flags .= "timestamp ";
								if ( $colData->flags & MYSQLI_SET_FLAG)            $flags .= "set ";
								$flags = substr((string)$flags, 0, -1);
							}
							
							$columnSpec[] = $flags;
							break;
				}
			}
			$idx++;
		}
		return($columnSpec);
	}

	function count() {
        $args = func_get_args();
        if (count($args)==2 && is_string($args[0]) && is_array($args[1]) && !preg_match('/^\s*SELECT/i',$args[0])) {
            list( $table, $conditions ) = $args;
            // looks like call is in the form of count($tableName,$conditionsHash)
            $sql = $this->conditionsFromHash($conditions, "SELECT COUNT(*) FROM `$table` WHERE ");
        } else {
            // assume that call is in the form of count($sql,$mergeData)
            $sql = $this->buildSql( $args );
            $sql = "SELECT COUNT(*) FROM (\n".$sql."\n /* */ ) countTable";
        }
		return $this->getValue($sql);
	}

	function getColumn( ) {
		$sqlStr = $this->buildSql( func_get_args() );

		$data = array();
		$row = array();
		$query = $this->query($sqlStr);
		while( $query->fetchInto($row, MYSQLI_NUM) ) {
			$data[] = $row[0];
		}
		$query->free();
		return $data;
	}

	function getValue( ) {
		$sqlStr = $this->buildSql( func_get_args() );
		$this->nextResultType = MYSQLI_NUM;
		$return = $this->getRow($sqlStr);

		if ($return===false || $return===null) return false;
		
		return $return[0];
	}

	function getRow( ) {
		$sqlStr = $this->buildSql( func_get_args() );

		if ($this->nextResultType !== false) {
			$resultType = $this->nextResultType;
			$this->nextResultType = false;
		} else $resultType = $this->resultType;

		$data = array();
		$query = $this->query($sqlStr);
		if (!$query) return false;
        
		$query->fetchInto($data, $resultType);
		$query->free();
		return $data;
	}

	/*
		Syntax:
		loadRow( <sql> )
		loadRow( <sql>, <prefix> )
		loadRow( <sql>, <mergefield1>, ..., <mergefieldN>, <prefix> );
		loadRow( <sql>, <mergefield1>, ..., <mergefieldN>, <[options]> );
			if the last parameter is an array then it is deemed to be a hash of the following options
			 prefix
			 overwrite   (whether to overwrite existing entries)
	*/
	
	function loadRow( ) {
		global $WS;
		$args = func_get_args();
		$prefix = '';
		if (count($args) > 1) $prefix = array_pop( $args );

		$overwriteExisting = true;
		if (is_array($prefix)) {
			$overwriteExisting = isset($prefix['overwrite'])?$prefix['overwrite']:true;
			$prefix = isset($prefix['prefix'])?$prefix['prefix']:'';
		}

		$this->nextResultType = MYSQLI_ASSOC;
		$values = $this->getRow( $args );

		if (is_array($values)) {
            enrichRowData($values);
			foreach ($values as $key=>$value) {
				if ($overwriteExisting || !isset($WS[$prefix.$key])) $WS[$prefix.$key]=$value;
			}
		}

        return is_array($values);
	}
	
	function getRows( ) {
		$sqlStr = $this->buildSql( func_get_args() );

		if ($this->nextResultType !== false) {
			$resultType = $this->nextResultType;
			$this->nextResultType = false;
		} else $resultType = $this->resultType;

		$data = array();
		$return = array();
		$query = $this->query($sqlStr);


		while( $query->fetchInto($data, $resultType) ) {
			$return[]=$data;
		}
		$query->free();
		return $return;
	}

	function getTables() {
		$queryh = mysqli_query( $this->dbHandle , "SHOW TABLES FROM `$this->dbName`");
		$tables = array();
		$i = mysqli_num_rows($queryh);
		while ($i-- > 0) {
			$tables[$i] = ((mysqli_data_seek($queryh,  $i) && (($___mysqli_tmp = mysqli_fetch_row($queryh)) !== NULL)) ? array_shift($___mysqli_tmp) : false);
		}
		return $tables;
	}

    function getQueryInfo() {
        $infoStr = mysqli_info ( $this->dbHandle );
        $reply = array('matched'=>null,'affected'=>null,'duplicates'=>null,'deleted'=>null,'warnings'=>null);
        if (preg_match('/Rows matched: (\d+)/',$infoStr,$matches)) $reply['matched']=(int)$matches[1];
        if (preg_match('/Changed: (\d+)/',$infoStr,$matches)) $reply['affected']=(int)$matches[1];
        if (preg_match('/Duplicates: (\d+)/',$infoStr,$matches)) $reply['duplicates']=(int)$matches[1];
        if (preg_match('/Records: (\d+)/',$infoStr,$matches)) $reply['affected']=(int)$matches[1];
        if (preg_match('/Deleted: (\d+)/',$infoStr,$matches)) $reply['warnings']=(int)$matches[1];
        return $reply; 
    }

	// $sql should be something like 'SELECT 1 FROM table WHERE id='
	
	function findAvailableId( $id, $sql, $charset='0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ' ) {
		$count = 1;
		$base = strlen($charset);
		$idLength = strlen($id);
		$maxCount = pow($base,$idLength);

		while ($count <= $maxCount) {
			list($exists) = $this->getValue($sql."'".mysqli_real_escape_string($this->dbHandle, $id)."' LIMIT 1");
			if (!isset($exists) or !strlen($exists)) return $id;

			$test = 0;
			while ( $count >= pow($base,$test)  ) {
				if (($count % pow($base,$test)) == 0) {
					$charPos = (strpos($charset,substr($id,$idLength-$test-1,1))+1) % $base;
					$id = substr($id,0,$idLength-$test-1).substr($charset,$charPos,1).substr($id,$idLength-$test);
				}
				$test++;
			}
			$count++;
		}
		return '';
	}

	// this function can be called in 2 ways
	// getEntityId( created, tableName, array( testColumn => testValue, etc.)[, idColumn][, extra] )
	// or
	// getEntityId( created, tableName, testColumn, testValue[, idColumn][, extra] )
	// "created" is passed by reference and used to let the caller know if the row had to be created or not.
	
	function getEntityId( &$created, $tableName, $testColumn, $testValue='', $idColumn='', $extra='' ) {
		$queryData = array(" FROM `$tableName` WHERE ");
		
		if (is_array($testColumn)) {
			foreach( $testColumn as $key=>$value ) {
				$queryData[0] .= "`$key`=? AND ";
				$queryData[] = $value;
			}
			$queryData[0] = substr( $queryData[0], 0, -4);
			// shuffle the inputs along
			$extra = $idColumn;
			$idColumn = $testValue;
		} else {
			$queryData[0] .= "`$testColumn`=? ";
			$queryData[] = $testValue;
		}
		
		if (!$idColumn) $idColumn = 'id';
		$queryData[0] = "SELECT `$idColumn` ".$queryData[0];
		
		$id = $this->getValue($queryData);
		if (!$id) {
			$created=true;
			if (!is_array($extra)) $extra = array();
			if (is_array($testColumn)) {
				$extra = array_merge( $extra, $testColumn ); 
			} else {
				$extra[$testColumn] = $testValue;
			}
			
			$id = $this->insert($tableName, $extra);
		} else {
			$created=false;
		}
		
		return $id;
	}
	
	// This table relies on the fact that the associated table has the following rows
	// lockedAt
	// lockId
	// In order to prevent a row from being locked in future set the lockId to 0
	// but leave the lockedAt set to something greater than 0
	// If only one column is requested (by passing cols as a string) then the values of this column from all rows are returned as an array
	// If more than one column is requested (by passing an array of cols) then an array of arrays is returned.
	function lockRows( $tableName, $cols='', $numRows=100, $lockTimeout=3600 ) {
	
		$lockId = getmypid()*10000+mt_rand(0,9999);
	
		// unlock any rows for which the lock has timed out
		$this->exec("UPDATE `$tableName` SET lockId=0, lockedAt=0 WHERE lockId>0 AND lockedAt<(UNIX_TIMESTAMP()-".((int)$lockTimeout).")");
	
		// attempt to lock some new rows
		$numLocked = $this->exec("UPDATE `$tableName` SET lockedAt=UNIX_TIMESTAMP(), lockId=? WHERE lockId=0 AND lockedAt=0 LIMIT ".((int)$numRows),$lockId);
		// if there were no rows locked then return an ampty array
		if (!$numLocked) return( array() );
		
		$cols = trim($cols);
		if ($cols == '') $cols = '*';
		
		if (is_array($cols) or $cols=='*') {
			if (is_array($cols)) {
				$cols = implode('`,`',$cols);
			}
			$rows = $this->getRows("SELECT `$cols` FROM $tableName WHERE lockId=?",$lockId);
		} else {
			// return the ids that we got a lock on
			$rows = $this->getColumn("SELECT `$cols` FROM $tableName WHERE lockId=?",$lockId);
		}

		return $rows;
	}
	
	function escape( $str ) {
		return mysqli_real_escape_string($this->dbHandle, $str);
	}

    function oneToManyUpdate( $table, $lookupColumn, $lookupValue, $dataColumn, $dataValues, $extraColumns=null ) {

        if (!is_array($dataValues)) $dataValues=array($dataValues);
        # remove any entries where the datavalue is an empty string
        $dataValues = array_filter( $dataValues );

        # Delete any existing records that are no longer required
        if (is_array($lookupColumn) && is_array($lookupValue)) {
            $lookupHash = array_combine( $lookupColumn, $lookupValue );
            $sql = $this->conditionsFromHash( $lookupHash );
            $conditions = $sql[0];
            $sql[0] = "SELECT `$dataColumn` FROM `$table` WHERE ".$sql[0];
            $currentValues = $this->getColumn($sql);

            // The NULL that buildSql() uses when $dataValues is empty doesn't work when the test is negated
            // but if we're not keeping anything we can just get rid of all the existing values
            $sql[0] = "DELETE FROM `$table` WHERE ".$conditions;
            if(count($dataValues)) {
                $sql[0] .= " AND `$dataColumn` NOT IN (?)";
                $sql[]=$dataValues;
            }
        } else {
            $currentValues = $this->getColumn("SELECT `$dataColumn` FROM `$table` WHERE `$lookupColumn`=?",$lookupValue);

            $sql[0] = "DELETE FROM `$table` WHERE `$lookupColumn`=?";
            $sql[] = $lookupValue;
            if(count($dataValues)) {
                $sql[0] .= " AND `$dataColumn` NOT IN (?)";
                $sql[] = $dataValues;
            }
        }
        $changed = $this->exec($sql);
	
		foreach ($dataValues as $dataValue) {
			if (!in_array($dataValue, $currentValues)) {
				$changed = true;
                $insert = array( $dataColumn => $dataValue );

                if (isset($lookupHash)) $insert = array_merge( $insert, $lookupHash );
                else $insert[$lookupColumn] = $lookupValue;
                
                if (is_array($extraColumns)) $insert = array_merge( $insert, $extraColumns );
				$this->insert($table,$insert);
			}
		}
		
		return $changed;
	}

    function getEnumValues($table, $column) {
        $info = $this->getRow("SHOW COLUMNS FROM $table WHERE FIELD = ?", $column);
        if(!isset($info['Type'])) return array();
        $info = $info['Type'];
        if(strpos($info, 'enum(') !== 0) return array();

        $info = str_getcsv(substr($info, 5, -1), ',', "'");
        $values = array();
        foreach($info as $value) {
            if($value === '') continue;
            $values[ucwords($value)] = $value;
        }

        return $values;
    }

    // Utility function to fix ints in result sets due to php returning everything as a string
    static function fixInts($rows, $intCols) {
        foreach($rows as $idx => $row) {
            foreach($intCols as $intCol) {
                $row[$intCol] = (int)$row[$intCol];
            }
            $rows[$idx] = $row;
        }

        return $rows;
    }
}

?>
