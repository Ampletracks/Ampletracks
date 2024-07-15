<?

function setPrimaryFilter($value) {
    $_COOKIE[PRIMARY_FILTER_NAME.'Filter']=$value;
}

function applyPrimaryFilter($idField) {
    global $DB, $WS, $USER_ID;
    // Add in the filter

    // get the filter from the cookie - or if this is missing then find their current defaut
    $cookieName = PRIMARY_FILTER_NAME.'Filter';
    $setCookie = isset($_REQUEST[PRIMARY_FILTER_NAME.'FilterChange']);
    if ($setCookie) {
        $newFilterValue = $_REQUEST[PRIMARY_FILTER_NAME.'FilterChange'];
        $_COOKIE[$cookieName] = $newFilterValue;
        // If they have just changed the filter then set this to be their current
        if ($USER_ID && $newFilterValue) $DB->update('user',array('id'=>$USER_ID),array($cookieName=>$newFilterValue));
    } else if (!isset($_COOKIE['branchFilter'])) {
        // Load the current branch filter setting from the user table
        $_COOKIE[$cookieName] = $DB->getValue('SELECT `'.$cookieName.'` FROM user WHERE id=?',$USER_ID);
        // If we still couldn't find one then just set it to 1
        if (!$_COOKIE[$cookieName]) $_COOKIE[$cookieName] = 1;
        $setCookie = true;
    }

    if ($setCookie) setCookie($cookieName, $_COOKIE[$cookieName],0,'/');

	$filterFieldName = 'filter_'.str_replace('.',':',$idField).'_eq';
	// don't override one that has been specifically set by the user - unless this has just been changed
	if (isset($_REQUEST[PRIMARY_FILTER_NAME.'FilterChange']) || !isset($WS[$filterFieldName])) $WS[$filterFieldName] = $_COOKIE[$cookieName];
}

function getPrimaryFilter() {
    global $DB;
    $cookieName = PRIMARY_FILTER_NAME.'Filter';
    if(isset($_COOKIE[$cookieName])) return $_COOKIE[$cookieName];

    global $USER_ID;
    $savedFilter = $DB->getValue('SELECT `'.$cookieName.'` FROM user WHERE id=?',$USER_ID);
    return $savedFilter ?: null;
}

function formatBytes($size, $precision = 0, $roundDown=false) {
    if (!$size) return '0 Bytes';
    $base = log($size) / log(1024);
    $suffixes = array(' B', ' KB', ' MB', ' GB', ' TB');
    if ($precision==-1) {
        $precision = 3 - strlen(round(pow(1024, $base - floor($base))));
        if ($precision<0) $precision=0;
    }

    $suffix = $suffixes[floor($base)];
    $result = pow(1024, $base - floor($base)) * pow(10,$precision);
    $result = $roundDown ? floor($result) : round($result);
    $result = $result / pow(10,$precision);
    
    return $result . $suffix;
}

function curlGet( $url ) {
    $curl = curl_init($url);
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => 1,
    ));
    $result = curl_exec($curl);
    curl_close($curl);
    return $result;
}

# This function checks if the file exists with a variety of extensions added.
# The first parameter is the base filename (passed by reference)
# the second parameter is either an array of extensions to try
# or a single extension as a string - in this case this is interpreted as being an optional extension
# and the file is sought first without then with this extension.
function checkFileExists( &$file, $extension ) {
	if ( !is_array($extension) ) $extension = array( '', $extension );
	foreach( $extension as $key=>$value ) {
		# echo "looking for '$file$value'<BR />";
		if ( file_exists( $file.$value ) && is_file( $file.$value ) ) {
			$file = $file.$value;
			# echo "found it";
			return(1);
		}
	}
	return(0);
}

function wsParseDate( $wsField ) {
	global $WS;
    $dateStr = preg_replace('/(\\d+)\\D+(\\d+)\\D+(\\d+)/','$3-$2-$1',ws($wsField));
    $WS[$wsField] = strtotime( $dateStr );
}

function defaultValue( $default, &$var1 ) {
	$args = func_num_args()-1;
	if ($args>10) $args=10;
	for ($idx = $args; $idx>0; $idx--) {
		$var = 'var'.$idx;
		if (!isset($$var)) $$var = $default;
	}
}

function coreError( $userError, $sysError=null, $exit=true ) {
	global $LOGGER, $_SERVER;
    if (empty($sysError)) $sysError=$userError;
	$LOGGER->log($sysError);

	echo "<H1>Error encountered</H1><HR>\n<H2>$userError</H2>";
	if ($exit) exit;
}

function getPageDebugInfo() {
	ob_start();
	phpinfo();
	echo "<HR><PRE>";
	print_r( $GLOBALS );
	echo "</PRE>";
	$info = ob_get_contents();
	ob_end_clean();
	return $info;
}

function htmlEcho( $str ) {
	echo htmlspecialchars( $str );
}

function jsEcho( $str ) {
	echo htmlspecialchars( addslashes($str) );
}

