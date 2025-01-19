<?

include_once(CORE_DIR.'/formAsyncUpload.php');

define( 'DATAFIELD_SYSTEM_DEFAULT',1 );
define( 'DATAFIELD_USER_DEFAULT',2 );

class DataField {

    const typeLookup = array(
         1 => "Divider",
         2 => "Commentary",
         3 => "Integer",
         4 => "Textbox",
         5 => "Textarea",
         6 => "Select",
         7 => "Date",
         8 => "Duration",
         9 => "Email Address",
        10 => "URL",
        11 => "Upload",
        12 => "Image",
        13 => "Float",
        14 => "Type To Search",
        15 => "Suggested Textbox",
        16 => "Chemical Formula",
        17 => "Graph",
    );

    static $saveDefaultJavascriptDisplayed = false;

    // Default filter spec - may be overridden later
    protected $filterSpec = array( 'width'=>10,'filter'=>['ct']);

    protected $params;
    private $defaulted=false;
    public $id;
    static $answers;
    static $unpackedAnswerCache = [];
    static $parentAnswers;
    static $inheritedFields;
    static $parameterPrefix = 'fieldParameters_';
    private $userDefaultAnswers = false;
    
    static function buildAllForRecord( $id, $extraSql=[] ) {
        global $DB;

        // get a list of all the data field IDs for the entry, together with their current validity and a hash of the current value
        $DB->returnHash();
        $dataFieldQuery = $DB->query('
            SELECT
                dataField.*, record.id AS recordId, recordData.valid, recordData.hidden, record.lastSavedAt AS recordLastSavedAt,
                MD5(CONCAT(recordData.data, recordData.inherited)) AS currentDataHash, LENGTH(recordData.data) AS currentDataLength,
                recordData.inherited, recordData.fromRecordId,
                dataField.inheritance="immutable" AND EXISTS(SELECT lockedByRecordId FROM recordDataChildLock WHERE recordId = record.id) AS childLocked
            FROM
                record
                INNER JOIN dataField ON dataField.recordTypeId = record.typeId
                INNER JOIN dataFieldType ON dataFieldType.id = dataField.typeId
                LEFT JOIN recordData ON recordData.recordId=record.id AND recordData.dataFieldId=dataField.id
            WHERE
                record.id=? AND !dataField.deletedAt
                '.(isset($extraSql['where'])?'AND '.$extraSql['where']:'').'
            ORDER BY dataField.orderId ASC
        ',$id);

        $dataFields = array();

        while( $dataFieldQuery->fetchInto($dataFieldInfo) ) {
            $dataField = DataField::build($dataFieldInfo);
            $dataField->setup( $id );
            $dataFields[$dataFieldInfo['id']] = $dataField;
        }

        return $dataFields;
    }

    static function loadAnswersForRecord( $id, $where='' ) {
        global $DB;

        if (strlen($where)) $where = ' AND '.$where;
        // load up all the data
        $answers = $DB->getHash('
            SELECT
                recordData.dataFieldId,
                recordData.`data`
            FROM
                record
                INNER JOIN recordData ON recordData.recordId=record.id
                INNER JOIN dataField ON dataField.id=recordData.dataFieldId
            WHERE
                record.id=?
        '.$where,$id);
        self::setAnswers( $answers );
    }

    static function loadInheritedFieldsForRecord( $id, $where = '' ) {
        global $DB;

        if (strlen($where)) $where = ' AND '.$where;
        $inheritedFields = $DB->getHash('
            SELECT
                recordData.dataFieldId,
                recordData.inherited
            FROM
                recordData
            WHERE
                recordData.recordId=?
        '.$where, $id);
        self::setInheritedFields($inheritedFields);
    }

    function displayValue() {
        if (!isset($this->params['recordId'])) return false;
        $this::displayValueStatic( $this, $this->getAnswer(), $this->params['recordId'], $this->id );
    }

    static function displayValueStatic( $typeIdOrObject, $value, $recordId, $fieldId ) {
        $objectOrName = is_object($typeIdOrObject) ? $typeIdOrObject : self::lookupObjectType($typeIdOrObject);

        if ($objectOrName!==false) {
            if (method_exists($objectOrName,'formatForDisplay')) {
                $objectOrName::formatForDisplay($value, $recordId, $fieldId);
                return;
            }
        }

        if (is_array($value)) $value = implode(', ',$value);
        // This is the default behaviour if no specific method is provided
        if (strlen($value)>100) $value = substr($value,0,97).'...';
        echo htmlspecialchars( $value );
        return;
    }

    static function packForJSON( $typeId, $value ) {
        $objectName = self::lookupObjectType($typeId);

        if ($objectName!==false) {
            if (method_exists($objectName,'jsonPack')) {
                $objectName::jsonPack($value);
                return;
            }
        }

        // This is the default behaviour if no specific method is provided
        return $value;
    }

    static function lookupObjectType( $typeId ) {
        // isset doesn't work on array constants so have to use this instead...
        if (!array_key_exists($typeId, DataField::typeLookup)) return false;
        $type = DataField::typeLookup[$typeId];
        return 'DataField_'.toCamelCase($type);
    }

    static function build($input) {
        global $DB;

        if (is_numeric($input)) $input=$DB->getRow('SELECT * FROM dataField WHERE id=?',$input);

        $input = (array)$input;

        if (!isset($input['typeId'])) return false;
        $typeId = $input['typeId'];

        $objectName = self::lookupObjectType($typeId);
        if ($objectName===false) return false;
        return new $objectName($input);
    }

    static function getAllTypes() {
        $return = array();
        foreach( DataField::typeLookup as $typeId=>$type ) {
            $objectName = 'DataField_'.toCamelCase($type);
            $return[ $typeId ] = new $objectName(array());
        }
        return $return;
    }

    static function serializeParameters( $typeId, &$inputs, $dataFieldId, $existing ) {
        $type = DataField::typeLookup[$typeId];
        $objectName = 'DataField_'.toCamelCase($type);
        if (isset($existing) && is_array($existing)) $data = $existing;
        else $data = array();
        foreach($objectName::parameters as $parameterName) {
            $isArray = substr($parameterName,-2) == '[]';
            if ($isArray) $parameterName = substr($parameterName,0,-2);

            $prefixedParameterName = self::$parameterPrefix.$parameterName;
            $value = isset($inputs[$prefixedParameterName]) ? $inputs[$prefixedParameterName] : '';

            if ($isArray) {
                if (!is_array($value)) $value = [$value];
            } else {
                if (!is_string($value)) $value = '';
            }
            $data[$parameterName] = $value;
        }
        $objectName::sanitizeParameters( $data, $dataFieldId );
        return serialize($data);
    }

    static function unserializeParameters( &$parameters, &$destination = null ) {
        if (!strlen($parameters)) return;
        $parameters = unserialize( $parameters );
        if($destination !== null) {
            foreach( $parameters as $name=>$value ) {
                $destination[ self::$parameterPrefix.$name ] = $value;
            }
        }
    }

    static function setAnswers(&$answers) {
        self::$answers = $answers;
    }

    static function setParentAnswers(&$parentAnswers) {
        self::$parentAnswers = $parentAnswers;
    }

    static function setInheritedFields(&$inheritedFields) {
        self::$inheritedFields = $inheritedFields;
    }

    static function setParameterPrefix($prefix) {
        self::$parameterPrefix = $prefix;
    }

    static function findValuesUserCanSee($dataFieldId,$extraWhere='', $userId=null,$recordTypeId=null) {
        global $USER_ID, $DB;
        if (is_null($recordTypeId)) {
            $recordTypeId = $DB->getValue('SELECT recordTypeId FROM dataField WHERE id=?',$dataFieldId);
        }
        if (is_null($userId)) $userId=$USER_ID;

        $limits = getUserAccessLimits([
            'entity' => 'recordTypeId:'.$recordTypeId,
            'prefix' => ''
        ]);
        $userAccessConditions = makeConditions( $limits );

        $return = $DB->getColumn('
            SELECT DISTINCT
                recordData.data
            FROM recordData
                INNER JOIN record ON record.id=recordData.recordId
            WHERE
                '.$userAccessConditions.'
                '.$extraWhere.'
                recordData.dataFieldId = ? AND
                recordData.hidden = 0 AND
                recordData.valid = 1
            ORDER BY data
        ', $dataFieldId);

        return $return;
    }

    function __construct( $params ) {
        if (isset($params['parameters']) && strlen($params['parameters'])) $unserializedParameters = @unserialize( $params['parameters'] );
        if (!isset($unserializedParameters) || !is_array($unserializedParameters)) $unserializedParameters = array();
        unset( $params['parameters'] );
        $this->params = array_merge($unserializedParameters,$params);
        if (isset($this->params['id'])) $this->id=$this->params['id'];
    }

    function __get( $field ) {
        if (isset($this->params[$field])) return $this->params[$field];
        return null;
    }

    static function sanitizeParameters( &$parameters, $dataFieldId ) {
    }

    function getAnswers() {
        return self::$answers;
    }    

    function getUserDefault() {
        global $USER_ID,$DB;

        // See if this field is allowed to be defaulted - if not return null
        if (!$this->params['allowUserDefault']) return null;

        if ( $this->userDefaultAnswers === false ) {
            $recordTypeId = $this->params['recordTypeId'];

            $query = $DB->query('
                SELECT 
                    dataField.id,
                    userDefaultAnswer.answer,
                    userDefaultAnswerCache.userDefaultAnswerId,
                    IFNULL( userDefaultAnswerCache.savedAt <= dataField.questionLastChangedAt, 1 ) AS questionChanged,
                    userDefaultAnswerCache.savedAt <= user.defaultsLastChangedAt AS defaultsChanged
                FROM dataField
                INNER JOIN user ON user.id = ?
                INNER JOIN dataFieldType ON dataFieldType.id = dataField.typeId
                LEFT JOIN userDefaultAnswerCache ON userDefaultAnswerCache.userId=user.id AND userDefaultAnswerCache.dataFieldId=dataField.id
                LEFT JOIN userDefaultAnswer ON userDefaultAnswer.id=userDefaultAnswerCache.userDefaultAnswerId
                WHERE
                    dataField.deletedAt=0 AND
                    dataField.allowUserDefault>0 AND
                    dataFieldType.hasValue>0 AND
                    dataField.recordTypeId=?
            ', $USER_ID, $recordTypeId );

            $dataFieldsNeedingRecheck = [];
            $cacheHits = 0;

            while( $query->fetchInto($cacheData) ) {
                // If the defaults changed then all bets are off
                if ($cacheData['defaultsChanged']>0) {
                    $cacheHits = 0;
                    break;
                }
                // If the question changed then only this dataField needs re-checking
                // The query will also set this to true if there is no current cached value
                if ($cacheData['questionChanged']>0) {
                    $dataFieldsNeedingRecheck[] = $cacheData['id'];
                } else {

                    // Hooray - we got a cache hit! Load this into the static cache of this object
                    $cacheHits++;
                    // If the userDefaultAnswerId is 0 that means we're cache a "no matching default answer" result
                    // so in that case store a null
                    $this->userDefaultAnswers[$cacheData['id']] = $cacheData['userDefaultAnswerId']==0 ? null : $cacheData['answer'];
                }
            }

            // If we didn't get any cache hits then basically we need to rebuild the whole cache
            if (!$cacheHits) $dataFieldsNeedingRecheck = [];

            // There is the possibility that $dataFieldsNeedingRecheck might be too big to for the "IN()" expression
            // This is very unlikely to happen so just truncate $dataFieldsNeedingRecheck
            array_splice( $dataFieldsNeedingRecheck, 200 );

            if ( !empty($dataFieldsNeedingRecheck) || $cacheHits==0 ) {
                // Need to recheck some - or maybe Aall
                $dataFieldWhere = '';
                if ( !empty($dataFieldsNeedingRecheck) ) {
                    foreach( $dataFieldsNeedingRecheck as $id ) {
                        $dataFieldWhere .= (int)$id.',';
                    }
                    $dataFieldWhere = 'AND dataField.id IN ( '.$dataFieldWhere.'0 )';
                }

                // Clear out existing cache entries
                $DB->exec('
                    DELETE userDefaultAnswerCache.* FROM userDefaultAnswerCache
                    INNER JOIN dataField ON dataField.id = userDefaultAnswerCache.dataFieldId
                    WHERE userDefaultAnswerCache.userId=? AND dataField.recordTypeId=? '.$dataFieldWhere
                    ,$USER_ID, $recordTypeId
                );

                // Load the user defaults
                $DB->returnHash();
                $userDefaultAnswers = $DB->getHash('
                    SELECT
                        id,matchType,LOWER(TRIM(question)) AS question,answer
                    FROM
                        userDefaultAnswer
                    WHERE
                        userId=?
                    ORDER BY orderId ASC
                ',$USER_ID);

                // Load all the datafields
                $DB->returnHash();
                $query = $DB->query('
                    SELECT
                        dataField.id,
                        LOWER(TRIM(dataField.question)) AS question
                    FROM 
                        dataField
                        INNER JOIN dataFieldType ON dataFieldType.id=dataField.typeId
                    WHERE
                        dataField.deletedAt=0 AND
                        dataField.allowUserDefault AND
                        dataField.recordTypeId=? AND
                        dataFieldType.hasValue>0
                        '.$dataFieldWhere.'
                ',$recordTypeId);

                # Iterate over all of the questions for the current record type
                while( $query->fetchInto($dataField) ) {
                    $matchingDefaultAnswerId=0;
                    # Iterate over all of the userDefault Answers
                    foreach($userDefaultAnswers as $defaultAnswerId => $defaultAnswerData ) {
                        if ($defaultAnswerData['matchType']=='exact') {
                            $result = ( $defaultAnswerData['question'] == $dataField['question'] );
                        } else if ($defaultAnswerData['matchType']=='anywhere') {
                            $result = ( strpos( $dataField['question'], $defaultAnswerData['question'] ) !== false );
                        } else {
                            $result = preg_match( '/'.str_replace('/','\/',$defaultAnswerData['question']).'/i', $dataField['question'] );
                        }
                        if ($result) {
                            $matchingDefaultAnswerId = $defaultAnswerId;
                            $this->userDefaultAnswers[$dataField['id']] = $defaultAnswerData['answer'];
                            break;
                        }
                    }
                    $DB->insert('userDefaultAnswerCache',[
                        'userId'                => $USER_ID,
                        'userDefaultAnswerId'   => $matchingDefaultAnswerId,
                        'dataFieldId'           => $dataField['id'],
                        'savedAt'               => time()
                    ]);
                }
            }
        }
        
        // The only way that $this->userDefaultAnswers[$this->id] can be unset is if someone adds a question WHILST this page is rendering
        // It could happen so handle that here.
        if (!isset($this->userDefaultAnswers[$this->id])) return null;
        else return $this->userDefaultAnswers[$this->id];
    }

    function getAnswer($dataFieldId=0) {
        if (!$dataFieldId) $dataFieldId=$this->params['id'];

        // First see if we have cached the unpacked answer
        if (isset(self::$unpackedAnswerCache[$dataFieldId])) return self::$unpackedAnswerCache[$dataFieldId];

        $return = null;
        if (isset(self::$answers[$dataFieldId]) && self::$answers[$dataFieldId]!=='' ) {
            $answer = self::$answers[$dataFieldId];
            $this->unpackFromStorage( $answer );
            $return = $answer;
        }
        else {
            // See if there is a user-specific default for this value
            // otherwise use the system default
            $userDefault = $this->getUserDefault( );
            if (!is_null($userDefault)) {
                $this->defaulted = DATAFIELD_USER_DEFAULT;
                $return = $userDefault;
            } else if ($this->default) {
                $this->defaulted = DATAFIELD_SYSTEM_DEFAULT;
                $return = $this->default;
            }
        }

        self::$unpackedAnswerCache[$dataFieldId] = $return;
        return $return;
    }

    static function saveDefaultJavascriptDisplay() {
        if (self::$saveDefaultJavascriptDisplayed) return;
        self::$saveDefaultJavascriptDisplayed=true;
        ?>
        <script>
            $(function(){
                let inputChangeHandler = function(){
                    let self = $(this);
                    let message = self.parent().find('.saveDefault');
                    if (self.val().trim().length) {
                        message.show();
                    } else {
                        message.hide();
                    }
                };
                $('div.saveDefault').parent().find(':input.dataField')
                .on('change',inputChangeHandler)
                .each( inputChangeHandler );
            });
        </script>
    <? }
    
    function displayDefaultWarning() {
        if ($this->defaulted) echo '<div class="info defaultValueUsed">'.cms('The default value has been used to populate this field').'</div>';
        else if ($this->params['allowUserDefault'] && is_null($this->getUserDefault())) {
            // We only want to offer the user the option of saving a default value IF
            // 1. this field is enabled for defaulting and
            // 2. it doesn't currently have a default
            if (!self::$saveDefaultJavascriptDisplayed) self::saveDefaultJavascriptDisplay();
            printf('<div class="saveDefault"><input type="checkbox" name="saveDefault[%d]" value="1">&nbsp;%s</div>',
                $this->params['id'],
                cms('Save this value as default for this question')
            );
        }
    }

    function getParentAnswer($dataFieldId=0) {
        if (!$dataFieldId) $dataFieldId=$this->params['id'];
        if (isset(self::$parentAnswers[$dataFieldId])) {
            $parentAnswer = self::$parentAnswers[$dataFieldId];
            $this->unpackFromStorage( $parentAnswer );
            return $parentAnswer;
        }
        else return null;
    }

    function getInherited($dataFieldId = 0) {
        if (!$dataFieldId) $dataFieldId=$this->params['id'];
        if (isset(self::$inheritedFields[$dataFieldId])) {
            return self::$inheritedFields[$dataFieldId];
        }
        else return null;
    }

    function setup($recordId) {
        // This does nothing - it is intended to be defined in sub-classes where neccessary
    }

    function getDefinitionHelp() {
        // This does nothing here - it is intended to be defined in sub-classes where neccessary
        // It should describe the purpose of thes particular type of data field
        return ''; 
    }

    function displayOnList() {
        return $this->params['displayOnList'];
    }

    function displayUnit() {
        $unit = trim($this->unit);
        if (strlen($unit)) echo '<span class="unit">'.htmlspecialchars($unit).'</span>';
    }

    function displayLabel() {
        echo htmlspecialchars($this->params['question']);
    }

    function displayPublicValue() {
        $answer = $this->getAnswer();
        // Don't display defaulted answers
        if ($this->defaulted || $answer==='' || is_null($answer)) {
            echo '<i>no value provided</i>';
        } else {
            $this->displayValue();
            $this->displayUnit();
        }
    }

    function displayRow( $isPublic = true, $hideLabel = false ) {
        $extraClasses = [];
        $title = '';
        if($this->displayToPublic && !$isPublic) {
            $extraClasses[] = 'displayToPublic';
            $title = 'This field is visible to the public';
        }
        ?>
        <div class="questionAndAnswer <?=htmlspecialchars($this->getType())?> <?=htmlspecialchars(implode(' ', $extraClasses))?>" <?=$this->getDependencyAttributes()?> <?=$title ? 'title="'.$title.'"' : ''?>>
			<? if (!$hideLabel) { ?>
				<div class="question">
					<? $this->displayLabel(); ?>
				</div>
			<? } ?>
            <div class="answer">
                <? if ($isPublic) {
                    $this->displayPublicValue();
                } else {
                    $this->displayInput();
                    inputError('dataField_'.$this->id);
                    $this->displayInherited();
                } ?>
            </div>
        </div>
        <?
    }

    function displayErrors() {
        inputError($this->inputName());
    }

    function inputName() {
        return 'dataField['.$this->params['id'].']';
    }

    function filterAlias() {
        return 'recordData_'.(int)$this->params['id'];
    }

    function filterNames() {
        $names = [];
        foreach($this->filterSpec['filter'] as $filter) {
            $names[] = 'filter_'.$this->filterAlias().':data_'.$filter;
        }
        return $names;
    }

    function getFilterValues() {
        $values = array();
        foreach( $this->filterNames() as $name ) {
            $values[] = ws($name);
        }
        return $values;
    }

    function displayFilter() {
        $values = $this->getFilterValues();
        foreach( $this->filterNames() as $idx=>$name ) {
            formTextbox($name,$this->filterSpec['width'],$this->maxLength,$values[$idx],'class="'.htmlspecialchars($this->getType()).'"');
        }
    }

    function sanitizeFilter($filter) {
        if (is_array($filter)) $filter = implode(' ',$filter);
        $maxLength = $this->maxLength;
        if (!$maxLength) $maxLength=250;
        return substr(trim($filter),0,$maxLength);
    }

    function inheritedName() {
        return 'dataFieldInherited['.$this->params['id'].']';
    }

    function versionLink() {
        if (!$this->params['recordId'] || !$this->params['recordLastSavedAt']) return;
        $href = '/recordDataVersion/list.php?filter_recordId_eq='.(int)$this->params['recordId'].'&filter_dataFieldId_eq='.(int)$this->params['id'];
        ?>
        <a title="View versions" class="version" href="<?=$href?>">
            <img src="/images/icon-versions.svg">
        </a>
        <?
    }

    function validate(&$value) {
        return true;
    }

    function hasValue() {
        return true;
    }

    function getType() {
        // All datafield objects class names start with "DataField_" - strip this off the front
        return substr(get_class($this),10);
    }

    function packForStorage(&$value) {
        // This does nothing - it is intended to be defined in sub-classes where neccessary
    }

    function unpackFromStorage(&$value) {
        // This does nothing - it is intended to be defined in sub-classes where neccessary
    }

    function prepareToSave( &$value ) {
        // Do some very basic sanitization and determine if the value is empty
        // If the field is mandatory (not optional) then see if it is empty
        if (is_array($value)) {
            // Remove the Special CORE_INPUT_EMPTY entry if it is present
            unset( $value[CORE_INPUT_EMPTY] );

            // Don't allow arrays of arrays
            $value = flatten($value);
            // Remove empty values from the array
            $value = array_filter($value,'strlen');

            $isEmpty = count($value)==0;
        } else {
            $value = (string)$value;
            $isEmpty = strlen($value)==0;
        }

        return $isEmpty;
    }

    function save( $value, $hidden, $inherited = null, $fromRecordId = null ) {
        global $DB, $USER_ID;
        if (!isset($this->params['recordId'])) return 'Can\'t save data field because the record ID is not set';
        if ($this->childLocked) return 'Can\'t save data field as it is immutable and locked by a child value';

        $hidden = $hidden ? 1 : 0;

        if($inherited === null) $inherited = $this->params['inherited'];
        $inherited = $inherited ? 1 : 0;

        if($fromRecordId === null) $fromRecordId = ($this->params['fromRecordId'] ?: $this->params['recordId']);

        $isEmpty = $this->prepareToSave( $value );

        $result=true;
        if (!$this->params['optional'] && $isEmpty ) {
            if (!$hidden) $result = cms('This field is required - you must supply an answer');
        } else if (!$isEmpty) {
            $result = $this->validate( $value );
        }

        $this->packForStorage( $value );

        // Only save if...
        // ... the answer is valid OR
        // ... we've been told to always save invalid answers
        // ... the answer is not valid, but the previous answer was empty AND we've been told to save invalid if was empty
        $saved = false;
        if (
            $result===true ||
            $this->params['saveInvalidAnswers']=='always' ||
            ( !$this->params['currentDataLength'] && in_array($this->params['saveInvalidAnswers'],array('only if unset but save version','only if unset')) )
        ) {
            $saved = $DB->exec(
                'REPLACE INTO recordData (recordId, dataFieldId, data, valid, inherited, hidden, fromRecordId) VALUES (?,?,?,?,?,?,?)',
                $this->params['recordId'], $this->params['id'], $value, $result===true?1:0, $inherited, $hidden, $fromRecordId
            );
        } else if (!$isEmpty) {
            $result .= cms('. The answer you provided has been replaced with the previous value');
        }

        // See if this is a new version
        if ( array_key_exists('currentDataHash',$this->params) && $this->params['currentDataHash']!=md5($value.$inherited) ) {
            // Save a version entry if it was valid, or either of the "but save version" options is set
            if ( $result===true ||
                in_array($this->params['saveInvalidAnswers'],array('never but save version','only if unset but save version'))
            ) {
                $DB->insert('recordDataVersion',array(
                    'recordId'      => $this->params['recordId'],
                    'dataFieldId'   => $this->params['id'],
                    'data'          => $value,
                    'valid'         => $result===true?1:0,
                    'saved'         => $saved?1:0,
                    'inherited'     => $inherited,
                    'fromRecordId'  => $fromRecordId,
                    'hidden'        => $hidden,
                    'userId'        => $USER_ID,
                    'savedAt'       => time()
                ));
            }
        }

        // never return errors against hidden fields
        if ($hidden) return true;

        return $result;
    }

    static function doInheritance($dataFieldId, $value, $recordId) {
        global $DB;
        $inheritance = $DB->getValue('SELECT inheritance FROM dataField WHERE id = ?', $dataFieldId);
        if($inheritance == 'none' || $inheritance == 'default') return true;

        $successAll = true;
        $childRecordIds = self::getDataFieldInheritedRecordIds($dataFieldId, $recordId, $inheritance != 'immutable');
        foreach($childRecordIds as $childRecordId) {
            $successAll = $successAll && self::propagateInheritance($dataFieldId, $value, $childRecordId, $inheritance, $recordId);
        }

        if($inheritance == 'immutable') {
            if($value != '') self::lockParentValues($recordId);
            else self::unlockParentValues($recordId);
        }

        return $successAll;
    }

    private static function getDataFieldInheritedRecordIds($dataFieldId, $parentRecordId, $considerInherited) {
        global $DB;
        $childRecordIds = $DB->getColumn('
            SELECT record.id
            FROM record
            LEFT JOIN recordData ON recordData.recordId = record.id AND recordData.dataFieldId = ?
            WHERE record.parentId = ?
            AND !record.deletedAt
            AND record.lastSavedAt
            '.($considerInherited ? 'AND (recordData.inherited OR recordData.recordId IS NULL)' : '').'
        ', $dataFieldId, $parentRecordId);
        return $childRecordIds;
    }

    private static function propagateInheritance($dataFieldId, $value, $recordId, $inheritance, $fromRecordId) {
        $dataFieldId = (int)$dataFieldId;
        $dataFields = self::buildAllForRecord($recordId, array('where' => "dataField.id = '$dataFieldId'"));
        $dataField = $dataFields[$dataFieldId];
        $successAll = $dataField->save($value, $dataField->hidden, 1, $fromRecordId);

        // A bit 'belt and braces' but this should make sure everything gets freed before we recurse
        foreach($dataFields as $key => $df) {
            unset($df);
            $dataFields[$key] = null;
        }
        unset($dataFields);

        $childRecordIds = self::getDataFieldInheritedRecordIds($dataFieldId, $recordId, $inheritance != 'immutable');
        foreach($childRecordIds as $childRecordId) {
            $successAll &= self::propagateInheritance($dataFieldId, $value, $childRecordId, $inheritance, $fromRecordId);
        }

        return $successAll;
    }

    static function getParentRecordIds($recordId) {
        global $DB;
        return array_filter(explode(',', $DB->getValue('SELECT path FROM record WHERE id = ?', $recordId)), function ($parentId) use($recordId) {
            return $parentId && $parentId != $recordId;
        });
    }

    private static function lockParentValues($recordId) {
        global $DB;
        $parentIds = self::getParentRecordIds($recordId);
        $changed = $DB->oneToManyUpdate('recordDataChildLock', 'lockedByRecordId', $recordId, 'recordId', $parentIds);
        return $changed;
    }

    private static function unlockParentValues($recordId) {
        global $DB;
        $success = $DB->delete('recordDataChildLock', array('lockedByRecordId' => $recordId));
        return $success;
    }

    static function changeInheritance($dataFieldId, $newInheritance = null) {
        global $DB;
        if($newInheritance === null) $newInheritance = $DB->getValue('SELECT inheritance FROM dataField WHERE id = ?', $dataFieldId);

        if($newInheritance == 'normal') {
            // Start with all entries for this field being marked as inherited
            $DB->exec('
                UPDATE recordData
                SET recordData.inherited = 1
                WHERE recordData.dataFieldId = ?
            ', $dataFieldId);

            $allUpdateData = $DB->getRows('
                SELECT
                    record.id AS recordId,
                    recordData.data AS recordDataValue,
                    parentRecord.id AS parentRecordId,
                    parentData.data AS parentDataValue
                FROM recordData
                INNER JOIN record ON record.id = recordData.recordId
                LEFT JOIN record AS parentRecord ON parentRecord.id = record.parentId
                LEFT JOIN recordData AS parentData ON parentData.recordId = parentRecord.id AND parentData.dataFieldId = recordData.dataFieldId
                WHERE recordData.dataFieldId = ?
                ORDER BY record.depth DESC
            ', $dataFieldId);

            foreach($allUpdateData as $updateData) {
                if($updateData['recordDataValue'] == $updateData['parentDataValue']) continue; // It's de facto inherited so leave it as-is. fromRecordId will be propagated in

                $DB->update('recordData', array('recordId' => $updateData['recordId'], 'dataFieldId' => $dataFieldId), array('inherited' => 0, 'fromRecordId' => $updateData['recordId']));
                self::doInheritance($dataFieldId, $updateData['recordDataValue'], $updateData['recordId']);
            }

            return true;
        }
    }

    function getDependencyAttributes() {
        global $DB;
        if (!$this->dependencyCombinator) return '';
        $return = ' dependencyCombinator="'.htmlspecialchars($this->dependencyCombinator).'" ';

        $dependencyQuery = $DB->query('SELECT * FROM dataFieldDependency WHERE dependentDataFieldId=?',$this->id);
        $idx=1;
        while( $dependencyQuery->fetchInto( $dependencyData ) ) {
            $return .= 'dependsOn'.$idx.'="dataField['.$dependencyData['dependeeDataFieldId'].'] '.$dependencyData['test'].' '.$dependencyData['testValue'].'"';
            $idx++;
        }
        return $return;
    }

    function displayInherited() {
        if(!$this->hasValue()) return;

        $inherited = false;
        $show = false;
        $answer = $this->getAnswer();
        if($this->inheritance == 'normal') {
            $inherited = $this->getInherited();
            $show = true;
        } else if(
            $this->inheritance == 'immutable' && (
                ($answer == '' && $this->childLocked) ||
                ($answer != '' && $this->fromRecordId != $this->recordId)
            )
        ) {
            $inherited = true;
        }

        $inherited = $inherited ? 1 : 0;
        $parentAnswer = $this->getParentAnswer();

        if($show && $parentAnswer !== null) {
            $checked = $inherited ? 'checked' : '';
            ?><span class="inherited"><input type="checkbox" class="inherited" name="<?=$this->inheritedName()?>" value="1" <?=$checked?> parentAnswer="<?=htmlspecialchars(json_encode($parentAnswer))?>"> inherited</span><?
        } else {
            ?><input type="hidden" class="inherited" name="<?=$this->inheritedName()?>" value="<?=$inherited?>"><?
        }
    }
}

/*
======================================================================================================
COMMENTARY
======================================================================================================
*/

class DataField_commentary extends DataField {

    const parameters = array( 'commentary' );

    function __construct( $params ) {
        parent::__construct($params);
    }

    function hasValue() {
        return false;
    }

    function displayDefinitionForm() {
        $prefix = parent::$parameterPrefix;
        questionAndAnswer(
            'Commentary',
            function() use($prefix){
                formTextarea($prefix.'commentary',80,5,null,'class="jodit"');
            }
        );
    }

    function displayRow( $isPublic = true, $hideLabel = false ) {
        ?>
        <div class="questionAndAnswer commentary" <?=$this->getDependencyAttributes()?>>
            <? if (strlen($this->question)) { ?><h2><?=htmlspecialchars($this->question)?></h2><? } ?>
            <p><?=$this->commentary?></p>
        </div>
        <?
    }

}

/*
======================================================================================================
DIVIDER
======================================================================================================
*/

class DataField_divider extends DataField {

    const parameters = array( 'commentary' );

    function __construct( $params ) {
        parent::__construct($params);
    }

    function hasValue() {
        return false;
    }

    function displayDefinitionForm() {

    }

    function displayRow( $isPublic = true, $hideLabel = false ) {
        ?>
        <div class="questionAndAnswer divider" <?=$this->getDependencyAttributes()?>>
            <h2><?=htmlspecialchars($this->question)?></h2>
        </div>
        <?
    }

}

/*
======================================================================================================
TEXTBOX
======================================================================================================
*/

class DataField_textbox extends DataField {

    const parameters = array( 'width', 'hint', 'maxLength', 'minLength', 'default');

    const filterSpec = array( 'width'=>10,'filter'=>'ct');

    function __construct( $params ) {
        parent::__construct($params);
    }

    function validate( &$value ) {
        if (!is_string($value)) return 'Invalid type of data supplied';

        $name = $this->name;
        if ( $this->maxLength>0 && strlen($value) > $this->maxLength ) return "$name must be less than ".$this->maxLength." characters long";
        if ( strlen($value) && strlen($value) < $this->minLength ) return "$name must be more than ".$this->minLength." characters long";
        return true;
    }

    function displayDefinitionForm() {
        $prefix = parent::$parameterPrefix;
        questionAndAnswer(
            'Width (characters)',
            function() use($prefix){
                formInteger($prefix.'width',1,1000,null,20);
            }
        );
        questionAndAnswer(
            'Maximum length (characters)',
            function() use($prefix){
                formInteger($prefix.'maxLength',0,255,null,0);
                echo '<div class="note">(Set to zero for no maximum)</div>';
            }
        );
        questionAndAnswer(
            'Minimum length (characters)',
            function() use($prefix){
                formInteger($prefix.'minLength',0,250,null,0);
            }
        );
        questionAndAnswer(
            'Hint',
            function() use($prefix){
                formTextbox($prefix.'hint',30,250);
            }
        );
        questionAndAnswer(
            'Default value',
            function() use($prefix){
                formTextBox($prefix.'default',1,1000,null,20);
            }
        );
    }

    function displayInput() {
        $inputName = $this->inputName();
        formTextbox($inputName,$this->width,$this->maxLength,$this->getAnswer(),'class="dataField '.htmlspecialchars($this->getType()).'" placeholder="'.htmlspecialchars($this->hint).'"');
        $this->displayUnit();
        $this->versionLink();
        inputError($inputName);
        $this->displayDefaultWarning();
    }

}

/*
======================================================================================================
TEXTAREA
======================================================================================================
*/
class DataField_textarea extends DataField {

    const parameters = array( 'width','height','hint', 'maxLength', 'minLength','default');

    function __construct( $params ) {
        parent::__construct($params);
    }

    static function sanitizeParameters( &$parameters, $dataFieldId ) {
        foreach( array('width','height') as $thing ) {
            $parameters[$thing] = (int)$parameters[$thing];
            if ($parameters[$thing]>500) $parameters[$thing]=500;
            if ($parameters[$thing]<2) $parameters[$thing]=2;
        }
    }

    function validate( &$value ) {
        if (!is_string($value)) return 'Invalid type of data supplied';

        $name = $this->name;
        if ( $this->maxLength>0 && str_word_count($value) > $this->maxLength ) return "$name must be less than ".$this->maxLength." words long";
        if ( str_word_count($value) < $this->minLength ) return "$name must be more than ".$this->minLength." words long";
        return true;
    }

    function displayDefinitionForm() {
        $prefix = parent::$parameterPrefix;
        questionAndAnswer(
            'Width (characters)',
            function() use($prefix){
                formInteger($prefix.'width',1,1000,null,100);
            }
        );
        questionAndAnswer(
            'Height (lines)',
            function() use($prefix){
                formInteger($prefix.'height',1,1000,null,5);
            }
        );
        questionAndAnswer(
            'Maximum length (words)',
            function() use($prefix){
                formInteger($prefix.'maxLength',0,10000000,null,0);
                echo '<div class="note">(Set to zero for no maximum)</div>';
            }
        );
        questionAndAnswer(
            'Minimum length (words)',
            function() use($prefix){
                formInteger($prefix.'minLength',0,10000000);
            }
        );
        questionAndAnswer(
            'Hint',
            function() use($prefix){
                formTextarea($prefix.'hint',100,5);
            }
        );
        questionAndAnswer(
            'Default value',
            function() use($prefix){
                formTextarea($prefix.'default',100,5);
            }
        );
    }

    function displayInput() {
        $inputName = $this->inputName();
        formTextarea($inputName,$this->width,$this->height,$this->getAnswer(),'class="dataField '.htmlspecialchars($this->getType()).'" placeholder="'.htmlspecialchars($this->hint).'"');
        $this->versionLink();
        inputError($inputName);
        $this->displayDefaultWarning();
    }
}

/*
======================================================================================================
INTEGER
======================================================================================================
*/

class DataField_integer extends DataField {

    const parameters = array( 'width','max','min','default' );

    protected $filterSpec = array('filter'=>['gt','lt']);

    function __construct( $params ) {
        parent::__construct($params);
        $this->filterSpec['width'] = ((int)$this->max == 0) ? 10 : ceil(log10((int)$this->max));
    }

    static function sanitizeParameters( &$parameters, $dataFieldId ) {
        foreach( array('width','max','min') as $thing ) {
            if (strlen($parameters[$thing])) $parameters[$thing] = (int)$parameters[$thing];
            else $parameters[$thing]='';
        }
        if ($parameters['min']>$parameters['max']) {
            $temp = $parameters['max'];
            $parameters['max'] = $parameters['min'];
            $parameters['min'] = $temp;
        }
        if ($parameters['width']>300) $parameters['width']=300;
        if ($parameters['width']<1) $parameters['width']=1;
    }

    function validate( &$value ) {
        if (!is_string($value)) return 'Invalid type of data supplied';

        $name = $this->name;
        if ( strlen($this->max) && $value > $this->max ) return "$name must not be greater than ".$this->max;
        if ( strlen($this->min) && $value < $this->min ) return "$name must be at least ".$this->min;
        return true;
    }

    function displayDefinitionForm() {
        $prefix = parent::$parameterPrefix;
        questionAndAnswer(
            'Display width',
            function() use($prefix){
                formInteger($prefix.'width',1,200,1,1);
                echo '<div class="note">Characters</div>';
            }
        );
        questionAndAnswer(
            'Maximum',
            function() use($prefix){
                formInteger($prefix.'max');
                echo '<div class="note">Leave empty for no maximum</div>';
            }
        );
        questionAndAnswer(
            'Minimum',
            function() use($prefix){
                formInteger($prefix.'min');
                echo '<div class="note">Leave empty for no minimum</div>';
            }
        );
        questionAndAnswer(
            'Default',
            function() use($prefix){
                formInteger($prefix.'default');
            }
        );

    }

    function displayInput() {
        $inputName = $this->inputName();
        formInteger( $inputName, $this->min, $this->max, 1, (int)$this->getAnswer(), 'class="dataField integer"' );
        $this->displayUnit();
        $this->versionLink();
        inputError($inputName);
        $this->displayDefaultWarning();
    }

    function displayFilter() {
        $names = $this->filterNames();
        $values = $this->getFilterValues();
        echo "&gt;&nbsp;";
        formInteger( $names[0], $this->min, $this->max, 1, $values[0] );
        echo "<br />&lt;&nbsp;";
        formInteger( $names[1], $this->min, $this->max, 1, $values[1] );
    }
}

/*
======================================================================================================
FLOAT
======================================================================================================
*/

class DataField_float extends DataField_integer {

    function __construct( $params ) {
        parent::__construct($params);
    }

    function displayDefinitionForm() {
        # see...
        # https://stackoverflow.com/questions/19011861/is-there-a-float-input-type-in-html5
    }

    function displayInput() {
        $inputName = $this->inputName();
        formTextbox($inputName,10,200,(float)$this->getAnswer(), 'class="dataField integer"');
        $this->displayUnit();
        $this->versionLink();
        inputError($inputName);
        $this->displayDefaultWarning();
    }

    function displayFilter() {
        $names = $this->filterNames();
        $values = $this->getFilterValues();
        echo "&gt;&nbsp;";
        formTextbox( $names[0], 5, 200, $values[0] );
        echo "<br />&lt;&nbsp;";
        formTextbox( $names[1], 5, 200, $values[1] );
    }
}

/*
======================================================================================================
SELECT
======================================================================================================
*/

class DataField_select extends DataField {

    const parameters = array( 'subtype', 'prompt', 'optionValues[]', 'optionDefaults[]' );

    function __construct( $params ) {
        parent::__construct($params);
    }

    function validate( &$value ) {
        if (strpos('|dropdown|radio|',$this->subtype)) {
            if (!is_string($value)) return 'Invalid type of data supplied';
            $testValues = array($value);
        } else {
            if (!is_array($value)) return 'Invalid type of data supplied';
            $testValues = $value;
        }

        // Check that the supplied values are all on the list of valid values

        foreach( $testValues as $testValue ) {
            if (!in_array($testValue, $this->optionValues)) return "The value chosen isn't on the list of valid answers";
        }

        return true;
    }

    function unpackFromStorage( &$value ) {
        $value = array_filter(explode('|',$value),'strlen');
    }

    function packForStorage( &$value ) {
        if (is_array($value)) $value = implode('|',$value);
    }

    static function sanitizeParameters( &$parameters, $dataFieldId ) {
        // Remove any duplicate options
        if (!isset($parameters['optionValues'])) $parameters['optionValues']=array();
        if (!isset($parameters['optionDefaults'])) $parameters['optionDefaults']=array();

        $values = array();
        $defaults = array();
        foreach($parameters['optionValues'] as $idx=>$value) {
            if (in_array($value,$values)) continue;
            $values[] = $value;
            $defaults[] = isset($parameters['optionDefaults'][$idx]) && $parameters['optionDefaults'][$idx] ? true : false;
        }

        // If the type is dropdown, and no prompt has been set, and no default is set - then set the first value to be the default
        if ($parameters['subtype']=='dropdown' && !strlen($parameters['prompt']) && !in_array(true,$defaults)) $defaults[0]=true;

        $parameters['optionValues'] = $values;
        $parameters['optionDefaults'] = $defaults;
    }

    function displayDefinitionForm() {
        $prefix = parent::$parameterPrefix;

        questionAndAnswer(
            'Subtype',
            function() use($prefix){
                formOptionbox($prefix.'subtype',array('Checkboxes'=>'checkboxes','Radio Buttons'=>'radio','Dropdown'=>'dropdown','Dual list picker'=>'picker'),'id="subtypeSelect"');
            }
        );

        $existingOptionData = array();
        $existingOptionValues = ws($prefix.'optionValues');
        if (is_array($existingOptionValues)) {
            $existingOptionDefaults = ws($prefix.'optionDefaults');
            $existingOptionData = array_combine( $existingOptionValues, $existingOptionDefaults );
        }

        ?>
        <div class="questionAndAnswer dataFieldParameter_prompt" dependsOn="<?=$prefix.'subtype'?> eq dropdown">
            <div class="question">
                Prompt
            </div>
            <div class="answer">
                <? formTextbox($prefix.'prompt',20,250); ?>
                <div class="info">
                    Prompt is optional. If you provide a prompt, then you cannot set a default value.<br />
                    If you want an empty prompt just type a space in the prompt box.
                </div>
            </div>
        </div>
        <div class="questionAndAnswer dataFieldParameter_options">
            <div class="question">
                Options<br />
                <button id="addSelectOptionButton">Add</button>
            </div>
            <div class="answer">
                <div class="selectDefinition">
                    <table>
                        <thead>
                            <tr>

                                <th class="defaultCheckbox">Is default?</th>
                                <th class="value">Option</th>
                                <th colspan="2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr style="display:none" class="template">
                                <td class="defaultCheckbox"><input type="checkbox" name="<?=$prefix.'optionDefaults[]'?>" value="1"></td>
                                <td class="value"><? formTextbox($prefix.'optionValues[]',20,250)?></td>
                                <td class="delete"></td>
                                <td class="handle"></td>
                            </tr>
                        </tbody>
                    </table>
                    <a id="sortOptionsButton" href="#">Sort Alphabetically</a>
                </div>
                <script src="/javascript/jquery-ui.justDraggable.min.js"></script>
                <script>
                    $(function(){
                        var rowTemplate = $('div.selectDefinition table tr.template');
                        var subTypeSelect = $('#subtypeSelect');
                        var promptInput = $('div.dataFieldParameter_prompt input');
                        var table = $('div.selectDefinition table');

                        table.find('tbody').sortable({
                            update:function(e,ui){
                            }
                        });

                        table.on('click','td.delete',function(){
                            $(this).closest('tr').remove();
                        });

                        $('#sortOptionsButton').click(function(){
                            var desc = table.data('sortOrder');
                            table.data('sortOrder',!desc);
                            tbody = table.find('tbody');
                            tbody.find('tr').sort(function(a, b) {
                                if (desc) {
                                    return $('td.value input', b).val().localeCompare($('td.value input', a).val());
                                } else {
                                    return $('td.value input', a).val().localeCompare($('td.value input', b).val());
                                }
                            }).appendTo(tbody);
                            return false;
                        });

                        function addOption( value, isDefault ) {
                            var newRow = rowTemplate.clone().appendTo(table).show();
                            if (value) newRow.find('td.value input').val(value);
                            if (isDefault) newRow.find('td.defaultCheckbox input').prop('checked',true);
                        }

                        $('#addSelectOptionButton').on('click',function(){
                            addOption();
                            return false;
                        });

                        function updateDefaultInputs() {
                            var subType = subTypeSelect.val();
                            console.log(subType);
                            var checkboxes = $('div.selectDefinition').find(':radio,:checkbox');
                            var defaultColumn = $('div.selectDefinition .defaultCheckbox');

                            if (subType=='radio' || subType=='dropdown') {
                                checkboxes.not(':checked:first').prop('checked',false).end().prop('type','radio');

                            } else {
                                checkboxes.prop('type','checkbox');
                            }

                            if (subType=='dropdown' && promptInput.val().length) {
                                defaultColumn.hide();
                            } else {
                                defaultColumn.show();
                            }

                        }

                        subTypeSelect.add(promptInput).on('change',updateDefaultInputs);

                        var existingOptions = <?=json_encode($existingOptionData)?>;
                        $.each(existingOptions,addOption);
                        updateDefaultInputs();
                    });
                </script>
            </div>
        </div>
    <? }

    function displayInput() {
        $inputName = $this->inputName();

        $this->default = [];
        foreach( $this->optionValues as $idx=>$value ) {
            if ($this->optionDefaults[$idx]) $this->default[]=$value;
        }
        $answer = $this->getAnswer();

        $optionBox = new formOptionbox( $inputName );

        $optionBox->setExtra('class="dataField select"');
        if ($this->prompt && $this->subtype=='dropdown') $optionBox->addOption($this->prompt,'');
        $optionBox->addOptions(array_combine($this->optionValues,$this->optionValues));
        if ($this->subtype=='checkboxes' || $this->subtype=='picker') {
            $optionBox->setMultiple(true);
        }
        if (!is_null($answer)) $optionBox->setDefault($answer);
        if ($this->subtype=='picker') {
            $optionBox->displayPicklist();
        } else if ($this->subtype=='checkboxes' || $this->subtype=='radio') {
            $optionBox->displayCheckboxes();
        } else {
            $optionBox->display();
        }
        $this->displayUnit();
        $this->versionLink();
        inputError($inputName);
        $this->displayDefaultWarning();
    }

    function displayFilter() {
        $optionBox = new formOptionbox( $this->filterNames()[0] );
        $optionBox->addOption('-- any --','');
        $optionBox->addOptions(array_combine($this->optionValues,$this->optionValues));

        $optionBox->display();
        $values = $this->getFilterValues();
    }}

/*
======================================================================================================
DATE
======================================================================================================
*/

class DataField_date extends DataField {

    const parameters = array( 'max','min','defaultToToday' );

    protected $filterSpec = array('width'=>'10','filter'=>['gt','lt']);

    function __construct( $params ) {
        parent::__construct($params);
    }

    static function sanitizeParameters( &$parameters, $dataFieldId ) {
        // Check that min and max are valid dates
        foreach(['min'=>'not before date','max'=>'not after date'] as $what=>$description) {
            if (!preg_match('/^\s*(\d{4})-(\d{2})-(\d{2})/',$parameters[$what],$matches)) return 'The '.$description.' is not valid';
        }

    }

    function validate( &$value ) {
        if (!preg_match('/^\\d+$/',$value)) return 'Invalid value supplied';
       
        $value = (int)$value;
        if ($this->max>0 && $value>$this->max) return 'The date supplied is beyond the permitted date range';
        if ($value < $this->min) return 'The date supplied is before the permitted date range';

        return true;
    }

    function displayDefinitionForm() {
        $prefix = parent::$parameterPrefix;

        questionAndAnswer(
            'Not before',
            function() use($prefix){
                formDate($prefix.'min');
            }
        );
        questionAndAnswer(
            'Not after',
            function() use($prefix){
                formDate($prefix.'max');
                ?>
                <div class="info">Leave unset for no date restrictions</div>
                <?
            }
        );
        questionAndAnswer(
            'Default to today',
            function() use($prefix){
                formYesNo($prefix.'defaultToToday',false);
            }
        );
    }

    function displayInput() {
        $inputName = $this->inputName();
        if ($this->defaultToToday) $this->default = time();
        formDate($inputName,$this->getAnswer(),$this->min,$this->max, 'class="dataField date"');
        $this->versionLink();
        inputError($inputName);
        $this->displayDefaultWarning();
    }

    function displayFilter() {
        $names = $this->filterNames();
        $values = $this->getFilterValues();
        echo "&gt;&nbsp;";
        formDate( $names[0], $values[0], $this->min, $this->max );
        echo "<br />&lt;&nbsp;";
        formDate( $names[1], $values[1], $this->min, $this->max );
    }

    static function formatForDisplay($value, $recordId, $fieldId) {
        if (!is_numeric($value)) return;
        echo date('d/m/Y',$value);
    }
}

/*
======================================================================================================
DURATION
======================================================================================================
*/

class DataField_duration extends DataField {

    function __construct( $params ) {
        parent::__construct($params);
    }

    function displayDefinitionForm() {
        echo "Not yet implemented";
    }

    function displayInput() {
        echo "Divider";
    }
}

/*
======================================================================================================
EMAIL ADDRESS
======================================================================================================
*/

class DataField_emailAddress extends DataField {

    const parameters = array('width', 'hint', 'default');

    const filterSpec = array('width'=>10,'filter'=>'ct');

    function __construct( $params ) {
        parent::__construct($params);
    }

    function validate( &$value ) {
        if (!is_string($value)) return 'Invalid type of data supplied';

        $name = $this->name;
        if(filter_var($value, FILTER_VALIDATE_EMAIL) === false) return "$name must be a valid email address";

        return true;
    }

    function displayDefinitionForm() {
        $prefix = parent::$parameterPrefix;
        questionAndAnswer(
            'Width (characters)',
            function() use($prefix){
                formInteger($prefix.'width',1,1000,null,20);
            }
        );
        questionAndAnswer(
            'Hint',
            function() use($prefix){
                formTextbox($prefix.'hint',30,250);
            }
        );
        questionAndAnswer(
            'Default',
            function() use($prefix){
                formTextbox($prefix.'default',30,250);
            }
        );
    }

    function displayInput() {
        $inputName = $this->inputName();
        formEmail($inputName, $this->width, '', $this->getAnswer(), 'class="dataField '.htmlspecialchars($this->getType()).'" placeholder="'.htmlspecialchars($this->hint).'"');
        $this->versionLink();
        inputError($inputName);
        $this->displayDefaultWarning();
    }
}

/*
======================================================================================================
URL
======================================================================================================
*/

class DataField_url extends DataField_textbox {

    const parameters = array( 'width', 'hint', 'maxLength', 'minLength', 'allowedSchemes[]', 'displayAsLink', 'openInNewTab', 'default' );
    const urlSchemes = array( 'file', 'ftp', 'http', 'https' );
    const defaultUrlSchemes = array( 'http', 'https' );

    static $javascriptDone = false;

    function __construct( $params ) {
        parent::__construct($params);
    }

    static function sanitizeParameters( &$params, $dataFieldId ) {
        // the array_values effectively re-orders the array if any elements are removed so it will always be zero-based
        if (isset($params['allowedSchemes'])) $params['allowedSchemes'] = array_values(array_filter( $params['allowedSchemes'], function($input) {
            return in_array($input,self::urlSchemes);
        }));

        if (!count($params['allowedSchemes'])) $params['allowedSchemes']=self::defaultUrlSchemes;
    }

    function displayDefinitionForm() {
        parent::displayDefinitionForm();

        $prefix = parent::$parameterPrefix;

        if (!is_array(ws($prefix.'allowedSchemes'))) ws($prefix.'allowedSchemes',self::defaultUrlSchemes);

        questionAndAnswer(
            'Allowed URL Schemes',
            function() use($prefix){
                $schemeSelect = new formOptionbox($prefix.'allowedSchemes',array_combine(self::urlSchemes,self::urlSchemes));
                $schemeSelect->setMultiple();
                $schemeSelect->displayCheckboxes();
            }
        );
    }

    function validate( &$value ) {
        $parentResult = parent::validate( $value );
        if ($parentResult!==true) return $parentResult;

        $value = trim($value);

        // Check the value supplied against the list of valid URL schemes
        $validScheme = false;
        if (!is_array($this->allowedSchemes)) $this->allowedSchemes=[];
        foreach($this->allowedSchemes as $scheme) {
            if (substr($value,0,strlen($scheme)+3)===$scheme.'://') {
                $validScheme=true;
                break;
            }
        }

        if (!$validScheme) {
            if (count($this->allowedSchemes)==1) return 'The URL must begin with '.$this->allowedSchemes[0].'://';
            return 'The URL must begin with one of: '.implode (' ', preg_filter('/$/', '://', $this->allowedSchemes));
        }

        if (!filter_var( $value, FILTER_VALIDATE_URL )) {
            return 'The URL supplied is not valid - please supply a valid URL';
        }

        return true;
    }

    function displayInput() {
        parent::displayInput( );
        echo '<a class="openUrl btn small" target="_blank" rel="noopener noreferrer" href="#">Open</a>';
        if (!self::$javascriptDone) {
            self::$javascriptDone = true;
            ?><script>
                $(function(){
                    $('div.questionAndAnswer.url input.url').on('change',function() {
                        let self = $(this);
                        let url = self.val();
                        let isValid = true;

                        try {
                            new URL(url);
                        } catch (error) {
                            if (url.match(/^https?:\/\//)) {
                                isValid = false;
                            } else {
                                url = 'https://'+url;
                                try {
                                    new URL(url);
                                } catch (error) {
                                    isValid = false;
                                }
                                if (isValid) self.val(url);
                            }
                            if (!isValid) {
                                alertPopup('The URL is not valid - please edit it and try again');
                                self.parent().find('a.openUrl').hide();
                                return true;
                            }
                        }
                        self.parent().find('a.openUrl').show();
                        return true;
                    });

                    $('div.questionAndAnswer.url a.openUrl').on('click',function(){
                        let self = $(this);
                        let urlInput = self.parent().find('input.url');
                        let url=urlInput.val().trim();
                        if (!url.length) return;

                        self.attr('href',url);
                        return true;
                    });
                });
            </script><?
        }

    }
}

/*
======================================================================================================
UPLOAD
======================================================================================================
*/

class DataField_upload extends DataField {

    private $upload = false;
    private $fieldName;

    const parameters = array( 'maxSize' );

    function __construct( $params ) {
        parent::__construct($params);
    }

    function displayDefinitionForm() {
        $prefix = parent::$parameterPrefix;

        questionAndAnswer(
            'Maximum size',
            function() use($prefix){
                $maxUploadSize = formAsyncUpload::getMaxUploadSize();
                $reason = formAsyncUpload::getMaxUploadSize(true);
                formInteger($prefix.'maxSize',0,round($maxUploadSize)/1048576);
                echo ' MB <div class="note">Set to zero to use the maximum set by the server which is '.formatBytes($maxUploadSize);
                if (!empty($reason)) echo " (limited by $reason configuration)";
                echo '</div>';
            }
        );
    }

    function setup($recordId) {
        $this->fieldName = 'upload_'.$this->id;
        $this->upload = new formAsyncUpload($this->fieldName,LIB_DIR.'/dataFieldFileUpload.php');
        $this->upload->setAttributes(array(
            'recordId'=>$recordId,
            'dataFieldId'=>$this->id
        ));

        foreach( explode(',','maxSize,minWidth,minHeight,maxWidth,maxHeight') as $thing ) {
            $$thing = isset($this->params[$thing]) && $this->params[$thing]>0 ? $this->params[$thing] : '-';
        }

        $this->upload->setState('maxSize',isset($this->params['maxSize'])?$this->params['maxSize']:0);
    }

    function save($value,$hidden, $inherited=NULL, $fromRecordId=NULL) {
        $result = $this->upload->store();

        if ($result === true) {
            $upload = $this->upload->getFileObject();

            if (!$this->params['optional'] && (!$upload || !$upload->exists()) ) {
                $result = cms('This field is required - you must upload a file');
            }
        }

        // Even if the upload field is hidden on the form we still save the image, but...
        // Don't return errors against hidden fields
        if ($hidden) return true;

        return $result;
    }

    function displayInput() {
        $this->upload->display();
        // errors might be logged against $this->fieldName (which is the special field name we use because it is an upload)
        inputError($this->fieldName);
        // OR... errors might be flagged against $this->inputName() (which is the name use by all standard fields)
        inputError($this->inputName());
    }

    function filterNames() {
        return [];
    }
}


/*
======================================================================================================
IMAGE
======================================================================================================
*/

class DataField_image extends DataField {

    private $upload = false;
    private $fieldName;

    const parameters = array(
        'maxSize', 'maxWidth', 'maxHeight', 'minWidth', 'minHeight', 'keepOriginal',
        'thumbnailWidth', 'thumbnailHeight', 'thumbnailResizeMode',
        'thumbnailBackgroundColour', 'thumbnailCompression', 'thumbnailSettingLastChangedAt',
        'previewWidth', 'previewHeight', 'previewResizeMode',
        'previewBackgroundColour', 'previewCompression', 'previewSettingLastChangedAt'
    );

    function __construct( $params ) {
        parent::__construct($params);
    }

    function displayDefinitionForm() {
        $prefix = parent::$parameterPrefix;

        questionAndAnswer(
            'Maximum file size',
            function() use($prefix){
                $maxUploadSize = formAsyncUpload::getMaxUploadSize();
                $reason = formAsyncUpload::getMaxUploadSize(true);
                formInteger($prefix.'maxSize',0,round($maxUploadSize)/1048576);
                echo ' MB <div class="note">Set to zero to use the maximum set by the server which is '.formatBytes($maxUploadSize);
                if (!empty($reason)) echo " (limited by $reason configuration)";
                echo '</div>';
            }
        );

        questionAndAnswer(
            'Maximum width',
            function() use($prefix){
                formInteger($prefix.'maxWidth');
                echo '<div class="note">Leave empty for no maximum.</div>';
            }
        );
        questionAndAnswer(
            'Maximum height',
            function() use($prefix){
                formInteger($prefix.'maxheight');
                echo '<div class="note">Leave empty for no maximum.</div>';
            }
        );
        questionAndAnswer(
            'Minimum width',
            function() use($prefix){
                formInteger($prefix.'minWidth');
                echo '<div class="note">Leave empty for no minimum.</div>';
            }
        );
        questionAndAnswer(
            'Minimum height',
            function() use($prefix){
                formInteger($prefix.'minHeight');
                echo '<div class="note">Leave empty for no minimum.</div>';
            }
        );

        questionAndAnswer(
            'Keep original',
            function() use($prefix){
                formYesNo($prefix.'keepOriginal',false);
                echo '<div class="note">Keeping the original file takes up more space, but allows users to download the original file and also allows you to change the size of the preview and thumbnail image at a later date without losing image quality.</div>';
            }
        );

        echo '<div class="columns2"><div class="column1">';

        questionAndAnswer(
            'Thumbnail width',
            function() use($prefix){
                formInteger($prefix.'previewWidth');
            }
        );
        questionAndAnswer(
            'Thumbnail height',
            function() use($prefix){
                formInteger($prefix.'previewHeight');
            }
        );
        questionAndAnswer(
            'Resize method',
            function() use($prefix){
                formOptionbox($prefix.'previewResizeMode',array(
                    'Pad the image to the desired size'                                         => 'fill',
                    'Reduce the image so it fits inside the thumbnail size, but do not pad it'  => 'fit',
                    'Reduce the image so it fill the thumbnail, cropping off any excess'        => 'crop',
                ));
            }
        );

        questionAndAnswer(
            'Padding colour',
            function() use($prefix){
                formColour($prefix.'previewBackgroundColour');
                echo '<div class="note">(click colour swatch above to change colour</div>';
            },
            '',
            'dependsOn="'.htmlspecialchars($prefix.'previewResizeMode').' eq fill"'
        );

        echo '</div><div class="column2">';

        questionAndAnswer(
            'Preview width',
            function() use($prefix){
                formInteger($prefix.'thumbnailWidth');
            }
        );
        questionAndAnswer(
            'Preview height',
            function() use($prefix){
                formInteger($prefix.'thumbnailHeight');
            }
        );
        questionAndAnswer(
            'Resize method',
            function() use($prefix){
                formOptionbox($prefix.'thumbnailResizeMode',array(
                    'Pad the image to the desired size'                                         => 'fill',
                    'Reduce the image so it fits inside the thumbnail size, but do not pad it'  => 'fit',
                    'Reduce the image so it fill the thumbnail, cropping off any excess'        => 'crop',
                ));
            }
        );
        questionAndAnswer(
            'Padding colour',
            function() use($prefix){
                formColour($prefix.'thumbnailBackgroundColour');
                echo '<div class="note">(click colour swatch above to change colour</div>';
            },
            '',
            'dependsOn="'.htmlspecialchars($prefix.'thumbnailResizeMode').' eq fill"'
        );
        echo '</div></div>';
    }


    static function sanitizeParameters( &$parameters, $dataFieldId ) {
        foreach ( explode(',','thumbnail,preview') as $which ) {
            if (!$parameters[$which.'Width']) $parameters[$which.'Width']=200;
            if (!$parameters[$which.'Height']) $parameters[$which.'Height']=200;
            if (!$parameters[$which.'ResizeMode']) $parameters[$which.'ResizeMode']='fit';
            if (!$parameters[$which.'BackgroundColour']) $parameters[$which.'BackgroundColour']='#ffffff';
            // Just hard code the compression for now
            $parameters[$which.'Compression']=9;
        }

        // If any of the thumbnail/preview parameters changed then we need to record the fact so that we can trigger a rebuild of the relevant image
        // To determine this we first need to know the old parameters...

        // Get the parameters from the database
        if (!$dataFieldId) {
            $parameters['thumbnailSettingLastChangedAt'] = $parameters['previewSettingLastChangedAt'] = time();
        } else {
            global $DB;
            list($oldSerializedParameters) = $DB->getRow('SELECT parameters FROM dataField WHERE id=?',$dataFieldId);
            $oldParameters=array();
            self::unserializeParameters( $oldSerializedParameters, $oldParameters );
            foreach ( explode(',','thumbnail,preview') as $which ) {
                if (!isset($oldParameters[self::$parameterPrefix.$which.'SettingLastChangedAt'])) {
                    $parameters[$which.'SettingLastChangedAt'] = time();
                } else {
                    $parameters[$which.'SettingLastChangedAt']=$oldParameters[self::$parameterPrefix.$which.'SettingLastChangedAt'];
                }
                foreach( explode(',','Width,Height,Compression,ResizeMode,BackgroundColour') as $param ) {
                    if (
                        !isset($oldParameters[self::$parameterPrefix.$which.$param]) ||
                        $oldParameters[self::$parameterPrefix.$which.$param]!=$parameters[$which.$param]
                    ) {
                        $parameters[$which.'SettingLastChangedAt']=time();
                        break;
                    }
                }
            }
        }
    }

    function setup($recordId) {
        $this->fieldName = 'image_'.$this->id;
        $this->upload = new formAsyncUpload($this->fieldName,LIB_DIR.'/dataFieldImage.php');
        $this->upload->setAttributes(array(
            'recordId'=>$recordId,
            'dataFieldId'=>$this->id
        ));

        foreach( explode(',','minWidth,minHeight,maxWidth,maxHeight') as $thing ) {
            $$thing = isset($this->params[$thing]) && $this->params[$thing]>0 ? $this->params[$thing] : '-';
        }

        $this->upload->setState('dims',array(
            'minDims'=> $minWidth.'x'.$minHeight,
            'maxDims'=> $maxWidth.'x'.$maxHeight
        ));
        $this->upload->setState('maxSize',isset($this->params['maxSize'])?$this->params['maxSize']:0);

        $sizes = array();
        $lastChanged = array();

        foreach ( explode(',','thumbnail,preview') as $which ) {
            $sizes[ $which ] = '';
            foreach( explode(',','Width,Height,Compression,ResizeMode,BackgroundColour') as $param ) {
                $sizes[ $which ] .= $this->params[$which.$param].'x';
            }
            $lastChanged[ $which ] = $this->params[$which.'SettingLastChangedAt'];
        }

        if (!$this->params['keepOriginal']) $sizes['original']='';

        $this->upload->setState('sizes', $sizes );
        $this->upload->setState('settingsLastChangedAt',$lastChanged);
    }

    function save($value,$hidden, $inherited=NULL, $fromRecordId=NULL ) {
        $result = $this->upload->store();

        if ($result === true) {
            $imageFile = $this->upload->getFileObject();

            if (!$this->params['optional'] && (!$imageFile || !$imageFile->exists()) ) {
                $result = cms('This field is required - you must upload an image');
            }
        }

        // Even if the image field is hidden on the form we still save the image, but...
        // Don't return errors against hidden fields
        if ($hidden) return true;

        return $result;
    }

    function displayInput() {
        $this->upload->display();
        // errors might be logged against $this->fieldName (which is the special field name we use because it is an upload)
        inputError($this->fieldName);
        // OR... errors might be flagged against $this->inputName() (which is the name use by all standard fields)
        inputError($this->inputName());
    }

    static function formatForDisplay($value, $recordId, $fieldId) {
        $image = new dataFieldImage( array(
            'recordId'  => (int)$recordId,
            'dataFieldId' => (int)$fieldId
        ));

        $image->display('thumbnail','short');
    }

    function filterNames() {
        return [];
    }

}

/*
======================================================================================================
TYPE TO SEARCH
======================================================================================================
*/

class DataField_typeToSearch extends DataField_textbox {

    const parameters = array( 'searchUrl', 'width', 'maxLength', 'minLength', 'hint' );
    protected $showNoResults = true;
    protected $hiddenInput = true; // Send the selected result in a hidden input and call the textbox something else
    static $nextId = 0;

    function __construct( $params ) {
        parent::__construct($params);
    }

    static function sanitizeParameters( &$parameters, $dataFieldId ) {
        // trim off any protocol from searchUrl and make sure it starts with '/'
    }

    function displayDefinitionForm() {
        $prefix = parent::$parameterPrefix;
        questionAndAnswer(
            'Search URL',
            function() use($prefix) {
                formTextbox($prefix.'searchUrl', 30, 50);
            }
        );
        parent::displayDefinitionForm();
    }

    function displayInput() {
        $inputName = $this->inputName();
        $searchUrl = $this->searchUrl;
        $extraQueryParams = 'dataFieldId='.$this->id.'&';

        if(strpos($searchUrl, '?') !== false) { // assume the url finishes with 'someSearchParam='
            $searchUrl = str_replace('?', '?'.$extraQueryParams, $searchUrl);
        } else {
            $searchUrl .= '?'.$extraQueryParams;
        }

        formTypeToSearch([
            'class' => 'dataField',
            'url' => $searchUrl,
            'showNoResults' => $this->showNoResults,
            'size' => $this->width,
            'default' => $this->getAnswer(),
            'hidden' => $this->hiddenInput,
            'name' => $inputName
        ]);

        $this->displayUnit();
        $this->versionLink();
        inputError($inputName);

        self::$nextId++;
    }
}

/*
======================================================================================================
SUGGESTED TEXTBOX
======================================================================================================
*/

class DataField_suggestedTextbox extends DataField_typeToSearch {

    const parameters = array( 'width', 'maxLength', 'minLength', 'hint', 'predefinedOptions', 'allowAdditions', 'suggestAdditions' );

    function __construct( $params ) {
        parent::__construct($params);
        $this->showNoResults = false;
        $this->hiddenInput = false;
        $this->searchUrl = 'suggestedTextboxSearch.php';
    }

    function getDefinitionHelp() {
    }

    static function sanitizeParameters( &$parameters, $dataFieldId ) {
        // trim all the predefined options
        $options = preg_split('/[\r\n]+/',$parameters['predefinedOptions']);
        $options = array_map('trim',$options);
        $parameters['predefinedOptions'] = implode("\n",$options);

        $parameters['allowAdditions'] = (bool)$parameters['allowAdditions'];
        if ($parameters['allowAdditions']) $parameters['suggestAdditions'] = (bool)$parameters['suggestAdditions'];
        else $parameters['suggestAdditions'] = false;
    }

    function validate( &$value ) {
        $parentResult = parent::validate( $value );
        if ($parentResult!==true) return $parentResult;

        if ($this->allowAdditions) return true;

        $value = trim($value);
        $options = explode("\n",$this->predefinedOptions);
        $options = array_combine( array_map('strtoupper',$options), $options );

        if (!isset($options[strtoupper($value)])) {
            return "You cannot add your own value - you must choose one of the predefined options";
        }

        $value = $options[strtoupper($value)];

        return true;
    }

    function displayDefinitionForm() {
        $prefix = parent::$parameterPrefix;
        questionAndAnswer(
            'Predefined options',
            function () use($prefix) {
                formTextarea($prefix.'predefinedOptions', 40, 10);
                echo '<div class="note">Enter each option on a new line</div>';
            }
        );
        questionAndAnswer(
            'Allow values not listed here',
            function () use($prefix) {
                formYesNo($prefix.'allowAdditions',false,true);
                echo '<div class="note">If set to "yes" then the user can enter new values not listed in the predefined options</div>';
            }
        );
        echo '<div class="questionAndAnswer dataFieldParameter_prompt" dependsOn="'.$prefix.'allowAdditions eq 1">';
        questionAndAnswer(
            'Suggest user-contributed answers to other users',
            function () use($prefix) {
                formYesNo($prefix.'suggestAdditions',false,true);
            }
        );
        echo '</div>';
    }

    function displayFilter() {
        $inputName = $this->filterNames()[0];
        $searchUrl = $this->searchUrl;
        $extraQueryParams = 'mode=search&dataFieldId='.$this->id.'&';

        if(strpos($searchUrl, '?') !== false) { // assume the url finishes with 'someSearchParam='
            $searchUrl = str_replace('?', '?'.$extraQueryParams, $searchUrl);
        } else {
            $searchUrl .= '?'.$extraQueryParams;
        }

        formTypeToSearch([
            'class' => 'dataField',
            'url' => $searchUrl,
            'showNoResults' => false,
            'name' => $inputName,
            'default' => ws($inputName)
        ]);

        self::$nextId++;
        return;
    }
}

/*
======================================================================================================
CHEMICAL FORMULA
======================================================================================================
*/

class DataField_chemicalFormula extends DataField {

    private static $javascriptDone = false;

    const parameters = array('default');

    const filterSpec = array( 'width'=>10,'filter'=>'ct');

    function __construct( $params ) {
        parent::__construct($params);
    }

    function validate( &$value ) {
        if (!is_string($value)) return 'Invalid type of data supplied';
        return true;
    }

    function displayDefinitionForm() { ?>
    <? }

    function displayInput() {
        if (!self::$javascriptDone) {
            self::$javascriptDone = true;
            ?>
       		<script defer src="/javascript/chemicalInput/chemicalInput.js"></script>
            <script defer src="/javascript/chemicalInputHandlers.js"></script>
            <script>
                var link = document.createElement("link");
                link.rel = "stylesheet";
                link.type = "text/css";
                link.href = "/javascript/chemicalInput/chemicalInput.css";
                document.head.appendChild(link);

                window.addEventListener('DOMContentLoaded', function() {
                    let picker = new ChemicalInputs({
                        'favouritesSaveHandler' : chemicalInputFavourites_saveHandler,
                        'favouritesLoadHandler' : chemicalInputFavourites_loadHandler
                    });
                });
            </script>
        <?}
        $inputName = $this->inputName();
        formTextbox($inputName,10,255,$this->getAnswer(),'data-type="chemical" class="dataField '.htmlspecialchars($this->getType()).'"');
        $this->versionLink();
        inputError($inputName);
        $this->displayDefaultWarning();
    }

}


/*
======================================================================================================
GRAPH
======================================================================================================
*/

class DataField_graph extends DataField {

    private static $javascriptDone = false;
    
    private $recordTypeId = null;
    
    private $upload = false;
    private $uploadQuestion = false;

    const parameters = array('default','uploadDataFieldId');

    function __construct( $params ) {
        parent::__construct($params);
    }

    function validate( &$value ) {
        if (!is_string($value)) return 'Invalid type of data supplied';
        return true;
    }

	function displayRow( $isPublic = true, $hideLabel = true ) {
		return parent::displayRow( $isPublic, $hideLabel );
	}
	
	function displayJavascript($definitionOnly) {
        if (!self::$javascriptDone) {
            self::$javascriptDone = true;
            ?>
				<script src="/javascript/chart/defineChart.js"></script>
				<script>
				// ECharts standard options
				const chartOptions = {
					'themes' : ['default', 'dark', 'vintage','shine'],
					'pointTypes' : ['none','circle', 'rect', 'roundRect', 'triangle', 'diamond', 'pin', 'arrow'],
					'lineTypes' : ['solid', 'dashed', 'dotted']
				};
				</script>
			<?
			if (!$definitionOnly) {
				?>
				<script src="/javascript/chart/xlsx.mini.min.js"></script>
				<script src="/javascript/chart/echarts.min.js"></script>
				<script src="/javascript/chart/themes/dark.js"></script>
				<script src="/javascript/chart/themes/vintage.js"></script>
				<script src="/javascript/chart/themes/shine.js"></script>				
				<script src="/javascript/chart/csvReader.js"></script>
				<script src="/javascript/chart/createChart.js"></script>
				<script>
					$('body').on('click','button.chartSettings',function(){
						$(this).hide().parent().find('.chartDefinition').show();
                        return false;
					});
				</script>
				<?
			}
		}
	}
	
    function displayDefinitionForm() {
		// We need to know the record Type ID in order to be able to build the connected upload field ID
		if (empty($this->recordTypeId)) echo '<div class="error">Field error: the record type ID has not been set</div>';
		$prefix = parent::$parameterPrefix;
	
		$uploadFieldTypeId = array_search('Upload',DataField::typeLookup);
		$uploadDataFieldSelect = new formOptionbox($prefix.'uploadDataFieldId');
		$uploadDataFieldSelect->addLookup('
			SELECT
				name, id
			FROM dataField
			WHERE
				dataField.deletedAt=0 AND
				dataField.recordTypeId=? AND
				dataField.typeId=?
		',$this->recordTypeId,$uploadFieldTypeId);

        questionAndAnswer(
            'Chart data from this file',
            function () use($uploadDataFieldSelect) {
                $uploadDataFieldSelect->display();
                ?>
                <div class="note">The source data for the chart will be loaded from this file.</div>
                <?
            }
        );
		
		
		$this->displayJavascript(true);
		
		formHidden($prefix.'default',null,null,'id="chartDefinition"');
		
		?>
		<h2>Default Chart Definition</h2>
		<div class="section" id="chartFormContainer">
		</div>
		<script>
			defineChart( $('#chartFormContainer').get(0), $('#chartDefinition').get(0), chartOptions);
		</script>
		<?
    }

	function setRecordTypeId( $recordTypeId ) {
		$this->recordTypeId=$recordTypeId;
	}
	
	function setup($recordId) {
        global $DB;
		include_once(LIB_DIR.'/dataFieldFileUpload.php');
		if (empty($this->params['uploadDataFieldId'])) return false;
        $this->upload = new dataFieldFileUpload([
            'recordId'=>$recordId,
            'dataFieldId'=>$this->params['uploadDataFieldId']
        ]);
        $this->uploadQuestion = $DB->getValue('SELECT question FROM dataField WHERE id=?',$this->params['uploadDataFieldId']);
        return true;
    }
    
    function displayInput() {
        if (!$this->upload->exists()) {
            ?>
            <div class="warning"><?= cms('Graph will appear here once a file has been uploaded for the following field and the page reloaded:',0)?><br /><?=htmlspecialchars($this->uploadQuestion);?></div>
            <?
            return;
        }
		echo '<div class="graphContainer">';
		echo '<button class="chartSettings"></button>';
		echo '<div class="chartContainer" style="width:600px;height:400px;" id="chart_'.$this->id.'"><div>Loading Chart Data...</div><div class="throbber"></div></div>';
		$this->displayJavascript(false);
        $inputName = $this->inputName();
        formHidden($inputName,$this->getAnswer(),null,'id="chartDefinition_'.$this->id.'"');
        ?>
        <div class="chartDefinition" style="display:none">
			<h2>Chart Definition</h2>
			<div class="section" id="chartFormContainer_<?=$this->id?>">
			</div>
			<script>
				let chartDefinition = $('#chartDefinition_<?=$this->id?>');
				defineChart( $('#chartFormContainer_<?=$this->id?>').get(0), chartDefinition.get(0), chartOptions);
				
				chartDefinition.on('change',function(){
					
					let chartOptions = JSON.parse(chartDefinition.val());
					let chartOptionOverrides = {
						src: <?= json_encode($this->upload->downloadUrl())?>, 
						target: document.getElementById('chart_<?=$this->id?>'),
						tooltip: {
							show: true,
							formatter: '{b}: {c}'
						},
						onError: function(error) {
							console.error('Error:', error);
						},
						onLoaded: function() {
							console.log('Loading XLSX file...');
						}
					}
									
					// Reload the graph
					graphXlsx({...chartOptions,...chartOptionOverrides});
				});
				
				chartDefinition.trigger('change');
				
			</script>
		</div>
		<?
		echo '</div>';
    }

}
