<?
/*
This include requires the following constants to be defined:

IOD_FAILURE_REDIRECT => URL to redirect user to if the subdomain is invalid
IOD_SECRET => Secret used to sign IOD subdomain
IOD_DB_PREFIX => Database name prefix for IOD databases
IOD_DOMAIN => IOD domain suffix
*/

(function(){
    // IOD = Instance On Demand
    define('IOD_ROLE','slave');

    $domainName=$_SERVER['HTTP_HOST'];
    if (!preg_match('/^([0-9a-f]{16}).\Q'.IOD_DOMAIN.'\E$/',$domainName,$matches)) {
        header('Location: '.IOD_FAILURE_REDIRECT);
        exit;
    }
    $siteHash = substr($matches[1],0,8);
    $signature = substr($matches[1],8);
    $secret = IOD_SECRET.'iodSubdomain'.$siteHash;
    define('SECRET',$secret);
    $desiredSignature = substr(hash('sha256',SECRET),0,8);
    if (!hash_equals($signature,$desiredSignature)) {
        header('Location: '.IOD_FAILURE_REDIRECT);
        exit;
    }
    define('DB_NAME',IOD_DB_PREFIX.$siteHash);
    define('DB_USER',DB_NAME);
    define('DB_PASSWORD',hash('sha256',SECRET.'iodDatabaseUsername'));

    define('SITE_URL','https://'.$domainName.'/');
    define('SITE_NAME',$domainName);
})();

function onDbConnectFailure($dbName,$dbUser,$dbPassword,$dbHost) {
    echo "The ampletracks demonstration site you are trying to access has expired and been deleted";
    exit;
}
