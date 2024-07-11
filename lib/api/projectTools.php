<?
namespace API;

require_once(LIB_DIR.'/api/baseClasses.php');

define('API_PER_PAGE_PROJECT', 2);


function getProjectList($entity, $vars, $filters) {
    if($entity != 'project' && $entity != 'NEXT_PAGE') {
        throw new ApiException('Bad entity');
    }
    if(!canDo('list', 'project')) {
        throw new ApiException('Forbidden', 403);
    }

    $page = 1;
    if($entity == 'NEXT_PAGE') {
        $qryFileId = $vars['listId'];
        $page = $vars['page'];
    } else {
        $qryFileId = '
            SELECT DISTINCT project.id
            FROM project
            INNER JOIN userProject ON userProject.projectId = project.id
            WHERE 1 = 1
        ';
        $limits = getUserAccessLimits(['entity' => 'project', 'prefix' => '']);
        $allFilters = array_merge($filters, $limits);
        $conditions = makeConditions($allFilters, 'apiFilter_');
        if($conditions) {
            $qryFileId .= " AND $conditions";
        }
    }
    $startIdx = ($page - 1) * API_PER_PAGE_PROJECT;

    $idStreamer = new IdStreamer($qryFileId, 'project', $startIdx);
    $ids = [];
    foreach($idStreamer->getIds(API_PER_PAGE_PROJECT) as $id) {
        $ids[] = $id;
    }

    global $DB;
    $apiIdPrefix = getAPIIdPrefix('project');
    $DB->returnHash();
    $projects = $DB->getRows('
        SELECT
            project.id AS realId,
            project.name,
            CONCAT(?, "_", project.apiId) AS id
        FROM project
        WHERE project.id IN (?)
    ', $apiIdPrefix, $ids);
    $projects = checkSetApiIds($projects, 'project');

    $numRecords = $idStreamer->getNumIds();
    $numPages = ceil($numRecords / API_PER_PAGE_PROJECT);
    $nextPageUrl = $page < $numPages ? '/api/v1'.$idStreamer->getPageUrl($page + 1) : '';
    return [
        'data' => $projects,
        'metadata' => [
            'numRecords' => $numRecords,
            'numPages' => $numPages,
            'nextPageUrl' => $nextPageUrl,
            'pageNumber' => $page,
        ],
    ];
}
