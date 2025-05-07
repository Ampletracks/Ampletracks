<?

$INPUTS = array(
    'delete' => array(
        'recipientProjectId' => 'INT'
    )
);

function processDeleteBefore($id) {
    global $DB;

    // See if there are records that need a new home
    $recordsNeedRehoming = $DB->getValue('SELECT COUNT(*) FROM record WHERE deletedAt=0 AND projectId=?',$id);
    if (!$recordsNeedRehoming) return true;

    // Check the recipient Project ID is valid
    $recipientProjectId = $DB->getValue('SELECT id FROM project WHERE deletedAt=0 AND id<>? AND id=?',$id,ws('recipientProjectId'));
    if (!$recipientProjectId) return false;

    $DB->update('record',['projectId'=>$id],['projectId'=>$recipientProjectId]);

    return true;
}

function processUpdateBefore( $id ) {
    global $DB, $originalProjectName;
    // check if a project already exists with this name
    $alreadyExists = $DB->getValue('SELECT id FROM project WHERE name=? AND !deletedAt AND id<>?',ws('project_name'),$id);
    if ($alreadyExists) inputError('project_name','A project with this name already exists - please choose a different name');

    $originalProjectName = null;
    if ($id) {
        $originalProjectName = $DB->getValue('SELECT name FROM project WHERE id=?',$id);
    }
}

function processUpdateAfter( $id ) {
    global $DB, $originalProjectName;

    // If the project name has changed then we need to update any s3Uploads that are marked as usesProject
    if (!is_null($originalProjectName) && $originalProjectName != ws('project_name')) {

        // We need to update only the s3Uploads attached to records in this project and which are marked as usesProject
        $DB->exec('
            UPDATE s3Upload
            INNER JOIN record ON record.id=s3Upload.recordId
            SET s3Upload.needsPathCheck=UNIX_TIMESTAMP()
            WHERE
                record.projectId=?
                record.deletedAt=0 AND
                s3Upload.deletedAt=0 AND
                s3Upload.usesProject=1
        ',$id);
    }

}

function prepareDisplay( $id ) {
    global $projectSelect;
    $projectSelect = new formPicklist( 'reassignProjectId', array('
        SELECT
            project.name AS `option`,
            project.id AS `value`
        FROM
            project
        WHERE
            !project.deletedAt
        ORDER BY project.name ASC
    ',$id));
    
}

include( '../../lib/core/adminPage.php' );
