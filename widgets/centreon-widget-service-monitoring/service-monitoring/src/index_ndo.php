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
 */

  //ini_set("display_errors", 'On');

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

session_start();
if (!isset($_SESSION['centreon']) || !isset($_REQUEST['widgetId']) || !isset($_REQUEST['page'])) {
    exit;
}

$db = new CentreonDB();
if (CentreonSession::checkSession(session_id(), $db) == 0) {
    exit;
}

/*
 * Init object Class
 */
$objHost = new CentreonHost($db);
$objSvc  = new CentreonService($db);

/*
 * Get NDO Informations
 */
$prefixRes = $db->query("SELECT db_prefix FROM cfg_ndo2db WHERE activate = '1' LIMIT 1");
if (!$prefixRes->numRows()) {
    exit;
}
$prefixRow = $prefixRes->fetchRow();
$ndoPrefix = $prefixRow['db_prefix'];

require_once $centreon_path ."GPL_LIB/Smarty/libs/Smarty.class.php";
$path = $centreon_path . "www/widgets/service-monitoring/src/";
$template = new Smarty();
$template = initSmartyTplForPopup($path, $template, "./", $centreon_path);

$centreon = $_SESSION['centreon'];
$widgetId = $_REQUEST['widgetId'];
$page = $_REQUEST['page'];

$dbb = new CentreonDB("ndo");
$widgetObj = new CentreonWidget($centreon, $db);
$preferences = $widgetObj->getWidgetPreferences($widgetId);


$res = $db->query("SELECT `key`, `value` FROM `options` WHERE `key` LIKE 'color%'");
$stateColors = array(0 => "#13EB3A",
                     1 => "#F8C706",
                     2 => "#F91D05",
                     3 => "#DCDADA",
                     4 => "#2AD1D4");
while ($row = $res->fetchRow()) {
    if ($row['key'] == "color_ok") {
        $stateColors[0] = $row['value'];
    } elseif ($row['key'] == "color_warning") {
        $stateColors[1] = $row['value'];
    } elseif ($row['key'] == "color_critical") {
        $stateColors[2] = $row['value'];
    } elseif ($row['key'] == "color_unknown") {
        $stateColors[3] = $row['value'];
    } elseif ($row['key'] == "color_pending") {
        $stateColors[4] = $row['value'];
    }
}

$stateLabels = array(0 => "Ok",
                     1 => "Warning",
                     2 => "Critical",
                     3 => "Unknown",
                     4 => "Pending");
$query = "SELECT SQL_CALC_FOUND_ROWS h.host_id,
				 h.display_name as hostname,
				 hs.current_state as h_state,
				 s.service_id,
				 s.display_name as description,
				 ss.current_state as s_state,
				 ss.last_hard_state,
				 ss.output,
				 ss.scheduled_downtime_depth as s_scheduled_downtime_depth,
				 ss.problem_has_been_acknowledged as s_acknowledged,
				 ss.notifications_enabled as s_notify,
				 s.active_checks_enabled as s_active_checks,
				 s.passive_checks_enabled as s_passive_checks,
				 hs.scheduled_downtime_depth as h_scheduled_downtime_depth,
				 hs.problem_has_been_acknowledged as h_acknowledged,
				 hs.notifications_enabled as h_notify,
				 h.active_checks_enabled as h_active_checks,
				 h.passive_checks_enabled as h_passive_checks,
				 UNIX_TIMESTAMP(ss.last_check) as last_check,
				 UNIX_TIMESTAMP(ss.last_state_change) as last_state_change,
				 UNIX_TIMESTAMP(ss.last_hard_state_change) as last_hard_state_change,
				 ss.current_check_attempt as check_attempt,
				 ss.max_check_attempts,
				 h.action_url as h_action_url,
				 h.notes_url as h_notes_url,
				 s.action_url as s_action_url,
				 s.notes_url as s_notes_url ";
