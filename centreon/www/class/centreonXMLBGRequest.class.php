<?php

/*
 * Copyright 2005-2021 Centreon
 * Centreon is developed by : Julien Mathis and Romain Le Merlus under
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

use Pimple\Container;

require_once realpath(__DIR__ . "/../../config/centreon.config.php");
require_once realpath(__DIR__ . "/../../bootstrap.php");

/**
 * Class
 *
 * @class CentreonXMLBGRequest
 * @description Class for XML/Ajax request
 */
class CentreonXMLBGRequest
{
    /** @var string */
    public $classLine;

    /*
     * Objects
     */

    /** @var CentreonDB */
    public $DB;
    /** @var CentreonDB */
    public $DBC;
    /** @var CentreonXML */
    public $XML;
    /** @var CentreonGMT */
    public $GMT;
    /** @var CentreonHost */
    public $hostObj;
    /** @var CentreonService */
    public $serviceObj;
    /** @var CentreonMonitoring */
    public $monObj;
    /** @var CentreonACL */
    public $access;
    /** @var string */
    public $session_id;
    /** @var */
    public $broker;

    /*
     * Variables
     */
    /** @var */
    public $buffer;
    /** @var int */
    public $debug;
    /** @var int|mixed */
    public $compress;
    /** @var int */
    public $header;
    /** @var */
    public $is_admin;
    /** @var */
    public $user_id;
    /** @var array */
    public $grouplist;
    /** @var string */
    public $grouplistStr;
    /** @var */
    public $general_opt;
    /** @var */
    public $class;
    /** @var string[] */
    public $stateType;
    /** @var string[] */
    public $statusHost;
    /** @var string[] */
    public $statusService;
    /** @var string[] */
    public $colorHost;
    /** @var string[] */
    public $colorHostInService;
    /** @var string[] */
    public $colorService;
    /** @var array */
    public $en;
    /** @var string[] */
    public $stateTypeFull;

    /** @var string[] */
    public $backgroundHost;
    /** @var string[] */
    public $backgroundService;

    /*
     * Filters
     */
    /** @var */
    public $defaultPoller;
    /** @var */
    public $defaultHostgroups;
    /** @var */
    public $defaultServicegroups;
    /** @var int */
    public $defaultCriticality = 0;

    /**
     * CentreonXMLBGRequest constructor
     *
     * <code>
     *  $obj = new CentreonBGRequest($_GET["session_id"], 1, 1, 0, 1);
     * </code>
     *
     * @param Container $dependencyInjector
     * @param string $session_id
     * @param bool $dbNeeds
     * @param bool $headerType
     * @param bool $debug
     * @param bool $compress
     * @param $fullVersion
     *
     * @throws PDOException
     */
    public function __construct(
        Container $dependencyInjector,
        $session_id,
        $dbNeeds,
        $headerType,
        $debug,
        $compress = null,
        $fullVersion = 1
    ) {
        if (!isset($debug)) {
            $this->debug = 0;
        }

        (!isset($headerType)) ? $this->header = 1 : $this->header = $headerType;
        (!isset($compress)) ? $this->compress = 1 : $this->compress = $compress;

        if (!isset($session_id)) {
            print "Your might check your session id";
            exit(1);
        } else {
            $this->session_id = htmlentities($session_id, ENT_QUOTES, "UTF-8");
        }

        /*
         * Enable Database Connexions
         */
        $this->DB = $dependencyInjector['configuration_db'];
        $this->DBC = $dependencyInjector['realtime_db'];

        /*
         * Init Objects
         */
        $this->hostObj = new CentreonHost($this->DB);
        $this->serviceObj = new CentreonService($this->DB, $this->DBC);

        /*
         * Init Object Monitoring
         */
        $this->monObj = new CentreonMonitoring($this->DB);

        if ($fullVersion) {
            /*
             * Timezone management
             */
            $this->GMT = new CentreonGMT($this->DB);
            $this->GMT->getMyGMTFromSession($this->session_id);
        }

        /*
         * XML class
         */
        $this->XML = new CentreonXML();

        /*
         * ACL init
         */
        $this->getUserIdFromSID();
        $this->isUserAdmin();
        $this->access = new CentreonACL($this->user_id, $this->is_admin);
        $this->grouplist = $this->access->getAccessGroups();
        $this->grouplistStr = $this->access->getAccessGroupsString();

        /*
         * Init Color table
         */
        $this->getStatusColor();

        /*
         * Init class
         */
        $this->classLine = "list_one";

        /*
         * Init Tables
         */
        $this->en = ["0" => _("No"), "1" => _("Yes")];
        $this->stateType = ["1" => "H", "0" => "S"];
        $this->stateTypeFull = ["1" => "HARD", "0" => "SOFT"];
        $this->statusHost = ["0" => "UP", "1" => "DOWN", "2" => "UNREACHABLE", "4" => "PENDING"];
        $this->statusService = ["0" => "OK", "1" => "WARNING", "2" => "CRITICAL", "3" => "UNKNOWN", "4" => "PENDING"];
        $this->colorHost = [0 => 'host_up', 1 => 'host_down', 2 => 'host_unreachable', 4 => 'pending'];
        $this->colorService = [0 => 'service_ok', 1 => 'service_warning', 2 => 'service_critical', 3 => 'service_unknown', 4 => 'pending'];

        $this->backgroundHost = [0 => '#88b917', 1 => '#e00b3d', 2 => '#818185', 4 => '#2ad1d4'];
        $this->backgroundService = [0 => '#88b917', 1 => '#ff9a13', 2 => '#e00b3d', 3 => '#bcbdc0', 4 => '#2ad1d4'];

        $this->colorHostInService = [0 => "normal", 1 => "#FD8B46", 2 => "normal", 4 => "normal"];
    }

