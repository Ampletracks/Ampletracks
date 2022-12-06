<?

// returns -1 if there is no record of the last run

function timeSinceLastRun( $update=true ) {
    global $DB;
    $now = time();
    
    $scriptPathMd5 = md5($_SERVER["SCRIPT_FILENAME"]);
    $lastRunAt = $DB->getValue('SELECT lastRunAt FROM timeSinceLastRun WHERE scriptPathMd5=?',$scriptPathMd5);
    if ($update) $DB->exec('REPLACE INTO timeSinceLastRun (scriptPathMd5,lastRunAt) VALUES(?,?)',$scriptPathMd5,$now);

    if (!$lastRunAt) return -1;
    return $now-$lastRunAt;
}
