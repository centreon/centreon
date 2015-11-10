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




$svcLimit = 100;
if (isset($centreon->optGen['tactical_service_limit'])) {
    $svcLimit = $centreon->optGen['tactical_service_limit'];
}


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
 * Get Status global for Services
 */
if (!$is_admin) {
    $rq2 = 	" SELECT count(DISTINCT CONCAT(h.host_id,';',s.service_id)) AS count, s.state" .
        " FROM services s, hosts h, centreon_acl" .
        " WHERE h.host_id = s.host_id".
        " AND h.name NOT LIKE '_Module_%' ".
        " AND h.host_id = centreon_acl.host_id ".
        " AND s.service_id = centreon_acl.service_id " .
        " AND h.enabled = 1 " .
        " AND s.state_type = 1 " .
        " AND centreon_acl.group_id IN (".$acl_access_group_list.") " .
        " AND s.enabled = 1 GROUP BY s.state ORDER BY s.state";
} else {
    $rq2 = 	" SELECT count(s.state) AS count, s.state".
        " FROM services s, hosts h " .
        " WHERE h.host_id = s.host_id".
        " AND h.name not like '_Module_%' ".
        " AND h.enabled = 1 " .
        " AND s.state_type = 1 " .
        " AND s.enabled = 1 GROUP BY s.state ORDER BY s.state";
}
$resNdo2 = $dbb->query($rq2);
$SvcStat = array(0=>0, 1=>0, 2=>0, 3=>0, 4=>0);

while ($ndo = $resNdo2->fetchRow()) {
    $SvcStat[$ndo["state"]] = $ndo["count"];
}
$resNdo2->free();

/*
 * Get on pb host
 */
if (!$is_admin) {
    $rq2 = 	" SELECT s.state, s.host_id".
        " FROM services s, hosts h, centreon_acl " .
        " WHERE h.host_id = s.host_id".
        " AND h.name NOT LIKE '_Module_%' ".
        " AND h.host_id = centreon_acl.host_id ".
        " AND s.service_id = centreon_acl.service_id " .
        " AND centreon_acl.group_id IN (".$acl_access_group_list.") " .
        " AND s.enabled = 1 " .
        " AND s.state_type = 1 ".
        " AND s.acknowledged = 0" .
        " AND s.state != 0 AND s.state != 4 GROUP BY s.service_id";
} else {
    $rq2 = 	" SELECT s.state, s.host_id".
        " FROM services s, hosts h " .
        " WHERE h.host_id = s.host_id".
        " AND h.name NOT LIKE '_Module_%' ".
        " AND s.enabled = 1 " .
        " AND s.state_type = 1 ".
        " AND s.acknowledged = 0" .
        " AND s.state != 0 AND s.state != 4 GROUP BY s.service_id";
}
$onPbHost = array(0=>0, 1=>0, 2=>0, 3=>0, 4=>0);

$resNdo1 = $dbb->query($rq2);
while($ndo = $resNdo1->fetchRow())	{
    if ($ndo["state"] != 0) {
        for ($i = 0; $i < $pbCount; $i++) {
            if (isset($hostPb[$i]) && ($hostPb[$i] == $ndo["host_id"])) {
                $onPbHost[$ndo["state"]]++;
            }
        }
    }
}
$resNdo1->free();


/*
 * Get Service Acknowledgements and Downtimes OK(0), WARNING(1),  CRITICAL(2), UNKNOWN(3)
 */
if (!$is_admin) {
    $rq1 = 	" SELECT DISTINCT s.state, " .
        " s.service_id, " .
        " s.acknowledged, " .
        " s.scheduled_downtime_depth " .
        " FROM services s, centreon_acl, hosts h" .
        " WHERE h.host_id = s.host_id " .
        " AND (s.acknowledged = 1 OR " .
        " s.scheduled_downtime_depth > 0) " .
        " AND s.enabled = 1 " .
        " AND s.state_type = 1 ".
        " AND s.host_id = centreon_acl.host_id ".
        " AND s.service_id = centreon_acl.service_id " .
        " AND centreon_acl.group_id IN (".$acl_access_group_list.") " .
        " AND h.name NOT LIKE '_Module_%' ";
} else {
    $rq1 = 	" SELECT DISTINCT s.state, " .
        " s.service_id, " .
        " s.acknowledged, " .
        " s.scheduled_downtime_depth " .
        " FROM services s, hosts h" .
        " WHERE h.host_id = s.host_id " .
        " AND (s.acknowledged = 1 OR " .
        " s.scheduled_downtime_depth > 0) " .
        " AND s.enabled = 1 " .
        " AND s.state_type = 1 ".
        " AND h.name NOT LIKE '_Module_%' ";
}
$resNdo1 = $dbb->query($rq1);

