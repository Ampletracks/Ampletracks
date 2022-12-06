<?

function makeDirectoriesWritable() {
    $directories = func_get_args();
    $user = array_shift($directories);
    $group = array_shift($directories);
    $fileMode = base_convert(array_shift($directories),10,8);
    $directoryMode = base_convert(array_shift($directories),10,8);
    $baseDir = array_shift($directories);
    if (strlen($group)) $user .= ':'.$group;
    // handle the case where the list of directories is passed as an array
    if (is_array($directories[0])) $directories = $directories[0];
    foreach($directories as $directory) {
        $dir = $baseDir.'/'.$directory;
        @mkdir( $dir,$mode,true );
        $commands = [
            'find '.escapeshellarg($dir).' -type f -printf "%m\n" | uniq'
                => ['change the permissions of', $fileMode, 'find '.escapeshellarg($dir).' -type f -exec chmod '.escapeshellarg($fileMode).' -- {} + 2>&1'],
            'find '.escapeshellarg($dir).' -type d -printf "%m\n" | uniq'
                => ['change the permissions of', $directoryMode, 'find '.escapeshellarg($dir).' -type d -exec chmod '.escapeshellarg($directoryMode).' -- {} + 2>&1'],
            'find '.escapeshellarg($dir).' -printf "'.(strlen($group)?'%u:%g':'%u').'\n" | uniq'
                => ['change the ownership of', $user, 'chown -R '.escapeshellarg($user).' '.escapeshellarg($dir).' 2>&1'],
        ];
        // First see if any files actually need their permissions changed
        foreach($commands as $checkCommand=>$details) {
            $needChanging = trim(`$checkCommand`);
            if ($needChanging!=$details[1]) {
                $cmd = $details[2];
                $cmdOutput = `$cmd`;
                if (!empty($cmdOutput)) {
                    echo "ERROR: Unable to {$details[0]} $dir (and any files and subdirectories) using the following command:\n$cmd\n";
                    echo "Got the following errors:\n$cmdOutput";
                    exit(1);
                }
            }
        }
    }
}

