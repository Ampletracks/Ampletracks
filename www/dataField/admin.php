<?

$INPUTS = array(
    'update' => array(
        'dataField_displayOnList'           => 'INT',
        'dataField_optional'                => 'INT',
        'dataField_dependencyCombinator'    => 'TEXT',
        'dependencyId'                      => 'INT ARRAY',
        'dependeeDataFieldId'               => 'INT ARRAY',
        'dependencyTest'                    => 'TEXT ARRAY',
        'nonValueDependencyTest'            => 'TEXT ARRAY',
        'dependencyTestValue'               => 'TEXT ARRAY',
    )
);

function postStartup() {
    include( LIB_DIR.'/dataField.php');
    include( CORE_DIR.'/search.php');
}

function processUpdateBefore( $id ) {
    global $DB,$WS,$recordTypeId;

    if ($id) {
        list( $recordTypeId, $currentPosition,$currentQuestion ) = $DB->getRow('SELECT recordTypeId, orderId, question FROM dataField WHERE id=?',$id);
    } else {
        $currentPosition=0;
        $currentQuestion=null;
        // populate the recordType ID if this is a new Field
        $recordTypeId=getPrimaryFilter();
        ws('dataField_recordTypeId',$recordTypeId);

        // If we are creating a new record and no exportName has been provided then default this
        // to be the camelCase version of the name
        // If they _really_ want to make this blank they'll have to edit it after creation
        if (empty(ws('dataField_exportName'))) {
            ws('dataField_exportName',toCamelCase(ws('dataField_name')));
        }
    }

    $oldInheritance = $DB->getValue('SELECT inheritance FROM  dataField WHERE id = ?', $id);
    ws('inheritanceChanged', ws('dataField_inheritance') != $oldInheritance);

    $newPosition=ws('dataField_orderId');

    if (isset($WS['dataField_orderId']) && $newPosition <> $currentPosition) {
        //if ($currentPosition) $DB->exec('UPDATE dataField SET orderId=orderId-1 WHERE !deletedAt AND recordTypeId=? AND orderId>?',$recordTypeId,$currentPosition);
        $DB->exec('UPDATE dataField SET orderId=orderId+1 WHERE !deletedAt AND recordTypeId=? AND orderId>=?',$recordTypeId,$newPosition);

        // Re-order all the dataFields
        $ids = $DB->getHash('SELECT id, orderId FROM dataField WHERE !deletedAt AND recordTypeId=? AND id!=? ORDER BY orderId ASC',$recordTypeId,$id);
        $idx=1;
        foreach($ids as $refId=>$orderId) {
            // Leave a gap for this datagield to slot into
            if ($idx==$newPosition) $idx++;
            if ($orderId<>$idx) $DB->update('dataField',array('id'=>$refId),array('orderId'=>$idx));
            //if ($position==$orderId) $DB->update('dataField',array('id'=>$id),array('orderId'=>$idx++));
            $idx++;
        }
    }
   
    if (ws('dataField_typeId')>0) {
        DataField::setParameterPrefix('fieldParameters_');
        // If they change the dataField type then there will be old parameters for the old field type
        // We don't want to lose these in case they decide to change the type back so we need to
        // keep any existing parameters and merge the new ones on top
        
        $existingParameters = $DB->getValue('SELECT parameters FROM dataField WHERE id=?',$id);
        if (strlen($existingParameters)) {
            DataField::unserializeParameters($existingParameters);
        } else $existingParameters = [];

        ws('dataField_parameters',DataField::serializeParameters(ws('dataField_typeId'),$_POST,$id,$existingParameters));
    }
    
    // Mysql gets upset if we try and set the ENUM to '' so if this is the case do not change this field
    if (!strlen(ws('dataField_dependencyCombinator'))) unset($WS['dataField_dependencyCombinator']);

    if (isset($WS['dataField_question']) && ( is_null($currentQuestion) || strtolower(trim($WS['dataField_question']))!==strtolower(trim($currentQuestion)))) {
        // Question has changed! make a note of that
        ws('dataField_questionLastChangedAt',time());
    }
}

