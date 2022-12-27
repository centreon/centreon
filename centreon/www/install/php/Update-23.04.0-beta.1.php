<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
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
$versionOfTheUpgrade = 'UPGRADE - 23.04.0-beta.1: ';
$errorMessage = '';

try {
    if ($pearDB->isColumnExist('cfg_centreonbroker', 'event_queues_total_size') === 0) {
        $errorMessage = "Impossible to update cfg_centreonbroker table";
        $pearDB->query(
            "ALTER TABLE `cfg_centreonbroker`
            ADD COLUMN `event_queues_total_size` INT(11) DEFAULT NULL
            AFTER `event_queue_max_size`"
        );
    }

    $errorMessage = "Impossible to delete color picker topology_js entries";
    $pearDB->query(
        "DELETE FROM `topology_JS`
        WHERE `PathName_js` = './include/common/javascript/color_picker_mb.js'"
    );

    $errorMessage = "Impossible to add column 'host_snmp_is_password' to host table";
    addSNMPCommunityIsPasswordColumn($pearDB);
    // Transactional queries
    $pearDB->beginTransaction();

    $errorMessage = 'Unable to update illegal characters fields from engine configuration of pollers';
    decodeIllegalCharactersNagios($pearDB);

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

/**
 * Update illegal_object_name_chars + illegal_macro_output_chars fields from cf_nagios table.
 * The aim is to decode entities from them.
 *
 * @param CentreonDB $pearDB
 */
function decodeIllegalCharactersNagios(CentreonDB $pearDB): void
{
    $configs = $pearDB->query(
        <<<'SQL'
            SELECT
                nagios_id,
                illegal_object_name_chars,
                illegal_macro_output_chars
            FROM
                `cfg_nagios`
            SQL
    )->fetchAll(PDO::FETCH_ASSOC);

    $statement = $pearDB->prepare(
        <<<'SQL'
            UPDATE
                `cfg_nagios`
            SET
                illegal_object_name_chars = :illegal_object_name_chars,
                illegal_macro_output_chars = :illegal_macro_output_chars
            WHERE
                nagios_id = :nagios_id
            SQL
    );
    foreach ($configs as $config) {
        $modified = $config;
        $modified['illegal_object_name_chars'] = html_entity_decode($config['illegal_object_name_chars']);
        $modified['illegal_macro_output_chars'] = html_entity_decode($config['illegal_macro_output_chars']);

        if ($config === $modified) {
            // no need to update, we skip a useless query
            continue;
        }

        $statement->bindValue(':illegal_object_name_chars', $modified['illegal_object_name_chars'], \PDO::PARAM_STR);
        $statement->bindValue(':illegal_macro_output_chars', $modified['illegal_macro_output_chars'], \PDO::PARAM_STR);
        $statement->bindValue(':nagios_id', $modified['nagios_id'], \PDO::PARAM_INT);
        $statement->execute();
    }
}

/**
 * Add host_snmp_is_password column to host table.
 * This column purpose is to save the SNMP Community has a password and avoid display of sensitive information in UI.
 * If Vault Configurations exists the SNMP Community will be added to the vault if it's a password.
 *
 * @param CentreonDB $pearDB
 * @return void
 */
function addSNMPCommunityIsPasswordColumn(CentreonDB $pearDB): void
{
    if ($pearDB->isColumnExist('host', 'host_snmp_is_password') === 0) {
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `host`
                ADD COLUMN host_snmp_is_password enum('0','1') DEFAULT '0' NOT NULL
                AFTER `host_snmp_community`
            SQL
        );
    }
}
