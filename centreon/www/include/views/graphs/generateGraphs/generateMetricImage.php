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

/**
 * Include config file
 */
require_once realpath(__DIR__ . '/../../../../../config/centreon.config.php');
require_once "{$centreon_path}/www/class/centreonDB.class.php";
require_once _CENTREON_PATH_ . '/www/class/centreonGraph.class.php';

/**
 * Create XML Request Objects
 */
session_start();
session_write_close();

$sid = session_id();
$pearDB = new CentreonDB();
$pearDBO = new CentreonDB('centstorage');

if (! CentreonSession::checkSession($sid, $pearDB)) {
    CentreonGraph::displayError();
}

if (false === isset($_GET['index']) && false === isset($_GET['svcId'])) {
    CentreonGraph::displayError();
}

if (isset($_GET['index'])) {
    if (false === is_numeric($_GET['index'])) {
        CentreonGraph::displayError();
    }
    $index = $_GET['index'];
} else {
    [$hostId, $svcId] = explode('_', $_GET['svcId']);
    if (false === is_numeric($hostId) || false === is_numeric($svcId)) {
        CentreonGraph::displayError();
    }
    $query = 'SELECT id FROM index_data
        WHERE host_id = ' . $hostId . ' AND service_id = ' . $svcId;
    $res = $pearDBO->query($query);
    if (! $res) {
        CentreonGraph::displayError();
    }
    $row = $res->fetch();
    if (! $row) {
        CentreonGraph::displayError();
    }
    $index = $row['id'];
}

require_once _CENTREON_PATH_ . 'www/include/common/common-Func.php';
$contactId = CentreonSession::getUser($sid, $pearDB);
$obj = new CentreonGraph($contactId, $index, 0, 1);

/**
 * Set One curve
 **/
$obj->onecurve = true;

/**
 * Set metric id
 */
if (isset($_GET['metric'])) {
    $obj->setMetricList($_GET['metric']);
}

/**
 * Set arguments from GET
 */
$obj->setRRDOption('start', $obj->checkArgument('start', $_GET, time() - (60 * 60 * 48)));
$obj->setRRDOption('end', $obj->checkArgument('end', $_GET, time()));

// $obj->GMT->getMyGMTFromSession($obj->session_id, $pearDB);

/**
 * Template Management
 */
if (isset($_GET['template_id'])) {
    $obj->setTemplate($_GET['template_id']);
} else {
    $obj->setTemplate();
}

$obj->init();
if (isset($_GET['flagperiod'])) {
    $obj->setCommandLineTimeLimit($_GET['flagperiod']);
}

$obj->initCurveList();

/**
 * Comment time
 */
$obj->setOption('comment_time');

/**
 * Create Legende
 */
$obj->createLegend();

/**
 * Set Colors
 */
$obj->setColor('BACK', '#FFFFFF');
$obj->setColor('FRAME', '#FFFFFF');
$obj->setColor('SHADEA', '#EFEFEF');
$obj->setColor('SHADEB', '#EFEFEF');
$obj->setColor('ARROW', '#FF0000');

/**
 * Display Images Binary Data
 */
$obj->displayImageFlow();
