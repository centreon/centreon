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

require_once realpath(__DIR__ . '/../config/centreon.config.php');
require_once _CENTREON_PATH_ . '/www/class/centreonPurgeEngine.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonDB.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreon-partition/partEngine.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreon-partition/config.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreon-partition/mysqlTable.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreon-partition/options.class.php';

// Create partitioned tables
$centreonDb = new CentreonDB('centreon');
$centstorageDb = new CentreonDB('centstorage', 3);
$partEngine = new PartEngine();

if (! $partEngine->isCompatible($centstorageDb)) {
    echo '[' . date(DATE_RFC822) . '] '
         . "CRITICAL: MySQL server is not compatible with partitionning. MySQL version must be greater or equal to 5.1\n";

    exit(1);
}

echo '[' . date(DATE_RFC822) . "] PARTITIONING STARTED\n";

$tables = [
    'data_bin',
    'logs',
    'log_archive_host',
    'log_archive_service',
];

try {
    foreach ($tables as $table) {
        $config = new Config(
            $centstorageDb,
            _CENTREON_PATH_ . '/config/partition.d/partitioning-' . $table . '.xml',
            $centreonDb
        );
        $mysqlTable = $config->getTable($table);
        $partEngine->updateParts($mysqlTable, $centstorageDb);
    }
} catch (Exception $e) {
    echo '[' . date(DATE_RFC822) . '] ' . $e->getMessage();

    exit(1);
}

echo '[' . date(DATE_RFC822) . "] PARTITIONING COMPLETED\n";

exit(0);
