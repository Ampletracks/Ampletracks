<?

// Database setup
$tables = array(
    'actionLog' => array(
        'id' => "INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY",
        'time' => "INT(10) UNSIGNED NOT NULL",
        'entity' => "VARCHAR(255) NOT NULL",
        'entityId' => "INT(10) UNSIGNED NOT NULL",
        'userId' => "INT(10) UNSIGNED NOT NULL",
        'message' => "TEXT NOT NULL",
        'index_userId' => "INDEX (`userId`,`entityId`)",
        'index_time' => "INDEX (`time`,`entityId`)",
        'index_entityId' => "INDEX (`entityId`)",
    ),
    'apiIdTablePrefix' => array(
        'tableName' => "VARCHAR(128) NOT NULL",
        'prefix' => "VARCHAR(4) NOT NULL",
        'index_tableName' => "UNIQUE INDEX (`tableName`)",
        'index_prefix' => "UNIQUE INDEX (`prefix`)",
    ),
    'apiInputSpecification' => array(
        'endpointPath' => 'VARCHAR(255) NOT NULL',
        'method' => 'VARCHAR(10) NOT NULL',
        'requestBodySchemaJson' => 'TEXT NOT NULL',
        'index_primary' => 'PRIMARY KEY (endpointPath, method)'
    ),
    'cms' => array(
        'id' => "INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY",
        'label' => "VARCHAR(255) NOT NULL",
        'content' => "mediumTEXT NOT NULL",
        'defaultContent' => "mediumTEXT NOT NULL",
        'allowMarkup' => "TINYINT(3) UNSIGNED NOT NULL DEFAULT '1'",
        'lookup' => "CHAR(33) DEFAULT NULL",
        'index_label' => "INDEX (`label`)",
        'index_lookup' => "INDEX (`lookup`)",
    ),
    'cmsPage' => array(
        'id' => "INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY",
        'page' => "mediumTEXT NOT NULL",
        'index_page' => "INDEX (`page`(250))",
    ),
    'cmsPageLabel' => array(
        'cmsId' => "INT(10) UNSIGNED NOT NULL",
        'pageId' => "INT(10) UNSIGNED NOT NULL",
        'index_pageIdCmsId' => "UNIQUE INDEX (`pageId`,`cmsId`)",
    ),
    'configuration' => array(
        'id' => "INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY",
        'name' => "varchar(255) DEFAULT NULL",
        'description' => "TEXT DEFAULT NULL",
        'value' => "TEXT DEFAULT NULL",
        'path' => "varchar(255) DEFAULT NULL",
        'index_name' => "UNIQUE INDEX (`name`(120))",
    ),
    'dataField' => array(
        'id' => "INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY",
        'orderId' => "SMALLINT(5) UNSIGNED NOT NULL",
        'recordTypeId' => "INT(10) UNSIGNED NOT NULL",
        'name' => "VARCHAR(255) DEFAULT NULL",
        'exportName' => "VARCHAR(255) DEFAULT NULL",
        'typeId' => "TINYINT(3) UNSIGNED NOT NULL",
        'optional' => "TINYINT(3) UNSIGNED NOT NULL DEFAULT 1",
        'saveInvalidAnswers' => "ENUM('never','never but save version','only if unset','only if unset but save version','always') NOT NULL DEFAULT 'only if unset but save version'",
        'shortHelp' => "MEDIUMTEXT NOT NULL DEFAULT ''",
        'longHelp' => "MEDIUMTEXT NOT NULL DEFAULT ''",
        'question' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'unit' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'displayOnList' => "TINYINT(3) UNSIGNED NOT NULL DEFAULT 0",
        'displayToPublic' => "TINYINT(3) UNSIGNED NOT NULL DEFAULT 0",
        'deletedAt' => "INT(10) UNSIGNED NOT NULL DEFAULT 0",
        'parameters' => "TEXT NOT NULL DEFAULT ''",
        'logChanges' => "ENUM ('no','basic','detailed') DEFAULT 'detailed'",
        'allowUserDefault' => "TINYINT(3) UNSIGNED NOT NULL DEFAULT 0",
        'inheritance' => "ENUM('normal','none','default','immutable') DEFAULT 'none'",
        'dependencyCombinator' => "ENUM ('and','or') DEFAULT 'and'",
        'questionLastChangedAt' =>  "INT(10) UNSIGNED NOT NULL DEFAULT 0",
        'index_orderId' => "INDEX (`recordTypeId`,`orderId`)",
        'index_recordTypeId' => "INDEX (`recordTypeId`,`displayOnList`)",
    ),
    'dataFieldDependency' => array(
        'id' => "INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY",
        'dependentDataFieldId' => "INT(10) UNSIGNED NOT NULL",
        'dependeeDataFieldId' => "INT(10) UNSIGNED NOT NULL",
        'test' => "CHAR(3) NOT NULL",
        'testValue' => "VARCHAR(4096) NOT NULL",
        'index_dependeeDataFieldId' => "INDEX (`dependeeDataFieldId`)",
        'index_dependdentDataFieldId' => 'UNIQUE INDEX (`dependentDataFieldId`,`dependeeDataFieldId`,`test`,`testValue`(256))'
    ),
    'dataFieldType' => array(
        'id' => "TINYINT(3) UNSIGNED NOT NULL PRIMARY KEY",
        'name' => "VARCHAR(255) DEFAULT NULL",
        'hasValue' => "TINYINT(3) UNSIGNED NOT NULL",
        'disabled' => "TINYINT(3) UNSIGNED NOT NULL DEFAULT 0",
    ),
    'email' => array(
        'id' => "INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY",
        'emailTemplateId'  => "INT(10) UNSIGNED NOT NULL",
        'priority' => "ENUM('low','medium','high','immediate') NOT NULL",
        'status' => "ENUM('held','sent','error','new') NOT NULL DEFAULT 'new'",
        'deletedAt'  => "INT(10) UNSIGNED NOT NULL DEFAULT 0",
        'lastError' => "MEDIUMTEXT DEFAULT NULL DEFAULT ''",
        'sendAfter' => "INT(10) UNSIGNED NOT NULL DEFAULT 0",
        'sendAttempts' => "INT(10) UNSIGNED NOT NULL DEFAULT 0",
        'lastSendAttemptedAt' => "INT(10) UNSIGNED NOT NULL DEFAULT 0",
        'fromDetails' => "MEDIUMTEXT NOT NULL DEFAULT ''",
        'index_priority' => 'INDEX (`priority`,`status`,`sendAfter`,`deletedAt`)',
        'index_sendAttempts' => 'INDEX (`sendAttempts`,`status`,`deletedAt`)',
        'index_emailTemplateId' => 'INDEX (`emailTemplateId`,`deletedAt`)',
    ),
    'emailTemplate' => array(
        'id' => "INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY",
        'name' => "VARCHAR(255) NOT NULL",
        'body' => "MEDIUMTEXT DEFAULT ''",
        'subject' => "VARCHAR(255) NOT NULL",
        'defaultStatus' => "ENUM('held','new') NOT NULL DEFAULT 'new'",
        'disabled' => "TINYINT(3) UNSIGNED NOT NULL DEFAULT 0",
        'extraCc' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'extraBcc' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'index_name' => 'UNIQUE INDEX (`name`)',
    ),
    'emailRecipient' => array(
        'emailId'  => "INT(10) UNSIGNED NOT NULL",
        'type' => "ENUM('to','cc','bcc') NOT NULL",
        'emailAddressId' => "INT(10) UNSIGNED NOT NULL",
        'index_emailId' => 'INDEX (`emailId`)',
        'index_emailAddressId' => 'INDEX (`emailAddressId`)'
    ),
    'emailAddress' => array(
        'id' => "INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY",
        'name' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'email' => "VARCHAR(255) NOT NULL",
        'index_emailName' => 'UNIQUE INDEX (`email`(100),`name`(100))'
    ),
    'failedLogin' => array(
        'hashedUsername'    => "CHAR(32)",
        'lastFailedAt'      => "INT(10) UNSIGNED NOT NULL",
        'attempts'          => "INT(10) UNSIGNED NOT NULL DEFAULT 1",
        'index_primary'     => "UNIQUE INDEX (hashedUsername)",
        'index_lastFailedAt'    => "INDEX(lastFailedAt)",
    ),
    'impliedAction' => array(
        'action' => "ENUM('list','view','edit','delete','create') NOT NULL",
        'impliedAction' => "ENUM('list','view','edit','delete','create') NOT NULL",
        'index_action' => "UNIQUE INDEX (`action`,`impliedAction`)",
    ),
    'impliedLevel' => array(
        'level' => " ENUM('global','project','own') NOT NULL",
        'impliedLevel' => " ENUM('global','project','own') NOT NULL",
        'index_level' => "UNIQUE INDEX (`level`,`impliedLevel`)",
    ),
    'iodRequest' => array(
        'id'        => "INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY",
        'createdAt' => "INT(10) UNSIGNED NOT NULL",
        'completedAt' => "INT(10) UNSIGNED NOT NULL DEFAULT 0",
        'deletedAt' => "INT(10) UNSIGNED NOT NULL DEFAULT 0",
        'userData'  => 'TEXT NOT NULL',
        'email'     => 'VARCHAR(255) NOT NULL',
        'status'    => 'ENUM("new","sendWelcome","running","deleted","abandoned")',
        'attempts'  => 'TINYINT UNSIGNED DEFAULT 0',
        'lastAttemptedAt' => "INT(10) UNSIGNED NOT NULL DEFAULT 0",
        'deleteAt'  => 'INT(10) UNSIGNED NOT NULL DEFAULT 0',
        'dbName'    => 'VARCHAR(255) NOT NULL',
        'subDomain'    => 'VARCHAR(255) NOT NULL',
        'index_email'   => "INDEX (email,status)",
        'index_status'   => "INDEX (status,completedAt)",
    ),
    'label' => array(
        'id'                    => "INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY",
        'securityCode'          => "CHAR(60)",
        'version'               => "TINYINT(3) UNSIGNED NOT NULL",
        'recordId'              => "INT(10) UNSIGNED NOT NULL",
        'assignedBy'            => "INT(10) UNSIGNED NOT NULL",
        'assignedAt'            => "INT(10) UNSIGNED NOT NULL",
        'index_recordId'        => "INDEX (recordId)",
    ),
    'number' => array(
        'number'            => "INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY",
    ),
    'passwordReset' => array(
        'id'                => "INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY",
        'userId'            => "INT(10) UNSIGNED NOT NULL",
        'createdAt'         => "INT(10) UNSIGNED NOT NULL",
        'validationCode'    => "CHAR(32)",
        'index_createdAt'   => "INDEX(`createdAt`)",
        'index_userId'      => "INDEX(`userId`)",
    ),
    'relationship' => array(
        'id'                    => "INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY",
        'relationshipLinkId'    => "INT(10) UNSIGNED NOT NULL",
        'fromRecordId'             => "INT(10) UNSIGNED NOT NULL",
        'toRecordId'             => "INT(10) UNSIGNED NOT NULL",
        'reciprocalRelationshipId'    => "INT(10) UNSIGNED NOT NULL",
        'index_fromRecordId'        => "UNIQUE INDEX(`fromRecordId`,`relationshipLinkId`,`toRecordId`)",
        'index_toRecordId'        => "INDEX(`toRecordId`,`fromRecordId`)",
    ),
    'relationshipPair' => array(
        'id'            => "INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY",
        'deletedAt'     => "INT(10) UNSIGNED NOT NULL DEFAULT 0",
    ),
    'relationshipLink' => array(
        'id'                    => "INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY",
        'relationshipPairId'    => "INT(10) UNSIGNED NOT NULL",
        'fromRecordTypeId'         => "INT(10) UNSIGNED NOT NULL",
        'toRecordTypeId'         => "INT(10) UNSIGNED NOT NULL",
        'description'            => "VARCHAR(255) NOT NULL DEFAULT ''",
        'max'                    => "INT(10) UNSIGNED NOT NULL DEFAULT 1",
        'index_relationshipPairId' => "INDEX(`relationshipPairId`)",
    ),
    'site' => array(
        'id' => "INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY",
        'fqdn' => "VARCHAR(255) NOT NULL DEFAULT ''",
    ),
    'systemAlert' => array(
        'id' => "INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY",
        'time' => "INT(10) UNSIGNED NOT NULL",
        'file' => "VARCHAR(255) NOT NULL",
        'lineNumber' => "INT(10) UNSIGNED NOT NULL",
        'userId' => "INT(10) UNSIGNED NOT NULL",
        'message' => "TEXT NOT NULL",
        'userType' => "ENUM('user','admin') DEFAULT NULL",
        'fixedAt' => "INT(10) UNSIGNED NOT NULL",
        'index_userId' => "INDEX (`userId`)",
        'index_fixedAt' => "INDEX (`fixedAt`)",
        'index_time' => "INDEX (`time`,`userId`)",
    ),
    'systemData' => array(
        'key'           => 'VARCHAR(255) NOT NULL',
        'value'         => 'TEXT NOT NULL',
        'index_key'     => 'UNIQUE INDEX( `key` )',
    ),
    'testLookup' => array(
        'id' => "TINYINT(3) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY",
        'test' => "CHAR(3) NOT NULL",
        'name' => "VARCHAR(255) DEFAULT NULL",
        'hasValue' => "TINYINT UNSIGNED NOT NULL",
        'index_test' => "UNIQUE INDEX (`test`)",
    ),
    'project' => array(
        'id' => "INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY",
        'apiId' => "VARCHAR(40) NOT NULL DEFAULT ''",
        'name' => "VARCHAR(255) NOT NULL",
        'deletedAt' => "INT(10) UNSIGNED NOT NULL DEFAULT 0",
        'index_name' => "UNIQUE INDEX(`name`,`deletedAt`)",
    ),
    'record' => array(
        'id' => "INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY",
        'typeId' => "INT(10) UNSIGNED NOT NULL",
        'createdBy' => "INT(10) UNSIGNED NOT NULL",
        'createdAt' => "INT(10) UNSIGNED NOT NULL",
        'ownerId' => "INT(10) UNSIGNED NOT NULL",
        'deletedAt' => "INT(10) UNSIGNED NOT NULL DEFAULT 0",
        'lastSavedAt' => "INT(10) UNSIGNED NOT NULL DEFAULT 0",
        'hiddenFields' => "TEXT NOT NULL DEFAULT ''",
        'parentId' => "INT(10) UNSIGNED NOT NULL DEFAULT 0",
        'projectId' => "INT(10) UNSIGNED NOT NULL DEFAULT 0",
        'path' => "TEXT NOT NULL DEFAULT ''",
        'depth' => "TINYINT UNSIGNED DEFAULT 0",
        'shareLinkSecret' => "VARCHAR(32) NOT NULL DEFAULT ''",
        'index_recordTypeId' => "INDEX (`typeId`,`deletedAt`)",
        'index_projectId' => "INDEX (`projectId`,`deletedAt`)",
        'index_ownerId' => "INDEX (`ownerId`,`deletedAt`)",
        'index_parentId' => "INDEX (`parentId`)",
        'index_path' => "INDEX (`path`(256),`depth`)",
        'index_depth' => "INDEX (`depth`)",
    ),
    'recordData' => array(
        'recordId' => "INT(10) UNSIGNED NOT NULL",
        'dataFieldId' => "INT(10) UNSIGNED NOT NULL",
        'data' => "TEXT NOT NULL DEFAULT ''",
        'inherited' => "TINYINT UNSIGNED NOT NULL DEFAULT 0",
        'fromRecordId' => "INT(10) UNSIGNED NOT NULL DEFAULT 0",
        'hidden' => "TINYINT UNSIGNED NOT NULL DEFAULT 0",
        'valid' => "TINYINT UNSIGNED NOT NULL DEFAULT 1",
        'index_recordId' => "UNIQUE INDEX(`recordId`,`dataFieldId`)",
        'index_dataFieldId' => "INDEX(`dataFieldId`,`data`(128))",
    ),
    'recordDataChildLock' => array(
        'recordId' => "INT(10) UNSIGNED NOT NULL",
        'lockedByRecordId' => "INT(10) UNSIGNED NOT NULL",
        'index_recordId' => "UNIQUE INDEX(`recordId`, `lockedByRecordId`)",
        'index_lockedByRecordId' => "INDEX(`lockedByRecordId`)",
    ),
    'recordDataVersion' => array(
        'recordId' => "INT(10) UNSIGNED NOT NULL",
        'dataFieldId' => "INT(10) UNSIGNED NOT NULL",
        'data' => "TEXT NOT NULL",
        'hidden' => "TINYINT UNSIGNED NOT NULL",
        'valid' => "TINYINT UNSIGNED NOT NULL",
        'saved' => "TINYINT UNSIGNED NOT NULL",
        'userId' => "INT(10) UNSIGNED NOT NULL",
        'savedAt' => "INT(10) UNSIGNED NOT NULL",
        'inherited' => "TINYINT UNSIGNED NOT NULL DEFAULT 1",
        'fromRecordId' => "INT(10) UNSIGNED NOT NULL",
        'index_recordId' => "INDEX(`recordId`,`dataFieldId`,`savedAt`)",
        'index_dataFieldId' => "INDEX(`dataFieldId`,`data`(128))",
        'index_userId' => "INDEX(`userId`,`recordId`)",
    ),
    'recordType' => array(
        'id' => "INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY",
        'name' => "VARCHAR(255) NOT NULL",
        'colour' => "CHAR(7) DEFAULT ''",
        'publicPreviewMessage' => "MEDIUMTEXT NOT NULL",
        'primaryDataFieldId' => "INT(10) UNSIGNED NOT NULL",
        'builtInFieldsToDisplay' => "VARCHAR(255) NOT NULL DEFAULT 'id|labelId|project|path|relationships'",
        'projectId' => "INT(10) UNSIGNED NOT NULL DEFAULT 0",
        'deletedAt' => "INT(10) UNSIGNED NOT NULL DEFAULT 0",
        'index_name' => "UNIQUE INDEX(`name`,`deletedAt`)",
        'index_projectId' => "INDEX(`projectId`,`name`,`deletedAt`)",
    ),
    'role' => array(
        'id' => "INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY",
        'name' => "VARCHAR(255) NOT NULL",
        'deletedAt' => "INT(10) UNSIGNED NOT NULL",
        'index_name' => "UNIQUE INDEX(`name`)",
    ),
    'rolePermission' => array(
        'id' => "INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY",
        'roleId' => "INT(10) UNSIGNED NOT NULL",
        'level' => " ENUM('global','project','own') NOT NULL",
        'entity' => "ENUM('actionLog','cms','configuration','dataField','user','recordTypeId','relationshipLink','project','recordType','superuser') NOT NULL",
        'recordTypeId' => "INT UNSIGNED NOT NULL DEFAULT 0",
        'action' => "ENUM('list','view','edit','delete','create') NOT NULL",
        'index_roleId' => "INDEX (`roleId`,`entity`,`action`,`level`)",
    ),

    'userLibrary' => array(
        'id' => "INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY",
        'userId' => "INT(10) UNSIGNED NOT NULL",
        'type' => "ENUM('chemical') NOT NULL",
        'name' => "VARCHAR(255) NOT NULL",
        'value'=> "TEXT NOT NULL",
        'index_userId' => "INDEX (`userId`,`type`,`name`)"
    ), 
    'user' => array(
        'id' => "INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY",
        'apiId' => "VARCHAR(40) NOT NULL DEFAULT ''",
        'firstName' => "VARCHAR(255) NOT NULL",
        'lastName' => "VARCHAR(255) NOT NULL",
        'email' => "VARCHAR(255) NOT NULL",
        'mobile' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'password' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'deletedAt' => "INT(10) UNSIGNED NOT NULL DEFAULT 0",
        'lastLoggedInAt' => "INT(10) UNSIGNED NOT NULL DEFAULT 0",
        'lastLoginIp' => "INT(10) UNSIGNED NOT NULL DEFAULT 0",
        'createdAt' => "INT(10) UNSIGNED NOT NULL DEFAULT 0",
        'recordTypeFilter' => "INT(10) UNSIGNED NOT NULL DEFAULT 0",
        'defaultsLastChangedAt' =>  "INT(10) UNSIGNED NOT NULL DEFAULT 0",
        'fontScale' => "TINYINT UNSIGNED NOT NULL DEFAULT 0",
        'index_email' => "UNIQUE INDEX (`email`,`deletedAt`)",
        'index_apiKey' => "UNIQUE INDEX (`apiId`)",
        'index_lastName' => "INDEX (`lastName`(40),`deletedAt`,`firstName`(40))",
        'index_deletedAt' => "INDEX (`deletedAt`)",
    ),
    'userAPIKey' => array(
        'id' => "INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY",
        'userId' => "INT(10) UNSIGNED NOT NULL",
        'name' => "VARCHAR(255) NOT NULL DEFAULT''",
        'apiKey' => "VARCHAR(128) NOT NULL DEFAULT''",
        'createdAt' => "INT(10) UNSIGNED NOT NULL DEFAULT 0",
        'deletedAt' => "INT(10) UNSIGNED NOT NULL DEFAULT 0",
        'index_apiKey' => "UNIQUE INDEX (`apiKey`)",
        'index_userId' => "INDEX (`userId`)",
    ),
    'userDefaultAnswer' => array(
        'id' => "INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY",
        'userId' => "INT(10) UNSIGNED NOT NULL",
        'question' => "VARCHAR(255) NOT NULL",
        'matchType' => "ENUM('exact','anywhere','regexp') DEFAULT 'exact'",
        'answer' => "VARCHAR(255) NOT NULL",
        'orderId' => "SMALLINT UNSIGNED NOT NULL DEFAULT 9999",
        'index_userId' => "UNIQUE INDEX (`userId`,`question`(100),`matchType`)",
    ),
    'userDefaultAnswerCache' => array(
        'userId' => "INT(10) UNSIGNED NOT NULL",
        'userDefaultAnswerId' => "INT(10) UNSIGNED NOT NULL",
        'dataFieldId' => "INT(10) UNSIGNED NOT NULL",
        'savedAt' => "INT(10) UNSIGNED NOT NULL",
        'index_userId' => "UNIQUE INDEX (`userId`,`dataFieldId`)",
    ),
    'userProject' => array(
        'id' => "INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY",
        'userId' => "INT(10) UNSIGNED NOT NULL",
        'projectId' => "INT(10) UNSIGNED NOT NULL",
        'orderId' => "SMALLINT UNSIGNED NOT NULL DEFAULT 0",
        'index_userId' => "INDEX (`userId`,`projectId`)",
        'index_roleId' => "INDEX (`projectId`)",
    ),
    'userRecordType' => array(
        'id' => "INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY",
        'userId' => "INT(10) UNSIGNED NOT NULL",
        'recordTypeId' => "INT(10) UNSIGNED NOT NULL",
        'index_userId' => "INDEX (`userId`,`recordTypeId`)",
        'index_recordTypeId' => "INDEX (`recordTypeId`)",
    ),
    'userRecordAccess' => array(
        'id' => "INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY",
        'userId' => "INT(10) UNSIGNED NOT NULL",
        'recordId' => "INT(10) UNSIGNED NOT NULL",
        'accessType' => "ENUM('view','edit')",
        'accessedAt' => "INT(10) UNSIGNED NOT NULL",
        'index_userId' => "INDEX (`userId`,`recordId`)",
        'index_accessedAt' => "INDEX (`accessedAt`,`userId`,`recordId`)",
        'index_recordId' => "INDEX (`recordId`,`accessedAt`)",
    ),
    'userRole' => array(
        'id' => "INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY",
        'userId' => "INT(10) UNSIGNED NOT NULL",
        'roleId' => "INT(10) UNSIGNED NOT NULL",
        'index_userId' => "UNIQUE INDEX (`userId`,`roleId`)",
        'index_roleId' => "INDEX (`roleId`)",
    ),
    'word' => array(
        'id' => "INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY",
        'word' => "VARCHAR(255) NOT NULL",
        'index_word' => "UNIQUE INDEX (`word`)",
    ),
    'tag' => array(
        'id' => "INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY",
        'check' => "TINYINT UNSIGNED NOT NULL",
        'word1' => "INT(10) UNSIGNED NOT NULL",
        'word2' => "INT(10) UNSIGNED NOT NULL",
        'word3' => "INT(10) UNSIGNED NOT NULL",
        'word4' => "INT(10) UNSIGNED NOT NULL",
        'index_words' => "UNIQUE INDEX (`word1`,`word2`,`word3`,`word4`)",
    ),
);

