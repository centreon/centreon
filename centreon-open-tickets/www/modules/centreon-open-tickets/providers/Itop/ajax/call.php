<?php
/*
* Copyright 2019 Centreon (http://www.centreon.com/)
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
require_once dirname(__FILE__) . '/../../../centreon-open-tickets.conf.php';
require_once $centreon_path . 'www/modules/centreon-open-tickets/providers/register.php';
require_once $centreon_path . 'www/modules/centreon-open-tickets/class/rule.php';
require_once $centreon_path . 'www/modules/centreon-open-tickets/class/centreonDBManager.class.php';
$centreon_open_tickets_path = $centreon_path . "www/modules/centreon-open-tickets/";
require_once $centreon_open_tickets_path . 'providers/Abstract/AbstractProvider.class.php';

session_start();
$db = new centreonDBManager();
$rule = new Centreon_OpenTickets_Rule($db);

if (isset($_SESSION['centreon'])) {
    $centreon = $_SESSION['centreon'];
} else {
    exit;
}

define('SMARTY_DIR', "$centreon_path/vendor/smarty/smarty/libs/");
require_once SMARTY_DIR . "Smarty.class.php";
require_once $centreon_path . 'www/include/common/common-Func.php';

// check if there is data in POST
if (!isset($_POST['data'])) {
    $result = array("code" => 1, "msg" => "POST 'data' is required.");
} else {
    $getInformation = isset($_POST['data']) ? json_decode($_POST['data'], true): null;
    $result = array('code' => 0, 'msg' => 'ok');

    // check if there is the provider id in the data
    if (is_null($getInformation['provider_id'])) {
        $result['code'] = 1;
        $result['msg'] = 'Please set the provider_id';
        return ;
    }

    // check if there is a provider method that we have to call
    if (is_null($getInformation['methods'])) {
        $result['code'] = 1;
        $result['msg'] = 'Please use a provider function';
        return ;
    }

    foreach ($register_providers as $name => $id) {
        if ($id == $getInformation['provider_id']) {
            $providerName = $name;
            break;
        }
    }

    // check if provider exists
    if (is_null($providerName) || !file_exists($centreon_open_tickets_path . 'providers/' . $providerName . '/' .
        $providerName . 'Provider.class.php')) {
            $result['code'] = 1;
            $result['msg'] = 'Please set a provider, or check that ' . $centreon_open_tickets_path .
                'providers/' . $providerName . '/' . $providerName . 'Provider.class.php exists';
            return ;
    }

    // initate provider
    require_once $centreon_open_tickets_path . 'providers/' . $providerName . '/' . $providerName .
        'Provider.class.php';
    $className = $providerName . 'Provider';
    $centreonProvider = new $className($rule, $centreon_path, $centreon_open_tickets_path, $getInformation['rule_id'],
        null, $getInformation['provider_id']);

    // check if methods exist
    foreach ($getInformation['methods'] as $method) {
        if (!method_exists($centreonProvider, $method)) {
            $result['code'] = 1;
            $result['msg'] = 'The provider method does not exist';
            return ;
        }
    }

    try {
        $data = array();
        if (array_key_exists('provider_data', $getInformation)) {
            $data = $getInformation['provider_data'];
        }
        foreach ($getInformation['methods'] as $method) {
            $result[$method] = $centreonProvider->$method($data);
        }
    } catch (\Exception $e) {
        $result['code'] = 1;
        $result['msg'] = $e->getMessage();
    }
}

header("Content-type: text/plain");
echo json_encode($result);
