<?

$INPUTS = array(
    'update'    => array(
    )
);

function processDeleteBefore( $id ) {
    global $DB;

    // Check that there aren't any live instances of this relationship
    $hasLiveInstance = $DB->getValue('
        SELECT * FROM relationship
            INNER JOIN relationshipLink ON relationshipLink.id=relationship.relationshipLinkId
            # We only need to check for the record at one end since the query will pick up both relationshipLinks
            # The other relationshipLink will check the record at the other end.
            # Aha... but what if we get a situation where the record at only one end of a relationship has been deleted
            # hmm.... so we better join to both ends
            INNER JOIN record fromRecord ON fromRecord.id=relationship.fromRecordId AND !fromRecord.deletedAt
            INNER JOIN record toRecord ON toRecord.id=relationship.toRecordId AND !toRecord.deletedAt
        WHERE relationshipLink.relationshipPairId=?
        LIMIT 1
    ',$id);

    return !$hasLiveInstance;
}

function processUpdateBefore( $id ) {
    global $DB;
    if ($id) {

        global $WS; dump($WS);
        $updated = false;
        // The only things they are allowed to edit are the max and description, NOT the record types
        foreach (array('forward','backward') as $direction) {
            $updates = [];
            foreach (array('max','description') as $thing) {
                $value = ws($direction.ucfirst($thing));
                if (strlen($value)) $updates[$thing] = $value;
            }
            if (count($updates)) {
                $updated = true;
                $DB->update('relationshipLink',array(
                    'relationshipPairId'    => $id,
                    'fromRecordTypeId'      => ws($direction=='forward' ? 'fromRecordTypeId':'toRecordTypeId')
                ),$updates);
            }
        }
        if (!$updated) return false;
            
        // Set up a trivial update to keep the CORE happy
        ws('relationshipPair_id',$relationshipPairId);        
    } else {
        $fromRecordTypeId = (int)ws('fromRecordTypeId');
        $toRecordTypeId = (int)ws('toRecordTypeId');
        
        // Creating a new relationshipPair
        $relationshipPairId = $DB->insert('relationshipPair',array('id'=>0));
        foreach (array(0,1) as $direction) {
            $DB->insert('relationshipLink',array(
                'relationshipPairId'    => $relationshipPairId,
                'fromRecordTypeId'      => $direction ? $fromRecordTypeId : $toRecordTypeId,
                'toRecordTypeId'        => $direction ? $toRecordTypeId : $fromRecordTypeId,
                'description'           => $direction ? ws('forwardDescription') : ws('backwardDescription'),
                'max'                   => $direction ? (int)ws('forwardMax') : (int)ws('backwardMax'),
            ));
        }
        ws('id',$relationshipPairId);
        // Set up a trivial update to keep the CORE happy
        ws('relationshipPair_id',$relationshipPairId);
    }
}

function processUpdateAfter( $id ) {
}

function prepareDisplay( $id ) {
    global $DB;

    if ($id) {
        $DB->returnHash();
        // Load in the relationship data
        $relationshipData = $DB->getRows('SELECT max, description, fromRecordTypeId, toRecordTypeId FROM relationshipLink WHERE relationshipPairId = ?',$id);
        ws('fromRecordTypeId',$relationshipData[0]['fromRecordTypeId']);
        ws('toRecordTypeId',$relationshipData[0]['toRecordTypeId']);
        ws('forwardDescription',$relationshipData[0]['description']);
        ws('backwardDescription',$relationshipData[1]['description']);
        ws('forwardMax',$relationshipData[0]['max']);
        ws('backwardMax',$relationshipData[1]['max']);
    }
    
    global $recordTypeSelect;
    $recordTypeSelect = new formOptionbox( 'relationshipPair_fromRecordTypeId');
    $recordTypeSelect->addLookup(array('
        SELECT name,id
        FROM
            recordType
        WHERE
            !recordType.deletedAt
        ORDER BY recordType.name ASC
    '));
    if ($id) $recordTypeSelect->disable();
}

include( '../../lib/core/adminPage.php' );
