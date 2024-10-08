<?php
/*
 * Copyright 2005-2015 Centreon
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */


/**
 * Class
 *
 * @class CentreonBase
 * @description Class for request
 */
class CentreonBase
{
    /** @var string */
    public $index;

    /*
	 * Objects
	 */

    /** @var CentreonDB */
    protected $DB;
    /** @var CentreonDB */
    protected $DBC;
    /** @var CentreonGMT */
    protected $GMT;
    /** @var CentreonHost */
    protected $hostObj;
    /** @var CentreonService */
    protected $serviceObj;
    /** @var string */
    protected $sessionId;

    /*
	 * Variables
	 */

    /** @var int */
    protected $debug;
    /** @var int|mixed */
    protected $compress;
    /** @var */
    protected $userId;
    /** @var */
    protected $general_opt;

    /**
     * CentreonBase constructor
     *
     * <code>
     *  $obj = new CentreonBGRequest($_GET["session_id"], 1, 1, 0, 1);
     * </code>
     *
     * @param string $sessionId
     * @param bool $index
     * @param bool $debug
     * @param bool $compress
     *
     * @throws PDOException
     */
    public function __construct($sessionId, $index, $debug, $compress = null)
    {
        if (!isset($debug)) {
            $this->debug = 0;
        }

        (!isset($compress)) ? $this->compress = 1 : $this->compress = $compress;

        if (!isset($sessionId)) {
            print "Your must check your session id";
            exit(1);
        } else {
            $this->sessionId = htmlentities($sessionId, ENT_QUOTES, "UTF-8");
        }

        $this->index = htmlentities($index, ENT_QUOTES, "UTF-8");

        /*
		 * Enable Database Connexions
		 */
        $this->DB = new CentreonDB();
        $this->DBC = new CentreonDB("centstorage");

        /*
		 * Init Objects
		 */
        $this->hostObj = new CentreonHost($this->DB);
        $this->serviceObj = new CentreonService($this->DB);

        /*
		 * Timezone management
		 */
        $this->GMT = new CentreonGMT($this->DB);
        $this->GMT->getMyGMTFromSession($this->sessionId);
    }

    /**
     * @param $options
     *
     * @return void
     */
    public function setGeneralOption($options): void
    {
        $this->general_opt = $options;
    }
}
