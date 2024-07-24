<?

function createRelationship($fromRecordId, $toRecordId, $relationshipLinkId, $userId = null) {
    global $USER_ID, $DB;
    if (is_null($userId)) $userId = $USER_ID;

    // Check the validity of the relationship they are trying to create
    // And get the reciprocalRelationshipLinkId at the same time
    $recordTypeLookup = $DB->getHash('SELECT record.id,record.typeId FROM record WHERE id IN(?)',[$fromRecordId,$toRecordId]);
    if (count($recordTypeLookup)<2) return 'Either or both of the records doesn\'t exist';

    $toRecordTypeId = $recordTypeLookup[$toRecordId];
    $fromRecordTypeId = $recordTypeLookup[$fromRecordId];

    $reciprocalRelationshipLinkId = $DB->getValue('
        SELECT reciprocalRelationshipLink.id
        FROM relationshipLink
            INNER JOIN relationshipLink reciprocalRelationshipLink ON
                reciprocalRelationshipLink.relationshipPairId=relationshipLink.relationshipPairId AND
                reciprocalRelationshipLink.fromRecordTypeId=relationshipLink.toRecordTypeId AND
                reciprocalRelationshipLink.toRecordTypeId=relationshipLink.fromRecordTypeId AND
                reciprocalRelationshipLink.id <> relationshipLink.id
        WHERE
            relationshipLink.id=? AND relationshipLink.fromRecordTypeId=? AND relationshipLink.toRecordTypeId=?
    ',$relationshipLinkId, $fromRecordTypeId, $toRecordTypeId);

    if (!$reciprocalRelationshipLinkId) return 'Can\'t find reciprocal relationship';

    // Check that they have permissions to at least view both records
    if (!canDo('view', $toRecordId, 'recordType:'.$toRecordTypeId, $userId)) {
        return 'You are not allowed to create a relationship to the destination record';
    }
    if (!canDo('view', $fromRecordId, 'recordType:'.$fromRecordTypeId, $userId)) {
        return 'You are not allowed to create a relationship from the source record';
    }

    // check that this isn't a duplicate relationship
    $isDuplicate = $DB->getValue(
        'SELECT id FROM relationship WHERE fromRecordId=? AND toRecordId=? AND relationshipLinkId=?',
        $fromRecordId,$toRecordId,$relationshipLinkId
    );
    if ($isDuplicate) return 'This relationship already exists';

    // Insert the forward relationship
    $forwardRelationshipId = $DB->insert('relationship',array(
        'fromRecordId'             => $fromRecordId,
        'toRecordId'               => $toRecordId,
        'relationshipLinkId'       => $relationshipLinkId,
        'reciprocalRelationshipId' => 0,
    ));
    if (!$forwardRelationshipId) return 'There was a problem creating the forward relationship';

    // Then create the reciprocal relationship
    $reciprocalRelationshipId = $DB->insert('relationship',array(
        'fromRecordId'             => $toRecordId,
        'toRecordId'               => $fromRecordId,
        'relationshipLinkId'       => $reciprocalRelationshipLinkId,
        'reciprocalRelationshipId' => $forwardRelationshipId,
    ));

    $success = false;
    if( $reciprocalRelationshipId ) {
        // Update the forward relationship to point to the new reciprocal
        $success = $DB->update('relationship',['id'=>$forwardRelationshipId],['reciprocalRelationshipId'=>$reciprocalRelationshipId]);
    }

    if (!$success) {
        $DB->delete('relationship',['id'=>[$forwardRelationshipId,$reciprocalRelationshipId]]);
        return 'Database error encountered whilst creating relationship';
    }

    return true;
}