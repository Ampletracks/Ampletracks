<?

if (!defined('LOG_FILE_PERMISSIONS')) define('LOG_FILE_PERMISSIONS',0660);

class Logger {

	var $logFile;

	function __construct() {
		$this->logFile = false;
	}

	function log( $info ) {
		global $_SERVER;
	
		// open the log file (if it's not already open)
		if (!$this->logFile) {

			// generate the log file name
			$logFilename = SITE_BASE_DIR.'/log/core_'.date('Ymd').'.log';
			
			// open the log file
            $newFile = !file_exists( $logFilename );
			$this->logFile = @fopen( $logFilename, 'a');
			if (!$this->logFile or !is_resource($this->logFile)) {
				echo $logFilename;
				echo "Error writing to log file\n";
				exit;
			}
            if ($newFile) chmod( $logFilename, LOG_FILE_PERMISSIONS );

		}
		
		// add a newline to the message if it doesn't have one
		if (!preg_match('/[\\r\\n]$/',$info)) $info.="\n";
		
		// write the message
		fputs( $this->logFile, date('H:i:s ').$_SERVER["SCRIPT_FILENAME"].' '.$info );
	
	}

	function __destruct() {
		// close the log file
		if (is_resource($this->logFile)) fclose($this->logFile);
	}
}

?>
