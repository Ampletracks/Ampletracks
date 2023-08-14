<?

define('PRIMARY_FILTER_NAME','recordType');

if (!defined('IS_DEV')) define('IS_DEV',false);
if (!defined('SITE_NAME')) define('SITE_NAME','AmpleTracks');

if (!defined('LABEL_SECURITY_CODE_HASH_COST')) define('LABEL_SECURITY_CODE_HASH_COST',11);
if (!defined('LABEL_SECURITY_CODE_LENGTH')) define('LABEL_SECURITY_CODE_LENGTH',6);
if (!defined('LABEL_SECURITY_CODE_KEYSPACE')) define('LABEL_SECURITY_CODE_KEYSPACE','ABCDEFGHIJKLMNOPQRSTUVWXYZ');
if (!defined('LABEL_QR_CODE_BASE_URL')) define('LABEL_QR_CODE_BASE_URL','http://mpltr.ac/');
if (!defined('LABEL_QR_CODE_ERROR_CORRECTION')) define('LABEL_QR_CODE_ERROR_CORRECTION','M'); // can be L,M,Q,H meaning Low - High

require_once("core/form.php");
require_once("core/cms.php");
require_once("tools.php");
require_once("svgIcons.php");
require_once("auth.php");
require_once("permissions.php");
