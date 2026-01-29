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

// using bootstrap.php to load the paths and the DB configurations
require_once __DIR__ . '/../../../../../bootstrap.php';
require_once _CENTREON_PATH_ . 'vendor/autoload.php';
require_once _CENTREON_PATH_ . 'www/class/centreonSession.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreon.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonLang.class.php';
require_once _CENTREON_PATH_ . 'www/include/common/common-Func.php';

const ACKNOWLEDGEMENT_ON_SERVICE = 70;
const ACKNOWLEDGEMENT_ON_HOST = 72;
const DOWNTIME_ON_SERVICE = 74;
const DOWNTIME_ON_HOST = 75;

$pearDB = $dependencyInjector['configuration_db'];

session_start();
session_write_close();

$centreon = $_SESSION['centreon'];

$centreonLang = new CentreonLang(_CENTREON_PATH_, $centreon);
$centreonLang->bindLang();

if (
    ! isset($centreon)
    || ! isset($_GET['o'])
    || ! isset($_GET['cmd'])
    || ! isset($_GET['p'])
) {
    exit();
}
if (session_id()) {
    $res = $pearDB->prepare('SELECT * FROM `session` WHERE `session_id` = :sid');
    $res->bindValue(':sid', session_id(), PDO::PARAM_STR);
    $res->execute();
    if (! $session = $res->fetch()) {
        exit();
    }
} else {
    exit;
}
$o = HtmlAnalyzer::sanitizeAndRemoveTags($_GET['o']);
$p = HtmlAnalyzer::sanitizeAndRemoveTags($_GET['p']);
$cmd = HtmlAnalyzer::sanitizeAndRemoveTags($_GET['cmd']);

if (
    (int) $cmd === ACKNOWLEDGEMENT_ON_SERVICE
    || (int) $cmd === ACKNOWLEDGEMENT_ON_HOST
) {
    require_once _CENTREON_PATH_ . 'www/include/monitoring/external_cmd/popup/massive_ack.php';
} elseif (
    (int) $cmd === DOWNTIME_ON_HOST
    || (int) $cmd === DOWNTIME_ON_SERVICE
) {
    require_once _CENTREON_PATH_ . 'www/include/monitoring/external_cmd/popup/massive_downtime.php';
}

exit();
