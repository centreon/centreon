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

require_once '../../require.php';
require_once $centreon_path . 'bootstrap.php';
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/class/centreonUtils.class.php';
require_once $centreon_path . 'www/class/centreonACL.class.php';
require_once $centreon_path . 'www/class/centreonHost.class.php';
require_once $centreon_path . 'www/class/centreonService.class.php';
require_once $centreon_path . 'www/class/centreonExternalCommand.class.php';
require_once $centreon_path . 'www/class/centreonGraph.class.php';

CentreonSession::start(1);

if (! isset($_GET['service'])) {
    exit;
}

[$hostId, $serviceId] = explode('-', $_GET['service']);

$db = $dependencyInjector['realtime_db'];
$query = <<<'SQL'
    SELECT
        1 AS REALTIME,
        `id`
    FROM index_data
    WHERE host_id = :hostId
      AND service_id = :serviceId
    LIMIT 1
    SQL;

$stmt = $db->prepare($query);
$stmt->bindValue(':hostId', $hostId, PDO::PARAM_INT);
$stmt->bindValue(':serviceId', $serviceId, PDO::PARAM_INT);
$stmt->execute();

if ($stmt->rowCount()) {
    $row = $stmt->fetch();
    $index = $row['id'];
} else {
    $index = 0;
}

/**
 * Create XML Request Objects
 */
$iIdUser = (int) $_GET['user'];

$obj = new CentreonGraph($iIdUser, $index, 0, 1);

require_once $centreon_path . 'www/include/common/common-Func.php';

/**
 * Set arguments from GET
 */
(int) $graphPeriod = $_GET['tp'] ?? (60 * 60 * 48);
$obj->setRRDOption('start', (time() - $graphPeriod));
$obj->setRRDOption('end', time());

$obj->GMT->getMyGMTFromSession(session_id());

/**
 * Template Management
 */
$obj->setTemplate();
$obj->init();

// Set colors

$obj->setColor('CANVAS', '#FFFFFF');
$obj->setColor('BACK', '#FFFFFF');
$obj->setColor('SHADEA', '#FFFFFF');
$obj->setColor('SHADEB', '#FFFFFF');

if (isset($_GET['width']) && $_GET['width']) {
    $obj->setRRDOption('width', (int) ($_GET['width'] - 110));
}

/**
 * Init Curve list
 */
$obj->initCurveList();

/**
 * Comment time
 */
$obj->setOption('comment_time');

/**
 * Create Legend
 */
$obj->createLegend();

/**
 * Display Images Binary Data
 */
$obj->displayImageFlow();
