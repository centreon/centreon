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

require_once __DIR__ . '/../../class/centreonLog.class.php';

$centreonLog = new CentreonLog();

// error specific content
$versionOfTheUpgrade = 'UPGRADE - 22.04.10: ';
$errorMessage = '';

try {

    // Transactional queries
    $pearDB->beginTransaction();

    // Update illegal_object_name_chars + illegal_macro_output_chars fields from cf_nagios table.
    // The aim is to decode entities from them.
    // We fix here in 22.04.10 the flag problem which changed between php 8.0 and php 8.1
    $errorMessage = 'Unable to update illegal characters fields from engine configuration of pollers';
    (static function (CentreonDB $pearDB): void {
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

        // We want to enforce the flags as if we are in PHP 8.1 and not let the current
        // version 8.0 default flags which not enough for entities like &#039; (ENT_COMPAT)
        $defaultPhp81Flags = ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401;

        $preparedUpdate = null;
        foreach ($configs as $config) {
            $modified = $config;
            $modified['illegal_object_name_chars'] = html_entity_decode($config['illegal_object_name_chars'], $defaultPhp81Flags);
            $modified['illegal_macro_output_chars'] = html_entity_decode($config['illegal_macro_output_chars'], $defaultPhp81Flags);

            if ($config === $modified) {
                // no need to update, we skip a useless query
                continue;
            }

            $preparedUpdate ??= $pearDB->prepare(
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

            $preparedUpdate->bindValue(':illegal_object_name_chars', $modified['illegal_object_name_chars'], \PDO::PARAM_STR);
            $preparedUpdate->bindValue(':illegal_macro_output_chars', $modified['illegal_macro_output_chars'], \PDO::PARAM_STR);
            $preparedUpdate->bindValue(':nagios_id', $modified['nagios_id'], \PDO::PARAM_INT);
            $preparedUpdate->execute();
        }
    })(
        $pearDB
    );

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