function upgradeDb($databaseName,$adminUsername,$adminPassword,$username,$password,$hostname,$tables) {

	class installToolsDbErrorHandler {
		private $surpress = false;
		
		function surpress( $value ) {
			$this->surpress = $value;
		}
		
		function handleError($errorCode, $basicMessage, $detailedMessage) {
			if (!$this->surpress) echo $detailedMessage."\n";
		}
	}

    $hostnameArg = escapeshellarg($hostname);
	// Create the database (and swallow any errors about it existing already)
	echo `mysql -h $hostnameArg -e "CREATE DATABASE \\\`$databaseName\\\`" 2>&1 | grep -v "database exists" `;

	// Create the web app db user
	echo `mysql -h $hostnameArg -e "REVOKE ALL ON *.* FROM '$username'@'localhost'" 2>&1`;
	echo `mysql -h $hostnameArg -e "DROP USER '$username'@'localhost'" 2>&1`;
	echo `mysql -h $hostnameArg -e "FLUSH PRIVILEGES" 2>&1`;
	echo `mysql -h $hostnameArg -e "CREATE USER '$username'@'localhost' IDENTIFIED BY '$password'" 2>&1`;
	echo `mysql -h $hostnameArg -e "GRANT USAGE ON *.* TO '$username'@'localhost'" 2>&1`;
	echo `mysql -h $hostnameArg -e "GRANT SELECT, INSERT, UPDATE, ALTER, CREATE, DROP, DELETE, LOCK TABLES ON \\\`$databaseName\\\`.* TO '$username'@'localhost'" 2>&1`;

	$errorHandler = new installToolsDbErrorHandler();
	$DB = new Dbif( $databaseName, $adminUsername, $adminPassword, $hostname, $errorHandler);

	if (!$DB->connected()) die("Couldn't connect to database");

	$existingTables = array_flip($DB->getTables());
    $pid = getmypid();

    $c=0;

    $maxTableNameLength = max(array_map('strlen', array_keys($tables)));

	foreach( $tables as $tableName=>$columns ) {
        $c++;
		$justCreated = false;
        $status = '';
		if (!isset($existingTables[$tableName])) {
			list($columnName,$columnDefinition) = each($columns);
			$DB->exec("CREATE TABLE `$tableName` (`$columnName` $columnDefinition) ENGINE=MyISAM DEFAULT CHARSET=utf8");
			$justCreated = true;
            $status = 'created';
		}
		
		$existingColumns = array_flip($DB->getColumnNames($tableName));

		// remove any existing columns that no longer exist
		foreach( $existingColumns as $columnName=>$notUsed ) {
			if (!isset($columns[$columnName])) {
				$DB->exec("ALTER TABLE `$tableName` DROP COLUMN `$columnName`");
                if ($status=='') $status = 'removed unused column(s):';
                $status .= $columnName." "; 
			}
		}
	
        # All of the temporary table shennanigans below are required because of the removal of ALTER TABLE IGNORE in MySQL 5.7.4
        # The workaround for this is to ceate a new copy of the table, Do the alters on this and then insert select the data over.	
        $tempTableName = 'temp_'.$tableName.'_'.$pid;
        $DB->exec("RENAME TABLE `$tableName` TO `{$tempTableName}`");
        # We have to use SHOW CREATE TABLE not CREATE TABLE LIKE because the latter doesn't preserve AUTO_INCREMENT_ID
        # The wait until we have moved the table to get the create SQL because at this point no more inserts can occur
        # so we know the inerst ID will be stable.
        $createSql = $DB->getRow("SHOW CREATE TABLE `$tempTableName`");
        $createSql = preg_replace('/CREATE\s+TABLE\s+`(.*?)`/',"CREATE TABLE `$tableName`",$createSql[1]);
        $DB->exec($createSql);
        # if anything queries sneak in here between the create and the lock it doesn't matter.
        $DB->exec("LOCK TABLES `$tableName` WRITE, `{$tempTableName}` WRITE");

        // Force all tables to be UTF8
        $DB->exec("ALTER TABLE `{$tableName}` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci");

        $newColumns = array();
        $indexes = array();

        $existingIndexes = array();
        $getIndexQuery = $DB->query("SHOW INDEX FROM `$tableName`");
        while( $getIndexQuery->fetchInto($row)) {
            $existingIndexes[$row['Key_name']]=true;
        }
		foreach( $columns as $columnName=>$columnDefinition ) {
			if (isset($existingColumns[$columnName])) {
				// This code doesn't handle changes to autoincrement columns
				if (preg_match('/AUTO_INCREMENT/i',$columnDefinition)) continue;
				if (preg_match('/PRIMARY KEY/i',$columnDefinition)) {
					$DB->exec("ALTER TABLE `{$tableName}` DROP PRIMARY KEY");
				}
				$DB->exec("ALTER TABLE `{$tableName}` MODIFY COLUMN `$columnName` $columnDefinition");
			} else {
				if (preg_match('/^(PRIMARY\s+|UNIQUE\s+|INDEX\s*\()/i',$columnDefinition)) {
					# for indexes
					if (preg_match('/^PRIMARY/i',$columnDefinition)) {
						if (!$justCreated && isset($existingIndexes[$columnName])) {
                            // benj 20170926 - I don't think we need to worry about dropping the index from the original table
                            //$DB->exec("ALTER TABLE `{$tempTableName}` DROP PRIMARY KEY");
                            $DB->exec("ALTER TABLE `$tableName` DROP PRIMARY KEY");
                        }
					} else {
						if (!$justCreated && isset($existingIndexes[$columnName])) {
                            // benj 20170926 - I don't think we need to worry about dropping the index from the original table
                            //$DB->exec("ALTER TABLE `{$tempTableName}` DROP INDEX `$columnName`");
                            $DB->exec("ALTER TABLE `$tableName` DROP INDEX `$columnName`");
                        }
						$columnDefinition = preg_replace('/INDEX\s*\(/',"INDEX `$columnName` (",$columnDefinition);
					}
					$indexes[$columnName] = $columnDefinition;
				} else {
					# for columns - do these later after we have sorted out the indexes
                    $newColumns[$columnName] = $columnDefinition;
				}
			}
		}

        foreach( $newColumns as $columnName=>$columnDefinition ) {
			$DB->exec("ALTER TABLE `{$tableName}` ADD COLUMN `$columnName` $columnDefinition");
			$DB->exec("ALTER TABLE `{$tempTableName}` ADD COLUMN `$columnName` $columnDefinition");
        }

        foreach( $indexes as $indexName=>$indexDefinition ) {
            $DB->exec("ALTER TABLE `{$tableName}` ADD $indexDefinition");
        }

        // Get the new table structure to see if anything actually changed...
        $newCreateSql = $DB->getRow("SHOW CREATE TABLE `$tableName`");

        // As an extra paranoia check we make sure no insterts snook in on the new temporary table
        $sneekyInserts = $DB->getValue("SELECT count(*) FROM `{$tableName}`");

        if ( !$sneekyInserts && $newCreateSql[1]===$createSql) {
            // The table hasn't changed in any way so no need to do anything - just put it all back as it was
            if ($status == '') $status = "unchanged";
            // Rename the original table back to its original name
            $DB->exec("DROP TABLE `{$tableName}`");
            $DB->exec("UNLOCK TABLES");
            $DB->exec("RENAME TABLE {$tempTableName} TO `$tableName`");
            
        } else {
            $status = "updated";
            // echo "\n$createSql\n============\n{$newCreateSql[1]}\n\n";
            $DB->exec("INSERT IGNORE INTO `{$tableName}` SELECT * FROM `{$tempTableName}`");
            $DB->exec("DROP TABLE `{$tempTableName}`");
            $DB->exec("UNLOCK TABLES");
        }



        echo str_pad($tableName,$maxTableNameLength," ",STR_PAD_RIGHT ).": $status\n";
		$existingTables[$tableName] = 1;
	}
	
	return $DB;
}

