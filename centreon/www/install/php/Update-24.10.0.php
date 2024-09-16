<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../../class/centreonLog.class.php';

$centreonLog = new CentreonLog();

//error specific content
$versionOfTheUpgrade = 'UPGRADE - 24.10.0: ';
$errorMessage = '';

// CLOCK WIDGET
$insertWebPageWidget = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to select data into table dashboard_widgets';
    $statement = $pearDB->executeQuery(
        <<<'SQL'
            SELECT 1 FROM `dashboard_widgets` WHERE `name` = 'centreon-widget-webpage'
            SQL
    );

    $errorMessage = 'Unable to insert data into table dashboard_widgets';
    if (false === (bool) $statement->fetch(\PDO::FETCH_COLUMN)) {
        $pearDB->executeQuery(
            <<<'SQL'
                INSERT INTO `dashboard_widgets` (`name`)
                VALUES ('centreon-widget-webpage')
                SQL
        );
    }
};

// Vault configuration
$insertVaultConfiguration = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to retrieve from topology table';
    $statement = $pearDB->executeQuery(
        <<<'SQL'
            SELECT 1 FROM `topology` WHERE `topology_name` = 'Vault'
            SQL
    );

    $errorMessage = 'Unable to insert data into table topology';
    if (false === (bool) $statement->fetch(\PDO::FETCH_COLUMN)) {
        $pearDB->executeQuery(
            <<<'SQL'
                INSERT INTO `topology` (`topology_name`, `topology_url`, `readonly`, `is_react`, `topology_parent`, `topology_page`, `topology_order`, `topology_group`, `topology_feature_flag`)
                VALUES ('Vault', '/administration/parameters/vault', '1', '1', 501, 50112, 100, 1, 'vault')
                SQL
        );
    }
};

$createDashboardThumbnailTable = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to add table dashboard_thumbnail_relation';
    $pearDB->executeQuery(
        <<<SQL
            CREATE TABLE IF NOT EXISTS `dashboard_thumbnail_relation` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `dashboard_id` INT UNSIGNED NOT NULL,
              `img_id` int(11) NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `dashboard_thumbnail_relation_unique` (`dashboard_id`,`img_id`),
              CONSTRAINT `dashboard_thumbnail_relation_dashboard_id`
                FOREIGN KEY (`dashboard_id`)
                REFERENCES `dashboard` (`id`) ON DELETE CASCADE,
              CONSTRAINT `dashboard_thumbnail_relation_img_id`
                FOREIGN KEY (`img_id`)
                REFERENCES `view_img` (`img_id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL
    );
};

try {
    $createDashboardThumbnailTable($pearDB);

    // Transactional queries
    if (! $pearDB->inTransaction()) {
        $pearDB->beginTransaction();
    }

    $insertVaultConfiguration($pearDB);
    $insertWebPageWidget($pearDB);

    $pearDB->commit();
} catch (\Exception $e) {

    if ($pearDB->inTransaction()) {
        $pearDB->rollBack();
    }

    $centreonLog->insertLog(
        4,
        $versionOfTheUpgrade . $errorMessage
            . ' - Code : ' . (int) $e->getCode()
            . ' - Error : ' . $e->getMessage()
            . ' - Trace : ' . $e->getTraceAsString()
    );

    throw new \Exception($versionOfTheUpgrade . $errorMessage, (int) $e->getCode(), $e);
}
