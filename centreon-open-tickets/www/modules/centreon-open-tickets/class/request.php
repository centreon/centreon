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

class Centreon_OpenTickets_Request
{
    /**
     *
     * @var array
     */
    protected $_postVar;

    /**
     *
     * @var array
     */
    protected $_getVar;

    /**
     * constructor
     *
     * @return void
     */
    public function __construct() {
        $this->_postVar = array();
        $this->_getVar = array();

        if (isset($_POST)) {
            foreach ($_POST as $key => $value) {
                $this->_postVar[$key] = $value;
            }
        }

        if (isset($_GET)) {
            foreach ($_GET as $key => $value) {
                $this->_getVar[$key] = $value;
            }
        }
    }

    /**
     * Return value of requested object
     *
     * @param string $index
     * @return mixed
     */
    public function getParam($index) {
        if (isset($this->_getVar[$index])) {
            return $this->_getVar[$index];
        }
        if (isset($this->_postVar[$index])) {
            return $this->_postVar[$index];
        }
        return null;
    }
}
