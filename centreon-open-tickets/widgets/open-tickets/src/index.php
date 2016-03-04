<?php
/*
 * Copyright 2016 Centreon (http://www.centreon.com/)
 *
 * Centreon is a full-fledged industry-strength solution that meets 
 * the needs in IT infrastructure and application monitoring for 
 * service performance.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0  
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,*
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
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
require_once $centreon_path . 'www/class/centreonService.class.php';

require_once $centreon_path . 'www/class/centreonMedia.class.php';
require_once $centreon_path . 'www/class/centreonCriticality.class.php';

require_once $centreon_path . "GPL_LIB/Smarty/libs/Smarty.class.php";

require_once $centreon_path . 'www/modules/centreon-open-tickets/class/rule.php';

session_start();
if (!isset($_SESSION['centreon']) || !isset($_REQUEST['widgetId']) || !isset($_REQUEST['page'])) {
    exit;
}

$db = new CentreonDB();
if (CentreonSession::checkSession(session_id(), $db) == 0) {
    exit();
}

// Init Smarty
$template = new Smarty();
$template = initSmartyTplForPopup($centreon_path . "www/widgets/open-tickets/src/templates/", $template, "./", $centreon_path);

/* Init Objects */
$criticality = new CentreonCriticality($db);
$media = new CentreonMedia($db);
$rule = new Centreon_OpenTickets_Rule($db);

$centreon = $_SESSION['centreon'];
$widgetId = $_REQUEST['widgetId'];
$page = $_REQUEST['page'];

$dbb = new CentreonDB("centstorage");
$widgetObj = new CentreonWidget($centreon, $db);
$preferences = $widgetObj->getWidgetPreferences($widgetId);

if (!isset($preferences['rule'])) {
    exit;
}

$macro_tickets = $rule->getMacroNames($preferences['rule'], $widgetId);

// Set Colors Table
$res = $db->query("SELECT `key`, `value` FROM `options` WHERE `key` LIKE 'color%'");
$stateSColors = array(0 => "#13EB3A",
                     1 => "#F8C706",
                     2 => "#F91D05",
                     3 => "#DCDADA",
                     4 => "#2AD1D4");
$stateHColors = array(0 => "#13EB3A",
                     1 => "#F91D05",
                     2 => "#DCDADA",
                     3 => "#2AD1D4");
while ($row = $res->fetchRow()) {
    if ($row['key'] == "color_ok") {
        $stateSColors[0] = $row['value'];
    } elseif ($row['key'] == "color_warning") {
        $stateSColors[1] = $row['value'];
    } elseif ($row['key'] == "color_critical") {
        $stateSColors[2] = $row['value'];
    } elseif ($row['key'] == "color_unknown") {
        $stateSColors[3] = $row['value'];
    } elseif ($row['key'] == "color_pending") {
        $stateSColors[4] = $row['value'];
    } elseif ($row['key'] == "color_up") {
        $stateHColors[4] = $row['value'];
    } elseif ($row['key'] == "color_down") {
        $stateHColors[4] = $row['value'];
    } elseif ($row['key'] == "color_unreachable") {
        $stateHColors[4] = $row['value'];
    }
}

$aStateType = array("1" => "H", "0" => "S");

$stateLabels = array(0 => "Ok",
                     1 => "Warning",
                     2 => "Critical",
                     3 => "Unknown",
                     4 => "Pending");
// Build Query
$query = "SELECT SQL_CALC_FOUND_ROWS h.host_id,
        h.name as hostname,
        s.latency,
        s.execution_time,
        h.state as h_state,
        s.service_id,
        s.description,
        s.state as s_state,
        h.state_type as state_type,
        s.last_hard_state,
        s.output,
        s.scheduled_downtime_depth as s_scheduled_downtime_depth,
        s.acknowledged as s_acknowledged,
        s.notify as s_notify,
        s.active_checks as s_active_checks,
        s.passive_checks as s_passive_checks,
        h.scheduled_downtime_depth as h_scheduled_downtime_depth,
        h.acknowledged as h_acknowledged,
        h.notify as h_notify,
        h.active_checks as h_active_checks,
        h.passive_checks as h_passive_checks,
        s.last_check,
        s.last_state_change,
        s.last_hard_state_change,
        s.check_attempt,
        s.max_check_attempts,
        h.action_url as h_action_url,
        h.notes_url as h_notes_url,
        s.action_url as s_action_url,
        s.notes_url as s_notes_url,
        h.last_hard_state_change as host_last_hard_state_change,
        CAST(cv6.value AS UNSIGNED) as host_ticket_time,
        cv5.value as host_ticket_id,
        CAST(cv4.value AS UNSIGNED) as service_ticket_time,
        cv3.value as service_ticket_id,
        cv2.value AS criticality_id,
        cv.value AS criticality_level,
        h.icon_image
