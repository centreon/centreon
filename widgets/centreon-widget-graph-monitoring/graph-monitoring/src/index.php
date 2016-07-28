<?php
//
//require_once "../../require.php";
//require_once $centreon_path . 'www/class/centreon.class.php';
//require_once $centreon_path . 'www/class/centreonSession.class.php';
//require_once $centreon_path . 'www/class/centreonDB.class.php';
//require_once $centreon_path . 'www/class/centreonWidget.class.php';
//require_once $centreon_path . 'www/class/centreonDuration.class.php';
//require_once $centreon_path . 'www/class/centreonUtils.class.php';
//require_once $centreon_path . 'www/class/centreonACL.class.php';
//require_once $centreon_path . 'www/class/centreonHost.class.php';
//require_once $centreon_path . 'www/class/centreonService.class.php';
//
//require_once $centreon_path . 'www/class/centreonMedia.class.php';
//require_once $centreon_path . 'www/class/centreonCriticality.class.php';
//
//require_once $centreon_path ."GPL_LIB/Smarty/libs/Smarty.class.php";
//
//session_start();
//if (!isset($_SESSION['centreon']) || !isset($_REQUEST['widgetId']) || !isset($_REQUEST['page'])) {
//    exit;
//}
//
//$db = new CentreonDB();
//if (CentreonSession::checkSession(session_id(), $db) == 0) {
//    exit();
//}
//
//// Init Smarty
//$template = new Smarty();
//$template = initSmartyTplForPopup($centreon_path . "www/widgets/graph-monitoring/src/", $template, "./", $centreon_path);
//
///* Init Objects */
//$criticality = new CentreonCriticality($db);
//$media = new CentreonMedia($db);
//
//$centreon = $_SESSION['centreon'];
//$widgetId = $_REQUEST['widgetId'];
//$page = $_REQUEST['page'];
//
//$dbb = new CentreonDB("centstorage");
//$widgetObj = new CentreonWidget($centreon, $db);
//$preferences = $widgetObj->getWidgetPreferences($widgetId);
//
///*
//* Prepare URL
//*/
//
//if (isset($preferences['service']) && $preferences['service']) {
//    $tab = split("-", $preferences['service']);
//    $res = $db2->query("SELECT host_name, service_description
//                            FROM index_data
//                            WHERE host_id = ".$db->escape($tab[0])."
//                            AND service_id = ".$db->escape($tab[1])."
//                           LIMIT 1");
//    if ($res->numRows()) {
//        $row = $res->fetchRow();
//        $host_name = $row["host_name"];
//        $service_description = $row["service_description"];
//    }
//}
//
///*
// * Check ACL
// */
//$acl = 1;
//if (isset($tab[0]) && isset($tab[1]) && $centreon->user->admin == 0) {
//    $query = "SELECT host_id
//            FROM centreon_acl
//            WHERE host_id = ".$dbAcl->escape($tab[0])."
//            AND service_id = ".$dbAcl->escape($tab[1])."
//            AND group_id IN (".$grouplistStr.")";
//    $res = $dbAcl->query($query);
//    if (!$res->numRows()) {
//        $acl = 0;
//    }
//}
//
//$servicePreferences = "";
//if ($acl == 1) {
//    if (isset($preferences['service']) && $preferences['service']) {
//        $servicePreferences .= "<div style='overflow:hidden;'><a href='#' id='linkGraph' target='_parent'><img id='graph'/></a></div>";
//    } else {
//        $servicePreferences .= "<div class='update' style='text-align:center;width:350px;'>"._("Please select a resource first")."</div>";
//    }
//} else {
//    $servicePreferences .= "<div class='update' style='text-align:center;width:350px;'>"._("You are not allowed to reach this graph")."</div>";
//}
//
//$autoRefresh = $preferences['refresh_interval'];
//$template->assign('widgetId', $widgetId);
//$template->assign('autoRefresh', $autoRefresh);
//$template->assign('preferences', $preferences);
//$template->assign('servicePreferences', $servicePreferences);