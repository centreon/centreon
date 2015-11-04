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
 * SVN : $URL
 * SVN : $Id
 *
 */


require_once "../../require.php";

require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonDB.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/class/centreonDuration.class.php';
require_once $centreon_path . 'www/class/centreonUtils.class.php';
require_once $centreon_path . 'www/class/centreonACL.class.php';
require_once $centreon_path . 'www/class/centreonHost.class.php';

require_once $centreon_path . 'www/class/centreonMedia.class.php';
require_once $centreon_path . 'www/class/centreonCriticality.class.php';
require_once $centreon_path . 'www/class/centreonLang.class.php';
require_once $centreon_path ."GPL_LIB/Smarty/libs/Smarty.class.php";

session_start();

if (!isset($_SESSION['centreon'])) {
    exit();
}

$db	 	= new CentreonDB();
$pearDB = $db;
$dbb 	= new CentreonDB("centstorage");
$centreon = $_SESSION['centreon'];

$criticality = new CentreonCriticality($db);
$media = new CentreonMedia($db);
$instanceObj = new CentreonInstance($db);

$centreonLang = new CentreonLang($centreon_path, $centreon);
$centreonLang->bindLang();

$acl_host_id_list = $centreon->user->access->getHostsString("ID", $dbb);
$acl_access_group_list = $centreon->user->access->getAccessGroupsString();

$is_admin = $centreon->user->access->admin;


$path = $centreon_path . "www/widgets/centreon-widget-host-status-summary/src/";
$template = new Smarty();
$template = initSmartyTplForPopup($path, $template, "./", $centreon_path);


/**
 * Options
 */
$hostLimit = 100;
if (isset($centreon->optGen['tactical_host_limit'])) {
    $hostLimit = $centreon->optGen['tactical_host_limit'];
}



// Get Status Globals for hosts
if ($is_admin) {
    $rq1 = 	" SELECT count(DISTINCT hosts.host_id) AS count, state" .
        " FROM hosts " .
        " WHERE enabled = 1 " .
        " AND state_type = 1 " .
        " AND name NOT LIKE '_Module_%' " .
        " GROUP BY state " .
        " ORDER BY state";
} else {
    $rq1 = 	" SELECT count(DISTINCT hosts.host_id) AS count, state" .
        " FROM hosts, centreon_acl acl " .
        " WHERE enabled = 1 " .
        " AND acl.host_id = hosts.host_id AND acl.service_id IS NULL ".
        " AND acl.group_id IN (".$acl_access_group_list.") " .
        " AND state_type = 1 " .
        " AND name NOT LIKE '_Module_%' " .
        " GROUP BY state " .
        " ORDER BY state";
}
$resNdo1 = $dbb->query($rq1);

$hostStatus = array(0=>0, 1=>0, 2=>0, 3=>0, 4=>0);
while ($ndo = $resNdo1->fetchRow()) {
    $hostStatus[$ndo["state"]] = $ndo["count"];
}
$resNdo1->free();

// Get Hosts Problems
if ($is_admin) {
    $rq1 = 	" SELECT DISTINCT h.host_id, h.name, h.notes, h.notes_url, h.action_url, h.state, h.last_check, h.output, h.icon_image, h.address, h.last_state_change AS lsc, i.name as instance_name " .
        " FROM hosts h, instances i " .
        " WHERE h.enabled = 1 " .
        " AND h.state_type = 1" .
        " AND h.instance_id = i.instance_id" .
        " AND h.state != 0" .
        " AND h.state != 4" .
        " AND h.acknowledged = 0" .
        " AND h.scheduled_downtime_depth = 0" .
        " AND h.name NOT LIKE '_Module_%'" .
        " ORDER by h.state LIMIT ". $hostLimit;
} else {
    $rq1 = 	" SELECT DISTINCT h.host_id, h.name, h.notes, h.notes_url, h.action_url, h.state, h.last_check, h.output, h.icon_image, h.address, h.last_state_change AS lsc, i.name as instance_name " .
        " FROM hosts h, instances i, centreon_acl acl " .
        " WHERE h.enabled = 1" .
        " AND acl.host_id = h.host_id AND acl.service_id IS NULL ".
        " AND acl.group_id IN (".$acl_access_group_list.") " .
        " AND h.state_type = 1" .
        " AND h.instance_id = i.instance_id" .
        " AND h.state != 0" .
        " AND h.state != 4" .
        " AND h.acknowledged = 0" .
        " AND h.scheduled_downtime_depth = 0" .
        " AND h.name NOT LIKE '_Module_%'" .
        " ORDER by h.state LIMIT ". $hostLimit;
}
$resNdoHosts = $dbb->query($rq1);

