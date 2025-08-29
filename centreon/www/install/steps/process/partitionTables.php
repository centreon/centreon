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

session_start();
require_once __DIR__ . '/../../../../bootstrap.php';
require_once '../functions.php';
require_once '../../../../config/centreon.config.php';
require_once '../../../class/centreonDB.class.php';
require_once '../../../class/centreon-partition/partEngine.class.php';
require_once '../../../class/centreon-partition/config.class.php';
require_once '../../../class/centreon-partition/mysqlTable.class.php';
require_once '../../../class/centreon-partition/options.class.php';

$return = ['id' => 'dbpartitioning', 'result' => 1, 'msg' => ''];

// Create partitioned tables
$database = new CentreonDB('centstorage');
$centreonDb = new CentreonDB('centreon');
$partEngine = new PartEngine();

if (! $partEngine->isCompatible($database)) {
    $return['msg'] = '[' . date(DATE_RFC822) . '] '
        . "CRITICAL: MySQL server is not compatible with partitionning. MySQL version must be greater or equal to 5.1\n";
    echo json_encode($return);

    exit;
}

$tables = ['data_bin', 'logs', 'log_archive_host', 'log_archive_service'];

try {
    foreach ($tables as $table) {
        $config = new Config(
            $database,
            _CENTREON_PATH_ . '/config/partition.d/partitioning-' . $table . '.xml',
            $centreonDb
        );
        $mysqlTable = $config->getTable($table);

        // past partitions do not need to be created
        // it optimizes the time for partition process
        $partEngine->createParts($mysqlTable, $database, false);
    }
} catch (Exception $e) {
    $return['msg'] = preg_replace('/\n/', '', $e->getMessage());
    echo json_encode($return);

    exit;
}

$return['result'] = 0;
echo json_encode($return);

exit;
