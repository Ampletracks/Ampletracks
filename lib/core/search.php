<?
/*
Every search is based on some SQL this can be provided in one of three ways;
	1. Specify SQL as a string
		build_search( "SELECT sql", [ db=$DB ] );
	2. Specify SQL as a string with placeholders and set of values to merge in
		build_search( array("SELECT sql WHERE foo=? and bar=?", $foo, $bar ), [ db=$DB ] );
	2. Point to a file containing SQL
		build_search( "search_name.sql", [ db=$DB ] );
			# NB file name MUST NOT start "SELECT " or "select "
			# if file contains no '/'s then it is assumed to be in the templates/search directory
			#	otherwise you're on your own!
	3. Autogenerate SQL
		build_search( cols, table_name, [search_specificer_prefix=table_name], [ db=$DB ] )
			# if the search_specifier_prefix == '' then the default (table_name) is used
			# search_specifier_prefix is case insensitive
This can either be done as part of the call to "new search" or later using the build_search method.

new search( search_name )
new search( search_name, "SELECT sql" [, db=$DB ] );
new search( search_name, cols, table_name, [search_specificer_prefix=table_name], [ db=$DB ] );
	# the search_name is not really used if you intend to set the templates using set_template
	# but you'll just jolly well have to make one up anyway!
display( [ scheme=$_WORSPACE['scheme']|"default" ] );
	# if no SQL has been provided at this point then the code will look for a
	# <search_name>.sql file in the search directory
	# if scheme is not provided then the value of $_WORSPACE['scheme'] is used
	# 	if this is not defined then the scheme called 'default' is used
	# Any templates that have been setup using set_template will overide ones loaded from the scheme
	# To 'unset' a template just use unset_template
*/

	class search {

		var $name='';
		var $dbh='';
		var $sql='';
		var $templates=array();
		var $function_names=array();
		var $bundle_cols = 0;
		var $count_rows = 0;
		var $num_rows = 0;
		var $scheme = '';
		var $extraRow = 0;
        var $query_handle = null;
        private $column_names = array();

		static 	$_SEARCH_REQUIRED_TEMPLATES = array( 'common' => 'common', 'header' => 'header', 'footer' => 'footer', 'list' => 'list', 'empty' => 'empty' );

		# Constructor
		######################################
		# new search( search_name )
		# new search( search_name, "SELECT sql" [, db=$DB ] );
		#	the SQL MUST START with "SELECT " otherwise it will be assumed to be a filename
		# new search( search_name, "search_sql_filename" [, db=$DB ] )
		#	the search_sql_filename should not start "SELECT "
		# new search( search_name, table_name, cols, [search_specificer_prefix=<table_name>_], [ db=$DB ] );
		#######################################
		function __construct( $search_name, $arg1=null, $arg2=null, $arg3=null, $arg4=null ) {
			$this->name=$search_name;
			$this->build_search( $arg1, $arg2, $arg3, $arg4 );
		}

		function build_search( $arg1=null, $arg2=null, $arg3=null, $arg4=null ) {
			if ( isset($arg2) && !is_object($arg3) ) {
				# the params are of the form ( table_name, cols, [search_specificer_prefix=table_name], [ db=$DB ] )
				if ( $arg1 == '' || $arg1 == null ) $arg1 = $this->name;
				if ( $arg2 == '' || $arg2 == null ) $arg2 = '*';
				if ( is_object($arg4)  ) $this->dbh = $arg4;
				if ( $arg3 == null ) $arg3 = $arg1.'_';
				$this->sql = "SELECT $arg2 FROM $arg1 WHERE ".makeConditions($arg3);
			} elseif ( isset($arg1) ) {
				if ( is_object($arg2)  ) $this->dbh = $arg2;
				if ( is_array($arg1) || preg_match( "/^[\\n\\r \\t]*SELECT[\\t\\n\\r ]/i", $arg1 ) ) {
					$this->sql = $arg1;
				} else {
					$this->sql = 'SELECT * FROM '.$arg1.' WHERE '.makeConditions($arg1.'_');
				}
			} else {
				$this->sql = 'SELECT * FROM '.$this->name.' WHERE '.makeConditions($this->name.'_');
			}
		}


		function load_sql_file() {
			if ( !check_file_exists($this->sql_file,'.sql') ) {
				coreError('Missing system file',"Couldn't find file '$this->sql_file' (tried with and without .sql extension) to build search SQL");
				return(0);
			}
			$this->sql = '';
			if ( !read_file( $this->sql, $this->sql_file ) ) {
				coreError('Unexpected system error',"Couldn't open file '$this->sql_file' to build search SQL");
				return(0);
			}

			return(1);
		}



		# this function adds in search conditions after the first " where " (case insensitive - will match WHERE or where but not WhErE or Where) in the sql search
		# It also ads on the search_spe_sql for you - if $prexif == '' then that's all it does
		function addConditions( $prefix , $type='where' ) {
			addConditions( $this->sql, $prefix, '', $type );
		}

		function orderBy( $column = '' ) {
			if ($column=='') {
				$column = ws('orderBy');
				if (preg_match('/^(ASC|DESC)$/i',ws('orderDirection'))) $column .= ' '.ws('orderDirection');
			}

			# just for safety filter the column string
			$column = preg_replace('/[^a-zA-Z_. -]/','',$column);
			$column = trim($column);
			if ($column =='') return 0;
			$column .= ', ';
			$this->sql = str_splice( $this->sql, "[\r\n\t ]".convertToCaseInsensitiveRegExp('ORDER BY')."[\r\n\t ]", $column );
		}

		function showFirstContaining( $field, $value ) {
			if ( !strlen($value) ) { return; }

			$clean_field = str_replace('.','_',$field);
			$this->sql = str_splice( $this->sql, "[\r\n ]*".convertToCaseInsensitiveRegExp('select')."[\r\n  ]" , "LENGTH( $field ) AS _".$clean_field."_length, POSITION( UCASE('$value') IN UCASE($field) ) AS _".$clean_field.'_score, ' );

			$order_clause = ' _'.$clean_field.'_score ASC, _'.$clean_field.'_length ASC ';

			if ( !preg_match( "/[\\r\\n ]ORDER BY[\\r\\n ]/i", $this->sql ) ) {
				# if the order by clause isn't there already then either...
				if ( preg_match( "/[\\r\\n ]LIMIT[\\r\\n ]/i", $this->sql ) ) {
					# or add it in just before the limit clause
					$this->sql = str_splice( $this->sql, "[\r\n ]".convertToCaseInsensitiveRegExp('limit')."[\r\n ]", " ORDER BY ", 0 );
				} else {
					# add the order by bit on the end
					$this->sql .= ' ORDER BY ';
				}
			} else {
				$order_clause .= ',';
			}
			$this->sql = str_splice( $this->sql, "[\r\n ]".convertToCaseInsensitiveRegExp('order by')."[\r\n ]" , $order_clause );
		}

		function check_template_name( &$which ) {
			self::$_SEARCH_REQUIRED_TEMPLATES;
			$which = strtolower($which);
			if ( !isset(self::$_SEARCH_REQUIRED_TEMPLATES[$which]) ) { 
                echo "$which"; exit;
				coreError('Unexpected system error',"Template can only be 'header', 'footer', 'empty' or 'list' - but got :$which");
				return(0);
			}
			return(1);
		}

		function set_template( $which, $string ) {
			if ( !$this->check_template_name($which) ) return(0);
			$this->templates[$which] = $string;
			return(1);
		}

		function unset_template( $which ) {
			if ( !$this->check_template_name($which) ) return(0);
			unset($this->templates[$which]);
			return(1);
		}

		function load_templates( $filename ) {
			$templates=array();
		
			if ( !( $fh = fopen($filename,'r') ) ) {
				coreError('Unexpected system error',"Couldn't open file '$filename' to load display templates");
				return(0);
			}
			$dest = '';
			while( $line = fgets($fh,1024) ) {
				if ( preg_match('/^ *< *TEMPLATE +NAME *= *" *([^"]+) *" *>/i', $line, $which) ) {
					$dest = strtolower($which[1]);
					$templates[$dest] = '';
				} elseif ( preg_match('!^ *< */ *TEMPLATE *>!i', $line) ) {
					$dest = '';
				} elseif ( $dest <> '' ) {
					if ( !isset($templates[$dest]) ) $templates[$dest] = '';
					$templates[$dest] .= $line;
				}
			}
			fclose( $fh );
		
			return $templates;
		}

		static function rowDataEcho( &$rowData, $name ) {
			echo isset($rowData[$name])?htmlOut($rowData[$name] ):'';
		}

		function compose_template_function( $which ) {
			global $_COMPILED_SEARCHES;
			if ( !$this->check_template_name($which) ) return('');
            if ( !isset($this->templates[$which])) return('');
			$markup = $this->templates[$which];
			$safe_which = preg_replace('/[^a-zA-Z_. -]/','',$which);

			$markup = preg_replace( '/<<([^>]+)>>/', '<? wsp(\'\1\'); ?>', $markup );
			$markup = preg_replace( '/@@([^@]+)@@/', '<? search::rowDataEcho($rowData,\'\1\'); ?>', $markup );
			if ($safe_which=='common') {
				$eval_str = "?>$markup<?";
			} else {
				$eval_str = "
					# $which
					if (\$mode=='$safe_which') {
						?>$markup<?
					}
				";
			}
#				echo "<FORM><TEXTAREA ROWS='20' COLS='120' >".htmlspecialchars($eval_str)."</TEXTAREA></FORM><HR />\n";
			return($eval_str);
		}

		function sql() {
			return $this->sql;
		}

		function skip( $skip ) {
			$this->skip = $skip;
		}

		function show( $show ) {
			$this->show = $show;
		}

		function countRows( $arg = 1 ) {
			$this->count_rows = $arg;
		}

		function numRows( ) {
			return $this->num_rows;
		}

		function extraRow( $set =1 ) {
			return $this->extraRow = $set;
		}
		
		function setScheme( $scheme ) {
			return $this->scheme = $scheme;
		}

/*
		This function sets up the show and skip parameters for this search
		If only one parameter is passed this is assumed to be a cgi name prefix - this routine then looks for
			$prefix.'show' and $prefix.'skip' in the workspace and uses the values therein
		If no parameters are passed then the search name is used as the prefix and the routine looks for
			$search_name.'_show' and $search_name.'_skip'
		If 2 parameters are given these are taken to be the values for show and skip
*/
		function show_skip( $arg1 = null, $arg2 = null ) {
			if ($arg2==null) {
				if ( $arg1==null ) $arg1=$this->name.'_';
				if (!ws($arg1.'show') && !ws($arg1.'show')) {
					$this->show( ws('show') );
					$this->skip( ws('skip') );
				} else {
					$this->show( ws($arg1.'show') );
					$this->skip( ws($arg1.'skip') );
				}
			} else {
				$this->show( $arg1 );
				$this->skip( $arg2 );
			}
		}

		function bundleHash( $value ) {
			$this->bundle_cols = 0 - $value;
		}

		function bundleArray( $value ) {
			$this->bundle_cols = $value;
		}

        function resultWasLimited() {
            return $this->resultWasLimited;
        }
/*
If bundle_hash or bundle_array has been called then rows retreived by the query where the value in the first column is the same will be bundled together. It is assumed that the first n-bundle_cols (for bundle_array) or n-1-bundle_cols (for bundle_hash ) columns of the return set will be identical for each bundle of rows. In the case of a bundle_hash it is then assumed that the n-bundle_cols-1 'th column contains an identifier for this part of the bundle.
Easiest way to show this is by example

EXAMPLE1
========
	id	country	param	value
	1	UK		size	10
	1	UK		width	20
	1	UK		height	15
	2	DE		size	11
	3	SE		height	10
	3	SE		width	11

bundle_hash(1)
	would result in 3 rows thus
		1=> row_data['id']=1, row_data['country']='UK', row_data['size']=10, row_data['width']=20, row_data['height']=15
		2=> row_data['id']=2, row_data['country']='DE', row_data['size']=11
		3=> row_data['id']=3, row_data['country']='SE', row_data['height']=10, row_data['width']=11

bundle_array(2)
	would result in 3 rows thus
		1=> row_data['id']=1, row_data['country']='UK', row_data['bundle']=array(array('param'=>'size','value'=>'10'), array('param'=>'width','value'=>'20'), array('param'=>'height','value'=>'15'))
		2=> row_data['id']=2, row_data['country']='DE', row_data['bundle']=array(array('param'=>'width','value'=>'15'))
		3=> row_data['id']=3, row_data['country']='SE', row_data['bundle']=array(array('param'=>'height','value'=>'10'), array('param'=>'width','value'=>'11'))

bundle_array(1)
	would be a bit stupid as this assumes that columns 1 to 3 are identical which they aren't but would result in 3 rows thus
		1=> row_data['id']=1, row_data['country']='UK', row_data['param']='height', row_data['bundle']=array(10,20,15)
		2=> row_data['id']=2, row_data['country']='DE', row_data['param']='size', row_data['bundle']=array(11)
		3=> row_data['id']=3, row_data['country']='SE', row_data['param']='width', row_data['bundle']=array(10,11)

EXAMPLE2
========

	id	country	param	value	flavour
	1	UK		size	10	A
	1	UK		width	20	G
	1	UK		height	15	B
	2	DE		size	11	A
	3	SE		height	10	C
	3	SE		width	11	G

bundle_hash(2)
	would result in 3 rows thus
		1=> row_data['id']=1, row_data['country']='UK', row_data['size']=array('value'=>10,'flavour'=>'A'), row_data['width']=array('value'=>20,'flavour'=>'G'), row_data['height']=array('value'=>15,'flavour'=>'b')
		2=> row_data['id']=2, row_data['country']='DE', row_data['size']=array('value'=>1,'flavour'=>'A')
		3=> row_data['id']=3, row_data['country']='SE', row_data['height']=array('value'=>10,'flavour'=>'C'), row_data['width']=array('value'=>11,'flavour'=>'G')


*/

        function doQuery($skip='', $show='' ) {
			global $DB;
			if ( !is_object($this->dbh) ) $this->dbh = $DB;

			if ( $skip=='' && isset( $this->skip ) ) { $skip = $this->skip; }
			if ( $show=='' && isset( $this->show ) ) { $show = $this->show; }

			# finally actually do the query and iterate over the results
			# See if the sql was passed as an array of SQL and merge fields
			if (is_array($this->sql)) {
				$sql_str = $this->sql[0];
				// array_slice is used here to create a copy of the array
				$sqlAndMergeFields = array_slice($this->sql,0);
			} else {
				$sql_str = $this->sql;
				$sqlAndMergeFields = array();
			}
						
            $this->num_rows = 0;
			# Add on the LIMIT clause
			if ( $show > 0 && $this->bundle_cols == 0) {
				# if count_rows has been set then run the query with count(*) before running it properly
				if ($this->count_rows && !( preg_match('/HAVING/i',$sql_str) && !preg_match('/HAVING *\\( *1 *= *1 *\\) *AND *\\( *1 *= *1 *\\)/i',$sql_str) ) ) {
					# replace the list of column names with a simple count(*)
					$count_sql = preg_replace( "/^[ \\t\\r\\n]*SELECT[ \\t\\r\\n]+.*[ \\t\\r\\n]+FROM[ \\t\\r\\n]/i","SELECT COUNT(*) FROM\n",$sql_str);
					# remove any order_by clauses
					$count_sql = preg_replace( "/ORDER BY.*\$/i","",$count_sql);
                    # echo $count_sql;
					$sqlAndMergeFields[0] = $count_sql;
					list($this->num_rows) = $this->dbh->getRow($sqlAndMergeFields);
				}
				$sql_str .= ' LIMIT ';
				if ($skip) { $sql_str .= $skip.','; }
				$sql_str .= $show;
			}

            # If there is a "LIMIT" then increase it by one - this allows us to tell if the limit comes into force, without forcing
            # MySQL to compute the entire result set (which would happen if we used SQL_CALC_FOUND_ROWS)
            $queryWasLimited = false;
            if (preg_match('/\s+LIMIT\s+(\d+)\s*$/',$sql_str,$matches)) {
                $queryWasLimited = true;
                $originalLimit = $matches[1];
                $sql_str = preg_replace('/\s+LIMIT\s+(\d*)\s*$/',' LIMIT '.($originalLimit+1),$sql_str);
            }

            # echo "about to run $sql_str<BR/>\n";
			$sqlAndMergeFields[0] = $sql_str;

			$this->query_handle = $this->dbh->query( $sqlAndMergeFields );
            $this->column_names = $this->query_handle->getColumns();

            $this->resultWasLimited = false;
            if ($queryWasLimited && $this->query_handle->numRows() > $originalLimit) {
                $this->resultWasLimited = true;
            }

            return $this->query_handle;
        }

		function display( $show_head_foot_if_empty=false, $skip='', $show='' ) {

			# set up the skip and show parameters
			# if they aren't passed as parameters see if they are stored in this object
			if ( $skip=='' && isset( $this->skip ) ) { $skip = $this->skip; }
			if ( $show=='' && isset( $this->show ) ) { $show = $this->show; }

            if($this->query_handle) {
                $query_handle = $this->query_handle;
                $query_handle->rewind();
            } else {
                $query_handle = $this->doQuery($skip, $show);
                if($query_handle === false) return 0;
            }

            $num_rows = $query_handle->numRows();
            if($this->resultWasLimited) $num_rows--;

			$search_base = SITE_BASE_DIR.'/search/';
			$file = $this->name;
            # echo "trying $file";
			# load in search_name.tpl if it exists
			if ( !file_exists( $search_base.$file.'.tpl' ) ) $file = 'default';
			if ( !file_exists( $search_base.$file.'.tpl' ) ) return false;
			$this->templates = $this->load_templates( $search_base.$file.'.tpl' );
			
			# look for search_name_header, search_name_footer and search_name_list files
			# this first looks for search_base/scheme/search_name_<template>[.tpl|.txt|.php|.php3]
			# then it looks for search_base/search_name_<template>[.tpl|.txt|.php|.php3]
			# then it looks for search_base/scheme/default_<template>[.tpl|.txt|.php|.php3]
			# then it looks for search_base/default_<template>[.tpl|.txt|.php|.php3]
			reset( self::$_SEARCH_REQUIRED_TEMPLATES );
			$eval_str = "
					global \$WS;
			";
			while ( list( $key, $value ) = each( self::$_SEARCH_REQUIRED_TEMPLATES  ) ) {
			
				# We now compose a function for each of the markup blocks
				# the names of the functions are stored in $this->function_names
				$eval_str .= $this->compose_template_function( $value );
			}
			$template_function = create_function('$mode, $rowData, $row, $shown, $numCols, $numRows, $search',$eval_str);

            $column_names = $this->column_names;
            $num_cols = count($column_names);
			$row = 0;
            if (!$skip) $skip=0;

            # echo "num rows = $num_rows\n<BR/>";
			if ( $num_rows == 0 && !$this->extraRow ) {
				if ($show_head_foot_if_empty) $template_function('header', $column_names, 0, 0, $num_cols, 0, $this);
				$template_function('empty', $column_names, 0, 0, $num_cols, 0 , $this);
				if ($show_head_foot_if_empty) $template_function('footer', $column_names, 0, 0, $num_cols, 0, $this );
			} else {
				$shown = 0;

				# actually print the table header
				$template_function('header',$column_names, $row, $shown, $num_cols, $num_rows, $this );

                $bundle_cols = $this->bundle_cols;

				# output the table data
				if ($bundle_cols == 0) {
					# first the easy one - iterate over all the rows returned - print one row per row of data
					$row = $skip;
                    $rows_done = 0;
                    while($query_handle->fetchInto($row_data) && $rows_done < $num_rows) {
                        enrichRowData( $row_data );
						$template_function('list',$row_data, $row, $shown, $num_cols, $num_rows, $this );
						$row++;
						$shown++;
                        $rows_done++;
					}

				} else {
					# now the tricky one - iterate over all the rows returned but bundle up some of the rows

					$bundle_col_titles = $column_names;
					$bundle_col_titles = splitArray($bundle_col_titles, 0-abs($bundle_cols));
					if ( $bundle_cols < 0 ) $bundle_key_col = $column_names[count($column_names)+$bundle_cols-1];
					$bundle_data = array();
					$last_row = null;

                    $rows_fetched = 0;
					while ( true ) {
                        $query_handle->fetchInto($row_data);
                        $rows_fetched++;

						# if there is some bundle_data and this row is different to the last - or if this is the last row of data we have

                        # We need to go one row past the end as we display the previous row
                        $beyondLastRow = $rows_fetched == $num_rows + 1;
						if (
                            (is_array($last_row) && is_array($row_data) && $last_row[0] <> $row_data[0]) ||
                            $beyondLastRow
                        ) {
							# add in the bundle data
							if ($bundle_cols < 0) {
                                foreach( $bundle_data as $key=>$value ) {
                                    $last_row[$key] = $value;
                                }
                                #dump($bundle_data);
							} else {
								$last_row['bundle']=$bundle_data;
							}
							# then print out a row
							$row++;
                            $last_row['_isLastRow'] = true;
							if ( $row > $skip ) {
                                enrichRowData( $last_row ) ;
								$template_function('list', $last_row, $row-1, $shown, $num_cols, $num_rows, $this );
								$shown++;
								$bundle_data = array();
							}
							if ( $row_data == null || ($show <> '' && $shown >= $show) || $rows_fetched == $num_rows + 1) break;
						}

						# don't bother bundling stuff if we're not even on the rows they want yet
						if ( $row >= $skip ) {
							# enlarge the bundle
							if ($bundle_cols < 0) {
								# its a "hash budle"
								$bundle_key = $row_data[$bundle_key_col];
								if (!isset( $bundle_data[$bundle_key] )) $bundle_data[$bundle_key] = array();

								if (count($bundle_col_titles) == 1) {
									# if there's only one bundle column stick this in as a value
									$bundle_data[$bundle_key] = $row_data[ $bundle_col_titles[0] ];
                                    # echo "xx $bundle_key => ".$row_data[ $bundle_col_titles[0] ]."<BR />";
								} else {
									# if there's lots store a hash of the different values keyed on column name
									reset( $bundle_col_titles );
									while ( list($key, $value) = each( $bundle_col_titles ) ) {
										$bundle_data[$bundle_key][$value] = $row_data[$value];
                                        # echo "yy $bundle_key => $value ".$row_data[$value]."<BR />";
 									}
								}
							} else {
								# its an "array bundle"
								if (count($bundle_col_titles) == 1) {
									# if there's only one bundle column stick this in as a value
									$bundle_data[] = $row_data[ $bundle_col_titles[0] ];
								} else {
									$sub_cols = array();
									reset($bundle_col_titles);
									while( list($key,$value) = each( $bundle_col_titles ) ) {
										$sub_cols[$value] = $row_data[$value];
									}
									$bundle_data[] = $sub_cols;
								}
							}
						}

						$last_row = $row_data;
					}
				}

				if ($this->extraRow) {
					$template_function('list', array(), $row, $shown, $num_cols, $num_rows, $this );
				}
				
				$template_function('footer', array(), $row, $shown, $num_cols, $num_rows, $this );
                # echo "<TEXTAREA ROWS='10' COLS='50' >$eval_str</TEXTAREA><BR />\n";
                # eval( $eval_str );
                if (!$this->num_rows) $this->num_rows=$row;
			}
		}

	} # end class

?>
