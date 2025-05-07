<?

function processUpdateBefore($id) {
    global $DB,$parameterName, $originalValue;
    if ($id) {
        list( $parameterName, $originalValue) = $DB->getRow('SELECT name, value FROM configuration WHERE id = ?', $id);
    }
}

function processUpdateAfter() {
    global $DB, $parameterName, $originalValue;
    if ($parameterName=='S3 upload path prefix' && $originalValue <> ws('configuration_value')) {
        // ALL S3 Uploads will need to have their path checked
        $DB->exec('UPDATE s3Upload SET needsPathCheck=1 WHERE deletedAt=0');
    }
}

include( '../../lib/core/adminPage.php' );
