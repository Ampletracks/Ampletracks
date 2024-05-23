<?

$INPUTS = array(
    '.*' => array(
        'record_typeId' 		=> 'INT',
        'subMode' 				=> 'TEXT',
        'anchor'				=> 'TEXT',
        'labelId'               => 'INT',
    ),
    'update' => array(
        'dataField'     		=> 'ARRAY ANY',
        'saveDefault'     		=> 'ARRAY INT',
        'dataFieldInherited' 	=> 'ARRAY INT',
        'relationshipLinkId'	=> 'INT',
        'toRecordId'			=> 'INT',
        'hiddenFields'  		=> 'TEXT',
        'record_ownerId'        => 'INT',
        'record_projectId'      => 'INT',
    ),
    'relationshipOptions' => array(
		'recordTypeId'			=> 'INT',
		'relationship'			=> 'TEXT',
	),
	'ttsSearch' => array(
		'relationshipLinkId'	=> 'INT',
		'ttsSearch'				=> 'TEXT'
	),
);

function postStartup($mode,$id) {
    include( LIB_DIR.'/dataField.php');
    global $DB, $dontProcessUploads, $USER_ID;

    $permissionsModeMap['showRelationships']='view';
    $permissionsModeMap['infoPanel']='view';

    // This function isn't relevant for some modes
    if ( in_array($mode,['relationshipOptions','ttsSearch','deleteRelationship','showRelationships','getShareLink','infoPanel'])) return;

    if ($id==0) {
        if (!ws('record_typeId')) ws('record_typeId',getPrimaryFilter());
    }
 
    // Surpress the automatic processing of uploads normally done by the CORE
    // We want to handle uploads ourselves
    $dontProcessUploads=true;

    global $permissionsEntity,$permissionsModeMap;

    $currentProjectId = 0;
    if ($id) {
        global $DB;
        list( $recordTypeId, $currentProjectId, $currentOwnerId, $createdBy, $lastSavedAt ) = $DB->getRow('SELECT typeId,projectId,ownerId,createdBy,lastSavedAt FROM record WHERE id=?',$id);
        $permissionsEntity = 'recordTypeId:'.$recordTypeId;

        if($mode == 'logEditAccess') {
            // Update a 'view' access from this user in the last couple of seconds as it's probably from
            // initially hitting the page with '#edit' on the end
            $updated = $DB->exec('
                UPDATE userRecordAccess
                SET accessType = "edit"
                WHERE userId = ?
                AND recordId = ?
                AND accessedAt >= ?
            ', $USER_ID, $id, time() - 2);
            if(!$updated) {
                $DB->insert('userRecordAccess', ['userId' => $USER_ID, 'recordId' => $id, 'accessType' => 'edit', 'accessedAt' => time()]);
            }
            echo 'OK';
            exit;
        } else if ($mode=='' || $mode=='view') {
            $DB->insert('userRecordAccess', ['userId' => $USER_ID, 'recordId' => $id, 'accessType' => 'view', 'accessedAt' => time()]);
        }
    } else { // No record ID provided...
        $permissionsEntity = 'recordTypeId:'.ws('record_typeId');
        $currentOwnerId = 0;
    }

    if (isSuperuser()) $limitSql='(TRUE OR project.id=? )';
    else $limitSql = '( !ISNULL(userProject.projectId) OR project.id=? )';
    
    global $projectSelect;
    $projectSelect = new formOptionbox( 'record_projectId');
    $projectSelect->addLookup("
        SELECT
            project.name,
            project.id
        FROM
            project
            LEFT JOIN userProject ON userProject.projectId=project.id AND userProject.userId=?
        WHERE
            project.deletedAt=0 AND
            $limitSql
        ORDER BY project.name
        ",$USER_ID,$currentProjectId
    );

    global $ownerSelect;
    $ownerSelect = new formOptionbox( 'record_ownerId', array('No one'=>0));
    if (isSuperuser()) {
        $ownerSelect->addLookup('
            SELECT CONCAT(firstname," ",lastName) AS name,id FROM user WHERE deletedAt=0 ORDER BY name
        ');
    } else {
        // see if they have project wide edit rights for records of this type
        // we do this by setting the ownerId to zero in the canDo() call
        if ( canDo('edit', 0,$currentProjectId, $permissionsEntity )) {
            // Allow them to pick only people in the same project(s) as them
            $ownerSelect->addLookup('
                SELECT
                    CONCAT(firstname," ",lastName) AS name,id
                FROM user
                WHERE
                    deletedAt=0 AND
                    id IN (
                        SELECT up2.userId FROM userProject up1
                        INNER JOIN userProject up2 ON up2.projectId=up1.projectId
                        WHERE up1.userId=?
                        UNION SELECT ?
                    )
                ORDER BY name
            ',$USER_ID,$currentOwnerId);
        } else {
            global $USER_FIRST_NAME, $USER_LAST_NAME;
            $ownerSelect->addOption($USER_FIRST_NAME.' '.$USER_LAST_NAME,$USER_ID);
        }
    }

    // When creating new records...
    // ... set the default owner to be the person who is logged in
    // ... and set the project to the first project on their list - but don't do this when we create the empty shell record - do it at the second stage just below
    if (!$id) {
        ws('record_ownerId',$USER_ID);
    }

    // Record creation is a two-stage process - first the empty shell record is created, then this shell is editted
    // This first edit is in effect part of the creation process so we should tell the permissions system that this
    // is still creation, and not editting
    global $permissionsMode;
    if ($id && $currentProjectId==0 && $lastSavedAt==0 && $createdBy=$USER_ID) {
        $permissionsMode='create';

        // This is the point at which we need to set the default project
        // Get the default project for this user
        $userDefaultProject = $DB->getValue('
            SELECT projectId
            FROM userProject
            WHERE userId=?
            ORDER BY orderId ASC
            LIMIT 1
        ',$USER_ID);
        $projectSelect->setDefault($userDefaultProject);
    }
}

function processInputs($mode,$id) {
    global $DB, $dataFields, $USER_ID;
  
    $isSuperuser = isSuperuser();
 
    if ($mode=='ttsSearch') {
		$ttsSearch = ws('ttsSearch');
		$labelId = preg_match('/^\d+$/',$ttsSearch) ? (int)$ttsSearch : 0;
		
		$sql = '
			SELECT
                record.Id,
                CONCAT(
                    record.Id,":",
                    recordData.data,
                    IF( label.id IS NOT NULL,CONCAT(" (Label: ",label.id,")"),""),
                    IF( user.firstName LIKE ? OR user.lastName LIKE ?, CONCAT(" - ", user.firstName, " ", user.lastName),"")
                ) AS name
			FROM
				relationshipLink
				INNER JOIN recordType ON recordType.id=relationshipLink.toRecordTypeId
				INNER JOIN dataField ON dataField.id=recordType.primaryDataFieldId
				INNER JOIN record ON record.typeId=recordType.id AND !record.deletedAt AND record.lastSavedAt
                LEFT JOIN user ON user.id=record.ownerId
				INNER JOIN recordData ON recordData.recordId=record.id AND recordData.dataFieldId=dataField.id
				LEFT JOIN label ON label.recordId=record.id';
        if (!$isSuperuser) {
            $sql .= '
                # If they arent superuser then make sure they have access to the record...
                INNER JOIN rolePermission ON rolePermission.roleId IN (?) AND rolePermission.entity="recordTypeId" AND rolePermission.recordTypeId=recordType.id AND rolePermission.action="list" AND ( rolePermission.level="global" OR (rolePermission.level="own" AND record.ownerId=?) OR (rolePermission.level="project" AND record.projectId IN (?)) )
            ';
        }
        $sql .= '
			WHERE
				relationshipLink.id=? AND
				record.id <> ? AND
				(recordData.data LIKE ? OR user.firstName LIKE ? OR user.lastName LIKE ? OR label.id=? OR record.id=?)
			LIMIT 300
		';

        $likeSearch = '%'.ws('ttsSearch').'%';
        if ($isSuperuser) {
    		$options = $DB->getHash($sql,$likeSearch,$likeSearch,ws('relationshipLinkId'),$id,$likeSearch,$likeSearch,$likeSearch,$labelId,$labelId);
        } else {
            $userRoleIds = $DB->getColumn('SELECT roleId FROM userRole WHERE userId=?',$USER_ID);
            $userProjectIds = $DB->getColumn('SELECT projectId FROM userProject WHERE userId=?',$USER_ID);
    		$options = $DB->getHash($sql,$likeSearch,$likeSearch, $userRoleIds, $USER_ID, $userProjectIds, ws('relationshipLinkId'),$id,$likeSearch,$likeSearch,$likeSearch,$labelId,$labelId);
        }
		echo json_encode($options);
		exit;
	}
    
    if ($mode=='relationshipOptions') {
		
		$DB->returnHash();
		$details = $DB->getHash('
			SELECT
				relationshipLink.id,
				recordType.name,
				reciprocal.description AS reciprocalRelationshipDescription,
				reciprocal.id AS reciprocalRelationshipLinkId,
				recordType.id AS recordTypeId,
				COUNT( DISTINCT record.id) AS count,
				relationshipLink.max,
				0 AS existing
			FROM
				relationshipLink
				INNER JOIN relationshipPair ON relationshipPair.id=relationshipLink.relationshipPairId AND !relationshipPair.deletedAt
				INNER JOIN relationshipLink reciprocal ON reciprocal.relationshipPairId=relationshipPair.id AND reciprocal.id<>relationshipLink.id
				INNER JOIN recordType ON recordType.id=relationshipLink.toRecordTypeId
				LEFT JOIN record ON record.typeId=recordType.id AND !record.deletedAt AND record.lastSavedAt
			WHERE
				
				relationshipLink.fromRecordTypeId=? AND
				relationshipLink.description=?
			GROUP BY recordType.id
		',ws('recordTypeId'),ws('relationship'));
		
		$queryh = $DB->query('
			SELECT
				relationship.relationshipLinkId AS id, COUNT(record.id) AS existing
			FROM
				relationship
				LEFT JOIN record ON record.id=relationship.toRecordId AND !record.deletedAt
			WHERE
				relationship.fromRecordId=? AND
				relationship.relationshipLinkId IN (?)
			GROUP BY relationship.relationshipLinkId
		', $id, array_keys($details));
		
		while( $queryh->fetchInto($row) ) {
			$details[$row['id']]['existing'] = $row['existing'];
		}
		
		echo json_encode($details);
		exit;
	}

    if ($mode=='deleteRelationship' && $id) {

        // In order to delete a relationship they must have edit rights to at least one of the 2 records involved
        list($from,$to) = $DB->getRow('SELECT fromRecordId,toRecordId FROM relationship WHERE id=?',$id);
        if (!( canDo('edit',$from,'record') || canDo('edit',$to,'record'))) {
            echo "You do not have permission to delete this relationship";
            exit;
        }

		$DB->exec('DELETE FROM relationship WHERE id=? OR reciprocalRelationshipId=?',$id,$id);
		echo 'OK';
		exit;
	}

    if ($mode=='showRelationships' && $id) {
		include(CORE_DIR.'/search.php');
		$relationshipList = new search('record/relationshipList',array('
			SELECT
				relationship.id,
				relationshipLink.description,
				recordType.name AS recordType,
				record.id AS recordId,
				IFNULL(recordData.data,CONCAT("problem determining record name - please check the primary data field for the ",recordType.name," record type")) AS name
			FROM
				relationship
				INNER JOIN relationshipLink ON relationshipLink.id=relationship.relationshipLinkId
				INNER JOIN relationshipPair ON relationshipPair.id=relationshipLink.relationshipPairId
				INNER JOIN record ON record.id=relationship.toRecordId
				INNER JOIN recordType ON recordType.id=record.typeId
				LEFT JOIN recordData ON recordData.recordId=record.id AND recordData.dataFieldId=recordType.primaryDataFieldId
			WHERE
				relationship.fromRecordId=? AND
				!relationshipPair.deletedAt AND
				!record.deletedAt
		',$id));
		$relationshipList->display(true);
		exit;
	}

    if ($mode=='clone' && $id) {
        $newRecordId=0;
        global $USER_ID;
        $DB->duplicateData('record',array('id'=>$id),array('id'=>'','createdAt'=>time(),'createdBy'=>$USER_ID),null,function($row,$recordId) use (&$newRecordId,$id) {
            $newRecordId=$recordId;
            global $DB;
            $DB->duplicateData('recordData',array('recordId'=>$id),array('recordId'=>$recordId));
            $DB->duplicateData('recordDataChildLock',array('recordId'=>$id),array('recordId'=>$recordId));
        }); 
        // Correct the path for the new record
        $DB->exec('UPDATE record SET path = REGEXP_REPLACE(path,?,?) WHERE id=?',$id.',$',$newRecordId.',',$newRecordId);

        // Non-inherited record data should have the fromRecordId pointing to itself
        // I don't think it really matters but this will be consistent with what we do elsewhere
        $DB->exec('UPDATE recordData SET fromRecordId = recordId WHERE recordId=? AND inherited=0',$newRecordId);

        header('Location: admin.php?id='.$newRecordId);
        exit;
    }

    if ($mode=='getShareLink' && $id) {
        header('Content-type: application/json');
        include_once(LIB_DIR.'/shareLinkTools.php');
        $url = getShareLink($id);
        echo json_encode([
            'status' => 'OK',
            'url' => $url
        ]);
        exit;
    }

    if ($mode!=='update') {
        if(!$id) {
            $redirect = 'admin.php?mode=update&parentId='.(int)ws('parentId');
            if (ws('labelId')) $redirect.='&labelId='.(int)ws('labelId');
            if (ws('record_typeId')) $redirect.='&record_typeId='.(int)ws('record_typeId');
            header('Location: '.$redirect);
            exit;
        } else if(ws('labelId')) {
            header('Location: admin.php?mode=update&id='.(int)ws('id').'&labelId='.(int)ws('labelId'));
            exit;
        }
    }

    if($id) ws('parentId', $DB->getValue('SELECT parentId FROM record WHERE id = ?', $id));
    global $parentAnswers;
    $parentAnswers = array();
    if(ws('parentId')) {
        $parentAnswers = $DB->getHash('
            SELECT
                recordData.dataFieldId,
                recordData.`data`
            FROM
                recordData
            WHERE
                recordData.recordId=?
        ', ws('parentId'));
    }

    // Build the set of dataField objects
    $dataFields = datafield::buildAllForRecord( $id );
}

function processUpdateBefore( $id ) {
    global $WS, $DB, $USER_ID;
    if (empty($id)) {
        if (!ws('record_typeId')) ws('record_typeId',getPrimaryFilter());
		
        if (ws('parentId')) ws('record_parentId',(int)ws('parentId'));

		ws('record_createdBy',$USER_ID);
		ws('record_createdAt',time());
    } else {
        ws('record_lastSavedAt',time());

        // Check they are setting the ownerId and projectId to values they are allowed to choose
        global $ownerSelect, $projectSelect;
        if (isset($WS['record_ownerId']) && !in_array($WS['record_ownerId'],$ownerSelect->options)) {
            unset($WS['record_ownerId']);
        }
        if (isset($WS['record_projectId']) && !in_array($WS['record_projectId'],$projectSelect->options)) {
            unset($WS['record_projectId']);
        }
    }
}

function processUpdateAfter( $id, $isNew ) {
    global $DB, $USER_ID, $dataFields, $editMode;
    
    $editMode=true;
    
    // Save all the data fields

    $hiddenFields = ws('hiddenFields');
    // Make all the hidden fields ID's into integers
    $hiddenFields = array_map('intval',explode(',',$hiddenFields));
    // Remove any zeros
    $hiddenFields = array_filter($hiddenFields);

    $saveDefault = ws('saveDefault');
    forceArray($saveDefault);
    $recordData =  ws('dataField');
    forceArray($recordData);
    $recordInherited = ws('dataFieldInherited');
    forceArray($recordInherited);
    
    global $parentAnswers;

    $saveDefault = array_filter($saveDefault);

    $defaultsChanged = 0;

    foreach( $dataFields as $dataFieldId=>$dataField) {

        // Don't save any fields that weren't submitted.
        // BEWARE - if you remove this next line then a bug appears when just assigning a label and storing no data
        // e.g. calling: /record/admin.php?mode=update&id=297&labelId=1493
        // this will remove the content of the first field on the form
        // HOWEVER... this doesn't hold for images - they are processed differently so we shouldn't expect a value in $recordData[$dataFieldId]
        if (!isset($recordData[$dataFieldId]) && !in_array($dataField->getType(),['image','upload'])) continue;

        $hidden = in_array($dataFieldId,$hiddenFields);        
        $inherited = isset($recordInherited[$dataFieldId]) ? $recordInherited[$dataFieldId] : 0;
        // Inheritance kicks in if the "inherited" box is ticked AND we actually have a parent
        // If we have no parent the parentAnswers array will be empty
        if($inherited && count($parentAnswers)) {
            $value = '';
            if (isset($parentAnswers[$dataFieldId])) {
                // In the case of an inheritted value it has just been pulled from the database
                // so it will need to be unpacked
                $value = $parentAnswers[$dataFieldId];
                $dataField->unpackFromStorage($value);
            }
        } else {
            $value = isset($recordData[$dataFieldId]) ? $recordData[$dataFieldId] : '';
        }
        if (isset($saveDefault[$dataFieldId]) && strlen($dataField->question)) {
            // see if this already exists
            $DB->setInsertType('REPLACE');
            $defaultsChanged += (int)$DB->insert('userDefaultAnswer',[
                'userId' => $USER_ID,
                'question' => $dataField->question,
                'matchType' => 'exact',
                'answer' => $value,
            ]);
        }
        $result = $dataField->save( $value, $hidden, $inherited, null );
        if ($result!==true) inputError('dataField['.$dataFieldId.']',$result);
        else DataField::doInheritance($dataFieldId, $value, $id);
    }

    if ($defaultsChanged) {
        $DB->update('user',['id'=>$USER_ID],['defaultsLastChangedAt' => time()]);
    }

    // Handle label adding
    $labelId = (int)ws('labelId');
    if ($labelId) {

        include(LIB_DIR.'/labelTools.php');
        $error = assignLabelToRecord( $labelId, $id );
        if ($error) {
            inputError('labelId',$error);
        } else {
            ws('labelId','');
        }
    }

    // Handle label removal
    $labelId = (int)ws('removeLabelId');
    if ($labelId) {
        $attachedToThisRecord = $DB->getValue('SELECT id FROM label WHERE recordId=? and id=?',$id,$labelId);
        if (!$attachedToThisRecord) inputError('removeLabelId','This label isn\'t currently attached to this record');
        else {
            $DB->update('label',array('id'=>$labelId),array('recordId'=>0));
            ws('removeLabelId','');
        }
    }

	// Add new relationships if any have been defined
	global $newRelationship;
	$newRelationship = false;
	$toRecordId = (int)ws('toRecordId');
	$relationshipLinkId = (int)ws('relationshipLinkId');
	if ($toRecordId && $relationshipLinkId) {
		// Check the validity of the relationship they are trying to create
		// And get the reciprocalRelationshipLinkId at the same time
		$toRecordTypeId = $DB->getValue('SELECT typeId FROM record WHERE id=?',$toRecordId);
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
		',$relationshipLinkId, ws('record_typeId'),$toRecordTypeId);

		// check that this isn't a duplicate relationship
		$isDuplicate = $DB->getValue(
			'SELECT id FROM relationship WHERE fromRecordId=? AND toRecordId=? AND relationshipLinkId=?',
			$id,$toRecordId,$relationshipLinkId
		);
		if (!$isDuplicate && $reciprocalRelationshipLinkId) {
			// Insert the forward relationship
			$forwardRelationshipId = $DB->insert('relationship',array(
				'fromRecordId'             => $id,
				'toRecordId'               => $toRecordId,
				'relationshipLinkId'       => $relationshipLinkId,
				'reciprocalRelationshipId' => 0,
			));
			if ($forwardRelationshipId) {
				// Then the reciprocal relationship
				$reciprocalRelationshipId = $DB->insert('relationship',array(
					'fromRecordId'             => $toRecordId,
					'toRecordId'               => $id,
					'relationshipLinkId'       => $reciprocalRelationshipLinkId,
					'reciprocalRelationshipId' => $forwardRelationshipId,
				));
				// Update the forward relationship to point to the new reciprocal
				$DB->update('relationship',['id'=>$forwardRelationshipId],['reciprocalRelationshipId'=>$reciprocalRelationshipId]);

				$newRelationship = true;
			}
		}
	}
	
    // if we created a new record and no specific parent was set then set the parent to be the item itself
    if ($isNew) {
        if (ws('record_parentId')) {
            $DB->returnHash();
            $updates = $DB->getRow('SELECT path,depth FROM record WHERE id=?',ws('record_parentId'));
        } else {
            $updates = array(
                'parentId'  => 0,
                'path'      => '',
                'depth'     => 0
            );
        }
        $updates['path'] .= $id.',';
        $updates['depth']++;

        if (ws('labelId')) {
            $DB->update('label',array('id'=>ws('labelId')),array('recordId'=>$id));
        }
            
        $DB->update('record',array('id'=>$id),$updates);
        
        if (ws('record_parentId')) {
            //Generate inherited record data as needed
            $parentDataFieldQuery = $DB->query('
                SELECT
                    dataField.*,
                    "'.$id.'" AS recordId,
                    recordData.data,
                    recordData.valid,
                    recordData.inherited,
                    recordData.fromRecordId,
                    recordData.hidden,
                    "" AS currentDataHash,
                    0 AS currentDataLength
                FROM recordData
                INNER JOIN dataField ON dataField.id = recordData.dataFieldId
                WHERE recordData.recordId = ?
                AND dataField.inheritance IN ("normal", "default", "immutable")
            ', ws('record_parentId'));

            while( $parentDataFieldQuery->fetchInto($dataFieldInfo) ) {
                $recordData = array(
                    'recordId' => $id,
                    'dataFieldId' => $dataFieldInfo['id'],
                    'data' => $dataFieldInfo['data'],
                    'inherited' => ($dataFieldInfo['inheritance'] == 'default' ? 0 : 1),
                    'fromRecordId' => $dataFieldInfo['fromRecordId'],
                    'valid' => 1,
                    'hidden' => $dataFieldInfo['hidden'],
                );
                $DB->insert('recordData', $recordData);

                global $USER_ID;
                $recordDataVersion = array(
                    'recordId' => $id,
                    'dataFieldId' => $dataFieldInfo['id'],
                    'data' => $dataFieldInfo['data'],
                    'inherited' => ($dataFieldInfo['inheritance'] == 'default' ? 0 : 1),
                    'fromRecordId' => $dataFieldInfo['fromRecordId'],
                    'valid' => 1,
                    'saved' => 1,
                    'userId' => $USER_ID,
                    'savedAt' => time(),
                );
                $DB->insert('recordDataVersion', $recordDataVersion);
            }
        }

		$anchor = ws('anchor');
		if (!strlen($anchor)) $anchor='edit';
		
        header('Location: admin.php?subMode=new&id='.$id.'#'.$anchor);
        exit;
    }
}

function processDeleteBefore($id) {
    global $DB, $deletedRecordChildIds, $deletedRecordAnswers;
    $deletedRecord = $DB->getRow('SELECT * FROM record WHERE id = ?', $id);
    $deletedRecordChildIds = $DB->getColumn('SELECT id FROM record WHERE parentId = ?', $id);
    DataField::loadAnswersForRecord($id);
    $deletedRecordAnswers = DataField::$answers;

    // Blank all data values that aren't inherited from this record's parent to start fixing up inheritance
    // This record's values will be propagated to its children, where appropriate, in processDeleteAfter()
    $dataFields = DataField::buildAllForRecord($id, array('where' => '!recordData.inherited'));
    foreach($dataFields as $dataFieldId => $dataField) {
        $dataField->save('', $dataField->hidden);
        DataField::doInheritance($dataFieldId, '', $id);
    }

    // Fix paths and depth
    $oldPrefix = $deletedRecord['path'];
    $newPrefix = substr($oldPrefix, 0, strlen($oldPrefix) - strlen($id.','));
    // not sure how this can happen - but if the path is ever empty then don't run this - that would be bad!
    if (strlen($oldPrefix)) {
        $DB->exec('UPDATE record SET depth = depth - 1, path = REGEXP_REPLACE(path, ?, ?) WHERE path LIKE ?', '^'.$oldPrefix, $newPrefix, $oldPrefix.'%');
    }

    // re-link children to new parent
    $DB->update('record', array('parentId' => $id), array('parentId' => $deletedRecord['parentId']));

    return true;
}

function processDeleteAfter($id) {
    global $DB, $deletedRecordChildIds, $deletedRecordAnswers;

    // Set child data values for inherited fields to the old parent's value, then propagate
    foreach($deletedRecordChildIds as $childRecordId) {
        $childDataFields = DataField::buildAllForRecord($childRecordId, array('where' => 'dataField.inheritance IN ("normal","immutable") AND recordData.inherited'));
        foreach($childDataFields as $dataFieldId => $childDataField) {
            if(isset($deletedRecordAnswers[$dataFieldId])) {
                $childDataField->save($deletedRecordAnswers[$dataFieldId], $childDataField->hidden, false);
                DataField::doInheritance($dataFieldId, $deletedRecordAnswers[$dataFieldId], $childRecordId);
            }
        }
    }

    $DB->delete('recordData', array('recordId' => $id));
}

function getRecordData( $condition, $conditionValue ) {
    global $DB;
    
    return $DB->getHash('
        SELECT
            record.id, recordData.data
        FROM
            record
            INNER JOIN recordType ON recordType.id=record.typeId
            INNER JOIN recordData ON recordData.dataFieldId=recordType.primaryDataFieldId AND recordData.recordId=record.id
        WHERE record.lastSavedAt AND !record.deletedAt AND '.$condition.'
        ORDER BY depth DESC
    ',$conditionValue);
}

function prepareDisplay( $id ) {
    global $DB, $extraScripts, $extraStylesheets, $heading, $title;
        
    $extraScripts[] = '/javascript/dependentInputs.js';
    $extraScripts[] = '/javascript/jodit/jodit.js';
    $extraScripts[] = '/javascript/dataField.js';
    $extraStylesheets[] = '/stylesheets/nodeInfoPanel.css';

    $recordTypeId = ws('record_typeId');

    global $entityName;
    list($entityName, $primaryDataFieldId) = $DB->getRow('SELECT name,primaryDataFieldId FROM recordType WHERE id=?',ws('record_typeId'));
       
	global $recordName;
	$recordName = $DB->getValue('
		SELECT recordData.data
		FROM
			record
			INNER JOIN recordType ON recordType.id=record.typeId
			INNER JOIN recordData ON recordData.dataFieldId=recordType.primaryDataFieldId AND recordData.recordId=record.id
		WHERE
			record.id=?
	',$id);

    DataField::loadAnswersForRecord($id);

    global $parentAnswers, $parentName;
    DataField::setParentAnswers($parentAnswers);

    DataField::loadInheritedFieldsForRecord($id);

    $parentName = '';
    if (isset($parentAnswers[$primaryDataFieldId])) $parentName=$parentAnswers[$primaryDataFieldId];

    if (ws('mode')=='infoPanel') {
        include( VIEWS_DIR.'/record/infoPanel.php' );
        exit;
    }

    // Load context data
    global $ancestors, $descendants, $children;
    $ancestorIds = array();
    $descendants = array();
    $children = array();
    if ($id==0) {
        if (ws('parentId')) {
            $parentPath = $DB->getValue('SELECT path FROM record WHERE id=?',ws('parentId'));
            $ancestorIds = explode(',',$parentPath);
            array_pop($ancestorIds);
        }
    } else {
        if (ws('subMode')=='new') $heading = 'Create new '.$entityName;
        else $heading=$entityName.' - '.$recordName;
        $ancestorIds = explode(',',ws('record_path'));
        // The last element will be empty and the one before will be itself
        // knock both off....
        array_splice($ancestorIds,-2);
        
        $children = getRecordData( 'record.parentId = ?',$id );
        $descendants = getRecordData( 'record.path LIKE ?',ws('record_path').'_%' );
    }
    $title = $entityName.' administration';

    // Load in the label data
    global $labelIds;
    $labelIds = $DB->getColumn('SELECT label.id FROM label WHERE label.recordId=? ORDER BY label.id ASC',$id);
    
    if (count($ancestorIds)) $ancestors = getRecordData( 'record.id IN (?)',$ancestorIds );
    else $ancestors = array();
    
    // Relationships...
    global $newRelationshipTypeSelect;
    $newRelationshipTypeSelect = new FormOptionbox('',array('--- Add new relationship ---'=>''));
    $newRelationshipTypeSelect->setExtra('class="relationship"');
    $newRelationshipTypeSelect->addLookup('
		SELECT DISTINCT description
		FROM
			relationshipLink
			INNER JOIN relationshipPair ON relationshipPair.id=relationshipLink.relationshipPairId
		WHERE
			fromRecordTypeId = ? AND
			!relationshipPair.deletedAt
	',$recordTypeId);
}

$editMode=false;

include( '../../lib/core/adminPage.php' );
