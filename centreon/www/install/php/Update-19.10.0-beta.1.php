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

include_once __DIR__ . '/../../class/centreonLog.class.php';
$centreonLog = new CentreonLog();

// update topology of poller wizard to display breadcrumb
$pearDB->query(
    'UPDATE topology
    SET topology_parent = 60901,
    topology_page = 60959,
    topology_group = 1,
    topology_show = "0"
    WHERE topology_url LIKE "/poller-wizard/%"'
);

try {
    $pearDB->query('SET SESSION innodb_strict_mode=OFF');
    // Add trap regexp matching
    if (! $pearDB->isColumnExist('traps', 'traps_mode')) {
        $pearDB->query(
            "ALTER TABLE `traps` ADD COLUMN `traps_mode` enum('0','1') DEFAULT '0' AFTER `traps_oid`"
        );
    }
} catch (PDOException $e) {
    $centreonLog->insertLog(
        2,
        'UPGRADE : 19.10.0-beta.1 Unable to modify regexp matching in the database'
    );
} finally {
    $pearDB->query('SET SESSION innodb_strict_mode=ON');
}
