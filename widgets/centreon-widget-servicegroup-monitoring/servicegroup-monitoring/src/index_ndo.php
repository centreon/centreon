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

require_once "../../require.php";
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonDB.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/class/centreonDuration.class.php';
require_once $centreon_path . 'www/class/centreonUtils.class.php';
require_once $centreon_path . 'www/class/centreonACL.class.php';
require_once $centreon_path . 'www/widgets/servicegroup-monitoring/src/class/ServicegroupMonitoring.class.php';

session_start();
if (!isset($_SESSION['centreon']) || !isset($_REQUEST['widgetId']) || !isset($_REQUEST['page'])) {
    exit;
}
$db = new CentreonDB();
if (CentreonSession::checkSession(session_id(), $db) == 0) {
    exit;
}

$prefixRes = $db->query("SELECT db_prefix FROM cfg_ndo2db WHERE activate = '1' LIMIT 1");
if (!$prefixRes->numRows()) {
    exit;
}
$prefixRow = $prefixRes->fetchRow();
$ndoPrefix = $prefixRow['db_prefix'];

require_once $centreon_path ."GPL_LIB/Smarty/libs/Smarty.class.php";
$path = $centreon_path . "www/widgets/servicegroup-monitoring/src/";
$template = new Smarty();
$template = initSmartyTplForPopup($path, $template, "./", $centreon_path);

$centreon = $_SESSION['centreon'];
$widgetId = $_REQUEST['widgetId'];
$page = $_REQUEST['page'];

$dbb = new CentreonDB("ndo");
$widgetObj = new CentreonWidget($centreon, $db);
$sgMonObj = new ServicegroupMonitoring($dbb);
$preferences = $widgetObj->getWidgetPreferences($widgetId);
$pearDB = $db;
$aclObj = new CentreonACL($centreon->user->user_id, $centreon->user->admin);

$res = $db->query("SELECT `key`, `value` FROM `options` WHERE `key` LIKE 'color%'");
$hostStateColors = array(0 => "#19EE11",
                         1 => "#F91E05",
                         2 => "#82CFD8",
                         4 => "#2AD1D4");

$serviceStateColors = array(0 => "#13EB3A",
                            1 => "#F8C706",
                            2 => "#F91D05",
                            3 => "#DCDADA",
                            4 => "#2AD1D4");

while ($row = $res->fetchRow()) {
    if ($row['key'] == "color_up") {
        $hostStateColors[0] = $row['value'];
    } elseif ($row['key'] == "color_down") {
        $hostStateColors[1] = $row['value'];
    } elseif ($row['key'] == "color_unreachable") {
        $hostStateColors[2] = $row['value'];
    } elseif ($row['key'] == "color_pending") {
        $hostStateColors[4] = $row['value'];
    } elseif ($row['key'] == "color_ok") {
        $serviceStateColors[0] = $row['value'];
    } elseif ($row['key'] == "color_warning") {
        $serviceStateColors[1] = $row['value'];
    } elseif ($row['key'] == "color_critical") {
        $serviceStateColors[2] = $row['value'];
    } elseif ($row['key'] == "color_unknown") {
        $serviceStateColors[3] = $row['value'];
    } elseif ($row['key'] == "color_pending") {
        $serviceStateColors[4] = $row['value'];
    }
}

$hostStateLabels = array(0 => "Up",
                         1 => "Down",
                         2 => "Unreachable",
                         4 => "Pending");

$serviceStateLabels = array(0 => "Ok",
                            1 => "Warning",
                            2 => "Critical",
                            3 => "Unknown",
                            4 => "Pending");

$query = "SELECT SQL_CALC_FOUND_ROWS DISTINCT name1 as name ";
$query .= "FROM {$ndoPrefix}servicegroups sg, {$ndoPrefix}objects o ";
$query .= "WHERE sg.servicegroup_object_id = o.object_id ";
if (isset($preferences['sg_name_search']) && $preferences['sg_name_search'] != "") {
    $tab = split(" ", $preferences['sg_name_search']);
    $op = $tab[0];
    if (isset($tab[1])) {
        $search = $tab[1];
    }
    if ($op && isset($search) && $search != "") {
        $query = CentreonUtils::conditionBuilder($query, "name1 ".CentreonUtils::operandToMysqlFormat($op)." '".$dbb->escape($search)."' ");
    }
}
if (!$centreon->user->admin) {
    $query = CentreonUtils::conditionBuilder($query, "name1 IN (".$aclObj->getServiceGroupsString("NAME").")");
}
$orderby = "name ASC";
if (isset($preferences['order_by']) && $preferences['order_by'] != "") {
    $orderby = $preferences['order_by'];
}
$query .= "ORDER BY $orderby";
$query .= " LIMIT ".($page * $preferences['entries']).",".$preferences['entries'];
$res = $dbb->query($query);
$nbRows = $dbb->numberRows();
$data = array();
$detailMode = false;
if (isset($preferences['enable_detailed_mode']) && $preferences['enable_detailed_mode']) {
    $detailMode = true;
}
while ($row = $res->fetchRow()) {
    foreach ($row as $key => $value) {
        $data[$row['name']][$key] = $value;
        $data[$row['name']]['host_state'] = $sgMonObj->getHostStates($row['name'], $detailMode, $centreon->user->admin, $aclObj, $preferences, true, $ndoPrefix);
        $data[$row['name']]['service_state'] = $sgMonObj->getServiceStates($row['name'], $detailMode, $centreon->user->admin, $aclObj, $preferences, true, $ndoPrefix);
    }
}
$template->assign('preferences', $preferences);
$template->assign('hostStateLabels', $hostStateLabels);
$template->assign('hostStateColors', $hostStateColors);
$template->assign('serviceStateLabels', $serviceStateLabels);
$template->assign('serviceStateColors', $serviceStateColors);
$template->assign('data', $data);
$template->display('index.ihtml');
?>
<script type="text/javascript">
	var nbRows = <?php echo $nbRows;?>;
	var currentPage = <?php echo $page;?>;
	var orderby = '<?php echo $orderby;?>';
	var nbCurrentItems = <?php echo count($data);?>;

	$(function () {
		$("#ServicegroupTable").styleTable();
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
		$("#ServicegroupTable").treeTable({
			treeColumn: 0,
			expandable: false
		});
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
