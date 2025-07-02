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

try {
    // Alter existing tables to conform with strict mode.
    $pearDBO->query(
        'ALTER TABLE `log_action_modification` MODIFY COLUMN `field_value` text NOT NULL'
    );
    // Add the audit log retention column for the retention options menu
    if (! $pearDBO->isColumnExist('config', 'audit_log_retention')) {
        $pearDBO->query(
            'ALTER TABLE `config` ADD COLUMN audit_log_retention int(11) DEFAULT 0'
        );
    }
} catch (PDOException $e) {
    $centreonLog->insertLog(
        2,
        'UPGRADE : Unable to process 19.10.0-post-beta 1 upgrade'
    );
}
