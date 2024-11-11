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

require_once __DIR__ . '/../../class/centreonLog.class.php';
$centreonLog = new CentreonLog();

//error specific content
$versionOfTheUpgrade = 'UPGRADE - 23.04.1: ';
$errorMessage = '';

$alterTopologyForFeatureFlag = function(CentreonDB $pearDB): void {
    if (!$pearDB->isColumnExist('topology', 'topology_feature_flag')) {
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `topology`
                ADD COLUMN `topology_feature_flag` varchar(255) DEFAULT NULL
                AFTER `topology_OnClick`
                SQL
        );
    }
};

$removeNagiosPathImg = function(CentreonDB $pearDB): void {
    $selectStatement = $pearDB->query("SELECT 1 FROM options WHERE `key`='nagios_path_img'");
    if($selectStatement->rowCount() > 0) {
        $pearDB->query("DELETE FROM options WHERE `key`='nagios_path_img'");
    }
};

$alterTopologyForTopologyUrlSubstitue = function(CentreonDB $pearDB): void {
    if(!$pearDB->isColumnExist('topology', 'topology_url_substitute')) {
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `topology`
                ADD COLUMN `topology_url_substitute` VARCHAR(255) DEFAULT NULL
                AFTER `topology_url_opt`
            SQL

        );
    }
};

try {
    // Transactional queries
    if (! $pearDB->inTransaction()) {
        $pearDB->beginTransaction();
    }

    $errorMessage = 'Impossible to remove nagios_path_img column from options table.';
    $removeNagiosPathImg($pearDB);

    $pearDB->commit();

    $errorMessage = 'Impossible to add column topology_feature_flag to topology table';
    $alterTopologyForFeatureFlag($pearDB);

    $errorMessage = 'Impossible to add column topology_url_substitute to topology table';
    $alterTopologyForTopologyUrlSubstitue($pearDB);
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
