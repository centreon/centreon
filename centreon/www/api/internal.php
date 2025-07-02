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

require_once __DIR__ . '/../../bootstrap.php';
require_once _CENTREON_PATH_ . 'www/class/centreonSession.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreon.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonDB.class.php';
require_once __DIR__ . '/class/webService.class.php';
require_once __DIR__ . '/interface/di.interface.php';

error_reporting(-1);
ini_set('display_errors', 0);

$pearDB = new CentreonDB();

CentreonSession::start(1);
if (! isset($_SESSION['centreon'])) {
    CentreonWebService::sendResult('Unauthorized', 401);
}

$pearDB = new CentreonDB();

// Define Centreon var alias
if (isset($_SESSION['centreon']) && CentreonSession::checkSession(session_id(), $pearDB)) {
    $oreon = $_SESSION['centreon'];
    $centreon = $_SESSION['centreon'];
}

if (! isset($centreon) || ! is_object($centreon)) {
    CentreonWebService::sendResult('Unauthorized', 401);
}

CentreonWebService::router($dependencyInjector, $centreon->user, true);