$svcAckDt = array(0=>0, 1=>0, 2=>0, 3=>0, 4=>0);
$svcAck = array(0=>0, 1=>0, 2=>0, 3=>0, 4=>0);
$svcDt = array(0=>0, 1=>0, 2=>0, 3=>0, 4=>0);
while ($ndo = $resNdo1->fetchRow()) {
    $svcAckDt[$ndo["state"]]++;
    if ($ndo['acknowledged']) {
        $svcAck[$ndo["state"]]++;
    }
    if ($ndo['scheduled_downtime_depth']) {
        $svcDt[$ndo["state"]]++;
    }
}
$resNdo1->free();


/*
 * Get Services Inactive objects
 */
if (!$is_admin) {
    $rq2 = 	" SELECT count(s.state), s.state" .
        " FROM services s, hosts h, centreon_acl " .
        " WHERE h.host_id = s.host_id".
        " AND h.name NOT LIKE '_Module_%' ".
        " AND s.host_id = centreon_acl.host_id ".
        " AND s.service_id = centreon_acl.service_id " .
        " AND centreon_acl.group_id IN (".$acl_access_group_list.") ".
        " AND s.enabled = 1 ".
        " AND s.state_type = 1 ".
        " AND s.active_checks = '0' GROUP BY s.state ORDER BY s.state";
} else {
    $rq2 = 	" SELECT count(s.state), s.state" .
        " FROM services s, hosts h" .
        " WHERE h.host_id = s.host_id".
        " AND h.name NOT LIKE '_Module_%' ".
        " AND s.enabled = 1 ".
        " AND s.state_type = 1 ".
        " AND s.active_checks = '0' GROUP BY s.state ORDER BY s.state";
}
$resNdo2 = $dbb->query($rq2);

$svcInactive = array(0=>0, 1=>0, 2=>0, 3=>0, 4=>0);
while ($ndo = $resNdo2->fetchRow()) {
    $svcInactive[$ndo["state"]] = $ndo["count(s.state)"];
}
$resNdo2->free();

/*
 * Get Undandled Services
 */
$svcUnhandled = array(0=>0, 1=>0, 2=>0, 3=>0, 4=>0);
for ($i=0; $i<=4; $i++) {
    $svcUnhandled[$i] = $SvcStat[$i] - $svcAckDt[$i] - $svcInactive[$i] - $onPbHost[$i];
}

/*
 * Get problem table
 */
if (!$is_admin) {
    $rq1 = 	" SELECT DISTINCT h.name, s.host_id, s.service_id, s.description, s.notes, s.notes_url, s.action_url, s.state, s.last_check as last_check, s.output, s.last_state_change as last_state_change, h.address, h.icon_image, i.name as instance_name" .
        " FROM services s, hosts h, centreon_acl, instances i " .
        " WHERE h.host_id = s.host_id " .
        " AND h.instance_id = i.instance_id " .
        " AND s.state != 0" .
        " AND s.state != 4" .
        " AND s.acknowledged = 0" .
        " AND s.scheduled_downtime_depth = 0" .
        " AND s.enabled = 1" .
        " AND s.state_type = 1 " .
        " AND h.enabled = 1" .
        " AND h.name NOT LIKE '_Module_%' " .
        " AND s.host_id = centreon_acl.host_id ".
        " AND s.service_id = centreon_acl.service_id " .
        " AND centreon_acl.group_id IN (".$acl_access_group_list.") " .
        " ORDER BY FIELD(s.state,2,1,3), s.last_state_change DESC, h.name LIMIT " . $svcLimit;
} else {
    $rq1 = 	" SELECT DISTINCT h.name, s.host_id, s.service_id, s.description, s.notes, s.notes_url, s.action_url, s.state, s.last_check as last_check, s.output, s.last_state_change as last_state_change, h.address, h.icon_image, i.name as instance_name" .
        " FROM hosts h, instances i, services s" .
        " WHERE h.host_id = s.host_id " .
        " AND h.instance_id = i.instance_id " .
        " AND s.state != 0" .
        " AND s.state != 4" .
        " AND s.acknowledged = 0" .
        " AND s.scheduled_downtime_depth = 0" .
        " AND s.enabled = 1".
        " AND s.state_type = 1".
        " AND h.enabled = 1" .
        " AND h.name NOT LIKE '_Module_%' " .
        " ORDER BY FIELD(s.state,2,1,3), s.last_state_change DESC, h.name LIMIT " . $svcLimit;
}