function wsp( $param, $echo=true, $convNL = false ) {
	global $WS;
    if (!isset($WS[$param]) ) return;
    $wsOut = htmlspecialchars($WS[$param]);
    if($convNL) $wsOut = nl2br($wsOut);
	if ($echo) echo $wsOut;
    else return $wsOut;
}

function ws( $param, $value=null ) {
	global $WS;
	if ($value!==null) {
		$WS[$param] = $value;
	}
	if (isset($WS[$param])) return $WS[$param];
	else return '';
}

function wsset($param) {
    global $WS;
    return isset($WS[$param]);
}

class rawOutput {
    private $raw;
    private $safe;
    
    function __construct( $str, $safe=null ) {
        $this->raw = $str;
        if (is_null($safe)) $this->safe = htmlspecialchars(strip_tags($str));
        else $this->safe = $safe;
    }

    public function __toString(){
        return $this->safe;
    }
    
    public function getRaw(){
        return $this->raw;
    }
}
    
function rawOutput( $raw, $safe=null ) {
    return( new rawOutput( $raw, $safe ) );
}

function htmlOut($str, $echo=0) {
    if (is_object($str) && get_class($str)=='rawOutput') $str = $str->getRaw();
    else $str = htmlspecialchars($str);
    
    if ($echo) echo $str;
    return $str;
}

function jsStringEscape( $str ) {
	return addslashes(htmlspecialchars($str));
}

function forceArray( &$var ) {
	if (!isset($var)) {
		$var = array();
	} else if (!is_array($var)) {
		$var = array($var);
	}
	return $var;
}

/**
 * $finalValue : false - use row data and DON'T remove dimensions (default)
 *                true - use row data and DO remove dimensions
 *          int/string - use $finalValue element of row data
 */
function buildNDimensionalArray($data, $dimensions, $finalValue = false)  {
    if(!is_array($data) || !is_array($dimensions) || !count($dimensions)) return null;

    $formattedData = array();
    foreach($data as $dataRow) {
        $currentLevel = &$formattedData;
        foreach($dimensions as $dimension) {
            if(!isset($currentLevel[$dataRow[$dimension]]) || !is_array($currentLevel[$dataRow[$dimension]])) $currentLevel[$dataRow[$dimension]] = array();
            $currentLevel = &$currentLevel[$dataRow[$dimension]];
            if($finalValue === true) unset($dataRow[$dimension]);
        }
        $currentLevel = is_bool($finalValue) ? $dataRow : $dataRow[$finalValue];
    }
    return $formattedData;
}

# This function adds $what into $str at the first occurence of $where
# $where remains in the output
# $what is either inserted before or after $what as determined by $after - default is afterwards
function strSplice( $str, $where, $what, $after=1, $which='first' ) {
	if (!preg_match("/$where/", $str)) return $str;
    if ($which=='first') {
    	list( $first_bit, $last_bit ) = preg_split( "/$where/", $str,2 );
    } else {
        $bits = preg_split( "/$where/", $str );
        $last_bit = array_pop($bits);
        $first_bit = substr( $str, 0, -1 * strlen($last_bit));
    }
    
	$pos = strlen( $first_bit );
	$length = strlen($str) - strlen( $last_bit ) - strlen( $first_bit );
	if ( $pos == strlen( $str ) ) return $str;
	if ( $after ) { $pos += $length; }
	$new_str = substr( $str, 0, $pos ) . $what . substr( $str, $pos );
	return $new_str;
}

# this function adds in search conditions after the first " where " (case insensitive - will match WHERE or where but not WhErE or Where) in the sql search
# It grabs $sql by reference and applies the changes in-situ for you
# It also adds on extra conditions if they are supplied - in fact you can call it with an empty prefix to just add extra conditions
function addConditions( &$sql, $prefix , $extra='', $type='where' ) {
	$new_sql = ' ';
	if ($prefix != '') $new_sql .= makeConditions( $prefix );
	if ($extra !== '') $new_sql .= $extra.' AND ';
	$sql = strSplice( $sql, "[\r\n\t ]".convertToCaseInsensitiveRegExp($type)."[\r\n\t ]", $new_sql,1,'last' );
	return $sql;
}

global $_SEARCH_SPEC_LOOKUP_TABLE;
$_SEARCH_SPEC_LOOKUP_TABLE = array(
	'lk' => '%s LIKE \'%s\'',
	'rl' => '%s RLIKE \'%s\'',
	'ct' => '%s LIKE \'%%%s%%\'',
	'eq' => '%s = \'%s\'',
	'eq_numeric' => '%s = %s',
	'gt' => '%s > %s',
	'ge' => '%s >= %s',
	'lt' => '%s < %s',
	'le' => '%s <= %s',
	'ne' => '%s <> \'%s\'',
	'sw' => '%s LIKE \'%s%%\'',
	'ew' => '%s LIKE \'%%%s\'',
	'nl' => 'ISNULL(%s) = \'%s\'',
  # the followig are special cases
	'cm' => '',
	'cy' => '',
	'cl' => '',
	'in' => '%s IN (%s)',
    'on' => '(%s >= %s AND %s < %s)'
);

