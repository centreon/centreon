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
$versionOfTheUpgrade = 'UPGRADE - 24.04.0: ';
$errorMessage = '';

// ------------ INSERT / UPDATE / DELETE
$insertTopologyForResourceAccessManagement = function(CentreonDB $pearDB): void {
    $statement = $pearDB->query(
        <<<'SQL'
            SELECT 1 FROM `topology` WHERE `topology_name` = 'Resource Access Management'
            SQL
    );

    if (false === (bool) $statement->fetch(\PDO::FETCH_COLUMN)) {
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `topology`
                    (`topology_name`, `topology_url`, `readonly`, `is_react`, `topology_parent`, `topology_page`,
                    `topology_order`, `topology_group`, `topology_feature_flag`)
                VALUES
                    ( 'Resource Access Management', '/administration/resource-access/rules', '1', '1', 502, 50206, 1, 1,
                    'resource_access_management');
                SQL
        );
    }
};

$alterAclGroupsTable = function (CentreonDB $pearDB): void {
    if (! $pearDB->isColumnExist('acl_groups', 'cloud_description')) {
        $pearDB->query(
            'ALTER TABLE `acl_groups` ADD COLUMN `cloud_description` TEXT DEFAULT NULL'
        );
    }

    if (! $pearDB->isColumnExist('acl_groups', 'cloud_specific')) {
        $pearDB->query(
            'ALTER TABLE `acl_groups` ADD COLUMN `cloud_specific` BOOLEAN NOT NULL DEFAULT 0'
        );
    }
};

$alterAclResourceGroupRelation = function (CentreonDB $pearDB) {
    if (! $pearDB->isColumnExist('acl_res_group_relations', 'order')) {
        $pearDB->query(
            'ALTER TABLE acl_res_group_relations ADD COLUMN `order` INT NOT NULL DEFAULT 0'
        );
    }
};

try {
    $errorMessage = '';
    // Tansactional queries
    if (! $pearDB->inTransaction()) {
        $pearDB->beginTransaction();
    }

    $errorMessage = 'Unable to insert topology for Resource Access Management';
    $insertTopologyForResourceAccessManagement($pearDB);

    $errorMessage = 'Unable to add columns description and create_from_cloud to acl_groups table';
    $alterAclGroupsTable($pearDB);

    $errorMessage = 'Unable to add column order to acl_res_group_relations table';
    $alterAclResourceGroupRelation($pearDB);
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
