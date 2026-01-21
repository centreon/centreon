<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

require_once realpath(__DIR__ . '/../../../../bootstrap.php');

require_once _CENTREON_PATH_ . 'www/class/centreonSession.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreon.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonDB.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonGMT.class.php';

session_start();
session_write_close();

$centreon = $_SESSION['centreon'];

global $centreon, $pearDB;

// Connect to DB
$pearDB = $dependencyInjector['configuration_db'];

session_start();
session_write_close();

if (! CentreonSession::checkSession(session_id(), $pearDB)) {
    exit();
}
$centreon = $_SESSION['centreon'];

// GMT management
$centreonGMT = new CentreonGMT($pearDB);
$sid = session_id();
$centreonGMT->getMyGMTFromSession($sid);

require_once _CENTREON_PATH_ . 'www/include/common/common-Func.php';
require_once _CENTREON_PATH_ . 'www/include/monitoring/common-Func.php';
include_once _CENTREON_PATH_ . 'www/include/monitoring/external_cmd/functionsPopup.php';

const ACKNOWLEDGEMENT_ON_SERVICE = 70;
const ACKNOWLEDGEMENT_ON_HOST = 72;
const DOWNTIME_ON_SERVICE = 74;
const DOWNTIME_ON_HOST = 75;

if (
    isset($_POST['resources'], $sid, $_POST['cmd'])
) {
    $is_admin = isUserAdmin($sid);
    $resources = json_decode($_POST['resources'], true);
    foreach ($resources as $resource) {
        switch ((int) $_POST['cmd']) {
            case ACKNOWLEDGEMENT_ON_SERVICE:
                massiveServiceAck($resource);
                break;
            case ACKNOWLEDGEMENT_ON_HOST:
                massiveHostAck($resource);
                break;
            case DOWNTIME_ON_SERVICE:
                massiveServiceDowntime($resource);
                break;
            case DOWNTIME_ON_HOST:
                massiveHostDowntime($resource);
                break;
        }
    }
}
