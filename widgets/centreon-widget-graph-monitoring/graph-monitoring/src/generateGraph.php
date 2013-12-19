<?php
/**
 * Copyright 2005-2011 MERETHIS
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
 * SVN : $URL: http://svn.centreon.com/branches/centreon-2.3.x/www/include/views/graphs/generateGraphs/generateImage.php $
 * SVN : $Id: generateImage.php 12494 2011-09-15 07:28:16Z shotamchay $
 *
 */

/**
 * Include config file
 */
include "../../require.php";

require_once $centreon_path.'/www/class/centreonGraph.class.php';
require_once $centreon_path.'/www/class/centreonDB.class.php';

session_start();

if (!isset($_GET['service']) || !isset($_GET['session_id'])) {
    exit;
}

list($hostId, $serviceId) = explode('-', $_GET['service']);

$db = new CentreonDB("centstorage");
$res = $db->query("SELECT `id`
				   FROM index_data
    			   WHERE host_id = ".$db->escape($hostId)."
    			   AND service_id = ".$db->escape($serviceId)."
    			   LIMIT 1");
if ($res->numRows()) {
    $row = $res->fetchRow();
    $index = $row["id"];
} else {
    $index = 0;
}


/**
 * Create XML Request Objects
 */
$obj = new CentreonGraph($_GET["session_id"], $index, 0, 1);

if (trim(session_id()) != trim($_GET['session_id'])) {
    $obj->displayError();
}

require_once $centreon_path."www/include/common/common-Func.php";

/**
 * Set arguments from GET
 */
$graphPeriod = isset($_GET['tp']) ? $_GET['tp'] : (60*60*48);
$obj->setRRDOption("start", (time() - $graphPeriod));
$obj->setRRDOption("end", time());

$obj->GMT->getMyGMTFromSession($obj->session_id, $db);

/**
 * Template Management
 */
$obj->setTemplate();
$obj->init();

/*
 * Set colors 
 */
$obj->setColor("CANVAS","#FFFFFF");
$obj->setColor("BACK","#FFFFFF");
$obj->setColor("SHADEA","#FFFFFF");
$obj->setColor("SHADEB","#FFFFFF");

if (isset($_GET['width']) && $_GET['width']) {
   $obj->setRRDOption("width", ($_GET['width'] - 110));
   //$obj->setRRDOption("width", 400);
}

/**
 * Init Curve list
 */
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
 * Display Images Binary Data
 */
$obj->displayImageFlow();
?>