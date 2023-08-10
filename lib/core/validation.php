<?

// This is used as a key value when submitting some picklist/selectbox/checkbox values to allow
// use to detect when an empty array has been submitted
// If the array consists of just one item with this key then we remove this leaving an empty array
// If the array has other stuff in then we still strip this out leaving just the other stuff
define('CORE_INPUT_EMPTY','__EMPTY__');

/*
Syntax
---------------------------------------------------
$spec = array(
	'modeRegexp' => array(
		'fieldName'	=> "[<source>] <type>[(<details>)] [array] [signed[(signing entity)]] [strict|mandatory] [trim] [toupper|tolower]"
	)
)

N.B. the "mode" CGI parameter will always be parsed - there is no need to include this in any input specification. It will be treated as follows
	'mode'	=> "GET|POST:ALPHANUMERIC-TRIM-TOUPPER"

modeRegexp	=	Each set of input specifications is checked against the "mode" using the modeRegexp - if there is a match then this set of inputs is spec.s is processed. If modeRegexp is empty then the specifications are processed irrespective of the mode.
fieldName	=	The CGI field name
strict 		=	If this is present then the value must match the criteria otherwise an error condition is flagged
				If strict or mandaroty is not specified then any non-conformant data in this field
				is wiped but no error is flagged	
mandatory	=	If this value is not present or equal to the empty string (or empty array) then an error will be flagged
				If the value is non-conformant an error will be flagged
<source>	=	GET|POST|COOKIE|ANY
<type>		=	ID|INT|FLOAT|ALPHA|ALPHANUMERIC|TEXT|REGEXP
<details>	=
				for REGEXP this is the regular expression itself e.g. GET:REGEXP(/^[a-z]*$/si)
				for other types this takes the form ([<minSize>-]<maxSize>)
<modifier> = TRIM|TOUPPER|TOLOWER

N.B. source,type and modifier specifices are all case insensitive

Default 


Examples
---------------------------------------------------
$spec = array (
	'' => array(
		'myId'		=> "GET:ID(255) mandatory",	// myId must be an unsigned integer which is less than or equal to 255
												// it must be present in the GET data (not the POST)
		'myName'	=> "POST:TEXT(60)",			// myName must be a string of 60 or less characters
												// it will only be read from POST data
												// if no data or non-conforming data is found then the empty string will
												// be substituted.
));
									
*/
include_once('deriveEntity.php');

class InputValidator {

	var $spec;
	var $errors;

	function __construct( $theSpec ) {
		$this->errors = array();
		if (is_array($theSpec)) {
			$this->spec = $theSpec;
			$this->parse();
		}
	}
	
	function parse( $overwrite=true ) {
		global $WS;
		$mode = $this->getFieldValue( 'mode', 'GET|POST ALPHANUMERIC TRIM TOUPPER' );
		$WS['mode'] = $mode;

		// now iterate over all the keys in the specification to see if they match the mode
		foreach ( $this->spec as $modeRegexp=>$fieldSpecs ) {
			// if the modeRegexp is empty then this applies to all modes
			if ($modeRegexp==='' || preg_match( '/^(?:'.addslashes($modeRegexp).')$/', $mode )) {
				// OK so we need to load the fields specified
				foreach ( $fieldSpecs as $field=>$fieldSpec ) {
					if (!$overwrite && isset($WS[$field])) continue;
                    $value = $this->getFieldValue( $field, $fieldSpec );
                    if ($value !== false) $WS[$field] = $this->getFieldValue( $field, $fieldSpec );
				}
			}
		}
	}
	
	function addError( $field, $code, $details='' ) {
		if (!isset($this->errors[$field])) $this->errors[$field] = array();
		if (!isset($this->errors[$field][$code])) $this->errors[$field][$code] = array();
		$this->errors[$field][$code][] = $details;
	}
	
