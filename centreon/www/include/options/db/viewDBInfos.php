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

if (! isset($centreon)) {
    exit();
}

require_once './class/centreonDB.class.php';
require_once './class/centreon-partition/partEngine.class.php';
require_once './class/centreon-partition/config.class.php';
require_once './class/centreon-partition/mysqlTable.class.php';
require_once './class/centreon-partition/options.class.php';

// Get Properties
$dataCentreon = $pearDB->getProperties();
$dataCentstorage = $pearDBO->getProperties();

// Get partitioning informations
$partEngine = new PartEngine();

$tables = ['data_bin', 'logs', 'log_archive_host', 'log_archive_service'];

$partitioningInfos = [];
foreach ($tables as $table) {
    $mysqlTable = new MysqlTable($pearDBO, $table, $conf_centreon['dbcstg']);
    $partitioningInfos[$table] = $partEngine->listParts($mysqlTable, $pearDBO, false);
}

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate('./include/options/db/');

$tpl->assign('conf_centreon', $conf_centreon);
$tpl->assign('dataCentreon', $dataCentreon);
$tpl->assign('dataCentstorage', $dataCentstorage);
$tpl->assign('partitioning', $partitioningInfos);

$tpl->display('viewDBInfos.ihtml');