$tab_macros = array('/\$hostid\$/i',
                    '/\$hostname\$/i',
            '/\$HOSTNOTES\$/i',
                    '/\$HOSTNOTESURL\$/i',
            '/\$HOSTACTIONURL\$/i',
                '/\$hoststate\$/i',
                '/\$LASTHOSTCHECK\$/i',
                '/\$hostoutput\$/i',
                    '/\$hosticon\$/i',
                '/\$hostaddress\$/i',
                '/\$LASTHOSTSTATECHANGE\$/i',
                    '/\$INSTANCENAME\$/i');

$nbhostpb = 0;
$tab_hostprobname[$nbhostpb] = "";
$tab_hostcriticality[$nbhostpb] = "";
$availableHostCriticalities = 0;
$tab_hostprobstate[$nbhostpb] = "";
$tab_hostnotesurl[$nbhostpb] = "";
$tab_hostnotes[$nbhostpb] = "";
$tab_hostactionurl[$nbhostpb] = "";
$tab_hostproblast[$nbhostpb] = "";
$tab_hostprobduration[$nbhostpb] = "";
$tab_hostproboutput[$nbhostpb] = "";
$tab_hostprobip[$nbhostpb] = "";
$tab_hosticone = array();
$tab_hostobjectid = array(0=>0, 1=>0, 2=>0, 3=>0);

while ($ndo = $resNdoHosts->fetchRow()) {
    $tab_hostprobname[$nbhostpb] = $ndo["name"];

    $tab_hostprobstate[$nbhostpb] = $ndo["state"];
    $tab_hostnotesurl[$nbhostpb] = preg_replace($tab_macros,$ndo,$ndo["notes_url"]);
    $tab_hostnotesurl[$nbhostpb] = str_replace("\$INSTANCEADDRESS\$",
                                               $instanceObj->getParam($ndo['instance_name'], "ns_ip_address"),
                                               $tab_hostnotesurl[$nbhostpb]);
    $tab_hostnotes[$nbhostpb] = preg_replace($tab_macros,$ndo,$ndo["notes"]);
    $tab_hostactionurl[$nbhostpb] = preg_replace($tab_macros,$ndo,$ndo["action_url"]);
    $tab_hostactionurl[$nbhostpb] = str_replace("\$INSTANCEADDRESS\$",
                                               $instanceObj->getParam($ndo['instance_name'], "ns_ip_address"),
                                               $tab_hostactionurl[$nbhostpb]);
    $tab_hostproblast[$nbhostpb] = $centreon->CentreonGMT->getDate(_("Y/m/d G:i"), $ndo["last_check"], $centreon->user->getMyGMT());
    $tab_hostprobduration[$nbhostpb] = CentreonDuration::toString(time() - $ndo["lsc"]);

    $ndo["output"] = str_replace("\n", '\n', $ndo["output"]);
    $outputTmp = explode('\n', $ndo["output"]);
    if (count($outputTmp)) {
        $tab_hostproboutput[$nbhostpb] = $outputTmp[0];
    } else {
        $tab_hostproboutput[$nbhostpb] = $ndo["output"];
    }

    $tab_hostprobip[$nbhostpb] = $ndo["address"];
    $tab_hosticone[$nbhostpb] = $ndo["icon_image"];
    $tab_hostobjectid[$nbhostpb] = $ndo['host_id'];

    // Check if host has criticality
    $tab_hostcriticality[$nbhostpb] = '';
    $rqCriticality = "SELECT cvs.value as criticality ".
                     "FROM customvariables cvs ".
                     "WHERE cvs.host_id = '".$ndo['host_id']."' ".
                     "AND cvs.name='CRITICALITY_ID'";

    $resCriticality = $dbb->query($rqCriticality);
    $critId = $criticality->getRealtimeHostCriticalityId($dbb, $ndo['host_id']);
    if ($critId) {
        $infoC = $criticality->getData($critId);
        if (isset($infoC)) {
            $availableHostCriticalities = 1;
    if (file_exists('../../../../../img/media/'.$media->getFilename($infoC["icon_id"]))) {
      $tab_hostcriticality[$nbhostpb] = './img/media/'.$media->getFilename($infoC["icon_id"]);
    } else {
      $tab_hostcriticality[$nbhostpb] = '';
    }
        }
    }

    $nbhostpb++;
}
$resNdoHosts->free();

