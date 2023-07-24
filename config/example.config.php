<?
// ================================= The following MUST be set =====================================
define('SITE_URL','https://<yourdomain.com>');
define('SITE_NAME','<A name for the website e.g. <Your Institution> Ampletracks');
define('SITE_BASE_DIR','<absolute path to where ampletracks code is checked out>');

// A random sercet used for signing things. This should be a crypographically secure random string
// You can either just mash the keyboard a bit - or better use the follwing command line to
// generate some randomness:
//   head /dev/urandom | openssl base64 -in - | head -1
// This command line will add the define line to the end of your configuration file for you
// if it is not already present (ideal if you want to automate the installation)
//   $config=<path to you config file>
//   grep -q "^define('SECRET'" "$config" || echo -e "\ndefine('SECRET','"`head /dev/urandom | openssl base64 -in - | head -c 60`"');" >> $config
define('SECRET','');

// You MUST define this on the first installation.
// Once ampletracks is installed you can remove this, although leaving it here doesn't do any harm.
// On first install a password will be generated for this user and printed as output from the install script
define('FIRST_USER_EMAIL','<email address of first admin user>');

// The name for the database - change this if you like
define('DB_NAME','ampletracks');
// The name of the user to be used by the PHP code to access the database - change this if you like
define('DB_USER','ampletracks');
// You can also specity the database host if the database is hosted on a separate server to the web application
// If omitted this defaults to localhost
// define('DB_HOST','my.database.hostname');

// The name to use for the cookie that keeps users logged in
define('AUTH_COOKIE_NAME','ampletracks_auth');

// ================================= The following are optional =====================================

// If you want to the Ampletracks code to automatically handle generation of an SSL certificate for you using LetsEncrypt
// then set this to be an email address
//define('ACME_ACCOUNT_EMAIL','admin@yourdomain.com');
// If you're using LetsEncrypt in a non-production environment then uncomment the next line
//define('ACME_USE_STAGING_ENVIRONMENT',true);


// These are required for RECAPTCHA which is used to prevent brute force login attacks
// If these are not set then this protection is disabled
//define('LOGIN_RECAPTCHA_SITE_KEY','<your recaptcha site key here>');
//define('LOGIN_RECAPTCHA_SECRET_KEY','<your recaptcha secret key here>');

// You can define the name of the user and group for files and directories that need to be writable
// by the web server. These can be omitted as long as the web server is running when you run the
// install script.
//define('WWW_DATA_USER','www-data');
//define('WWW_DATA_GROUP','www-data');

// Set this to true on any development sites
// It does things like relax the requirement for the site to run over HTTPS
//define('IS_DEV',true);

// This function is included in the header of every page - you can use it for things like
// adding third party web tracking code
function globalHeaderMarkup() {?>
<?}


// ============================= The following MUST NOT be changed  ================================

define('PRODUCT', 'AMPLETRACKS');