/*

	ct => Contains*'
	eq => Equals*
		- use lk if you want to test for emptiness
	gt => Greater than*+
	ge => Greater than or equal to*+
	ne => Not equal to
	lt => Less than*+
	le => Less than or equal to*+
	sw => Starts with*'
	ew => Ends with*'
	lk => Like'
	ct => Contains
	cy => Contains any*^
	cl => Contains all*^
	in => in*
	rl => RLike
	nl => ISNULL()
	cm => Compare - first character of value is used as type of comparison (<,>,=) if none supplied default is =
	on => on date (assumes input is dd/mm/yyyy and db field is Unix timestamp)

	* = Tests that will be ignored if empty
	+ = Numeric only
	^ = Extra negative logic applies
		syntax is "[^][!]word [[!]word]..."
	' = These tests accept "like" expression wild cards
		- see http://dev.mysql.com/doc/refman/4.1/en/string-comparison-functions.html
*/

function makeConditions( $prefix, $paramSpacePrefix = '' ) {
	global $WS, $DB;
	global $_SEARCH_SPEC_LOOKUP_TABLE;
	$condition_sql = '';
	$limited_param_space=0;
	if (is_array($prefix)) {
		$param_space = $prefix;
		$prefix = $paramSpacePrefix;
		$limited_param_space=1;
	} else {
		$param_space = $WS;
	}

	foreach( $param_space as $full_var=>$val ) {

#		echo "Testing $full_var against $prefix<BR />";
		$sub_sql = '(';

		# next line is required to stop people submitting CGI variables with names of the form
		# "seemingly_OK_variable_but_includes|filter_bogus_filter_eq"
		# This might get past some CHECKS with the author of the code thinking its OK
		# 'cos it doesn't start filter_ but the explode below would have acted on the filter.
		if (!empty($prefix) && !preg_match( "/^$prefix/i", $full_var )) continue;

		if ( is_object($val) ) continue;

		# Prevent SQL injection
		if (is_array($val)) {
			foreach($val as $key=>$value) {
				if (is_array($value) || is_object($value)) continue;
				$val[$key] = addslashes($value);
			}
		} else {
			$val = $DB->escape($val);
		}

		$sub_fields = explode('|',$full_var);
		foreach( $sub_fields as $key=>$var ) {
			$not=0;
			if (substr( $var, -4, 2 ) == '_!') {
				$not=1;
				$var = substr( $var, 0, -3 ).substr( $var, -2 );
			}
			if ( (substr( $var, -3, 1 ) == '_' || substr( $var, -4, 2 ) == '_!') && preg_match( "/^$prefix/i", $var ) ) {
				$var = preg_replace( "/^$prefix/i", '', $var );
#				echo "$var looks good<BR />";
				# just double check
				if ( substr( $var, -3, 1 ) == '_' || substr( $var, -4, 2 ) == '_!') {
					$end = substr( $var, -2 );
#					echo "checking '$end'<BR />\n";
					$start = substr( $var, 0, -3 );
					# DONT let the user put any ;'s in the column names - this could be bad
					# e.g. filter_;drop table blah; select_eq=1
					# This only applies if the param_space was set to the whole workspace ($WS)
					# Don't do this if we were passed in a specific param_space
					if (!$limited_param_space) $start = preg_replace( '/[^a-zA-Z0-9&:()_+*\/\\-\,]/','',$start );
					# replace : with .
					$start = str_replace( ':', '.', $start );
					# limit the length
					$start = substr( $start, 0, $limited_param_space?100:35 );

					if ( $end == 'gt' || $end == 'lt' || $end == 'ge' || $end == 'le') {
						// Reformat dates
						$val = preg_replace( '/^(\\d{1,2})\\D(\\d{1,2})\\D(\d{2,4})$/','$1-$2-$3',$val,1,$matches);
						if ($matches) {
							$val = strtotime($val);
                            # when using gt or le with dates we want to go from the end of the day not the start
                            if ( $val && $end == 'gt' || $end == 'le' ) {
                                $val+=86400;
                            }
						} else if ($val!=='') {
							$val = (int)preg_replace( '/[^0-9\\.]/','', $val );
						}
					}
#					if ( $val == '' && strpos(' ,ct,eq,gt,lt,sw,ew,cy,cl,in,',",$end,") ) {
#						echo "Skipping empty $start $end<BR />\n";
#					}
					if ( $val == '' && strpos(' ,ct,eq,gt,ge,lt,le,sw,ew,cy,cl,cm,in,',",$end,") ) continue;
					if ( $end == 'in' && is_array($val) && (!count($val) || $val[0]== '') ) continue;
					# array values are only allowed for "in" filters
					if ( $end != 'in' && is_array($val) ) $val = implode(' ',$val);

					if ( isset( $_SEARCH_SPEC_LOOKUP_TABLE[$end] ) ) {
#						echo "adding ".sprintf( $_SEARCH_SPEC_LOOKUP_TABLE[$end], $start, (string)$val );
						switch ($end) {
							case 'cy':
							case 'cl':
								$not=0;
								if (substr($val,0,1)=='^') {
									$not = 1;
									$val = substr($val,1);
								}
								$join = $end=='cy'?'OR ':'AND';
								# remove multiple spaces, spaces of the end and spaces of the start
								$val = preg_replace( '/  +/', ' ', trim($val) );
								$test_values = explode( ' ', $val );
								foreach( $test_values as $test_key=>$test_value ) {
									$thing = '';
									if (substr($test_value,0,1)=='!') {
										$thing = ' NOT';
										$test_value = substr($test_value,1);
									}
									$thing = " $start$thing LIKE '%".addslashes($test_value)."%' $join";
									$sub_sql .= $thing;
								}
								$sub_sql = substr($sub_sql,0,-4);
								if ($not) { $sub_sql = ' NOT'.$sub_sql.' '; }
								break;
                            case 'on':
                                $time=0;
                                if (preg_match('/(\\d{1,2})\D(\\d{1,2})\D(\\d{1,4})/',$val,$matches)) {
									if ($matches[3]<70) $matches[3]+=2000;
									if ($matches[3]<100) $matches[3]+=1900;
									$time = strtotime($matches[3].'-'.$matches[2].'-'.$matches[1]);
								} else if (preg_match('/^\\d{5,}$/',$val,$matches)) {
									$time = (int)$val;
								}
                                if (!$time) {
                                    $sub_sql .= ' 1=1 ';
                                    continue 2;
                                }
                                $sub_sql .= sprintf( $_SEARCH_SPEC_LOOKUP_TABLE[$end], $start, $time, $start, $time+86400 );
                                break;
							case 'in':
								# allow comma-separated numerical lists
								if (is_array($val)) {
									if (!count($val)) break;
									$val = "'".implode("','",$val)."'";
								} else if (preg_match('/^(\\d+,)+\\d+$/',$val)) {
									# do nothing - allow $val through as it is
								} else {
									$end = 'eq';
								}
							default:
								if (!is_array($val)) $val = array($val);
								if (!count($val)) $sub_sql .= ' 1=1 ';
								else {
									$sub_sql .= ' ( ';
									foreach ( $val as $term ) {
										$not=0;
										if (substr($term,0,1)=='!') {
											$not = 1;
											$term = substr($term,1);
										}
										if ($not) $sub_sql .= ' NOT(';

										if ($end=='cm') {
											if (strpos(' <>=',$term[0])) {
												$test = $term[0];
												$term = substr( $term,1 );
											} else $test='=';

                                            // if $term is not entirely numeric then quote it
                                            if ( !preg_match('/^\\d+$/',$term) ) $term = '\''.$term.'\'';
											$sub_sql .= ''.$start.' '.$test.' '.$term;
										} else if ($end=='eq' && preg_match('/^\\d+$/',$term)) {
                                            $sub_sql .= sprintf($_SEARCH_SPEC_LOOKUP_TABLE['eq_numeric'], $start, (string)$term);
                                        } else $sub_sql .= sprintf($_SEARCH_SPEC_LOOKUP_TABLE[$end], $start, (string)$term);


										if ($not) $sub_sql .= ') ';
										$sub_sql .= ' AND ';
									}
									$sub_sql = substr($sub_sql,0,-5);
									$sub_sql .= ' ) ';
								}
						}
						$sub_sql .= ' OR ';
					}
				}
			}
		}
		if (strlen($sub_sql) > 1) {
			$sub_sql = substr( $sub_sql, 0, -3). ') AND ';
			$condition_sql .= $sub_sql;
		}
	}
	return($condition_sql);
}