function processUpdateAfter( $id ) {
    global $WS,$DB, $recordTypeId;
    
    // Save the dependencies
    if (isset($WS['dataField_dependencyCombinator'])) {
        
        // If the dependency combinator is set but empty that means they have switched off all dependencies
        if (!$WS['dataField_dependencyCombinator']) {
            $DB->delete('dataFieldDependency',array('dependentDataFieldId'=>$id));
        } else {

            $fields = array('dependencyIds','dependeeDataFieldIds','dependencyTests','nonValueDependencyTests','dependencyTestValues');
            foreach($fields as $field) {
                $$field = ws(substr($field,0,-1));
                forceArray($$field);
            }

            // The dependencyTestValue is allowed to be missing so remove this from the list of fields now
            array_pop($fields);
            // nonValueDependencyTests is also allowed to be missing - see a bit further down
            array_pop($fields);
            
            for ($i=0; $i<10; $i++) {

                // The dependency test type will either be in $dependencyTests[$i] or $nonValueDependencyTests[$i]
                // One of these should be hidden so only one should actually be set
                // Move the one that is set into dependencyTests[$i]
                if (!isset($dependencyTests[$i]) && isset($nonValueDependencyTests[$i])) $dependencyTests[$i]=$nonValueDependencyTests[$i];
                
                foreach($fields as $field) {
                    if (!isset(${$field}[$i])) continue 2;
                }

                // Check the selected dependeeDataFieldId actually belonds to the recordType
                $dataFieldIdCheck = $DB->count( 'dataField', array(
                    'recordTypeId'  => $recordTypeId,
                    'id'            => $dependeeDataFieldIds[$i]
                ));
                if (!$dataFieldIdCheck) $dependeeDataFieldIds[$i]=0;
                
                // Check that the specified test exists
                $testCheck = 0;
                if ( strlen($dependencyTests[$i]) ) $testCheck = $DB->count('testLookup',array('test'=>$dependencyTests[$i]));
                if (!$testCheck) $dependeeDataFieldIds[$i]=0;
                
                $params = array(
                    'test'                  => $dependencyTests[$i],
                    'testValue'             => isset($dependencyTestValues[$i])?$dependencyTestValues[$i]:'',
                    'dependeeDataFieldId'   => $dependeeDataFieldIds[$i],
                    'dependentDataFieldId'  =>  $id
                );
                
                // Work out if we are inserting, updating or deleting...
                if ($dependencyIds[$i]>0) {
                    // Update/delete an existing dependency
                    if (!$dependeeDataFieldIds[$i]) {
                        // This is a delete
                        $DB->delete('dataFieldDependency',array('id'=>$dependencyIds[$i],'dependentDataFieldId'=>$id));
                    } else {
                        // This is an update
                        $DB->update('dataFieldDependency',array('id'=>$dependencyIds[$i],'dependentDataFieldId'=>$id),$params);
                    }
                } else {
                    // insert a new dependency
                    if (!$dependeeDataFieldIds[$i]) continue;
                    $DB->setInsertType('INSERT IGNORE');
                    $DB->insert('dataFieldDependency',$params);
                }
            }
        }
    }

    if(ws('inheritanceChanged')) {
        DataField::changeInheritance($id, ws('dataField_inheritance'));
    }

    // Handle "Save and Next"
    if (preg_match('/save.*next/i',ws('submitButton'))) {
        ws('submitButton','Save & Close');
        $nextDataFieldId = $DB->getValue('SELECT id FROM dataField WHERE orderId>"@@dataField_orderId@@" AND deletedAt=0 AND recordTypeId="@@dataField_recordTypeId@@" ORDER BY orderId limit 1');
        global $backHref;
        if ($nextDataFieldId) $backHref = 'admin.php?id='.$nextDataFieldId;
    }
}

function processDeleteBefore( $id ) {
    global $DB;
    
    $recordTypeId = $DB->getValue('SELECT recordTypeId FROM dataField WHERE id=?',$id);
    $DB->exec('SET @count:=0');
    $DB->exec('UPDATE dataField SET orderId=@count:=@count+1 WHERE recordTypeId=? AND id<>? AND !deletedAt ORDER BY orderId',$recordTypeId,$id);
}

