<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

// error specific content
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
    if (false === (bool) $statement->fetch(PDO::FETCH_COLUMN)) {
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
    if (false === (bool) $statement->fetch(PDO::FETCH_COLUMN)) {
        $pearDB->executeQuery(
            <<<'SQL'
                INSERT INTO `topology` (`topology_name`, `topology_url`, `readonly`, `is_react`, `topology_parent`, `topology_page`, `topology_order`, `topology_group`, `topology_feature_flag`)
                VALUES ('Vault', '/administration/parameters/vault', '1', '1', 501, 50112, 100, 1, 'vault')
                SQL
        );
    }
};

$addDisableServiceCheckColumn = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to add column host_down_disable_service_checks to table cfg_nagios';
    if (! $pearDB->isColumnExist('cfg_nagios', 'host_down_disable_service_checks')) {
        $pearDB->executeQuery(
            <<<'SQL'
                ALTER TABLE `cfg_nagios`
                ADD COLUMN `host_down_disable_service_checks` ENUM('0', '1') DEFAULT '0'
                AFTER `enable_predictive_service_dependency_checks`
                SQL
        );
    }
};

// ACC
$fixNamingAndActivateAccTopology = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to retrieve data from informations table';
    $statement = $pearDB->executeQuery(
        <<<'SQL'
            SELECT `value` FROM `informations` WHERE `key` = 'isCentral'
            SQL
    );
    $isCentral = $statement->fetch(PDO::FETCH_COLUMN);

    $errorMessage = 'Unable to update table topology';
    $constraintStatement = $pearDB->prepareQuery(
        <<<'SQL'
            UPDATE `topology`
            SET `topology_show` = :show,
                `topology_name` = 'Additional Connector Configurations',
                `topology_order` = 91
            WHERE `topology_url` = '/configuration/additional-connector-configurations'
            SQL
    );
    $pearDB->executePreparedQuery($constraintStatement, [':show' => $isCentral === 'yes' ? '1' : '0']);
};

// Nagios Macros
$updateNagiosMacros = function (CentreonDB $pearDB) use (&$errorMessage): void {
    $errorMessage = 'Unable to check for existing macros in nagios_macro table';
    $statement = $pearDB->executeQuery(
        <<<'SQL'
            SELECT COUNT(*) FROM `nagios_macro`
            WHERE `macro_name` IN (
                '$NOTIFICATIONAUTHOR$',
                '$NOTIFICATIONAUTHORNAME$',
                '$NOTIFICATIONAUTHORALIAS$',
                '$NOTIFICATIONCOMMENT$'
            )
            SQL
    );

    $errorMessage = 'Unable to insert new macros into nagios_macro table';
    if (0 === (int) $statement->fetch(PDO::FETCH_COLUMN)) {
        $pearDB->executeQuery(
            <<<'SQL'
                INSERT INTO `nagios_macro` (`macro_name`)
                VALUES
                    ('$NOTIFICATIONAUTHOR$'),
                    ('$NOTIFICATIONAUTHORNAME$'),
                    ('$NOTIFICATIONAUTHORALIAS$'),
                    ('$NOTIFICATIONCOMMENT$')
                SQL
        );
    }

    $errorMessage = 'Unable to delete deprecated macros from nagios_macro table';
    $pearDB->executeQuery(
        <<<'SQL'
            DELETE FROM `nagios_macro`
            WHERE `macro_name` IN (
                '$HOSTACKAUTHOR$',
                '$HOSTACKCOMMENT$',
                '$SERVICEACKAUTHOR$',
                '$SERVICEACKCOMMENT$'
            )
            SQL
    );
};

try {
    $addDisableServiceCheckColumn($pearDB);

    // Transactional queries
    if (! $pearDB->inTransaction()) {
        $pearDB->beginTransaction();
    }

    $insertVaultConfiguration($pearDB);
    $insertWebPageWidget($pearDB);
    $fixNamingAndActivateAccTopology($pearDB);
    $updateNagiosMacros($pearDB);

    $pearDB->commit();
} catch (Exception $e) {

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

    throw new Exception($versionOfTheUpgrade . $errorMessage, (int) $e->getCode(), $e);
}
