<?php
/*
 * Copyright 2015-2019 Centreon (http://www.centreon.com/)
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

require_once __DIR__ . '/../../../centreon-open-tickets.conf.php';
require_once $centreon_path . 'www/modules/centreon-open-tickets/class/centreonDBManager.class.php';
require_once $centreon_path . 'www/modules/centreon-open-tickets/class/rule.php';
require_once $centreon_path . 'www/modules/centreon-open-tickets/providers/register.php';
require_once $centreon_path . "www/class/centreonXMLBGRequest.class.php";
$centreon_open_tickets_path = $centreon_path . "www/modules/centreon-open-tickets/";
require_once $centreon_open_tickets_path . 'providers/Abstract/AbstractProvider.class.php';

session_start();
$centreon_bg = new CentreonXMLBGRequest($dependencyInjector, session_id(), 1, 1, 0, 1);
$db = $dependencyInjector['configuration_db'];
$rule = new Centreon_OpenTickets_Rule($db);

if (isset($_SESSION['centreon'])) {
    $centreon = $_SESSION['centreon'];
} else {
    exit;
}

require_once $centreon_path . 'www/include/common/common-Func.php';

$resultat = array("code" => 0, "msg" => "");
$actions = array(
    "get-form-config" => __DIR__ . "/actions/getFormConfig.php",
    "save-form-config" => __DIR__ . "/actions/saveFormConfig.php",
    "validate-format-popup" => __DIR__ . "/actions/validateFormatPopup.php",
    "submit-ticket" => __DIR__ . "/actions/submitTicket.php",
    "close-ticket" => __DIR__ . "/actions/closeTicket.php",
    "service-ack" => __DIR__ . "/actions/serviceAck.php",
    "upload-file" => __DIR__ . "/actions/uploadFile.php",
    "remove-file" => __DIR__ . "/actions/removeFile.php"
);
if (!isset($_POST['data']) && !isset($_REQUEST['action'])) {
    $resultat = array("code" => 1, "msg" => "POST 'data' needed.");
} else {
    $get_information = isset($_POST['data']) ? json_decode($_POST['data'], true): null;
    $action = !is_null($get_information) && isset($get_information['action']) ?
        $get_information['action'] : (isset($_REQUEST['action']) ? $_REQUEST['action'] : 'none');
    if (!isset($actions[$action])) {
        $resultat = array("code" => 1, "msg" => "Action not good.");
    } else {
        include($actions[$action]);
    }
}

header("Content-type: text/plain");
echo json_encode($resultat);