$j = 0;
$tab_hostname[$j] = "";
$tab_svccriticality[$j] = "";
$availableSvcCriticalities = 0;
$tab_svcname[$j] = "";
$tab_state[$j] = "";
$tab_notes_url[$j] = "";
$tab_notes[$j] = "";
$tab_action_url[$j] = "";
$tab_last[$j] = "";
$tab_duration[$j] = "";
$tab_output[$j] = "";
$tab_ip[$j] = "";
$tab_icone[$j] = "";
$tab_objectid[$j] = "";
$tab_hobjectid[$j] = "";

$tab_macros = array('/\$hostname\$/i',
                    '/\$hostid\$/i',
                    '/\$serviceid$/i',
                    '/\$servicedesc\$/i',
                    '/\$SERVICENOTES\$/i',
                    '/\$SERVICENOTESURL\$/i',
                    '/\$SERVICEACTIONURL\$/i',
                    '/\$servicestate\$/i',
                    '/\$LASTSERVICECHECK\$/i',
                    '/\$serviceoutput\$/i',
                    '/\$LASTSERVICESTATECHANGE\$/i',
                    '/\$hostaddress\$/i',
                    '/\$hosticon\$/i',
                '/\$INSTANCENAME\$/i');
$resNdo1 = $dbb->query($rq1);

while ($ndo = $resNdo1->fetchRow()){
    $is_unhandled = 1;

    for ($i = 0; $i < $pbCount && $is_unhandled; $i++){
        if (isset($hostPb[$i]) && ($hostPb[$i] == $ndo["host_id"]))
            $is_unhandled = 0;
    }

    if ($is_unhandled) {
        $tab_hostname[$j] = $ndo["name"];
        $tab_svcname[$j] = $ndo["description"];

        $tab_state[$j] = $ndo["state"];
        $tab_notes_url[$j] = preg_replace($tab_macros,$ndo,$ndo["notes_url"]);
        $tab_notes_url[$j] = str_replace("\$INSTANCEADDRESS\$",
                                         $instanceObj->getParam($ndo['instance_name'], "ns_ip_address"),
                                         $tab_notes_url[$j]);
        $tab_notes[$j] = preg_replace($tab_macros,$ndo,$ndo["notes"]);
        $tab_action_url[$j] = preg_replace($tab_macros,$ndo,$ndo["action_url"]);
        $tab_action_url[$j] = str_replace("\$INSTANCEADDRESS\$",
                                         $instanceObj->getParam($ndo['instance_name'], "ns_ip_address"),
                                         $tab_action_url[$j]);
        $tab_last[$j] = $centreon->CentreonGMT->getDate(_("Y/m/d G:i"), $ndo["last_check"], $centreon->user->getMyGMT());
        $tab_ip[$j] = $ndo["address"];
        $tab_duration[$j] = " - ";
        if ($ndo["last_state_change"] > 0 && time() > $ndo["last_state_change"]) {
            $tab_duration[$j] = CentreonDuration::toString(time() - $ndo["last_state_change"]);
        }

        $ndo["output"] = str_replace("\n", '\n', $ndo["output"]);
        $outputTmp = explode('\n', $ndo["output"]);
        if (count($outputTmp)) {
            $tab_output[$j] = $outputTmp[0];
        } else {
            $tab_output[$j] = $ndo["output"];
        }



        $tab_icone[$j] = $ndo["icon_image"];
        $tab_objectid[$j] = $ndo['host_id'] . "_" . $ndo['service_id'];
        $tab_hobjectid[$j] = $ndo['host_id'];

        // Check if service has criticality
        $tab_svccriticality[$j] = '';
        $critId = $criticality->getRealtimeServiceCriticalityId($dbb, $ndo['service_id']);
        if ($critId) {
            $infoC = $criticality->getData($critId, true);
            if (isset($infoC)) {
                $availableSvcCriticalities = 1;
        if (file_exists('../../../../../img/media/'.$media->getFilename($infoC["icon_id"]))) {
          $tab_svccriticality[$j] = './img/media/'.$media->getFilename($infoC["icon_id"]);
        } else {
          $tab_svccriticality[$j] = '';
        }
            }
        }

        $j++;
    }
}
$resNdo1->free();
$nb_pb = $j;

