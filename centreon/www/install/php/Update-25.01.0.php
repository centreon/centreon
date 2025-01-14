<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

$centreonLog = CentreonLog::create();

// error specific content
$versionOfTheUpgrade = 'UPGRADE - 25.01.0: ';

$errorMessage = '';

$createUserProfileTable = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to add table user_profile';
    $pearDB->executeQuery(
        <<<SQL
        CREATE TABLE IF NOT EXISTS `user_profile` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `contact_id` INT(11) NOT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `unique_user_profile` (`id`, `contact_id`),
          CONSTRAINT `fk_user_profile_contact_id`
            FOREIGN KEY (`contact_id`)
            REFERENCES `contact` (`contact_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        SQL
    );
};

$createUserProfileFavoriteDashboards = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to add table user_profile_favorite_dashboards';
    $pearDB->executeQuery(
        <<<SQL
        CREATE TABLE IF NOT EXISTS `user_profile_favorite_dashboards` (
          `profile_id` INT UNSIGNED NOT NULL,
          `dashboard_id` INT UNSIGNED NOT NULL,
          CONSTRAINT `fk_user_profile_favorite_dashboards_profile_id`
            FOREIGN KEY (`profile_id`)
            REFERENCES `user_profile` (`id`) ON DELETE CASCADE,
          CONSTRAINT `fk_user_profile_favorite_dashboards_dashboard_id`
            FOREIGN KEY (`dashboard_id`)
            REFERENCES `dashboard` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        SQL
    );
};

try {
    $createUserProfileTable($pearDB);
    $createUserProfileFavoriteDashboards($pearDB);
} catch (Exception $e) {

    $centreonLog->log(
        CentreonLog::TYPE_UPGRADE,
        CentreonLog::LEVEL_ERROR,
        $versionOfTheUpgrade . $errorMessage
        . ' - Code : ' . (int) $e->getCode()
        . ' - Error : ' . $e->getMessage()
        . ' - Trace : ' . $e->getTraceAsString()
    );

    throw new Exception($versionOfTheUpgrade . $errorMessage, (int) $e->getCode(), $e);
}