function deriveInputsFromFile( $files=null ) {
	global $_SERVER, $INPUTS;
	if (!isset($INPUTS['.*'])) $INPUTS['.*'] = array();

	// get the right filename
	// if no file is specified then use the current file
	if (is_null($files)) $files = $_SERVER["DOCUMENT_ROOT"].$_SERVER["SCRIPT_NAME"];

	forceArray($files);

	foreach( $files as $file ) {
		if (!strlen($file)) continue;
		// if the filename doesn't start with "/" then it is a relative path - make it absolute by adding the base_dir
		if (strpos($file, DIRECTORY_SEPARATOR)!==0) $file = SITE_BASE_DIR.DIRECTORY_SEPARATOR.$file;

		// ignore this file if it doesn't exist;
		if (!file_exists($file)) continue;


		// read in the file data and split it into HTML tags
		$contents = file_get_contents( $file );
		$tags = explode('<',$contents);
		foreach( $tags as $tag ) {

			// ignore this if it isn't a tag
			if (!strpos($tag,'>')) continue;
			// ignore all closing tags
			if (preg_match('/^\s*\\//',$tag)) continue;

			if (preg_match('/\\w+/',$tag,$matches)) {
				// ignore any tag that doesn't contain form data
				if (!strpos(' |INPUT|TEXTAREA| ', strtoupper("|{$matches[0]}|"))) continue;

				// find the name attribute
                if (preg_match('/\\s+NAME\\s*=(["\'])(.*?)\\1/smi',$tag,$matches)) {
                    
                    if (preg_match('/\\s+TYPE\\s*=(["\'])NUMBER\\1/smi',$tag)) $fieldType = 'FLOAT';
                    else $fieldType = 'ALPHANUMERIC';
					$fieldName = trim($matches[2]);
					// remove [] from the end of array fields
					$fieldName = preg_replace('/\\[\\]\\Z/','',$fieldName,1,$matches);
					if ($matches) $fieldType.=' ARRAY';
					// Don't override inputs definitions that already exist
					if (!isset($INPUTS['.*'][$fieldName])) $INPUTS['.*'][$fieldName] = $fieldType;
				}
			}
		}

		// Now look for CODE function calls in the source
		if (preg_match_all('/(form(?:Label|Hidden|Textbox|Float|Integer|Textarea|Placeholder|Optionbox|Checkbox|Radio|Picklist|YesNo|Date)|defaultFormField)\\(\s*(["\'])(.*?)\\2/i',$contents,$matches)) {
			// Following line just to stop intelephense complaining
			if (0) $matches[3] = [];
			
			foreach( $matches[3] as $idx => $fieldName ) {
                if(strtolower($matches[1][$idx]) == 'forminteger') $fieldType = 'INT';
                else if(strtolower($matches[1][$idx]) == 'formfloat') $fieldType = 'FLOAT';
                else $fieldType = 'ALPHANUMERIC';
				// remove [] from the end of array fields
				$fieldName = preg_replace('/\\[\\]\\Z/','',trim($fieldName),1,$fnMatches);
				if ($fnMatches) $fieldType.=' ARRAY';
                else if(strtolower($matches[1][$idx]) == 'formpicklist') $fieldType.=' ARRAY'; // formPicklist is always an array but isn't called with '[]' on the name
				if (!isset($INPUTS['.*'][$fieldName])) $INPUTS['.*'][$fieldName] = $fieldType;
			}
		}
	}
}