$dbSetup = function() {
    global $DB;
    
    $DB->exec("
    REPLACE INTO impliedAction VALUES
    ('list','list'),
    ('view','view'),
    ('view','list'),
    ('edit','edit'),
    ('edit','view'),
    ('edit','list'),
    ('delete','delete'),
    ('delete','list'),
    ('create','create')
    ");
    $DB->exec("
    REPLACE INTO impliedLevel VALUES
    ('own','own'),
    ('project','own'),
    ('project','project'),
    ('global','own'),
    ('global','project'),
    ('global','global')
    ");

    $DB->exec("REPLACE INTO rolePermission (id,roleId,level,entity,recordTypeId,action) VALUES (1,1,'global','superuser',0,'edit')");
    $DB->exec("REPLACE INTO role (id,name,deletedAt) VALUES (1,'superuser',0)");
   
    if (defined('FIRST_USER_EMAIL') && !empty(FIRST_USER_EMAIL)) {
        $firstUserAlreadyExists = $DB->getValue('SELECT id FROM user WHERE email=? and deletedAt=0',FIRST_USER_EMAIL);
        if (!$firstUserAlreadyExists) {
            echo "Creating first user\n";
            if (!defined('FIRST_USER_PASSWORD')) {
                define('FIRST_USER_PASSWORD',substr(base64_encode(random_bytes(32)),0,20));
                echo "Admin user created with the following details:\n Username: ".FIRST_USER_EMAIL."\n Password: ".FIRST_USER_PASSWORD."\n";
            }
            // Avoid the admin user having a predictable ID
            $userId = $DB->getValue('SELECT MAX(id) FROM user');
            $userId += random_int(1,9999);
            $DB->insert('user',[
                'id'              => $userId,
                'firstName'       => 'First Install',
                'lastName'        => 'User',
                'email'           => FIRST_USER_EMAIL,
                'password'        => password_hash(FIRST_USER_PASSWORD,PASSWORD_DEFAULT)
            ]);
    
            $DB->exec('REPLACE INTO userRole ( userId, roleId ) VALUES (?,1)',$userId);
        }
    }

    $DB->exec('INSERT IGNORE INTO configuration (name,description,value,path) VALUES
        ("Minimum user password length","The minimum permissable password length for user accounts","10","/"),
        ("Shortcut Icon","URL of the shortcut icon (commonly known as the favicon)","","/"),
        ("Hide add button on record list","Set this to \"Yes\" to remove the \"Add\" button on the record list - this means that new records can only be created from the record type list page, or as children of existing records.","","/record/list.php"),
        ("Cobranding logo URL","URL for the logo which is presented alongside the Ampletracks logo","/images/brand-logo.png","/"),
        ("New account request email","Email address where requests to create a new account are sent. Leave this empty to disable this functionality. You can specify multiple space-separated addresses.","","/"),
        ("Timezone","The system timezone","","/"),
        ("Font scale factor","Scale up or down font sizes across the site. Defaults to 100%","","/"),
        ("Custom header markup","Any markup you add here will be injected into the header of every page. One common application for this is to add any analytics tracking code.","","/"),
        ("Font scale factor","Scale up or down font sizes across the site. Defaults to 100%. This is combined with (multiplied by) any user-specific scale factor","","/"),
        ("Pause email delivery","Set this to \"yes\" to pause all outgoing email delivery.","no","/"),
        ("Email engine","Choose the email delivery engine - currently only supported engine is SMTP - if this is empty then email sending is disabled.","SMTP","/"),
        ("Email SMTP username","Username used to connect to the SMTP server if email delivery engine is SMTP.","","/"),
        ("Email SMTP password","Password used to connect to the SMTP server if email delivery engine is SMTP.","","/"),
        ("Email SMTP port","Port used to connect to the SMTP server if email delivery engine is SMTP.","587","/"),
        ("Email SMTP server","Domain name of the SMTP server if email delivery engine is SMTP.","","/"),
        ("Email SMTP encryption mechanism","This must be either SMTPS or STARTTLS.","STARTTLS","/"),
        ("Email from name","Any emails sent by the system will use this as their from name. This is optional.","","/"),
        ("Email from address","Any emails sent by the system will use this as their from address. This must be set for email to work","","/"),
        ("Email reply-to name","Any emails sent by the system will use this as their reply-to name. This is optional.","","/"),
        ("Email reply-to address","Any emails sent by the system will use this as their reply-to address. This is optional.","","/"),
        ("Email sending throttle per minute","This determines the maximum number of queued emails the system will send per minute. This throttle is applied in addition to the per day and per hour throttles. N.B. This throttle will not prevent the delivery of any \"immediate priority\" emails such as forgotten password retreival emails, however immediate priority emails that have been sent do count towards the throttle. Empty (or any non-integer value) means unlimited.","100","/cron/email.php"),
        ("Email sending throttle per hour","This determines the maximum number of queued emails the system will send per hour. This throttle is applied in addition to the per minute and per day throttles. N.B. This throttle will not prevent the delivery of any \"immediate priority\" emails such as forgotten password retreival emails, however immediate priority emails that have been sent do count towards the throttle. Empty (or any non-integer value) means unlimited.","1000","/cron/email.php"),
        ("Email sending throttle per day","This determines the maximum number of queued emails the system will send per day. This throttle is applied in addition to the per minute and per hour throttles. N.B. This throttle will not prevent the delivery of any \"immediate priority\" emails such as forgotten password retreival emails, however immediate priority emails that have been sent do count towards the throttle. Empty (or any non-integer value) means unlimited.","50000","/cron/email.php"),
        ("Only send emails to","Emails will only be sent to these addresses/domains. This is primarily intended for testing/development sites where you don\'t want emails being sent out to most users, but it might have other applications. This is a comma separated list. If this list is empty all emails will be sent. If there is one or more entries in this list then only emails which match one of the entries on this list will be sent. Matching is done based on a partial match anchored at the END of the email address e.g. .domain.com  matches all addresses for any subdomain of domain.com; @my.domain.com macthes all addresses at my.domain.com; name@domain.com matches name@domain.com and also my.name@domain.com","","/")
    ');
    
    $words='his,that,from,word,other,were,which,time,each,tell,also,play,small,home,hand,port,large,spell,even,land,here,must,high,kind,need,house,animal,point,mother,world,near,build,self,earth,father,work,part,take,place,made,after,back,little,only,round,man,year,came,show,every,good,under,name,very,just,form,great,think,help,line,differ,turn,much,mean,before,move,right,boy,old,many,write,like,long,make,thing,more,day,number,sound,most,people,water';
    
    foreach(explode(',',$words) as $word) {
        $DB->exec('INSERT IGNORE INTO word (word) VALUES(?)',$word);
    }
    
    $DB->exec('REPLACE INTO testLookup (id,test,name,hasValue) VALUES ( 1,"eq","Equals",1),( 2,"cl","Contains all of",1),( 3,"cy","Contains any of",1),( 4,"gt","Greater than",1),( 5,"lt","Less than",1),( 6,"bt","Between (inclusive)",1),( 7,"sw","Starts with",1),( 8,"ew","Ends with",1),( 9,"em","Is empty",1),(10,"!em","Not empty",1),(11,"!eq","Not equals",1),(12,"!cl","Doesn\'t contain all of",1),(13,"!cy","Doesn\'t contains any of",1),(14,"!gt","Less than or equal to",1),(15,"!lt","Greater than or equal to",1),(16,"!bt","Outside",1),(17,"!sw","Doesn\'t start with",1),(18,"!ew","Doesn\'t end with",1),(19,"vi","Is visible",0),(20,"!vi","Is not visible",0),(21,"sn","Set but not equal to",1)');
    
    # Add 10000 numbers into the numbers table
    for ($i=0; $i<100; $i++) {
        $sql = "REPLACE INTO number (number) VALUES";
        for ($j=0; $j<100; $j++) {
            $sql.='('.($i*100+$j).'),';
        };
        $DB->exec(substr($sql,0,-1));
    }
    
    $DB->exec('INSERT IGNORE INTO dataFieldType (id,name,hasValue,disabled) VALUES
    ( 1,"Divider","0","0"),
    ( 2,"Commentary","0","0"),
    ( 3,"Integer","1","0"),
    ( 4,"Textbox","1","0"),
    ( 5,"Textarea","1","0"),
    ( 6,"Select","1","0"),
    ( 7,"Date","1","0"),
    ( 8,"Duration","1","0"),
    ( 9,"Email Address","1","0"),
    (10,"URL","1","0"),
    (11,"Upload","1","0"),
    (12,"Image","1","0"),
    (13,"Float","1","0"),
    (14,"Type To Search","1","1"),
    (15,"Suggested Textbox","1","0"),
    (16,"Chemical Formula","1","0")
    ');
    
    // set any missing path depths
    $DB->exec('update record set depth=length(path)-length(replace(path,",","")) WHERE depth=0');

    // Create email templates if they don't exist
    $emailTemplateDir = SITE_BASE_DIR.'/scripts/defaultEmailTemplates';
    if (is_dir($emailTemplateDir)) {
        $titleDisplayed = false;
        $directory = new RecursiveDirectoryIterator($emailTemplateDir, RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new RecursiveIteratorIterator($directory, RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $fileinfo) {
            if ($fileinfo->isFile() && $fileinfo->getExtension() === 'html') {
                $fullPath = $fileinfo->getRealPath();
                $templateName = substr($fullPath, strlen($emailTemplateDir) + 1);
                $templateName = preg_replace('/\.html$/i','',$templateName);
                $templateContent = file_get_contents( $fullPath );
                list( $subject, $body ) = explode("\n",$templateContent,2);

                $templateData = $DB->getRow('SELECT * FROM emailTemplate WHERE name=?',$templateName);
                if (!$templateData) {
                    if (!$titleDisplayed) {
                        echo "Importing default email templates...\n";
                        $titleDisplayed = true;
                    }
                    echo "    Adding $templateName. Subject: $subject\n";
                    $DB->insert('emailTemplate',[
                        'body' => $body,
                        'subject' => $subject,
                        'defaultStatus' => 'new',
                        'disabled' => 0,
                        'name' => $templateName
                    ]);
                }
            }
        }
    }

    // Add these as needed, and add an `apiId` column to the relevant table definition
    $DB->exec('
        INSERT IGNORE INTO apiIdTablePrefix (tableName, prefix) VALUES
        ("user", "u"),
        ("project", "p")
    ');
};

$writableDirectories = ['data/images','data/tmp/uploads','data/system','data/acme','data/emailMergeData'];


# ======================================================================================
# Version specific changes

$upgrades = array(
    // =====================================================================================================
    2 => function() {
        global $DB;
        $DB->exec('UPDATE record SET ownerId=createdBy WHERE ownerId=0');
    },
    3 => function() {
        global $DB;
        $firstUser = $DB->getValue('SELECT id FROM user WHERE deletedAt=0 ORDER BY id ASC LIMIT 1');
        $DB->exec('UPDATE label SET assignedBy=?, assignedAt=UNIX_TIMESTAMP() WHERE recordId>0 AND assignedAt=0',$firstUser);
    },
    4 => function() {
        global $DB;
        #$DB->exec('INSERT IGNORE INTO userRole (userId, roleId) SELECT id,1 FROM user WHERE deletedAt=0');
        #$DB->exec('INSERT IGNORE INTO project (id,name) VALUES(1,"Default project")');
        #$DB->exec('UPDATE record SET projectId=1');
    },
    5 => function() {
        global $DB;
        $needUpdating = $DB->getHash('SELECT id, name FROM dataField WHERE ISNULL(exportName)');
        foreach( $needUpdating as $id=>$name) {
            $DB->update('dataField',['id'=>$id],['exportName'=>toCamelCase($name)]);
        }
    },
    6 => function() {
        global $DB;
        $DB->exec('UPDATE dataField SET allowUserDefault=1 WHERE typeId IN (SELECT id FROM dataFieldType WHERE hasValue)');
    },

    // =====================================================================================================
);

# ======================================================================================

$postSetup = function(){
    global $DB;
    if (defined('IOD_ROLE') && IOD_ROLE=='master') {
        $DB->exec('GRANT RELOAD ON *.* TO ?@"localhost"',DB_USER);
    }
};

include( __DIR__.'/../lib/core/installTools.php');

install('AMPLETRACKS',$writableDirectories,$tables,$dbSetup,$upgrades,$postSetup);
