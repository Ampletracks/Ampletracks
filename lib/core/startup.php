<?

if (get_magic_quotes_gpc()) {
	die('PLEASE SWITCH OF MAGIC QUOTES');
}

// Find to config file
// ===================================================================
//
// Site specific configuration must be defined by setting the environment
// variable "CONFIG_LOCATION".
// This can usually be done in the Apache configuration.
// It is best to do this in the main apache config and not in htaccess
// since htaccess files may get copied from dev environments to live.
// This tells the script where to find its site-specific configuration data.
// This means that live, dev and staged etc. can use different configuration data
// by setting different values for this enviornment variable in the corresponding VirtualHost configuration
if (isset($_SERVER["CONFIG_LOCATION"])) {
	$configLocation = $_SERVER["CONFIG_LOCATION"];
} else if (isset($_ENV["CONFIG_LOCATION"])) {
	$configLocation = $_ENV["CONFIG_LOCATION"];
}

if (!isset($configLocation)) { echo "Configuration location not set - aborting"; exit; }

// Load in config
$configLocation = str_replace('\\','/',$configLocation);
if (!file_exists($configLocation) ) { echo "Could not find configuration file - aborting"; exit; }

// Define some useful constants
// ===================================================================
$baseDir = dirname(dirname($configLocation)).'/';
define('LIB_DIR',$baseDir.'lib/');
define('CORE_DIR',LIB_DIR.'core/');
define('VIEWS_DIR',$baseDir.'views/');
define('CONFIG_DIR',$baseDir.'config/');
define('DATA_DIR',$baseDir.'data/');

// The configuration may want to enhance the database error handler
// so load in Dbif before loading the main config
require_once(CORE_DIR."Dbif.php");
require_once($configLocation);

// Ascertain the SITE_BASE_DIR if not already set in config
// ===================================================================
if (!defined('SITE_BASE_DIR')) define('SITE_BASE_DIR',dirname(dirname($configLocation)).'/');

$CORE_CONFIG = array();

// Load in the logging code
// ===================================================================
// This is done early on so that we can log any errors that occur during startup
require_once(CORE_DIR."logging.php");
$LOGGER = new logger();

// Pull in associated library files
// ===================================================================
require_once(CORE_DIR."tools.php");
require_once(CORE_DIR."validation.php");

// Connect to the DB if one is specified in config
// ===================================================================
// Small class for handling DB errors
if (!class_exists('DbErrorHandler')) {
	class DbErrorHandler extends DbifDefaultErrorHandler {
		function handleError($code, $basicMessage, $detailedMessage) {
			global $LOGGER;
			// Log error message
			$LOGGER->log("Database error: $basicMessage - $detailedMessage\n");
		}
	}
}

// Make sure SITE_NAME and SECRET are defined
if (!defined('SITE_NAME') || empty(SITE_NAME)) coreError("Configuration file didn't define SITE_NAME - aborting");
if (!defined('SECRET') || empty(SECRET)) coreError("Configuration file didn't define SECRET - aborting");

// Load the database config if it exists
$dbConfigFilename = CONFIG_DIR.'/'.SITE_NAME.'.db.php';
if (file_exists($dbConfigFilename)) include($dbConfigFilename);
if (defined('DB_NAME')) {
	// Connect to DB
	$errorHandler = new DbErrorHandler();
	$DB = new Dbif( DB_NAME, DB_USER, DB_PASSWORD, DB_HOST, $errorHandler );
	if (!$DB->connected()) {
		$LOGGER->log("Failed to connect to database - aborting\n");
		exit;
	}
}

// Define some more useful variables
// ===================================================================
if (!defined('IS_DEV')) define('IS_DEV',false);

if (preg_match('!/([^/]*?)/(.+/)?([^/]*?).php$!i',$_SERVER['SCRIPT_NAME'],$matches)) {
    $PAGE_NAME = $matches[2];
    if (!isset($ENTITY)) $ENTITY = $matches[1];
} else if (preg_match('!/([^/]*?).php$!i',$_SERVER['SCRIPT_NAME'],$matches)) {
    $PAGE_NAME = '';
    if (!isset($ENTITY)) $ENTITY = $matches[1];
} else {
    $PAGE_NAME = '';
    if (!isset($ENTITY)) $ENTITY = '';
}	

// Setup the workspace and perform default input validation
// ===================================================================

if (!isset($INPUTS)) $INPUTS = array();
if (isset($INPUTS_FROM_FILE)) deriveInputsFromFile($INPUTS_FROM_FILE);
if (function_exists('INPUTS')) {
	$INPUTS = array_replace_recursive( $INPUTS, INPUTS() );
}

$WS = array();
$INPUT = new InputValidator( $INPUTS );

// Call the appStartup page if it exists
if (file_exists(LIB_DIR.'appStartup.php')) include_once(LIB_DIR.'appStartup.php');
