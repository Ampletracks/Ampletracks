<?
namespace API;

# $API_ITEMS_PER_PAGE = 2;

$API_SQL = [
    'idList' => '
        SELECT DISTINCT project.id
        FROM project
        INNER JOIN userProject ON userProject.projectId = project.id
        WHERE project.deletedAt=0
    ',
    'getData' => '
        SELECT
            project.id AS id,
            project.name,
            CONCAT(?, "_", project.apiId) AS apiId
        FROM project
        WHERE project.deletedAt=0 AND project.id IN (?)
    '
];

require('../../../lib/api/startup.php');