";
$query .= " FROM hosts h ";
$query .= " LEFT JOIN customvariables cv5 ON (h.host_id = cv5.host_id AND cv5.service_id IS NULL AND cv5.name = '" . $macro_tickets['ticket_id'] . "') ";
$query .= " LEFT JOIN customvariables cv6 ON (h.host_id = cv6.host_id AND cv6.service_id IS NULL AND cv6.name = '" . $macro_tickets['ticket_time'] . "') ";
$query .= ", services s ";
$query .= " LEFT JOIN customvariables cv ON (s.service_id = cv.service_id AND s.host_id = cv.host_id AND cv.name = 'CRITICALITY_LEVEL') ";
$query .= " LEFT JOIN customvariables cv2 ON (s.service_id = cv2.service_id AND s.host_id = cv2.host_id AND cv2.name = 'CRITICALITY_ID') ";
$query .= " LEFT JOIN customvariables cv3 ON (s.service_id = cv3.service_id AND s.host_id = cv3.host_id AND cv3.name = '" . $macro_tickets['ticket_id'] . "') ";
$query .= " LEFT JOIN customvariables cv4 ON (s.service_id = cv4.service_id AND s.host_id = cv4.host_id AND cv4.name = '" . $macro_tickets['ticket_time'] . "') ";
if (!$centreon->user->admin) {
    $query .= " , centreon_acl acl ";
}
$query .= " WHERE s.host_id = h.host_id ";

# For Open Tickets
if (!isset($preferences['opened_tickets']) || $preferences['opened_tickets'] == 0) {
    $query .= " AND (NULLIF(cv5.value, '') IS NULL OR CAST(cv6.value AS UNSIGNED) < h.last_hard_state_change) ";
    $query .= " AND (NULLIF(cv3.value, '') IS NULL OR CAST(cv4.value AS UNSIGNED) < s.last_hard_state_change) ";
} else {
    $query .= " AND ((NULLIF(cv5.value, '') IS NOT NULL AND CAST(cv6.value AS UNSIGNED) > h.last_hard_state_change) ";
    $query .= "       OR (NULLIF(cv3.value, '') IS NOT NULL AND CAST(cv4.value AS UNSIGNED) > s.last_hard_state_change)) ";
}

$query .= " AND h.name NOT LIKE '_Module_%' ";
$query .= " AND s.enabled = 1 ";
if (isset($preferences['host_name_search']) && $preferences['host_name_search'] != "") {
    $tab = split(" ", $preferences['host_name_search']);
    $op = $tab[0];
    if (isset($tab[1])) {
        $search = $tab[1];
    }
    if ($op && isset($search) && $search != "") {
        $query = CentreonUtils::conditionBuilder($query, "h.name ".CentreonUtils::operandToMysqlFormat($op)." '".$dbb->escape($search)."' ");
    }
}
if (isset($preferences['service_description_search']) && $preferences['service_description_search'] != "") {
    $tab = split(" ", $preferences['service_description_search']);
    $op = $tab[0];
    if (isset($tab[1])) {
        $search = $tab[1];
    }
    if ($op && isset($search) && $search != "") {
        $query = CentreonUtils::conditionBuilder($query, "s.description ".CentreonUtils::operandToMysqlFormat($op)." '".$dbb->escape($search)."' ");
    }
}
$stateTab = array();
if (isset($preferences['svc_warning']) && $preferences['svc_warning']) {
    $stateTab[] = 1;
}
if (isset($preferences['svc_critical']) && $preferences['svc_critical']) {
    $stateTab[] = 2;
}
if (isset($preferences['svc_unknown']) && $preferences['svc_unknown']) {
    $stateTab[] = 3;
}

if (count($stateTab)) {
    $query = CentreonUtils::conditionBuilder($query, " s.state IN (" . implode(',', $stateTab) . ")");
}

if (isset($preferences['acknowledgement_filter']) && $preferences['acknowledgement_filter']) {
    if ($preferences['acknowledgement_filter'] == "ack") {
        $query = CentreonUtils::conditionBuilder($query, " s.acknowledged = 1");
    } elseif ($preferences['acknowledgement_filter'] == "nack") {
        $query = CentreonUtils::conditionBuilder($query, " s.acknowledged = 0 AND h.acknowledged = 0 AND h.scheduled_downtime_depth = 0 ");
    }
}