function dump( $vars, $html=true ) {
	if ($html) echo "<pre>";
	print_r($vars);
	if ($html) echo "</pre>";
}


/*
This routine uses prune_list to remove entries from target_hash
Depending on the values of target_key either the key (target_key=1) or the value (target_key=0) of each entry in target_hash is chechked to see if it occurs in the prune list.
Depending on the value of list_key the data as to which entries to prune is taken from either the keys or the values of the prune_list
i.e.
	list_key=0 => prune out entries by testing against keys in the prune list
	list_key=1 => prune out entries by testing against the values in the prune list
The test parameter defines what compairson is used
	0 -> exact comparison
	1 -> case insensitive comparison
	2 -> case sensitive regular expression
	3 -> case insensitive regular expression
Add 10 to test to negate it
*/

function pruneHash( &$target_hash, $prune_list, $target_key = 1, $list_key = 1, $test = 2 ) {

	if ( strtolower($target_key) == 'key' ) $target_key = 1;
	if ( strtolower(substr($target_key,0,3)) == 'val' ) $target_key = 0;
	if ( strtolower($list_key) == 'key' ) $list_key = 0;
	if ( strtolower(substr($list_key,0,3)) == 'val' ) $list_key = 1;
	reset( $target_hash );
	if (!is_array($prune_list)) {
		$prune_list = array( $prune_list );
		$list_key = 1;
	}
	$negate = 0;
	if ( $test > 9 ) {
		$test -= 10;
		$negate = 1;
	}
	foreach( $target_hash as $key=>$value ) {
		$compare = $target_key?$key:$value;
		if ( $test==1 ) $compare = strtolower($compare);
#		echo "testing $compare (negate = $negate)<BR />";
		# check if this is to be excluded
		# this first one is a special case which is really easy
		if ( !$list_key && ($test == 0) ) {
			if ( ( isset($prune_list[$compare]) && !$negate ) || ( !isset($prune_list[$compare]) && $negate ) ) {
				unset( $target_hash[$key] );
			}
		} else {
			# otherwise we have to plod our way through the prune list
			foreach( $prune_list as $subkey=>$subvalue ) {
				if ( $test==1 ) $subvalue = strtolower($subvalue);
#				echo "testing '$subvalue' against '$compare'<BR />";
				if (
					( ( $test==1 || $test == 0 ) && ( $subvalue == $compare ) ) ||
					( $test==2 && preg_match( '/'.$subvalue.'/', $compare ) ) ||
					( $test==3 && preg_match( '/'.$subvalue.'/i', $compare ) )
					) {
					if (!$negate) unset( $target_hash[$key] );
					break;
				} else {
					if ($negate) unset( $target_hash[$key] );
				}
			}
		}
	}
}

# This function takes a hash and a regexp string as inputs
# It returns a hash consisting of all elements of the hash
#   for which the regexp matches the key name
function fuzzyLookup( &$hash, $lookup ) {
	$return = array();
	foreach( $hash as $key=>$value ) {
#		echo "comparing $key against $lookup <BR />";
		if (preg_match( '/'.$lookup.'/', $key )) $return[$key] = $value;
	}
	return $return;
}

