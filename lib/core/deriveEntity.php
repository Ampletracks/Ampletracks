<?
global $ENTITY;

if (!isset($ENTITY)) {
	$dirSeparator = DIRECTORY_SEPARATOR;
//	if (preg_match("/\\$dirSeparator([^\\$dirSeparator]+)\\$dirSeparator[^\\$dirSeparator]*/",$_SERVER["SCRIPT_FILENAME"], $matches )) {
	if (preg_match("/\\{$dirSeparator}([^\\{$dirSeparator}]+)\\{$dirSeparator}[^\\{$dirSeparator}]*\$/",$_SERVER["SCRIPT_FILENAME"], $matches )) {
		$ENTITY = $matches[1];
	} else {
		exit;
	}
}