if (isset($preferences['downtime_filter']) && $preferences['downtime_filter']) {
    if ($preferences['downtime_filter'] == "downtime") {
        $query = CentreonUtils::conditionBuilder($query, " s.scheduled_downtime_depth > 0 ");
    } elseif ($preferences['downtime_filter'] == "ndowntime") {
        $query = CentreonUtils::conditionBuilder($query, " s.scheduled_downtime_depth = 0 ");
    }
}

if (isset($preferences['state_type_filter']) && $preferences['state_type_filter']) {
    if ($preferences['state_type_filter'] == "hardonly") {
        $query = CentreonUtils::conditionBuilder($query, " s.state_type = 1 ");
    } elseif ($preferences['state_type_filter'] == "softonly") {
        $query = CentreonUtils::conditionBuilder($query, " s.state_type = 0 ");
    }
}

if (isset($preferences['hostgroup']) && $preferences['hostgroup']) {
    $query = CentreonUtils::conditionBuilder($query, 
    " s.host_id IN (
      SELECT host_host_id
      FROM ".$conf_centreon['db'].".hostgroup_relation
      WHERE hostgroup_hg_id = ".$dbb->escape($preferences['hostgroup']).")");
}
if (isset($preferences['servicegroup']) && $preferences['servicegroup']) {
    $query = CentreonUtils::conditionBuilder($query, 
    " s.service_id IN (SELECT service_service_id
      FROM ".$conf_centreon['db'].".servicegroup_relation
      WHERE servicegroup_sg_id = ".$dbb->escape($preferences['servicegroup'])."
      UNION
      SELECT sgr.service_service_id
      FROM ".$conf_centreon['db'].".servicegroup_relation sgr, ".$conf_centreon['db'].".host_service_relation hsr
      WHERE hsr.hostgroup_hg_id = sgr.hostgroup_hg_id
      AND sgr.servicegroup_sg_id = ".$dbb->escape($preferences['servicegroup']).") ");
}
if (isset($preferences["display_severities"]) && $preferences["display_severities"] 
    && isset($preferences['criticality_filter']) && $preferences['criticality_filter'] != "") {
  $tab = split(",", $preferences['criticality_filter']);
  $labels = "";
  foreach ($tab as $p) {
    if ($labels != '') {
      $labels .= ',';
    }
    $labels .= "'".trim($p)."'";
  }
  $query2 = "SELECT sc_id FROM service_categories WHERE sc_name IN (".$labels.")";
  $RES = $db->query($query2);
  $idC = "";
  while ($d1 = $RES->fetchRow()) {
    if ($idC != '') {
      $idC .= ",";
    }
    $idC .= $d1['sc_id'];
  }
  $query .= " AND cv2.`value` IN ($idC) "; 
}
if (!$centreon->user->admin) {
    $pearDB = $db;
    $aclObj = new CentreonACL($centreon->user->user_id, $centreon->user->admin);
    $groupList = $aclObj->getAccessGroupsString();
    $query .= " AND h.host_id = acl.host_id
	AND acl.service_id = s.service_id
	AND acl.group_id IN ($groupList)";
}
if (isset($preferences['output_search']) && $preferences['output_search'] != "") {
    $tab = split(" ", $preferences['output_search']);
    $op = $tab[0];
    if (isset($tab[1])) {
        $search = $tab[1];
    }
    if ($op && isset($search) && $search != "") {
        $query = CentreonUtils::conditionBuilder($query, "s.output ".CentreonUtils::operandToMysqlFormat($op)." '".$dbb->escape($search)."' ");
    }
}
$orderby = "hostname ASC , description ASC";
if (isset($preferences['order_by']) && $preferences['order_by'] != "") {
    $orderby = $preferences['order_by'];
}

$query .= "ORDER BY $orderby";
$query .= " LIMIT ".($page * $preferences['entries']).",".$preferences['entries'];

$res = $dbb->query($query);
$nbRows = $dbb->numberRows();
$data = array();
$outputLength = $preferences['output_length'] ? $preferences['output_length'] : 50;