function prepareDisplay( $id ) {
    global $DB, $WS, $extraStylesheets,$extraScripts;
    $extraScripts[] = '/javascript/dependentInputs.js';
    $extraStylesheets[] = '/javascript/jodit/jodit.css';
    $extraScripts[] = '/javascript/jodit/jodit.js';
    
    $DB->loadRow(['SELECT name FROM recordType WHERE id=?',ws('dataField_recordTypeId')],'recordType_');
    if ($id) $recordTypeId = ws('dataField_recordTypeId');
    else $recordTypeId = getPrimaryFilter();
    
    global $dataFieldTypeSelect;
    $dataFieldTypeSelect = new formOptionbox('dataField_typeId','
        SELECT name,id FROM dataFieldType WHERE !disabled ORDER BY name ASC
    ');

    global $inheritanceSelect;
    $inheritanceSelect = new formOptionbox('dataField_inheritance', $DB->getEnumValues('dataField', 'inheritance'));

    global $saveInvalidAnswersSelect;
    $saveInvalidAnswersSelect = new formOptionbox('dataField_saveInvalidAnswers',array(
        'Never' => 'never',
        'Never (but save the invalid answer in the version history)' => 'never but save version',
        'Only if currently unset' => 'only if unset',
        'Only if currently unset (but save the invalid answer in the version history)' => 'only if unset but save version',
        'Always' => 'always',
    ));
    
    global $positionSelect;
    $firstFieldLabel = 'First field';
    $currentPosition = ws('dataField_orderId');
    if (!$currentPosition) {
        $currentPosition = $DB->getValue('SELECT MAX(orderId)+1 FROM dataField WHERE recordTypeId=? AND !deletedAt',$recordTypeId);
        ws('dataField_orderId',$currentPosition);
    } else if ($currentPosition==1) {
        $firstFieldLabel .= ' - Current position';
    }
   
    if (!$id) $currentPosition = 0; 
    $positionSelect = new formOptionbox('dataField_orderId',array($firstFieldLabel=>1));
    $positionSelect->addLookup('SELECT CONCAT("After ",name,IF(orderId=?," - Current position","")),orderId+IF(orderId>?,0,1) FROM dataField WHERE !deletedAt AND id!=? AND recordTypeId=? ORDER BY orderId ASC',$currentPosition-1,$currentPosition, $id,$recordTypeId);
    
    if ($id) DataField::unserializeParameters($WS['dataField_parameters'],$WS);
    unset($WS['dataField_parameters']);
    
    global $dependeeFieldSelect, $dependencyCombinatorSelect, $dependencyTestSelect, $nonValueDependencyTestSelect;
    
    $dependencyTestSelect = new formOptionbox('',array('Delete dependency'=>''));
    $dependencyTestSelect->addLookup('SELECT name, test FROM testLookup');
    $nonValueDependencyTestSelect = new formOptionbox('',array('Delete dependency'=>''));
    $nonValueDependencyTestSelect->addLookup('SELECT name, test FROM testLookup WHERE !hasValue');
    
    $dependeeFieldSelect = new formOptionbox('','
        SELECT CONCAT( orderId,". ", dataField.name) AS name,dataField.id
        FROM dataField
            INNER JOIN dataFieldType ON dataFieldType.id=dataField.typeId
        WHERE dataField.recordTypeId='.((int)$recordTypeId).' AND !dataField.deletedAt
        ORDER BY dataField.orderId ASC
    ');
    if ($id) {
        $dependeeFieldSelect->removeOption($id);
    }

    global $dependeeFieldList;
    $dependeeFieldList = new Search('dataField/dependencyList',array('
        SELECT id,dependeeDataFieldId, test, testValue
        FROM
            dataFieldDependency
        WHERE
            dependentDataFieldId = ?
        UNION ALL
        SELECT 0,0,"","" FROM number WHERE number <11
        LIMIT 10
    ',$id));
    
    $numDependencies = $DB->getValue('SELECT COUNT(*) FROM dataFieldDependency WHERE dependentDataFieldId = ?',$id);
    // Default the combinator select to "no dependencies" if none have been defined
    if ($numDependencies==0) ws('dataField_dependencyCombinator','');
    
    $dependencyCombinatorSelect = new FormOptionbox('dataField_dependencyCombinator',array('No dependencies'=>'','AND'=>'and','OR'=>'or'));
    
    global $nonValueQuestions;
    $nonValueQuestions = $DB->getColumn('
        SELECT dataField.id
        FROM dataField
        INNER JOIN dataFieldType ON dataFieldType.id=dataField.typeId
        WHERE
            !dataFieldType.hasValue AND
            !dataField.deletedAt AND
            dataField.recordTypeId=?
    ',$recordTypeId);
    if (!count($nonValueQuestions)) $nonValueQuestions=array('xx');
    $nonValueQuestions = implode('|',$nonValueQuestions);
}

function extraButtonsAfter() {
    ?>
    <button class="saveAndNext btn" name="submitButton" type="submit" value="Save &amp; Next">Save &amp; Next</button>
    <?
}

include( '../../lib/core/adminPage.php' );