$hostUnhand = array(0=>$hostStatus[0], 1=>$hostStatus[1], 2=>$hostStatus[2], 3=>$hostStatus[3], 4=>$hostStatus[4]);
/*
 * Get the id's of problem hosts
*/
if ($is_admin) {
    $rq1 = 	" SELECT host_id, state ".
        " FROM hosts h " .
        " WHERE enabled = 1 " .
        " AND state_type = 1 " .
        " AND name NOT LIKE '_Module_%' " .
        " GROUP BY host_id";
} else {
    $rq1 = 	" SELECT h.host_id, h.state ".
        " FROM hosts h, centreon_acl acl " .
        " WHERE h.enabled = 1 " .
        " AND acl.host_id = h.host_id AND acl.service_id IS NULL ".
        " AND acl.group_id IN (".$acl_access_group_list.") " .
        " AND h.state_type = 1 " .
        " AND h.name NOT LIKE '_Module_%' " .
        " GROUP BY host_id";
}    
$resNdo1 = $dbb->query($rq1);
$pbCount = 0;
while ($ndo = $resNdo1->fetchRow()) {
    if ($ndo["state"] != 0) {
        $hostPb[$pbCount] = $ndo["host_id"];
        $pbCount++;
    }
}
$resNdo1->free();

/*
 * Get Host Ack  UP(0), DOWN(1),  UNREACHABLE(2)
 */
if ($is_admin) {
    $rq1 = 	" SELECT name, state, acknowledged, scheduled_downtime_depth " .
        " FROM hosts " .
        " WHERE enabled = 1 " .
        " AND state_type = 1 " .
        " AND name NOT LIKE '_Module_%' " .
        " AND (acknowledged = 1 OR " .
        " scheduled_downtime_depth > 0) ".
        " ORDER by state";
} else {
    $rq1 = 	" SELECT name, state, acknowledged, scheduled_downtime_depth " .
        " FROM hosts, centreon_acl acl" .
        " WHERE enabled = 1 " .
        " AND acl.host_id = hosts.host_id AND acl.service_id IS NULL ".
        " AND acl.group_id IN (".$acl_access_group_list.") " .
        " AND state_type = 1 " .
        " AND name NOT LIKE '_Module_%' " .
        " AND (acknowledged = 1 OR " .
        " scheduled_downtime_depth > 0) ".
        " ORDER by state";
}
$hostAck = array(0=>0, 1=>0, 2=>0, 3=>0);
$hostDt = array(0=>0, 1=>0, 2=>0, 3=>0);
$resNdo1 = $dbb->query($rq1);
while ($ndo = $resNdo1->fetchRow()) {
    if ($ndo['acknowledged']) {
        $hostAck[$ndo["state"]]++;
    }
    if ($ndo['scheduled_downtime_depth']) {
        $hostDt[$ndo['state']]++;
    }
    $hostUnhand[$ndo["state"]]--;
}
$resNdo1->free();