function usage($error) {
    global $argv;
    static $vars;

    if (is_array($error)) {
        $vars = $error;
        return;
    }

    if ($error) echo "ERROR: $error\n\n";

echo <<< END_USAGE
This script accepts the following parameters [square brackets indicate optional parameters]:
    SITE_NAME
    SITE_BASE_DIR
    [WWW_DATA_USER]
        What user does a directory/file need to be owned by to writable by
        the web server.
        This is usually one of either httpd, apache, or www_data depending on
        your web server and linux distribution.
        If this is omitted the following command will be used to ascertain this:
            {$vars['webUserAscertainingCommand']}
        N.B. This command will fail if the web server is not actually running.
    [WWW_DATA_GROUP]
        What group does a directory/file need to be owned by to writable by
        the web server.
        If this is ommitted the command described for WWW_DATA_USER will be used.
    [WWW_DATA_FILE_MODE]
        The octal mode required for a file to be writable by the web server.
        Defaults to 660
    [WWW_DATA_DIRECTORY_MODE]
        The octal mode required for a directories to be writable by the web
        server.
        Defaults to 770
    [INSTALL_DB_USER]
        The database user used to create the database tables and user for the 
        web app.
        This can be omitted if the user running the script has automatic MySQL
        login configured and the MySQL user has enough permissions.
        If this is omitted then command line MySQL tools will be used to create
        a temporary database user for the instalation process. The temporary
        user will be deleted by this script immediately after database has been
        setup.
    [INSTALL_DB_PASSWORD]
        This can be omitted if the user running the script has automatic MySQL
        login configured and the MySQL user has enough permissions
    [DB_USER]
        Defaults to site name if not specified
    [DB_NAME]
        Defaults to site name if not specified
    [DB_HOST]
        Defaults to localhost if not specified
    [FIRST_USER_EMAIL]
        Mandatory on first run but optional thereafter. If the user is found to
        exist in the database already then first user creation is skipped i.e.
        the password of the first user will not be reset
    [FIRST_USER_PASSWORD]
        If this is ommitted and FIRST_USER_EMAIL is set and the user does not
        exist in the database, then a new random password will be generated and
        printed out.
    [PRODUCT]
        Mandatory if using a PHP config file (see 2 below)
        If present this must be set to {$vars['product']}

These parameters can be passed in 2 ways
1. By defining them as environment variables
2. By providing the location of a PHP configuration file on the command line that defines them
    as constants e.g.
    $argv[0] install.conf.php

In the case of (2) the php file might be the same PHP file that is used to
configure the running site, or a separate PHP file used solely for the purposes
of running this install script.

In the case of (2) the php file can either be specified by an absolute or
relative path name, or just a site name. If a site name is used then this
script will look for the file ../config/<siteName> and ../config/<siteName>.php

This script needs to run as root on the first occassion in order to have the
privileges required to create and change ownership on the data directories which
need to be writable by the web server. On subsequent occassions

END_USAGE;
    exit;
}