	function getFieldValue( $fieldName, $spec ) {
		global $INPUT_ERRORS;
		
		$type = $typeDetails = $array = $strict = $trim = $caseTransform = $signed = $signingEntity = '';
        $specVarRegex = array(
            'type' => 'ID|INT|FLOAT|ALPHA|ALPHANUMERIC|TEXT|ANY|REGEXP',
            'typeDetails' => '\(.*\)',
            'array' => 'ARRAY',
            'strict' => 'STRICT|MANDATORY',
            'trim' => 'TRIM',
            'caseTransform' => 'TOUPPER|TOLOWER',
            'signed' => 'SIGNED(?:\((.*?)(?:,(.*?))?\))?',
        );
        $specParts = explode(' ', strtoupper($spec));
        $extraVars = array();
        foreach($specParts as $specPart) {
            foreach($specVarRegex as $specVar => $regex) {
                if(preg_match('/^'.$regex.'$/', $specPart, $matches)) {
                    $$specVar = $specPart;
                    array_shift($matches);
                    $extraVars[$specVar]=$matches;
                    break;
                }
            }
        }

		$array = strtoupper($array) == 'ARRAY';
		$type = strtoupper($type);
        $signed = substr(strtoupper($signed), 0, 6) == 'SIGNED';

		// See if we can find the parameter in the sources listed
        $sources = array('GET', 'POST', 'COOKIE');

		$value = NULL;
		foreach( $sources as $source ) {
			// the field spec. parsing above means that only valid sources should be extracted
			// but as a fail safe we check again here (deliberatley use different method - i.e. not regexp)
			if ( $source!=='GET' && $source!=='POST' && $source!=='COOKIE' ) continue;
			$source = '_'.$source;
			// see if this parameter exists in this source
			if (isset($GLOBALS[$source]) && isset($GLOBALS[$source][$fieldName])) {
				$value = $GLOBALS[$source][$fieldName];
				// echo "Extracting $fieldName from $source => '$value'";
				break;
			}
		}

        // Check signing
        if($signed && $value !== null) {
            $signingEntity='';
            $sigLength = 0;
            if(count($extraVars['signed'])) {
                for( $i=0; $i<2; $i++ ) {
                    if (!isset($extraVars['signed'][$i])) break;
                    if (is_numeric($extraVars['signed'][$i])) {
                        $sigLength=$extraVars['signed'][$i];
                    } else {
                        $signingEntity=$extraVars['signed'][$i];
                    }
                }
            }
            $value = checkSignedInput($value, $signingEntity, $sigLength);
        }
		
		// First lets deal with what happens if we can't find a value for this parameter
		if (!isset($value)) {
			// If the parameter wasn't mandatory then it doesn't much matter
			if ($strict!=='MANDATORY') {
				return false;
			} else {
				// But if it was mandatory then we need to flag an error
				$this->addError($field,1);
			}
		}
		
		// Tidy up the variable based on its type
		
		if (is_array($value)) {
            $values = $value;
            unset($values[CORE_INPUT_EMPTY]);
        }
		else $values = array($value);

		foreach( $values as &$value ) {
			if ($type!='ANY' && !is_string($value)) $value='';

			if ($type=='ANY') {
                if ($trim) $value=trim($value);

			} else if ( strpos('|ALPHANUMERIC|TEXT|',$type) ) {
                if ($trim) $value=trim($value);

			} else if ($type=='ALPHA') {
				$value = preg_replace('/[^a-zA-Z]/','',$value);
                if ($trim) $value=trim($value);

			// If INT or ID or no type is specified or the type is invalid then assume INT as this is the most restrictive
			} else {
				# Allow empty string values
				if (trim($value)==='') $value='';
				else {
                    $value = 0 + (int)preg_replace('/[^0-9\\.-]/','',$value);
                    if ( $type=='INT' ) $value = round($value,0);
                    // don't allow negative ID's
                    if ( $type=='ID' && $value<0 ) $value=0;
                }
			}	
		}
		
		# CAREFUL - DON'T USE $value AFTER THIS POINT - it is still a reference to the last element in the array
		if ($array) return $values;
		return $values[0];
	}

}

define('SIGNING_SECRET_FN_PREFIX', 'input_doNotDelete_');

function signInput($value, $signingEntity = '', $sigLength = 0) {
    global $entity;
    $signingEntity = strtolower($signingEntity ?: $entity);

    if ($sigLength==0) $sigLength=defined('INPUT_SIGNATURE_LENGTH')?INPUT_SIGNATURE_LENGTH:0;


    $signature = hash_hmac('sha256', $value, $signingEntity.getLocalSecret(SIGNING_SECRET_FN_PREFIX));

    if ($sigLength) $signature = substr($signature,0,$sigLength);
    if(defined('INPUT_SIGNATURE_HYPHEN_EVERY') && is_numeric(INPUT_SIGNATURE_HYPHEN_EVERY)) $signature = implode('-', str_split($signature, INPUT_SIGNATURE_HYPHEN_EVERY));

    return $value.':'.$signature;
}

function checkSignedInput($signedInput, $signingEntity = '', $sigLength = 0) {
    global $entity;

    list($value, $signature) = explode(':', $signedInput.':');
    $signature = str_replace('-', '', $signature);
    $signingEntity = strtolower($signingEntity ?: $entity);

    if ($sigLength==0) $sigLength=defined('INPUT_SIGNATURE_LENGTH')?INPUT_SIGNATURE_LENGTH:0;

    $checkSignature = hash_hmac('sha256', $value, $signingEntity.getLocalSecret(SIGNING_SECRET_FN_PREFIX));
    if ($sigLength) $checkSignature = substr($checkSignature, 0, $sigLength);
    else $sigLength = strlen($checkSignature);

    $signature = substr($signature,0,$sigLength);
    if ($signature===false) $signature='';
    return hash_equals($checkSignature, $signature) ? $value : null;
}
