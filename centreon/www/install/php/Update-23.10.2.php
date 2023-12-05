<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../../class/centreonLog.class.php';

$centreonLog = new CentreonLog();

//error specific content
$versionOfTheUpgrade = 'UPGRADE - 23.10.2: ';
$errorMessage = '';

$createNotificationContactgroupRelationTable = function (CentreonDB $pearDB) use (&$errorMessage): void
{
    $errorMessage = 'Unable to create table: notification_contactgroup_relation';
    $pearDB->query(
        <<<'SQL'
            CREATE TABLE IF NOT EXISTS `notification_contactgroup_relation` (
              `notification_id` INT UNSIGNED NOT NULL,
              `contactgroup_id` INT NOT NULL,
              UNIQUE KEY `notification_contactgroup_relation_unique_index` (`notification_id`,`contactgroup_id`),
              CONSTRAINT `notification_contactgroup_relation_notification_id`
                FOREIGN KEY (`notification_id`)
                REFERENCES `notification` (`id`) ON DELETE CASCADE,
              CONSTRAINT `notification_contactgroup_relation_contactgroup_id`
                FOREIGN KEY (`contactgroup_id`)
                REFERENCES `contactgroup` (`cg_id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        SQL
    );
};

try {
    $createNotificationContactgroupRelationTable($pearDB);
} catch (\Exception $e) {
    $centreonLog->insertLog(
        4,
        $versionOfTheUpgrade . $errorMessage
        . ' - Code : ' . (int) $e->getCode()
        . ' - Error : ' . $e->getMessage()
        . ' - Trace : ' . $e->getTraceAsString()
    );

    throw new \Exception($versionOfTheUpgrade . $errorMessage, (int) $e->getCode(), $e);
}