function defaultLookup( &$hash, $lookup, $default ) {
	if (!isset($hash[$lookup])) return $default;
	return $hash[$lookup];
}

function applyDefaults( $src, &$dest ) {
    foreach ( $src as $key=>$value ) {
        if (!isset($dest[$key])) $dest[$key] = $value;
    }
}

function defaultKey( &$hash, $lookup, $default ) {
	if (!isset($hash[$lookup])) {
		$hash[$lookup] = $default;
		return true;
	}
	return false;
}

function keyIsTrue( &$hash, $key ) {
	return ( isset($hash[$key]) && $hash[$key] );
}

# if pos is negative counts that many from the end of the array
function splitArray( &$array, $pos ) {
	if ($pos < 0) $pos = count($array)+$pos;
	if ($pos < 0) $pos = 0;

	$ar1 = array();
	$ar2 = array();
	foreach( $array as $key=>$value ) {
		if ( $key < $pos ) {
			$ar1[] = $value;
		} else {
			$ar2[] = $value;
		}
	}

	$array = $ar1;
	return $ar2;
}

function isAjaxRequest() {
	static $answer = null;

	if ($answer !== null) return $answer;

	global $_SERVER;
	if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
		$answer = true;
	} else $answer = false;

	return $answer;
}

function flatten(array $array) {
    $return = array();
    array_walk_recursive($array, function($a) use (&$return) { $return[] = $a; });
    return $return;
}

function enclose($startTag, $thing) {
	forceArray($thing);
	$endTag = preg_replace('/^</','</',$startTag);
	return $startTag.implode($endTag.$startTag,$thing).$endTag;
}

function definedAndTrue($varName) {
    return defined($varName) && constant($varName);
}

function getConstant($varName, $default='') {
    return defined($varName) ? constant($varName) : $default;
}

function toCamelCase($string) {
    // Treat acronyms as word - e.g. HTML->Html
    $string = preg_replace_callback('/[A-Z][A-Z]+/',function($matches){ return strtolower($matches[0]); },$string);
    return lcfirst(preg_replace_callback('/(_|\\s)(.?)/',function($matches){ return strtoupper($matches[2]); },$string));
}

function fromCamelCase($string) {
    return trim(ucwords( preg_replace( '/([A-Z])/', ' $1', $string ) ));
}

function inet_aton($ip)
{
        $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) return 0;
                return sprintf("%u", ip2long($ip));
}

function inet_ntoa($num)
{
        $num = trim($num);
            if ($num == "0") return "0.0.0.0";
                return long2ip(-(4294967295 - ($num - 1)));
}


