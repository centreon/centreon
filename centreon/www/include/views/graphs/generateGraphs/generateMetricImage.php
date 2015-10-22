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

require_once _CENTREON_PATH_."/www/class/centreonGraph.class.php";
/**
 * Create XML Request Objects
 */
session_start();
$sid = session_id();
$obj = new CentreonGraph($sid, $_GET["index"], 0, 1);


if (isset($obj->session_id) && CentreonSession::checkSession($obj->session_id, $obj->DB)) {
	;
} else {
	$obj->displayError();
}

require_once _CENTREON_PATH_."www/include/common/common-Func.php";

/**
 * Set One curve
 **/
$obj->onecurve = true;

/**
 * Set metric id
 */
if (isset($_GET["metric"])) {
	$obj->setMetricList($_GET["metric"]);
}

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
