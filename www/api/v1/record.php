<?
namespace API;

# $API_ITEMS_PER_PAGE = 2;


$API_SQL = [
    'idList' => '
        SELECT
            record.id AS id
        FROM
            record
            LEFT JOIN record parent ON parent.id=record.parentId
            INNER JOIN project ON project.id=record.projectId
            INNER JOIN recordType ON recordType.id=record.typeId
            INNER JOIN recordData name ON name.recordId=record.id AND name.dataFieldId=recordType.primaryDataFieldId
            LEFT JOIN user owner ON owner.id=record.ownerId
        WHERE 
            record.deletedAt=0
    ',
    'getData' => '
        SELECT
            record.id AS id,
            record.apiId AS apiId,
            name.data AS name,
            project.id AS projectId,
            project.apiId AS projectApiId,
            parent.id AS parentRecordId,
            parent.apiId AS parentRecordApiId,
            record.path,
            owner.id AS ownerId,
            owner.apiId AS ownerApiId,
            recordType.id AS recordTypeId,
            recordType.apiId AS recordTypeApiId
        FROM
            record
            LEFT JOIN record parent ON parent.id=record.parentId
            INNER JOIN project ON project.id=record.projectId
            INNER JOIN recordType ON recordType.id=record.typeId
            INNER JOIN recordData name ON name.recordId=record.id AND name.dataFieldId=recordType.primaryDataFieldId
            LEFT JOIN user owner ON owner.id=record.ownerId
        WHERE 
            record.deletedAt=0 AND record.id IN (?)
    '
];

$API_ID_MAPPINGS = [
    [ 'record', 'id', 'apiId'],
    [ 'record', 'parentRecordId', 'parentRecordApiId' ],
    [ 'project', 'projectId', 'projectApiId' ],
    [ 'user', 'ownerId', 'ownerApiId' ],
    [ 'recordType', 'recordTypeId', 'recordTypeApiId' ],
];

require('../../../lib/api/startup.php');