/*
 * Get Host inactive objects
 */
if ($is_admin) {
    $rq1 = 	" SELECT count(DISTINCT hosts.host_id) as count, state" .
        " FROM hosts " .
        " WHERE enabled = 1 ".
        " AND state_type = 1 ".
        " AND active_checks = 0 " .
        " AND name NOT LIKE '_Module_%' " .
        " GROUP BY state " .
        " ORDER BY state";
} else {
    $rq1 = 	" SELECT count(DISTINCT hosts.host_id) as count, state" .
        " FROM hosts, centreon_acl acl " .
        " WHERE enabled = 1 ".
        " AND state_type = 1 ".
        " AND active_checks = 0 " .
        " AND acl.host_id = hosts.host_id AND acl.service_id IS NULL ".
        " AND acl.group_id IN (".$acl_access_group_list.") " .
        " AND name NOT LIKE '_Module_%' " .
        " GROUP BY state " .
        " ORDER BY state";
}
$resNdo1 = $dbb->query($rq1);
$hostInactive = array(0=>0, 1=>0, 2=>0, 3=>0);
while ($ndo = $resNdo1->fetchRow())	{
    $hostInactive[$ndo["state"]] = $ndo["count"];
    $hostUnhand[$ndo["state"]] -= $hostInactive[$ndo["state"]];
}
$resNdo1->free();




$general_opt = getStatusColor($db);
if (is_array($general_opt)) {
    foreach ($general_opt as $key => $val) {
        $template->assign($key, $val);
    }
}

$template->assign('hostUp', $hostStatus[0]);
$template->assign('hostUpInactive', $hostInactive[0]);
$template->assign('hostDown', $hostStatus[1]);
$template->assign('hostDownAck', $hostAck[1]);
$template->assign('hostDownInact', $hostInactive[1]);
$template->assign('hostDownUnhand', $hostUnhand[1]);
$template->assign('hostUnreach', $hostStatus[2]);
$template->assign('hostUnreachAck', $hostAck[2]);
$template->assign('hostUnreachInact', $hostInactive[2]);
$template->assign('hostUnreachUnhand', $hostUnhand[2]);
$template->assign('hostPending', $hostStatus[4]);
$template->assign('hostPendingAck', $hostAck[3]);
$template->assign('hostPendingInact', $hostInactive[3]);
$template->assign('hostPendingUnhand', $hostUnhand[3]);
                        

$template->assign("url_hostPb",     "main.php?p=20202&o=hpb&search=");
$template->assign("url_hostOK",     "main.php?p=20202&o=h_up&search=");
$template->assign("url_host_unhand", "main.php?p=20202&o=h_unhandled&search=");
$template->assign("url_host_pending", "main.php?p=20202&o=h_pending&search=");
$template->assign("url_host_down", "main.php?p=20202&o=h_down&search=");


//$template->assign("url_hostdetail", "./main.php?p=201&o=hd&host_name=");
$template->assign("str_hosts", _("Hosts"));
$template->assign("str_up", _("Up"));
$template->assign("str_down", _("Down"));
$template->assign("str_unreachable", _("Unreachable"));
$template->assign('centreon_web_path', $centreon->optGen['oreon_web_path']);
/*
 *  Strings for the service part
 */
$template->assign("str_services", _("Services"));
$template->assign("str_ok", _("OK"));
$template->assign("str_warning", _("Warning"));
$template->assign("str_critical", _("Critical"));
$template->assign("str_unknown", _("Unknown"));
$template->assign("str_pbhost", _("On Problem Host"));
$template->assign("str_unhandledpb", _("Unhandled"));

/*
 *  Common Strings for both the host and service parts
 */
$template->assign("str_pending", _("Pending"));
$template->assign("str_disabled", _("Disabled"));
$template->assign("str_acknowledged", _("Acknowledged"));


$template->display('index.ihtml');