function enrichRowData( &$rowData = null, $direction='outbound', $stripOriginals=null ) {
	if ($direction!='outbound') $direction='inbound';
	// Default for strip original depends on direction
	// for inbound we DO strip by default
	// for outbound we don't
	if (is_null($stripOriginals)) $stripOriginals = $direction=='inbound';
	
    static $overrideFuncs = null;

    if(!is_array($overrideFuncs) || $rowData === null) {
        $overrideFuncs = array('inbound'=>array(),'outbound'=>array());
    }

    if(!is_array($rowData)) return;

	$key = array_key_first($rowData);
	$val = $rowData[$key];

	if(is_callable($val)) {
        $overrideFuncs[$direction] = $rowData;
        return;
    }

	$override = &$overrideFuncs[$direction];
	if ($direction=='outbound') {
		
		foreach( $rowData as $key=>$value ) {
			if ( substr($key,-2)==='At'  || substr($key,-5)==='Until' || substr($key,-4)==='From' || substr($key,-5)==='After' ) {
				$baseName = $key;
				// For any dates we assume that any timestamps back in 1970 are not really timestamps but where the field has been overloaded with other data
				// so only process the data if the value is beyond 1971
				if ($value>365*86400) {
					if (!isset($rowData[$baseName.'Date'])) {
						if(isset($override['formatDate']) && is_callable($override['formatDate'])) $rowData[$baseName.'Date'] = $override['formatDate']($value);
						else if (function_exists('formatDate')) $rowData[$baseName.'Date'] = formatDate($value);
						else $rowData[$baseName.'Date'] = date('d/m/Y',$value);
						if ($stripOriginals) unset( $rowData[$baseName.'Date'] );
					}
					if (!isset($rowData[$baseName.'DateTime'])) {
						if(isset($override['formatDateTime']) && is_callable($override['formatDateTime'])) $rowData[$baseName.'DateTime'] = $override['formatDateTime']($value);
						else if (function_exists('formatDateTime')) $rowData[$baseName.'DateTime'] = formatDateTime($value);
						else $rowData[$baseName.'DateTime'] = date('d/m/Y H:i',$value);
						if ($stripOriginals) unset( $rowData[$baseName.'DateTime'] );
					}
				} else {
                    $rowData[$baseName.'Date'] = '';
                    $rowData[$baseName.'DateTime'] = '';
                }
				// However.... we _do_ allow anything less than 86400 for time fields and interpret this as an abstract time of day
				// BUT BEWARE - if you're planning to use "date" to convert these back to hours and minutes and you're based in the UK
				// From 1968-1971 there was the "British Standard Time experiment" which meant that clocks were one out forward even in January
				// Also, in this case we want to ignore any other timezone data if we're on a server configured to another zone
				// So... use gmdate if you get one of these times.
				if (is_numeric($value) && !isset($rowData[$baseName.'Time'])) {
					if(isset($override['formatTime']) && is_callable($override['formatTime'])) $rowData[$baseName.'Time'] = $override['formatTime']($value);
					else if (function_exists('formatTime')) $rowData[$baseName.'Time'] = formatTime($value);
					else {
						if ($value<=86400) $rowData[$baseName.'Time'] = gmdate('H:i',$value);
						else $rowData[$baseName.'Time'] = date('H:i',$value);
					}
					if ($stripOriginals) unset( $rowData[$baseName.'Time'] );
				}
			} else if($key == 'id' && isset($override['signId']) && is_callable($override['signId'])) {
				$rowData['idSigned'] = $override['signId']($value);
			} else if(substr($key,-2)==='Ip') {
				// The following are just to stop intelephense moaning about undefined functions (in the absense of any supression functionality)
				if (0) { function formatIp(){} }
				if (0) { function parseDate(){} }
				if (0) { function parseDateTime(){} }
				if (0) { function parseTime(){} }
				
                if(isset($override['formatIp']) && is_callable($override['formatIp'])) $rowData[$key.'Address'] = $override['formatIp']($value);
                else if (function_exists('formatIp')) $rowData[$key.'Address'] = formatIp($value);
                else $rowData[$key.'Address'] = long2ip($value);
                if ($stripOriginals) unset( $rowData[$key] );
			}

		}
	} else {

		$datesAndTimes = array();
		$toStrip = array();
		
		foreach( $rowData as $key=>$value ) {
			$suffix = substr($key,-4);
			$prefix = substr($key,0,-4);
			if ( $suffix==='Date' ) {
				if ( isset( $rowData[$prefix.'Time']) ) {
					$datesAndTimes[$prefix.'DateTime'] = $value.' '.$rowData[$prefix.'Time'];
					$toStrip[] = $prefix.'Time';
					$toStrip[] = $prefix.'Date';
					continue;
				}
			} else if ( $suffix==='Time' ) {
				if ( isset($rowData[$prefix.'Date']) ) continue;
			} else {
                
                // Anything non-datey goes in here
                if (substr($key,-9)==='IpAddress') {
                    $toStrip[] = $key;
                    $rowData[substr($key,0,-9)] = ip2long( $value );
                }
            
				continue;
			}
			$toStrip[] = $key;
			$datesAndTimes[$key] = $value;
		}
		
		foreach( $datesAndTimes as $key=>$value ) {
			if ( substr($key,-4)==='Date' ) {
				$baseName = substr($key,0,-4);
				if(isset($override['parseDate']) && is_callable($override['parseDate'])) $rowData[$baseName] = $override['parseDate']($value);
				else if (function_exists('parseDate')) $rowData[$baseName] = parseDate($value);
				else $rowData[$baseName] = strtotime($value);
			} else if ( substr($key,-8)==='DateTime' ) {
				$baseName = substr($key,0,-8);
				if(isset($override['parseDateTime']) && is_callable($override['parseDateTime'])) $rowData[$baseName] = $override['parseDateTime']($value);
				else if (function_exists('parseDateTime')) $rowData[$baseName] = parseDateTime($value);
				else $rowData[$baseName] = strtotime($value);
			} else if ( substr($key,-4)==='Time' ) {
				$baseName = substr($key,0,-4);
				if(isset($override['parseTime']) && is_callable($override['parseTime'])) $rowData[$baseName] = $override['parseTime']($value);
				else if (function_exists('parseTime')) $rowData[$baseName] = parseTime($value);
				// Just a bare time (without a date) - use UTC for the reasons state above regarding timestamps that are < 86400
				else $rowData[$baseName] = strtotime($value.' UTC');
			}
		}
		
		if ($stripOriginals) {
			foreach( $toStrip as $key ) {
				unset($rowData[$key]);
			}
		}
	}
}

/**
* Make regular expression for case insensitive match
* i.e: convertToCaseInsensitiveRegExp('Foo - bar.'); Output: [Ff][Oo][Oo] - [Bb][Aa][Rr].
* @param String $s
* @return String
*/
function convertToCaseInsensitiveRegExp($s) {
     return array_reduce(str_split($s), function ($result, $char) {
         return (preg_match("/[A-Za-z]/", $char))
             ? $result . '[' . strtoupper($char) . strtolower($char) . ']'
             : $result . $char;
     });
}

