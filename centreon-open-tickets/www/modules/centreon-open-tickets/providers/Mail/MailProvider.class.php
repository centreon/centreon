<?php
/*
 * CENTREON
 *
 * Source Copyright 2005-2015 CENTREON
 *
 * Unauthorized reproduction, copy and distribution
 * are not allowed.
 *
 * For more information : contact@centreon.com
 *
*/

class MailProvider
    /**
     * constructor
     *
     * @return void
     */
    public function __construct() {
    }
    
    //Get Config
    // Two container can be set: OTcontainer1 and OTcontainer2
    public function getConfigHtml() {
        
    }
    
    // Get some config like:
    //  url, open ticket message, lists
    protected function _getConfigContainer1Main() {
        
    }
    
    // Get specific config:
    //   mail config (from, subject, headers, body
    protected function _getConfigContainer1Extra() {
        
    }
    
    // Get some config like:
    //  Popup format, Macro Name
    protected function _getConfigContainer2Main() {
        
    }
    
    // Get specific config:
    //   -
    protected function _getConfigContainer2Extra() {
        
    }
}
