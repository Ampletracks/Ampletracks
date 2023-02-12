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
    global $DB;
    // check if a project already exists with this name
    $alreadyExists = $DB->getValue('SELECT id FROM project WHERE name=? AND !deletedAt AND id<>?',ws('project_name'),$id);
    if ($alreadyExists) inputError('project_name','A project with this name already exists - please choose a different name');
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