function install($product,$writableDirectories,$tables,$dbSetup,$upgrades) {
    global $argv,$DB;

    // This is used in the usage message and below - so define it once here
    $webUserAscertainingCommand = 'ps -eo comm,euser,supgrp | grep -E "apache|apache2|nginx" | grep -v "root" | uniq';

    // Pass some variables into the usage function
    usage([
        'product' => $product,
        'webUserAscertainingCommand' => $webUserAscertainingCommand
    ]);

    $dbConfigToSave = [];

    include( 'Dbif.php');
    include( 'tools.php');

    // Check to see if a config files has been passed on the command line
    if (isset($argv[1])) {
        // Check we can find the config file
        $configFile = $argv[1];
        do {
            // absolute or relative file first
            if (file_exists($configFile)) break;
            $configFile = '../config/'.$configFile;
            if (file_exists($configFile)) break;
            $configFile = $configFile.'.php';
            if (file_exists($configFile)) break;
            $configFile = '';
        } while (false);
        if (!strlen($configFile)) usage('Couldn\'t find config file: '.$argv[1] );
        // So... we have a config file
        echo "Loading config from: ".$configFile."\n";
        include($configFile);

        // Check that the config file defined the minimum required parameters
        if (!defined('PRODUCT')) {
            usage("The configuration file must define PRODUCT");
        }
        if (strtoupper(PRODUCT) != $product) {
            die('ERROR: This is the '.$product.' installer. The provided config file '.(
                defined('PRODUCT') ?
                'is for '.PRODUCT :
                'does not have a PRODUCT defined'
            ).".\n");
        }
    }

    // See if any of the parameters have been given as environment variables
    foreach( explode(',','SITE_NAME,SITE_BASE_DIR,WWW_DATA_USER,WWW_DATA_GROUP,WWW_DATA_FILE_MODE,WWW_DATA_DIRECTORY_MODE,INSTALL_DB_USER,INSTALL_DB_PASSWORD,DB_USER,DB_NAME,DB_HOST,FIRST_USER_EMAIL,FIRST_USER_PASSWORD') as $parameter) {
        if (isset($_ENV[$parameter]) && !defined($parameter)) {
            define($parameter, $_ENV[$parameter]);
            // if any of the DB parameters have come from environment variables then we need to store them in the config file
            if (strpos($parameter,'DB_')===0) $dbConfigToSave[$parameter] = $_ENV[$parameter];
        }
    }

    if (!defined('WWW_DATA_USER') || !defined('WWW_DATA_GROUP')) {
        $cmdOutput = `$webUserAscertainingCommand`;
        $cmdOutput = trim(preg_replace('/^\w+/','',$cmdOutput));
        list($WWW_DATA_USER,$WWW_DATA_GROUP) = preg_split('/\s+|,/',$cmdOutput);
        foreach( ['WWW_DATA_USER','WWW_DATA_GROUP'] as $thing ) {
            if (!defined($thing)) {
                if (empty($$thing)) {
                    echo "$thing was not defined and my attempts to determine the correct value using the command below failed:\n$cmd\n";
                    exit(1);
                }
                define($thing,$$thing);
            }
        }
    }

    // One way or another we MUST have SITE_NAME, SITE_BASE_DIR
    if (!( defined('SITE_NAME') && defined('SITE_BASE_DIR') && defined('WWW_DATA_USER'))) {
        usage("You must define at least SITE_NAME, SITE_BASE_DIR and WWW_DATA_USER");
    }

    // Default any undefined constants
    foreach( explode(',','WWW_DATA_GROUP,WWW_DATA_FILE_MODE,WWW_DATA_DIRECTORY_MODE,DB_USER,DB_HOST,DB_NAME,FIRST_USER_EMAIL') as $parameter) {
        if (defined($parameter)) continue;

        $default = '';
        if ($parameter=='WWW_DATA_FILE_MODE') $default='660';
        if ($parameter=='WWW_DATA_DIRECTORY_MODE') $default='770';
        else if ($parameter=='DB_NAME' || $parameter=='DB_USER') $default=SITE_NAME;
        else if ($parameter=='DB_HOST') $default='localhost';
        define($parameter, $default);
        // if any of the DB parameters get defaulted then we need to store them in the config file
        if (strpos($parameter,'DB_')===0) $dbConfigToSave[$parameter] = $default;
    }

    echo "Working in base directory: ".SITE_BASE_DIR."\n";

    $fileMode = base_convert(WWW_DATA_FILE_MODE,8,10);
    $directoryMode = base_convert(WWW_DATA_DIRECTORY_MODE,8,10);
    if (!isset($writableDirectories)) $writableDirectories = [];
    $writableDirectories = array_merge( $writableDirectories,['log','data','tmp']);
    makeDirectoriesWritable( WWW_DATA_USER, WWW_DATA_GROUP, $fileMode, $directoryMode, SITE_BASE_DIR,$writableDirectories);


    // If we haven't been give an INSTALL_DB_USER then create one
    $adminUsername = '';
    if (!defined('INSTALL_DB_USER')) {
        $adminUsername = 'install_'.SITE_NAME.bin2hex(random_bytes(4));
        $adminPassword = bin2hex(random_bytes(32));
        echo "Creating temporary MySQL admin user\n";
        // use the command line to create a temporary database user we can use to create the database
        $commands = [
            ['mysql -h %s -e "CREATE USER \'%s\'@\'localhost\' IDENTIFIED BY \'%s\'" 2>&1',escapeshellarg(DB_HOST),addslashes($adminUsername),addslashes($adminPassword)],
            ['mysql -h %s -e "GRANT USAGE ON *.* TO \'%s\'@\'localhost\'" 2>&1',escapeshellarg(DB_HOST),addslashes($adminUsername)],
            ['mysql -h %s -e "GRANT GRANT OPTION, SELECT, INSERT, UPDATE, DELETE, CREATE, CREATE VIEW, DROP, INDEX, ALTER, CREATE TEMPORARY TABLES, LOCK TABLES ON \\`%s\\`.* TO \'%s\'@\'localhost\'" 2>&1',escapeshellarg(DB_HOST),DB_NAME,addslashes($adminUsername)],
        ];
        foreach( $commands as $bits ) {
            $cmd = call_user_func_array('sprintf',$bits);
            echo system($cmd);
        }
            
        define('INSTALL_DB_USER',$adminUsername);
        define('INSTALL_DB_PASSWORD',$adminPassword);
    }

    // Generate a random password for the web app to use
    define('DB_PASSWORD',bin2hex(random_bytes(32)));

    $dbConfigFilename = SITE_BASE_DIR.'/config/'.SITE_NAME.'.db.php';
    $dbConfigToSave['DB_PASSWORD'] = DB_PASSWORD;

    // Store the password in the web app config
    $config = "<? \n";
    foreach( $dbConfigToSave as $key=>$value ) {
        $config .= sprintf("define('%s','%s');\n",addslashes($key),addslashes($value));
    }
    file_put_contents($dbConfigFilename,$config);

    echo "Database config written to $dbConfigFilename\n";

    $DB = upgradeDb(DB_NAME,INSTALL_DB_USER,INSTALL_DB_PASSWORD,DB_USER,DB_PASSWORD,DB_HOST,$tables);

    // Remove the temporary database user (if we created one)
    if (strlen($adminUsername)) {
        echo "Removing temporary MySQL admin user\n";
        $cmd = sprintf('mysql -h %s -e "REVOKE ALL ON *.* FROM \'%s\'@\'localhost\';" 2>&1',escapeshellarg(DB_HOST),addslashes($adminUsername));
        echo `$cmd`;
        $cmd = sprintf('mysql -h %s -e "DROP USER \'%s\'@\'localhost\';" 2>&1',escapeshellarg(DB_HOST),addslashes($adminUsername));
        echo `$cmd`;
    }

    $extraFile = SITE_BASE_DIR.'/scripts/install_'.SITE_NAME.'.php';
    if (file_exists($extraFile)) include( $extraFile );

    $currentDbVersion = $DB->getValue('SELECT `value` FROM systemData WHERE `key`="databaseVersion"');
    if (!$currentDbVersion) $currentDbVersion = 0;

    $dbSetup();

    ksort($upgrades,SORT_NUMERIC);
    foreach( $upgrades as $version=>$code ) {
        if ($currentDbVersion<$version) {
            echo "Running upgrades for database version $currentDbVersion -> $version\n";
            $code();
            $DB->exec('REPLACE INTO systemData (`key`,`value`) VALUES ("databaseVersion",?)',$version);
            $currentDbVersion=$version;
        }
    }

    echo "All done\n";
}