function ob_get_output( $arg1, &$arg2=array() ) {
    $callable = $arg1;

    ob_start();
    $return = $callable();
    $buffer = ob_get_contents();
    // two arguments passed - so the second must be the buffer
    // In this case, the output goes in the buffer which was passed in and we return the result of the function call
    if (!is_array($arg2)) $arg2=$buffer;
    else $return = $buffer;
    ob_end_clean();

    return $return;
}

function getLocalSecret($secretName = '') {
    static $localSecrets = null;
    $secretName = md5($secretName);

    if (!is_array($localSecrets)) $localSecrets = array();
    if (!isset($localSecrets[$secretName])) {
        $localSecretFile = DATA_DIR.'/secrets/'.$secretName.'.txt';

        $localSecret = trim(@file_get_contents($localSecretFile));

        if(!$localSecret) {
            @mkdir(dirname($localSecretFile), 0755, true);
            $newLocalSecret = bin2hex(openssl_random_pseudo_bytes(32));
            file_put_contents($localSecretFile, $newLocalSecret);

            $localSecret = (string)trim(file_get_contents($localSecretFile));
            if(!$localSecret) {
                alert("Error setting local secret in $localSecretFile");
            }
        }

        $localSecrets[$secretName] = $localSecret;
    }

    return $localSecrets[$secretName];
}

// Only available from v5.6
if(!function_exists('hash_equals')) {
    function hash_equals($known_string, $user_string) {
        if(strlen($known_string) != strlen($user_string)) return false;

        $hashEquals = true;
        for($i = 0; $i < strlen($known_string); $i++) {
            if(substr($known_string, $i, 1) != substr($user_string, $i, 1)) $hashEquals = false;
        }

        return $hashEquals;
    }
}

function addUserNotice( $notice, $type = '' ) {
    if (session_id() == "") session_start();
    if (!isset($_SESSION['userNotices'])) $_SESSION['userNotices'] = [];

    $noticeData = [
        'notice' => $notice,
        'type' => $type,
    ];
    
    // md5() key prevents duplicates if we've jumped with a 'location' header before displaying notices
    $_SESSION['userNotices'][md5(serialize($notice))] = $noticeData;
}

function displayUserNotices() {
    if (session_id() == "") session_start();
    if (!isset($_SESSION['userNotices']) || !is_array($_SESSION['userNotices'])) return;


    if(count($_SESSION['userNotices'])) {
        ?>
        <div class="notify flashMessage">
            <? foreach ($_SESSION['userNotices'] as $noticeData ) { ?>
                <div class="<?=$noticeData['type']?>">
                    <?=$noticeData['notice']?>
                </div>
            <? } ?>
        </div>
        <?
    }
    $_SESSION['userNotices'] = [];
}

// polyfill for random_bytes if we're not running on PHP 7
if (!function_exists('random_bytes')) {
    function random_bytes( $length ) {
        return openssl_random_pseudo_bytes($length);
    }
}


function gmtOffset( $timezone ) {
    $dateTimeZone = timezone_open( $timezone );
    if ($dateTimeZone===false) {
        return array(0,'UTC');
    }
    
    $dateTimeZoneUTC = timezone_open('UTC');
    $dateTimeUTC = date_create("now", $dateTimeZoneUTC);
    $dateTime = date_create("now", $dateTimeZone);

    return array( timezone_offset_get( $dateTimeZone, $dateTimeUTC ), date_format($dateTime,'T') );
}

function questionAndAnswer( $question, $answer, $extraClass='', $extra='' ) {
    if (is_array($extraClass)) $extraClass = implode(' ',$extraClass);
    ?>
        <div class="questionAndAnswer <?=htmlspecialchars($extraClass)?>" <?=$extra?>>
            <div class="question">
                <?= (is_object($question) && ($question instanceof Closure)) ? $question() : $question ?>
            </div>
            <div class="answer">
                <?= (is_object($answer) && ($answer instanceof Closure)) ? $answer() : $answer ?>
            </div>
        </div>
    <?
}

function systemData($key, $value = null, $useCache = true) {
    global $DB;
    static $systemDataCache = [];

    if($value === null) {
        if(!isset($systemDataCache[$key]) || !$useCache) {
            $systemDataCache[$key] = $DB->getValue('SELECT value FROM systemData WHERE `key` = ?', $key);
        }
        $returnValue = $systemDataCache[$key];
    } else {
        $returnValue = isset($systemDataCache[$key]) ? $systemDataCache[$key] : null;
        $systemDataCache[$key] = $value;
        $DB->exec('
            INSERT INTO systemData (`key`, value)
            VALUES(?, ?)
            ON DUPLICATE KEY UPDATE value = ?
        ', $key, $value, $value);
    }

    return $returnValue;
}

function base64URLEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64URLDecode($data) {
    return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
}

function array_length( &$var ) {
    if (!is_array($var)) return false;
    return count($var);
}

// Polyfill for array_key_first in PHP 7.2
if (!function_exists('array_key_first')) {
    function array_key_first(array $array) { foreach ($array as $key => $value) { return $key; } }
}

// Reinstate the deprecated each function
if (!function_exists('each')) {
    function each(&$array) {
        return [
            key($array),
            current($array)
        ];
    }
}
