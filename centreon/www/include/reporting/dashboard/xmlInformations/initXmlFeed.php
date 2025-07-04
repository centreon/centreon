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

require_once realpath(__DIR__ . '/../../../../../config/centreon.config.php');

require_once _CENTREON_PATH_ . 'www/class/centreon.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonUser.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonXML.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonDB.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonDuration.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonACL.class.php';
require_once _CENTREON_PATH_ . 'www/include/reporting/dashboard/DB-Func.php';
require_once _CENTREON_PATH_ . 'www/include/reporting/dashboard/common-Func.php';
require_once _CENTREON_PATH_ . 'www/include/reporting/dashboard/xmlInformations/common-Func.php';

session_start();

if (isset($_SESSION['centreon'])) {
    $centreon = $_SESSION['centreon'];
} else {
    exit();
}

$buffer = new CentreonXML();
$buffer->startElement('data');

$pearDB = new CentreonDB();
$pearDBO = new CentreonDB('centstorage');

$sid = session_id();

$DBRESULT = $pearDB->query("SELECT * FROM session WHERE session_id = '" . $pearDB->escape($sid) . "'");
if (! $DBRESULT->rowCount()) {
    exit();
}

// Definition of status
$state = [];
$statesTab = [];
if ($stateType == 'host') {
    $state['UP'] = _('UP');
    $state['DOWN'] = _('DOWN');
    $state['UNREACHABLE'] = _('UNREACHABLE');
    $state['UNDETERMINED'] = _('UNDETERMINED');
    $statesTab[] = 'UP';
    $statesTab[] = 'DOWN';
    $statesTab[] = 'UNREACHABLE';
} elseif ($stateType == 'service') {
    $state['OK'] = _('OK');
    $state['WARNING'] = _('WARNING');
    $state['CRITICAL'] = _('CRITICAL');
    $state['UNKNOWN'] = _('UNKNOWN');
    $state['UNDETERMINED'] = _('UNDETERMINED');
    $statesTab[] = 'OK';
    $statesTab[] = 'WARNING';
    $statesTab[] = 'CRITICAL';
    $statesTab[] = 'UNKNOWN';
}