$general_opt = getStatusColor($db);
if (is_array($general_opt)) {
    foreach ($general_opt as $key => $val) {
        $template->assign($key, $val);
    }
}


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

/*
 *  Strings for service problems
 */
$template->assign("str_unhandled", sprintf(_("Unhandled Service problems (last %s)"), $svcLimit));
$template->assign("str_no_unhandled", _("No unhandled service problem"));
$template->assign("str_hostname", _("Host Name"));
$template->assign("str_servicename", _("Service Name"));
$template->assign("str_criticality", _("C"));
$template->assign("str_status", _("Status"));
$template->assign("str_lastcheck", _("Last Check"));
$template->assign("str_duration", _("Duration"));
$template->assign("str_output", _("Status Output"));
$template->assign("str_actions", _("Actions"));
$template->assign("str_ip", _("IP Address"));


    /*
 *  Services
 */
$template->assign('svcOk', $SvcStat[0]);
$template->assign('svcOkInactive', $svcInactive[0]);

$template->assign('svcWarning', $SvcStat[1]);
$template->assign('svcWarningAck', $svcAck[1]);
$template->assign('svcWarningInact', $svcInactive[1]);
$template->assign('svcWarningUnhand', $svcUnhandled[1]);
$template->assign('svcWarningOnpbHost', $onPbHost[1]);

$template->assign('svcCritical', $SvcStat[2]);
$template->assign('svcCriticalAck', $svcAck[2]);
$template->assign('svcCriticalInact', $svcInactive[2]);
$template->assign('svcCriticalUnhand', $svcUnhandled[2]);
$template->assign('svcCriticalOnpbHost', $onPbHost[2]);

$template->assign('svcUnknown', $SvcStat[3]);
$template->assign('svcUnknownAck', $svcAck[3]);
$template->assign('svcUnknownInact', $svcInactive[3]);
$template->assign('svcUnknownUnhand', $svcUnhandled[3]);
$template->assign('svcUnknownOnpbHost', $onPbHost[3]);

$template->assign('svcPending', $SvcStat[4]);
$template->assign('svcPendingAck', $svcAck[4]);
$template->assign('svcPendingInact', $svcInactive[4]);
$template->assign('svcPendingUnhand', $svcUnhandled[4]);
$template->assign('svcPendingOnpbHost', $onPbHost[4]);


$template->assign("url_svc_unhand", "main.php?p=20201&o=svc_unhandled&search=");
$template->assign("url_svc_ack",    "main.php?p=20201&o=svcOV&acknowledge=1&search=");
$template->assign("url_ok",         "main.php?p=20201&o=svc_ok&search=");
$template->assign("url_critical",   "main.php?p=20201&o=svc_critical&search=");
$template->assign("url_warning",    "main.php?p=20201&o=svc_warning&search=");
$template->assign("url_unknown",    "main.php?p=20201&o=svc_unknown&search=");
$template->assign("url_svcdetail",  "main.php?p=202&o=svcd&host_name=");
$template->assign("url_svcdetail2", "&service_description=");
$template->assign('centreon_web_path', $centreon->optGen['oreon_web_path']);

$template->display('services_status.ihtml');