<?php
/**
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
 * Include config file
 */
require_once realpath(dirname(__FILE__) . "/../../../../../config/centreon.config.php");

require_once _CENTREON_PATH_ . '/www/class/centreon.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonACL.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonGraph.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonDB.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonBroker.class.php';


$pearDB = new CentreonDB();

//$mySessionId = isset($_GET['session_id']) ? $_GET['session_id'] : '' ;
session_start();
$mySessionId = session_id();
/**
 * Checks for token
 */
if ((isset($_GET["token"]) || isset($_GET["akey"])) && isset($_GET['username'])) {    
    $token = isset($_GET['token']) ? $_GET['token'] : $_GET['akey'];
    $DBRESULT = $pearDB->query("SELECT * FROM `contact`
    				WHERE `contact_alias` = '".$pearDB->escape($_GET["username"])."'
    				AND `contact_activate` = '1'
    				AND `contact_autologin_key` = '".$token."' LIMIT 1");
    if ($DBRESULT->numRows()) {
        $row = $DBRESULT->fetchRow();
        $res = $pearDB->query("SELECT session_id FROM session WHERE session_id = '".$mySessionId."'");
        if (!$res->numRows()) {
            $pearDB->query("INSERT INTO `session` (`session_id` , `user_id` , `current_page` , `last_reload`, `ip_address`) VALUES ('".$mySessionId."', '".$row["contact_id"]."', '', '".time()."', '".$_SERVER["REMOTE_ADDR"]."')");
        }        
    } else {
        die('Invalid token');
    }

}

$index = isset($_GET['index']) ? $_GET['index'] : 0;

if (isset($_GET["hostname"]) && isset($_GET["service"])) {
    $pearDBO = new CentreonDB("centstorage");
    $DBRESULT = $pearDBO->query("SELECT `id`
                                 FROM index_data
    				 WHERE host_name = '".$pearDB->escape($_GET["hostname"])."'
    				 AND service_description = '".$pearDB->escape($_GET["service"])."'
    				 LIMIT 1");
    if ($DBRESULT->numRows()) {
        $res = $DBRESULT->fetchRow();
        $index = $res["id"];
    } else {
        die('Resource not found');
    }
}

$sql = "SELECT c.contact_id, c.contact_admin 
        FROM session s, contact c
        WHERE s.session_id = '".$mySessionId."'
        AND s.user_id = c.contact_id 
        LIMIT 1";
$res = $pearDB->query($sql);
if (!$res->numRows()) {
    die('Unknown user');
}

$row = $res->fetchRow();
$isAdmin = $row['contact_admin'];
$contactId = $row['contact_id'];

if (!$isAdmin) {
    $acl = new CentreonACL($contactId, $isAdmin);
    $dbstorage = new CentreonDB('centstorage');

    $aclGroups = $acl->getAccessGroupsString();
    $sql = "SELECT host_id, service_id FROM index_data WHERE id = " .$pearDB->escape($index);
    $res = $dbstorage->query($sql);
    if (!$res->numRows()) {
        die('Graph not found');
    }    
    $row = $res->fetchRow();
    unset($res);
    $hostId = $row['host_id'];
    $serviceId = $row['service_id'];
    $sql = "SELECT service_id FROM centreon_acl WHERE host_id = $hostId AND service_id = $serviceId AND group_id IN ($aclGroups)";
    $res = $dbcentstorage->query($sql);
    if (!$res->numRows()) {
        die('Access denied');
    }
}

/**
 * Create XML Request Objects
 */
$obj = new CentreonGraph($mySessionId, $index, 0, 1);

if (isset($obj->session_id) && CentreonSession::checkSession($obj->session_id, $obj->DB)) {
    ;
} else {
    $obj->displayError();
}

require_once _CENTREON_PATH_."www/include/common/common-Func.php";

/**
 * Set arguments from GET
 */
$obj->setRRDOption("start", $obj->checkArgument("start", $_GET, time() - (60*60*48)) );
$obj->setRRDOption("end",   $obj->checkArgument("end", $_GET, time()) );

$obj->GMT->getMyGMTFromSession($obj->session_id, $pearDB);

/**
 * Template Management
 */
if (isset($_GET["template_id"])) {
    $obj->setTemplate($_GET["template_id"]);
} else {
    $obj->setTemplate();
}

$obj->init();
if (isset($_GET["flagperiod"])) {
    $obj->setCommandLineTimeLimit($_GET["flagperiod"]);
}

/**
 * Init Curve list
 */
if (isset($_GET["metric"])) {
    $obj->setMetricList($_GET["metric"]);
}
$obj->initCurveList();

/**
 * Comment time
 */
$obj->setOption("comment_time");

/**
 * Create Legende
 */
$obj->createLegend();

/**
 * Set Colors
 */
/*$colors = array("Min"=>"#19EE11", "Max"=>"#F91E05", "Average"=>"#2AD1D4",
    "Last_Min"=>"#2AD1D4", "Last_5_Min"=>"#13EB3A", "Last_15_Min"=>"#F8C706",
    "Last_Hour"=>"#F91D05", "Up"=>"#19EE11", "Down"=>"#F91E05",
    "Unreach"=>"#2AD1D4", "Ok"=>"#13EB3A", "Warn"=>"#F8C706",
    "Crit"=>"#F91D05", "Unk"=>"#2AD1D4", "In_Use"=>"#13EB3A",
    "Max_Used"=>"#F91D05", "Total_Available"=>"#2AD1D4"
);
foreach($colors as $colorName => $colorValue) {
    $obj->setColor($colorName, $colorValue);
}*/
$obj->setColor('BACK', '#FFFFFF');
$obj->setColor('FRAME', '#FFFFFF');
$obj->setColor('SHADEA', '#FFFFFF');
$obj->setColor('SHADEB', '#FFFFFF');

/**
 * Display Images Binary Data
 */
$obj->displayImageFlow();

/**
 * Closing session
 */
if (isset($_GET['akey'])) {
    $pearDB->query("DELETE FROM session WHERE session_id = '".$pearDB->escape($mySessionId)."'AND user_id = (SELECT contact_id from contact where contact_autologin_key = '".$_GET['akey']."')");
}

