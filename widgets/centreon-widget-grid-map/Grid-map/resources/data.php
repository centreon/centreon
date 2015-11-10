<?php
/**
 * Copyright 2005-2015 CENTREON
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
 * As a special exception, the copyright holders of this program give MERETHIS
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of MERETHIS choice, provided that
 * MERETHIS also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

ini_set("log_errors", 1);
ini_set("error_log", "/tmp/php-error.log");

//require_once "../../require.php";
require_once "/usr/share/centreon/www/widgets/require.php";
require_once "./DB-Func.php";

require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonDB.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/class/centreonDuration.class.php';
require_once $centreon_path . 'www/class/centreonUtils.class.php';
require_once $centreon_path . 'www/class/centreonACL.class.php';
require_once $centreon_path . 'www/class/centreonHost.class.php';

 // Load specific Smarty class //
require_once $centreon_path ."GPL_LIB/Smarty/libs/Smarty.class.php";

error_log("Après require");

// check if session is alive //
session_start();
if (!isset($_SESSION['centreon']) || !isset($_REQUEST['widgetId'])) {
    exit;
}


$db_centreon = new CentreonDB("centreon");
if (CentreonSession::checkSession(session_id(), $db_centreon) == 0) {
    exit;
}

error_log("Debut widget claire");

// Configure new smarty object
$path = $centreon_path . "www/widgets/Top10-modified/src/";
$template = new Smarty();
$template = initSmartyTplForPopup($path, $template, "./", $centreon_path);

// Get widgets info & parameters
$centreon = $_SESSION['centreon'];
$widgetId = $_REQUEST['widgetId'];

$widgetObj = new CentreonWidget($centreon, $db_centreon);
$preferences = $widgetObj->getWidgetPreferences($widgetId);

// Beginning of the specific widget code

if (isset($preferences['ba_id']) && $preferences['ba_id']!='') {
    $baID = $preferences['ba_id'];
    $reportingPeriod = $preferences['reporting_period'];
}else{
   $baID = 0;
    $reportingPeriod= 0;
}


// Get the right date regarding the parameter

$reportingPeriodStart = 0;
$reportingPeriodEnd = 0;
$periodName = "defaultName";
$orderBy = 'start_time';

$data = array();
$db = new CentreonDB("centstorage");

$query = "SELECT host_name, service_description, service_id, host_id, cpu.current_value as current_value 
            FROM index_data, metrics cpu 
            WHERE service_description LIKE '%cpu%' AND cpu.index_id = id AND cpu.metric_name LIKE '%cpu%' ORDER BY current_value DESC LIMIT 10";

error_log($query);
$title ="Default Title";

$numLine = 1;

$res = $db->query($query);
while ($row = $res->fetchRow()) {
$row['numLin'] = $numLine;  
 $data[] = $row;
 $numLine++;
}
error_log(json_encode($data));
$template->assign('title', $title);
$template->assign('data', $data);
$template->display('table.ihtml');
?>
