<?
namespace API;

# $API_ITEMS_PER_PAGE = 2;

$API_SQL = [
    'getIdList' => '
        SELECT DISTINCT project.id
        FROM project
        INNER JOIN userProject ON userProject.projectId = project.id
        WHERE project.deletedAt=0
    ',
    'getListData' => '
        SELECT
            project.id AS id,
            project.name,
            CONCAT("p_", project.apiId) AS apiId
        FROM project
        WHERE project.deletedAt=0 AND project.id IN (?)
    '
];

require('../../../lib/api/startup.php');

