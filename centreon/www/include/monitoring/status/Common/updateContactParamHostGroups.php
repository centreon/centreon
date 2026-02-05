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

if (! isset($_GET['uid']) || ! isset($_GET['hostgroups'])) {
    exit(0);
}

// sanitize parameter
$hostGroup = filter_var($_GET['hostgroups'], FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);

require_once realpath(__DIR__ . '/../../../../../config/centreon.config.php');
require_once _CENTREON_PATH_ . '/www/class/centreonDB.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonSession.class.php';

$pearDB = new CentreonDB();

CentreonSession::start();

$_SESSION['monitoring_default_hostgroups'] = $hostGroup;
