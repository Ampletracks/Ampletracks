<?php

class emailDeliveryNull {
    
    public function __construct() {
    }

    public function getErrors() {
        return [];
    }

    public function deliver($details) {
        // Use this for debug
        global $LOGGER;
        $LOGGER->log(print_r($details,true));
        //
        
        return true;
    }
}