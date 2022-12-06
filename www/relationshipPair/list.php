<?

$listSql = '
    SELECT
        relationshipPair.id,
        rl1.description AS forwardDescription,rl1.max AS forwardMax,
        rl2.description AS backwardDescription,rl2.max AS backwardMax,
        fromRecordType.name AS fromRecordType,
        toRecordType.name AS toRecordType,
        SUM(IF((relationship.id IS NOT NULL) AND IFNULL(fromRecord.deletedAt,1)=0 AND IFNULL(fromRecord.deletedAt,1)=0,1,0)) AS numInstances
    FROM
        relationshipPair
        INNER JOIN relationshipLink rl1 ON rl1.relationshipPairId=relationshipPair.id
        INNER JOIN relationshipLink rl2 ON rl2.relationshipPairId=relationshipPair.id
        INNER JOIN recordType fromRecordType ON fromRecordType.id=rl1.fromRecordTypeId
        INNER JOIN recordType toRecordType ON toRecordType.id=rl1.toRecordTypeId
        LEFT JOIN relationship ON relationship.relationshipLinkId=rl1.id
        LEFT JOIN record fromRecord ON fromRecord.id=relationship.fromRecordId
        LEFT JOIN record toRecord ON toRecord.id=relationship.toRecordId
    WHERE rl1.id<rl2.id AND !relationshipPair.deletedAt
    GROUP BY relationshipPair.id
';

include('../../lib/core/listPage.php');
