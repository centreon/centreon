<?php
/*
 * CENTREON
 *
 * Source Copyright 2005-2015 CENTREON
 *
 * Unauthorized reproduction, copy and distribution
 * are not allowed.
 *
 * For more information : contact@centreon.com
 *
*/

require_once '../../../centreon-open-tickets.conf.php';
require_once $centreon_path . 'www/modules/centreon-open-tickets/class/centreonDBManager.class.php';
require_once $centreon_path . 'www/modules/centreon-open-tickets/providers/register.php';
require_once $centreon_path . "www/class/centreonXMLBGRequest.class.php";
$centreon_open_tickets_path = $centreon_path . "www/modules/centreon-open-tickets/";

session_start();
$centreon_bg = new CentreonXMLBGRequest(session_id(), 1, 1, 0, 1);
$db = new centreonDBManager();

if (isset($_SESSION['centreon'])) {
    $centreon = $_SESSION['centreon'];
} else {
    exit;
}

define('SMARTY_DIR', "$centreon_path/GPL_LIB/Smarty/libs/");
require_once SMARTY_DIR . "Smarty.class.php";
require_once $centreon_path . 'www/include/common/common-Func.php';

$resultat = array("code" => 0, "msg" => "");
$actions = array("get-form-config" => "actions/getFormConfig.php",
                 "save-form-config" => "actions/saveFormConfig.php");
if (!isset($_POST['data'])) {
    $resultat = array("code" => 1, "msg" => "POST 'data' needed.");
} else {
    $get_information = json_decode($_POST['data'], true);
    if (!isset($get_information['action']) ||
        !isset($actions[$get_information['action']])) {
        $resultat = array("code" => 1, "msg" => "Action not good.");
    } else {
        include($actions[$get_information['action']]);
    }
}

header("Content-type: text/plain");
echo json_encode($resultat);

?>
