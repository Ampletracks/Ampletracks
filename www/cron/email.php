<?php
$requireLogin=false;
include('../../lib/core/startup.php');
include(LIB_DIR.'email.php');

// Only provide debug if we are being run by a logged in user
$debug = $USER_ID>0;

$debug = function($message) {
    global $debug;
    if (!$debug) return;
    echo $message;
    echo str_repeat(' ',4096);
    flush();
};

header('Content-type: text/plain');

$EMAIL->processQueue( $debug );