$hostObj = new CentreonHost($db);
$svcObj = new CentreonService($db);
$gmt = new CentreonGMT($db);
$gmt->getMyGMTFromSession(session_id(), $db);
while ($row = $res->fetchRow()) {
    foreach ($row as $key => $value) {
        if ($key == "last_check") {
            $value = $gmt->getDate("Y-m-d H:i:s", $value);
            //$value = date("Y-m-d H:i:s", $value);
        } elseif ($key == "last_state_change" || $key == "last_hard_state_change") {
            $value = time() - $value;
            $value = CentreonDuration::toString($value);
        } elseif ($key == "check_attempt") {
            $value = $value . "/" . $row['max_check_attempts']. ' ('.$aStateType[$row['state_type']].')';
        } elseif ($key == "s_state") {
            $data[$row['host_id']."_".$row['service_id']]['color'] = $stateSColors[$value];
            $value = $stateLabels[$value];
        } elseif ($key == "h_state") {
            $data[$row['host_id']."_".$row['service_id']]['hcolor'] = $stateHColors[$value];
            $value = $stateLabels[$value];
        } elseif ($key == "output") {
            $value = substr($value, 0, $outputLength);
        } elseif (($key == "h_action_url" || $key == "h_notes_url") && $value) {
            $value = urlencode($hostObj->replaceMacroInString($row['hostname'], $value));
        } elseif (($key == "s_action_url" || $key == "s_notes_url") && $value) {
            $value = $hostObj->replaceMacroInString($row['hostname'], $value);
            $value = urlencode($svcObj->replaceMacroInString($row['service_id'], $value));
        } elseif ($key == "criticality_id" && $value != '') {
            $critData = $criticality->getData($row["criticality_id"], 1);
            $value = "<img src='../../img/media/".$media->getFilename($critData['icon_id'])."' title='".$critData["sc_name"]."' width='16' height='16'>";        
        }
        $data[$row['host_id']."_".$row['service_id']][$key] = $value;
    }

    $data[$row['host_id'].'_'.$row['service_id']]['encoded_description'] = urlencode(
      $data[$row['host_id'].'_'.$row['service_id']]['description']
    );

    $data[$row['host_id'].'_'.$row['service_id']]['encoded_hostname'] = urlencode(
      $data[$row['host_id'].'_'.$row['service_id']]['hostname']
    );
    
    if ($row['host_ticket_time'] > $row['host_last_hard_state_change'] && 
        isset($row['host_ticket_id']) && !is_null($row['host_ticket_id']) && $row['host_ticket_id'] != '') {
        $ticket_id = $row['host_ticket_id'];
        $url = $rule->getUrl($preferences['rule'], $ticket_id, $row, $widgetId);
        if (!is_null($url) && $url != '') {
            $ticket_id = '<a href="' . $url . '" target="_blank">' . $ticket_id . '</a>';
        }
        $data[$row['host_id']."_".$row['service_id']]['ticket_id'] = $ticket_id;
        $data[$row['host_id']."_".$row['service_id']]['ticket_time'] = $gmt->getDate("Y-m-d H:i:s", $row['host_ticket_time']);
    } else if ($row['service_ticket_time'] > $row['last_hard_state_change'] && 
               isset($row['service_ticket_id']) && !is_null($row['service_ticket_id']) && $row['service_ticket_id'] != '') {
        $ticket_id = $row['service_ticket_id'];
        $url = $rule->getUrl($preferences['rule'], $ticket_id, $row, $widgetId);
        if (!is_null($url) && $url != '') {
            $ticket_id = '<a href="' . $url . '" target="_blank">' . $ticket_id . '</a>';
        }
        $data[$row['host_id']."_".$row['service_id']]['ticket_id'] = $ticket_id;
        $data[$row['host_id']."_".$row['service_id']]['ticket_time'] = $gmt->getDate("Y-m-d H:i:s", $row['service_ticket_time']);
    }
}
$template->assign('centreon_web_path', $centreon->optGen['oreon_web_path']);
$template->assign('preferences', $preferences);
$template->assign('data', $data);
$template->display('index.ihtml');
?>
<script type="text/javascript">
var nbRows = <?php echo $nbRows;?>;
var currentPage = <?php echo $page;?>;
var orderby = '<?php echo $orderby;?>';
var nbCurrentItems = <?php echo count($data);?>;

$(function () {
    if (nbRows > itemsPerPage) {
        $("#pagination").pagination(nbRows, {
            items_per_page	: itemsPerPage,
            current_page : pageNumber,
            callback : paginationCallback
	    }).append("<br/>");
    }
    
    $("#nbRows").html(nbCurrentItems+"/"+nbRows);
    
    $(".selection").each(function() {
        var curId = $(this).attr('id');
        if (typeof(clickedCb[curId]) != 'undefined') {
            this.checked = clickedCb[curId];
        }
    });
    
    var tmp = orderby.split(' ');
    var icn = 'n';
    if (tmp[1] == "DESC") {
      icn = 's';
    }
    $("[name="+tmp[0]+"]").append('<span style="position: relative; float: right;" class="ui-icon ui-icon-triangle-1-'+icn+'"></span>');
});

function paginationCallback(page_index, jq)
{
  if (page_index != pageNumber) {
    pageNumber = page_index;
    clickedCb = new Array();
    loadPage();
  }
}
</script>