    /**
     * @return void
     */
    private function isUserAdmin(): void
    {
        $statement = $this->DB->prepare("SELECT contact_admin, contact_id FROM contact " .
            "WHERE contact.contact_id = :userId LIMIT 1");
        $statement->bindValue(":userId", (int) $this->user_id, PDO::PARAM_INT);
        $statement->execute();
        $admin = $statement->fetchRow();
        $statement->closeCursor();
        $this->is_admin = $admin !== false && $admin["contact_admin"] ? 1 : 0;
    }

    /**
     * Get user id from session_id
     *
     * @return void
     * @throws PDOException
     */
    protected function getUserIdFromSID()
    {
        $query = "SELECT user_id FROM session " .
            "WHERE session_id = '" . CentreonDB::escape($this->session_id) . "' LIMIT 1";
        $dbResult = $this->DB->query($query);
        $admin = $dbResult->fetchRow();
        unset($dbResult);
        if (isset($admin["user_id"])) {
            $this->user_id = $admin["user_id"];
        }
    }

    /**
     * Decode Function
     *
     * @param string $arg
     *
     * @return string
     */
    private function myDecode($arg)
    {
        return html_entity_decode($arg ?? '', ENT_QUOTES, "UTF-8");
    }

    /**
     * Get Status Color
     *
     * @return void
     * @throws PDOException
     */
    protected function getStatusColor()
    {
        $this->general_opt = [];
        $DBRESULT = $this->DB->query("SELECT * FROM `options` WHERE `key` LIKE 'color%'");
        while ($c = $DBRESULT->fetchRow()) {
            $this->general_opt[$c["key"]] = $this->myDecode($c["value"]);
        }
        $DBRESULT->closeCursor();
        unset($c);
    }

    /**
     * Send headers information for web server
     *
     * @return void
     */
    public function header(): void
    {
        /* Force no encoding compress */
        $encoding = false;

        header('Content-Type: text/xml');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Cache-Control: no-cache, must-revalidate');
        if ($this->compress && $encoding) {
            header('Content-Encoding: ' . $encoding);
        }
    }

    /**
     * @return string
     */
    public function getNextLineClass()
    {
        $this->classLine = $this->classLine == "list_one" ? "list_two" : "list_one";
        return $this->classLine;
    }

    /**
     * @return void
     */
    public function getDefaultFilters(): void
    {
        $this->defaultPoller = -1;
        $this->defaultHostgroups = null;
        $this->defaultServicegroups = null;
        if (isset($_SESSION['monitoring_default_hostgroups'])) {
            $this->defaultHostgroups = $_SESSION['monitoring_default_hostgroups'];
        }
        if (isset($_SESSION['monitoring_default_servicegroups'])) {
            $this->defaultServicegroups = $_SESSION['monitoring_default_servicegroups'];
        }
        if (isset($_SESSION['monitoring_default_poller'])) {
            $this->defaultPoller = $_SESSION['monitoring_default_poller'];
        }
        if (isset($_SESSION['criticality_id'])) {
            $this->defaultCriticality = $_SESSION['criticality_id'];
        }
    }

    /**
     * @param $instance
     *
     * @return void
     */
    public function setInstanceHistory($instance): void
    {
        $_SESSION['monitoring_default_poller'] = $instance;
    }

    /**
     * @param $hg
     *
     * @return void
     */
    public function setHostGroupsHistory($hg): void
    {
        $_SESSION['monitoring_default_hostgroups'] = $hg;
    }

    /**
     * @param $sg
     *
     * @return void
     */
    public function setServiceGroupsHistory($sg): void
    {
        $_SESSION['monitoring_default_servicegroups'] = $sg;
    }

    /**
     * @param $criticality
     *
     * @return void
     */
    public function setCriticality($criticality): void
    {
        $_SESSION['criticality_id'] = $criticality;
    }

    /**
     * @param $name
     * @param $tab
     * @param $defaultValue
     *
     * @return string
     */
    public function checkArgument($name, $tab, $defaultValue)
    {
        if (isset($name) && isset($tab)) {
            if (isset($tab[$name])) {
                if ($name == 'num' && $tab[$name] < 0) {
                    $tab[$name] = 0;
                }
                $value = htmlspecialchars($tab[$name], ENT_QUOTES, 'utf-8');
                return CentreonDB::escape($value);
            } else {
                return CentreonDB::escape($defaultValue);
            }
        }
    }// FIXME no return

    /**
     * @param string $name
     *
     * @return array|string|string[]
     */
    public function prepareObjectName($name)
    {
        $name = str_replace("/", "#S#", $name);
        $name = str_replace("\\", "#BS#", $name);
        return $name;
    }
}
