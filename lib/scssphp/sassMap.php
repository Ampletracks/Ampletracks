#!/usr/bin/php
<?
/*
    JIT SASS processing script for use with Apache RewriteMap
    =========================================================

    Copyright 2015 Ben Jefferson <skwirrel@gmail.com>

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.

*/

# ================ CONFIGURATION ===============

# If 2 or more requests are made to process the same file within this many seconds then
# the file will only be checked for changes on the first request
define('MIN_CHECK_INTERVAL',2);

# If $LOG is defined then details of processing will be written to the log file specified
# To supress logging simply do not define $LOG (i.e. comment out the next line)
$LOG = '/tmp/ampletracks_sassMap.log';

# ============= END OF CONFIGURATION ============


include('scss.inc.php');

# Functions
#================================================

function sassCheck($cssFile) {
    global $LOG,$scss;
    static $seenFiles;
    if (!isset($seenFiles)) $seenFiles = array();

    static $ignoreUntil;
    if (!isset($ignoreUntil)) $ignoreUntil = array();

    if (isset($ignoreUntil[$cssFile])) {
        if ($ignoreUntil[$cssFile]<time()) {
            unset($ignoreUntil[$cssFile]);
        } else {
            if ($LOG) fputs( $LOG, "\tFile was only just checked\n");
            return false;
        }
    }    

    # whether the file gets processed or not, if we get another request very soon then
    # we don't need to recheck it.
    $ignoreUntil[$cssFile] = time() + MIN_CHECK_INTERVAL;

    $scssFile = preg_replace('/\\.css$/i','.scss',$cssFile,-1,$matches);
    clearstatcache(true,$scssFile);
    if ($matches) {
        fputs( $LOG, "\tChecking file:".$scssFile."\n");
        $lastSourceEdit = @filemtime($scssFile);
        if (!$lastSourceEdit) {
            if ($LOG) fputs( $LOG, "\tCouldn't find scss file: $scssFile\n");
            return false;
        }

        $notSeen = false;
        if (!isset($seenFiles[$cssFile])) {
            $notSeen = true;
        } else {
            # check the dependencies - if any of these has been editted after $lastSourceEdit then update $lastSourceEdit
            foreach( $seenFiles[$cssFile] as $dependency ) {
                fputs( $LOG, "Depends on: $dependency\n");
                $dependencyEditTime = filemtime($dependency);
                if ($dependencyEditTime > $lastSourceEdit) {
                    $lastSourceEdit=$dependencyEditTime;
                    if ($LOG) fputs( $LOG, "\tBasing last edit time on $dependency\n");
                }
            }
        }

        # see if the .scss file is newer than the .css 
        # or if this is the first time we have seen this file
        if ($LOG) {
            if ($notSeen) fputs( $LOG, "\tFirst time we have seen this file\n");
            else fputs( $LOG, "\tChecking date of $cssFile compared to ".date('d/m/Y H:i:s',$lastSourceEdit)."\n");
        }
        if ($notSeen || !file_exists($cssFile) || $lastSourceEdit>filemtime($cssFile)) {
            # scss file needs rebuilding
            try {
                if ($LOG) fputs( $LOG, "\tRecompiling css\n");
                $scss->clearImportList();
                $scss->clearImportCache();
                $scss->setImportPaths(array(dirname($cssFile)));
                $scssCode = file_get_contents($scssFile);
                file_put_contents($cssFile,$scss->compileString($scssCode)->getCss());
                $imports = $scss->getImportList();
                if ($LOG) fputs( $LOG, "File relied on these imports:\t\n".implode("\t\n",$imports)."\n");
                $seenFiles[$cssFile] = $imports;
            } catch (Exception $e) {
                if ($LOG) fputs( $LOG, "\tError compiling css: ".$e->getMessage()."\n");
                file_put_contents($cssFile,"/*\n".$e->getMessage()."\n*/\n".file_get_contents($scssFile));
            }
            return true;
        } else if ($LOG) fputs( $LOG, "\tFile is up-to-date\n");
    }
    return false;
}
/*
class scss_formatter_extra_compressed extends ScssPhp\ScssPhp\Formatter\Compressed {
    public function property($name, $value) {
        return trim($name) . $this->assignSeparator . trim($value) . ";";
    }
}
*/


# Setup
#================================================

$stdin = fopen('php://stdin', 'r');
if (isset($LOG) && strlen($LOG) ) {
    $LOG = fopen($LOG,'a');
    fputs( $LOG, "Starting at ".date('Y m dc')."\n==============================\n" );
}

use ScssPhp\ScssPhp\Compiler;

$scss = new Compiler();
// $scss->setFormatter('scss_formatter_extra_compressed');
$scss->setOutputStyle(\ScssPhp\ScssPhp\OutputStyle::COMPRESSED);


# Main loop
#=================================================
$runOnce = false;
if (count($argv)) {
    array_shift($argv);
    $args = strtoupper(' '.implode(' ',$argv));
    if (strpos($args,'RUNONCE')) $runOnce = true;
}

while(1) {
    $filename = trim(fgets(STDIN));
    if ($runOnce && feof(STDIN)) break;
    # If we have be pointed at the scss file then look at the css instead
    $filename = preg_replace('/\\.scss$/','.css',$filename);
    $return = $filename;
    if (sassCheck($filename)) $return .= '#';
    if ($LOG) fputs( $LOG, "\t< $return\n");
    echo "$return\n";
    flush();
}

