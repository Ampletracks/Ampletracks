<?

$requireLogin = false;

include('../lib/core/startup.php');

if (!defined('IOD_ROLE') || IOD_ROLE != 'master') exit;

function error($msg) {
    echo $msg."\n";
    exit;
}

function retryLater($id) {
    global $DB;
    $DB->exec('UPDATE iodRequest SET lastAttemptedAt=UNIX_TIMESTAMP(), attempts=attempts+1 WHERE id=?',$id);
}

if (php_sapi_name() != 'cli') error( "This script can only run from CLI" );

// Use named locks to ensure that only one copy of this script is running at a time
$result = $DB->exec('SELECT GET_LOCK("iodRequest",20)');
if (!$result) error("Couldn't get iodRequest lock - another {$argv[0]} process must be running");

list($notUsed,$mysqlDataDir) = $DB->getRow('SHOW VARIABLES LIKE "datadir"');
if (!$mysqlDataDir) error("Couldn't determine MySQL data directory");
if (!is_dir($mysqlDataDir)) error("MySQL data directory doesn't seem to exist");

include(LIB_DIR.'email.php');
if (!$EMAIL) error("Email isn't setup for this instance");

while (true) {
    // Delete any old unactioned requests - people will already have given up on these
    $DB->exec('UPDATE iodRequest SET status="abandoned" WHERE createdAt<UNIX_TIMESTAMP()-86400');

    // Double check we haven't reached the limit for today
    $createdToday = $DB->getValue('SELECT COUNT(*) FROM iodRequest WHERE deletedAt=0 AND createdAt>UNIX_TIMESTAMP()-86400');
    if ($createdToday>IOD_DAILY_LIMIT) {
        sleep(300);
        continue;
    }

    // Unlock any tasks that are due a retry
    $DB->exec('
        UPDATE iodRequest
        SET lastAttemptedAt=0
        WHERE
            lastAttemptedAt < UNIX_TIMESTAMP()-3600 AND
            attempts<5
    ');

        
    $newRequests = $DB->getHash('
        SELECT id,userData
        FROM iodRequest
        WHERE
            deletedAt=0 AND
            lastAttemptedAt=0 AND
            status="new"
    ');

    foreach($newRequests as $id=>$requestData) {
        // copy the data underlying database
        $requestData = json_decode($requestData,true);
        if (!is_array($requestData) || !isset($requestData['email'])) {
            $LOGGER->log('Email was missing from IOD request ID:'.$id);
            continue;
        }
        $secret = defined('IOD_SECRET')?IOD_SECRET:SECRET.'iodSubdomain';
        $siteHash = substr(hash('sha256',$secret.$requestData['email']),0,8);
        $secret .= $siteHash;
        $siteSubdomain = $siteHash.substr(hash('sha256',$secret),0,8);

        $newDbName = IOD_DB_PREFIX.$siteHash;
        // Check if the database exists exists already
        $alreadyExists = $DB->exec('SHOW DATABASES LIKE ?',$newDbName);
        if (!$alreadyExists) {
            $DB->update('iodRequest',['id'=>$id],[
                'dbName'=>$newDbName,
                'subDomain'=>$siteSubdomain,
            ]); 
            $DB->exec('FLUSH TABLES WITH READ LOCK');
            $cmd = sprintf(
                'cp -a %s %s',
                escapeshellarg($mysqlDataDir.DB_NAME),
                escapeshellarg($mysqlDataDir.$newDbName)
            );
            system($cmd);
            $DB->exec('UNLOCK TABLES');

            // Create a user for this instance
            $username = $newDbName;
            $password = hash('sha256',$secret.'iodDatabaseUsername');

            echo `mysql -e "DROP USER IF EXISTS '$username'@'localhost'" 2>&1`;
            echo `mysql -e "CREATE USER '$username'@'localhost' IDENTIFIED BY '$password'" 2>&1`;
            echo `mysql -e "GRANT USAGE ON *.* TO '$username'@'localhost'" 2>&1`;
            echo `mysql -e "GRANT SELECT, INSERT, UPDATE, ALTER, CREATE, DROP, DELETE, LOCK TABLES ON \\\`$newDbName\\\`.* TO '$username'@'localhost'" 2>&1`;
            echo `mysql -e "FLUSH PRIVILEGES" 2>&1`;

            // Connect to the new database
            $newDb = new Dbif( $newDbName, $username, $password );

            if (!$newDb->connected()) {
                retryLater($id);
                continue;
            } else {
                // Clear down the action log
                $newDb->delete('actionLog',[]);
                // Delete all users except for the model user
                $newDb->exec('DELETE FROM user WHERE deletedAt OR email<>?',IOD_MODEL_USER);

                // change the email, name and password for the model user
                $newDb->update('user',['email'=>IOD_MODEL_USER],$requestData);
                $newDb->close();
            }
        }

        // Send the welcome email
        $DB->update('iodRequest',['id'=>$id],['attempts'=>0,'lastAttemptedAt'=>0,'status'=>'sendWelcome']);
    }

    $emailsToSend = $DB->getHash('
        SELECT id,userData
        FROM iodRequest
        WHERE
            deletedAt=0 AND
            status="sendWelcome" AND
            lastAttemptedAt=0
    ');

    foreach( $emailsToSend as $id=>$requestData ) {
        $requestData = json_decode($requestData,true);
        if (!is_array($requestData) || !isset($requestData['email'])) {
            $LOGGER->log('Email was missing from IOD request ID:'.$id);
            continue;
        }

        if (defined('IOD_LIFETIME') && IOD_LIFETIME>0) {
            $requestData['deleteAt']=time()+IOD_LIFETIME;
            enrichRowData($requestData);
        } else {
            $requestData['deleteAtDate'] = 'never';
        }

        $subDomain = $DB->getValue('SELECT subDomain FROM iodRequest WHERE id=?',$id);
        $requestData['siteUrl'] = 'https://'.$subDomain.'.'.IOD_DOMAIN.'/';

        $sendResult = $EMAIL->send([
            'template' => 'iod-success',
            'to' => [$requestData['email']],
            'priority' => 'immediate',
            'mergeData' => $requestData
        ]);
        if (!$sendResult) {
            $LOGGER->log(implode(' & ',$EMAIL->errors()));
            $retryLater($id);
        } else {
            $DB->update('iodRequest',['id'=>$id],[
                'deleteAt'=>$requestData['deleteAt'],
                'lastAttemptedAt'=>0,
                'attempts'=>0,
                'status'=>'running'
            ]);
        }
    }

    exit;
}
