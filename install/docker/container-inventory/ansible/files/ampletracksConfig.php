<?
// ================================= The following MUST be set =====================================
define('SITE_URL','https://{{ ampletracks_domain_name }}/');
define('SITE_NAME','{{ ampletracks_domain_name }}');
define('SITE_BASE_DIR','{{ ampletracks_install_path }}');

// A random sercet used for signing things. This should be a crypographically secure random string
// You can either just mash the keyboard a bit - or better use the follwing command line to
// generate some randomness:
//   head /dev/urandom | openssl base64 -in - | head -1
// This command line will add the define line to the end of your configuration file for you
// if it is not already present (ideal if you want to automate the installation)
//   $config=<path to you config file>
//   grep -q "^define('SECRET'" "$config" || echo -e "\ndefine('SECRET','"`head /dev/urandom | openssl base64 -in - | head -c 60`"');" >> $config
define('SECRET','{{ site_secret }}');

// You MUST define this on the first installation.
// However, for the purposes of the ansible script this will be passed in as an environment variable rather than setting it here
// Once ampletracks is installed you can remove this, although leaving it here doesn't do any harm.
// On first install a password will be generated for this user and printed as output from the install script
// define('FIRST_USER_EMAIL','you@yourdomain.com');

// The name for the database - change this if you like
define('DB_NAME','ampletracks');
// The name of the user to be used by the PHP code to access the database - change this if you like
define('DB_USER','ampletracks');

// The name to use for the cookie that keeps users logged in
define('AUTH_COOKIE_NAME','ampletracks_auth');

// ================================= The following are optional =====================================

// If you want to the Ampletracks code to automatically handle generation of an SSL certificate for you using LetsEncrypt
// then set this to be an email address
define('ACME_ACCOUNT_EMAIL',"{{ lets_encrypt_admin_email }}");
define('ACME_USE_STAGING_ENVIRONMENT',{{ lets_encrypt_use_staging_environment }});


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