$query .= " FROM {$ndoPrefix}hosts h, {$ndoPrefix}hoststatus hs, {$ndoPrefix}services s, {$ndoPrefix}servicestatus ss ";
if (!$centreon->user->admin) {
    $query .= " , centreon_acl acl ";
}
$query .= " WHERE s.host_object_id = h.host_object_id ";
$query .= " AND h.host_object_id = hs.host_object_id ";
$query .= " AND ss.service_object_id = s.service_object_id";
$query .= " AND h.display_name NOT LIKE '_Module_%' ";
$query .= " AND s.config_type = 0 ";
if (isset($preferences['host_name_search']) && $preferences['host_name_search'] != "") {
    $tab = split(" ", $preferences['host_name_search']);
    $op = $tab[0];
    if (isset($tab[1])) {
        $search = $tab[1];
    }
    if ($op && isset($search) && $search != "") {
        $query = CentreonUtils::conditionBuilder($query, "h.display_name ".CentreonUtils::operandToMysqlFormat($op)." '".$dbb->escape($search)."' ");
    }
}
if (isset($preferences['service_description_search']) && $preferences['service_description_search'] != "") {
    $tab = split(" ", $preferences['service_description_search']);
    $op = $tab[0];
    if (isset($tab[1])) {
        $search = $tab[1];
    }
    if ($op && isset($search) && $search != "") {
        $query = CentreonUtils::conditionBuilder($query, "s.display_name ".CentreonUtils::operandToMysqlFormat($op)." '".$dbb->escape($search)."' ");
    }
}
$stateTab = array();
if (isset($preferences['svc_ok']) && $preferences['svc_ok']) {
    $stateTab[] = 0;
}
if (isset($preferences['svc_warning']) && $preferences['svc_warning']) {
    $stateTab[] = 1;
}
if (isset($preferences['svc_critical']) && $preferences['svc_critical']) {
    $stateTab[] = 2;
}
if (isset($preferences['svc_unknown']) && $preferences['svc_unknown']) {
    $stateTab[] = 3;
}
if (isset($preferences['svc_pending']) && $preferences['svc_pending']) {
    $stateTab[] = 4;
}

if (count($stateTab)) {
    $query = CentreonUtils::conditionBuilder($query, " ss.current_state IN (" . implode(',', $stateTab) . ")");
}

if (isset($preferences['acknowledgement_filter']) && $preferences['acknowledgement_filter']) {
    if ($preferences['acknowledgement_filter'] == "ack") {
        $query = CentreonUtils::conditionBuilder($query, " ss.problem_has_been_acknowledged = 1");
    } elseif ($preferences['acknowledgement_filter'] == "nack") {
        $query = CentreonUtils::conditionBuilder($query, " ss.problem_has_been_acknowledged = 0 AND hs.problem_has_been_acknowledged = 0 AND hs.scheduled_downtime_depth = 0");
    }
}

if (isset($preferences['downtime_filter']) && $preferences['downtime_filter']) {
    if ($preferences['downtime_filter'] == "downtime") {
        $query = CentreonUtils::conditionBuilder($query, " ss.scheduled_downtime_depth > 0 ");
    } elseif ($preferences['downtime_filter'] == "ndowntime") {
        $query = CentreonUtils::conditionBuilder($query, " ss.scheduled_downtime_depth = 0 ");
    }
}

if (isset($preferences['state_type_filter']) && $preferences['state_type_filter']) {
    if ($preferences['state_type_filter'] == "hardonly") {
        $query = CentreonUtils::conditionBuilder($query, " ss.state_type = 1 ");
    } elseif ($preferences['state_type_filter'] == "softonly") {
        $query = CentreonUtils::conditionBuilder($query, " ss.state_type = 0 ");
    }
}

