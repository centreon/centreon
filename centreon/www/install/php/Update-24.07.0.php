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
$versionOfTheUpgrade = 'UPGRADE - 24.07.0: ';
$errorMessage = '';

$deleteVaultTables = function(CentreonDB $pearDB) use(&$errorMessage): void {
    $errorMessage = 'Unable to drop table vault configuration';
        $pearDB->query(
            <<<'SQL'
                DROP TABLE IF EXISTS `vault_configuration`
                SQL
        );

    $errorMessage = 'Unable to drop table vault';
    $pearDB->query(
        <<<'SQL'
            DROP TABLE IF EXISTS `vault`
            SQL
    );

};

$updateCfgResourceTable = function (CentreonDB $pearDB) use(&$errorMessage): void {
    $errorMessage = 'Unable to update table cfg_resource';
    if (!$pearDB->isColumnExist('cfg_resource', 'is_password')) {
        $pearDB->query(
            <<<'SQL'
            ALTER TABLE `cfg_resource` ADD COLUMN `is_password` tinyint(1) NOT NULL DEFAULT 0
            SQL
        );
    }
};

$updateBrokerCfgFieldTable = function (CentreonDB $pearDB) use(&$errorMessage): void {
    $errorMessage = 'Unable to update table cb_field';
    $pearDB->query(
        <<<'SQL'
            UPDATE `cb_field` SET cb_fieldgroup_id = 1 WHERE fieldname = 'category' AND fieldtype = 'multiselect'
            SQL
    );
};

try {
    $deleteVaultTables($pearDB);
    $updateCfgResourceTable($pearDB);

    // Tansactional queries
    if (! $pearDB->inTransaction()) {
        $pearDB->beginTransaction();
    }
    $updateBrokerCfgFieldTable($pearDB);

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
