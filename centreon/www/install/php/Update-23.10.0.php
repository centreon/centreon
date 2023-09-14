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
$versionOfTheUpgrade = 'UPGRADE - 23.10.0: ';
$errorMessage = '';

$removeNagiosPathImg = function(CentreonDB $pearDB) {
    $selectStatement = $pearDB->query("SELECT 1 FROM options WHERE `key`='nagios_path_img'");
    if ($selectStatement->rowCount() > 0) {
        $pearDB->query("DELETE FROM options WHERE `key`='nagios_path_img'");
    }
};
//Change the type of check_attempt and max_check_attempts columns from table resources
$errorMessage = "Couldn't modify resources table";
$alterResourceTableStmnt = "ALTER TABLE resources MODIFY check_attempts SMALLINT UNSIGNED,
    MODIFY max_check_attempts SMALLINT UNSIGNED";

$alterMetricsTable = function(CentreonDB $pearDBO) {
    $pearDBO->query(
        <<<'SQL'
            ALTER TABLE `metrics`
            MODIFY COLUMN `metric_name` VARCHAR(1021) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL
            SQL
    );
};

$enableDisabledServiceTemplates = function(CentreonDB $pearDB) {
    $pearDB->query(
        <<<'SQL'
            UPDATE `service`
                SET service_activate = '1'
            WHERE service_register = '0'
                AND service_activate = '0'
            SQL
    );
};

$enableDisabledHostTemplates = function(CentreonDB $pearDB) {
    $pearDB->query(
        <<<'SQL'
            UPDATE `host`
                SET host_activate = '1'
            WHERE host_register = '0'
                AND host_activate = '0'
            SQL
    );
};

$addTopologyFeatureFlag = function(CentreonDB $pearDB) {
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

$updateSecurityTokenTable = function (CentreonDB $pearDB) {
    if (!$pearDB->isColumnExist('security_authentication_tokens', 'token_name')) {
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `security_authentication_tokens`
                ADD COLUMN `token_name` varchar(255) DEFAULT NULL,
                ADD COLUMN `token_type` enum('auto', 'manual') NOT NULL DEFAULT 'auto',
                ADD COLUMN `creator_id` int(11) DEFAULT NULL,
                ADD COLUMN `creator_name` varchar(255) DEFAULT NULL,
                ADD COLUMN `is_revoked` BOOLEAN NOT NULL DEFAULT 0,
                ADD KEY `security_authentication_tokens_creator_id_fk` (`creator_id`),
                ADD CONSTRAINT `security_authentication_tokens_creator_id_fk` FOREIGN KEY (`creator_id`)
                    REFERENCES `contact` (`contact_id`) ON DELETE SET NULL
                SQL
        );
    }
};

try {
    $pearDBO->query($alterResourceTableStmnt);

    $errorMessage = 'Impossible to alter metrics table';
    $alterMetricsTable($pearDBO);

    $errorMessage = 'Impossible to add column topology_feature_flag to topology table';
    $addTopologyFeatureFlag($pearDB);

    $errorMessage = 'Unable to alter security_authentication_tokens table';
    $updateSecurityTokenTable($pearDB);

    $errorMessage = '';
    // Transactional queries
    if (! $pearDB->inTransaction()) {
        $pearDB->beginTransaction();
    }
    $errorMessage = "Unable to Delete nagios_path_img from options table";
    $removeNagiosPathImg($pearDB);

    $errorMessage = 'Unable to activate deactivated service templates';
    $enableDisabledServiceTemplates($pearDB);

    $errorMessage = 'Unable to activate deactivated host templates';
    $enableDisabledHostTemplates($pearDB);

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