if (isset($preferences['hostgroup']) && $preferences['hostgroup']) {
    $query = CentreonUtils::conditionBuilder($query, " h.display_name IN
                                                (SELECT host_name
    						 FROM ".$conf_centreon['db'].".hostgroup_relation hgr, ".$conf_centreon['db'].".host h
                                                 WHERE h.host_id = hgr.host_host_id  
    						 AND hgr.hostgroup_hg_id = ".$dbb->escape($preferences['hostgroup']).")");
}
if (isset($preferences['servicegroup']) && $preferences['servicegroup']) {    
    $sgRes = $db->query('SELECT sg_name FROM servicegroup WHERE sg_id = '.$preferences['servicegroup']);
    if ($sgRes->numRows()) {
        $sgRow = $sgRes->fetchRow();
        $sgName = $sgRow['sg_name'];
        $query = CentreonUtils::conditionBuilder($query, " s.service_object_id IN (SELECT subsgm.service_object_id 
                                                                                   FROM {$ndoPrefix}servicegroup_members subsgm, {$ndoPrefix}servicegroups subsg, {$ndoPrefix}objects subo
                                                                                   WHERE subsgm.servicegroup_id = subsg.servicegroup_id
                                                                                   AND subsg.servicegroup_object_id = subo.object_id
                                                                                   AND subo.name1 = '".$dbb->escape($sgName)."') ");
    }
}
if (!$centreon->user->admin) {
    $pearDB = $db;
    $aclObj = new CentreonACL($centreon->user->user_id, $centreon->user->admin);
    $groupList = $aclObj->getAccessGroupsString();
    $query .= " AND h.display_name = acl.host_name
    			AND acl.service_description = s.display_name
    			AND acl.group_id IN ($groupList)";
}
$orderby = "hostname ASC , description ASC";
if (isset($preferences['order_by']) && $preferences['order_by'] != "") {
    $orderby = $preferences['order_by'];
}
$query .= "ORDER BY $orderby ";
$query .= "LIMIT ".($page * $preferences['entries']).",".$preferences['entries'];
$res = $dbb->query($query);
$nbRows = $dbb->numberRows();
$data = array();
$outputLength = $preferences['output_length'] ? $preferences['output_length'] : 50;
while ($row = $res->fetchRow()) {
    $row['host_id'] = $objHost->getHostId($row['hostname']);
    $row['service_id'] = $objSvc->getServiceId($row['description'], $row['hostname']);
    foreach ($row as $key => $value) {
        if ($key == "last_check") {
            $value = date("Y-m-d H:i:s", $value);
        } elseif ($key == "last_state_change" || $key == "last_hard_state_change") {
            $value = time() - $value;
            $value = CentreonDuration::toString($value);
        } elseif ($key == "check_attempt") {
            $value = $value . "/" . $row['max_check_attempts'];
        } elseif ($key == "s_state") {
            $data[$row['host_id']."_".$row['service_id']]['color'] = $stateColors[$value];
            $value = $stateLabels[$value];
        } elseif ($key == "output") {
            $value = substr($value, 0, $outputLength);
        } elseif (($key == "h_action_url" || $key == "h_notes_url") && $value) {
            $value = $objHost->replaceMacroInString($row['hostname'], $value);
        } elseif (($key == "s_action_url" || $key == "s_notes_url") && $value) {
            $value = $objHost->replaceMacroInString($row['hostname'], $value);
            $value = $objSvc->replaceMacroInString($row['service_id'], $value);
        }
        $data[$row['host_id']."_".$row['service_id']][$key] = $value;
    }
}
$template->assign('centreon_web_path', trim($centreon->optGen['oreon_web_path'], "/"));
$template->assign('preferences', $preferences);
$template->assign('data', $data);
$template->assign('broker', "ndo");
$template->display('index.ihtml');
?>
<script type="text/javascript">
	var nbRows = <?php echo $nbRows;?>;
	var currentPage = <?php echo $page;?>;
	var orderby = '<?php echo $orderby;?>';
	var nbCurrentItems = <?php echo count($data);?>;

	$(function () {
		$("#HostTable").styleTable();
		if (nbRows > itemsPerPage) {
            $("#pagination").pagination(nbRows, {
                							items_per_page	: itemsPerPage,
                							current_page	: pageNumber,
                							callback		: paginationCallback
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
