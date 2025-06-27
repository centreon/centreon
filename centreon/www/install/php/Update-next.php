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

/**
 * This file contains changes to be included in the next version.
 * The actual version number should be added in the variable $version.
 */
$version = 'xx.xx.x';
$errorMessage = '';

/**
 * Add column `show_deprecated_custom_views` to contact table.
 */
$addDeprecateCustomViewsToContact=  function() use (&$errorMessage, &$pearDB): void {
    $errorMessage = 'Unable to add column show_deprecated_custom_views to contact table';
    if (! $pearDB->isColumnExist('contact', 'show_deprecated_custom_views')) {
        $pearDB->executeQuery(
            <<<SQL
            ALTER TABLE contact ADD COLUMN show_deprecated_custom_views ENUM('0','1') DEFAULT '0'
            SQL
        );
    }
};

/**
 * Switch Topology Order between Dashboards and Custom Views.
 */
$updateDashboardAndCustomViewsTopology = function() use(&$errorMessage, &$pearDB): void {
    $errorMessage = 'Unable to update topology of Custom Views';
    $pearDB->executeQuery(
        <<<SQL
        UPDATE topology SET topology_order = 2, is_deprecated ="1" WHERE topology_name = "Custom Views"
        SQL
    );
    $errorMessage = 'Unable to update topology of Dashboards';
    $pearDB->executeQuery(
        <<<SQL
        UPDATE topology SET topology_order = 1 WHERE topology_name = "Dashboards"
        SQL
    );
};

/**
 * Set Show Deprecated Custom Views to true by default is there is existing custom views.
 */
$updateContactsShowDeprecatedCustomViews = function() use(&$errorMessage, &$pearDB): void {
    $errorMessage = 'Unable to retrieve custom views';
    $statement = $pearDB->executeQuery(
        <<<SQL
        SELECT 1 FROM custom_views
        SQL
    );

    if (! empty($statement->fetchAll())) {
        $pearDB->executeQuery(
            <<<SQL
            UPDATE contact SET show_deprecated_custom_views = '1'
            SQL
        );
    }
};

$updateCfgParameters = function () use ($pearDB, &$errorMessage): void {
    $errorMessage = 'Unable to update cfg_nagios table';

    $pearDB->executeQuery(
        <<<'SQL'
            UPDATE cfg_nagios
            SET enable_flap_detection = '1',
                host_down_disable_service_checks = '1'
            WHERE enable_flap_detection != '1'
               OR host_down_disable_service_checks != '1'
        SQL
    );
};

/** -------------------------------------------- BBDO cfg update -------------------------------------------- */
$bbdoDefaultUpdate= function () use ($pearDB, &$errorMessage): void {
    if ($pearDB->isColumnExist('cfg_centreonbroker', 'bbdo_version') !== 1) {
        $errorMessage = "Unable to update 'bbdo_version' column to 'cfg_centreonbroker' table";
        $pearDB->query('ALTER TABLE `cfg_centreonbroker` MODIFY `bbdo_version` VARCHAR(50) DEFAULT "3.1.0"');
    }
};

$bbdoCfgUpdate = function () use ($pearDB, &$errorMessage): void {
    $errorMessage = "Unable to update 'bbdo_version' version in 'cfg_centreonbroker' table";
    $pearDB->query('UPDATE `cfg_centreonbroker` SET `bbdo_version` = "3.1.0"');
};

try {

    $bbdoDefaultUpdate();
    $addDeprecateCustomViewsToContact();

    // Transactional queries for configuration database
    if (! $pearDB->inTransaction()) {
        $pearDB->beginTransaction();
    }

    $updateDashboardAndCustomViewsTopology();
    $updateContactsShowDeprecatedCustomViews();
    $updateCfgParameters();
    $bbdoCfgUpdate();

    $pearDB->commit();

} catch (\Throwable $exception) {
    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: " . $errorMessage,
        exception: $exception
    );
    try {
        if ($pearDB->inTransaction()) {
            $pearDB->rollBack();
        }
    } catch (\PDOException $rollbackException) {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: error while rolling back the upgrade operation for : {$errorMessage}",
            exception: $rollbackException
        );

        throw new \Exception(
            "UPGRADE - {$version}: error while rolling back the upgrade operation for : {$errorMessage}",
            (int) $rollbackException->getCode(),
            $rollbackException
        );
    }

    throw new \Exception("UPGRADE - {$version}: " . $errorMessage, (int) $exception->getCode(), $exception);
}
