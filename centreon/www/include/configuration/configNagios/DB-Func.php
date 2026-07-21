<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

function testExistence($name = null)
{
    global $pearDB, $form;

    $id = null;
    if (isset($form)) {
        $id = $form->getSubmitValue('nagios_id');
    }

    $statement = $pearDB->prepare(
        'SELECT nagios_name, nagios_id FROM cfg_nagios WHERE nagios_name = :name'
    );
    $statement->bindValue(':name', htmlentities($name, ENT_QUOTES, 'UTF-8'), PDO::PARAM_STR);
    $statement->execute();
    $nagios = $statement->fetch();
    if ($statement->rowCount() >= 1 && $nagios['nagios_id'] == $id) {
        return true;
    }

    return ! ($statement->rowCount() >= 1 && $nagios['nagios_id'] != $id);
}

/**
 * @param null $nagiosId
 * @throws Exception
 */
function enableNagiosInDB($nagiosId = null)
{
    global $pearDB, $centreon;
    if (! $nagiosId) {
        return;
    }

    $statement = $pearDB->prepare(
        'SELECT `nagios_server_id` FROM cfg_nagios WHERE nagios_id = :nagios_id'
    );
    $statement->bindValue(':nagios_id', (int) $nagiosId, PDO::PARAM_INT);
    $statement->execute();
    $data = $statement->fetch();

    $statement = $pearDB->prepare(
        "UPDATE `cfg_nagios`
        SET `nagios_activate` = '0'
        WHERE `nagios_server_id` = :nagios_server_id"
    );
    $statement->bindValue(':nagios_server_id', (int) $data['nagios_server_id'], PDO::PARAM_INT);
    $statement->execute();

    $statement = $pearDB->prepare(
        "UPDATE cfg_nagios SET nagios_activate = '1' WHERE nagios_id = :nagios_id"
    );
    $statement->bindValue(':nagios_id', (int) $nagiosId, PDO::PARAM_INT);
    $statement->execute();

    $query = "SELECT `id`, `name` FROM nagios_server WHERE `ns_activate` = '0' AND `id` = :id";
    $statement = $pearDB->prepare($query);
    $statement->bindValue(':id', (int) $data['nagios_server_id'], PDO::PARAM_INT);
    $statement->execute();
    $activate = $statement->fetch(PDO::FETCH_ASSOC);
    if ($activate && $activate['name']) {
        $query = "UPDATE `nagios_server` SET `ns_activate` = '1' WHERE `id` = :id";
        $statement = $pearDB->prepare($query);
        $statement->bindValue(':id', (int) $activate['id'], PDO::PARAM_INT);
        $statement->execute();
        $centreon->CentreonLogAction->insertLog('poller', $activate['id'], $activate['name'], 'enable');
    }
}

/**
 * @param null $nagiosId
 * @throws Exception
 */
function disableNagiosInDB($nagiosId = null)
{
    global $pearDB, $centreon;

    if (! $nagiosId) {
        return;
    }

    $statement = $pearDB->prepare(
        'SELECT `nagios_server_id` FROM cfg_nagios WHERE nagios_id = :nagios_id'
    );
    $statement->bindValue(':nagios_id', (int) $nagiosId, PDO::PARAM_INT);
    $statement->execute();
    $data = $statement->fetch();

    $statement = $pearDB->prepare(
        "UPDATE cfg_nagios SET nagios_activate = '0' WHERE `nagios_id` = :nagios_id"
    );
    $statement->bindValue(':nagios_id', (int) $nagiosId, PDO::PARAM_INT);
    $statement->execute();

    $query = "SELECT `nagios_id` FROM cfg_nagios WHERE `nagios_activate` = '1' "
             . 'AND `nagios_server_id` = :nagios_server_id';
    $statement = $pearDB->prepare($query);
    $statement->bindValue(':nagios_server_id', (int) $data['nagios_server_id'], PDO::PARAM_INT);
    $statement->execute();
    $activate = $statement->fetch(PDO::FETCH_ASSOC);

    if (! $activate['nagios_id']) {
        $query = "UPDATE `nagios_server` SET `ns_activate` = '0' WHERE `id` = :id";
        $statement = $pearDB->prepare($query);
        $statement->bindValue(':id', (int) $data['nagios_server_id'], PDO::PARAM_INT);
        $statement->execute();

        $query = 'SELECT `id`, `name` FROM nagios_server WHERE `id` = :id';
        $statement = $pearDB->prepare($query);
        $statement->bindValue(':id', (int) $data['nagios_server_id'], PDO::PARAM_INT);
        $statement->execute();
        $poller = $statement->fetch(PDO::FETCH_ASSOC);

        $centreon->CentreonLogAction->insertLog('poller', $poller['id'], $poller['name'], 'disable');
    }
}

function deleteNagiosInDB($nagios = [])
{
    global $pearDB;

    $deleteNagiosStmt = $pearDB->prepare('DELETE FROM cfg_nagios WHERE nagios_id = :id');
    $deleteBrokerStmt = $pearDB->prepare('DELETE FROM cfg_nagios_broker_module WHERE cfg_nagios_id = :id');

    foreach ($nagios as $key => $value) {
        $deleteNagiosStmt->bindValue(':id', (int) $key, PDO::PARAM_INT);
        $deleteNagiosStmt->execute();

        $deleteBrokerStmt->bindValue(':id', (int) $key, PDO::PARAM_INT);
        $deleteBrokerStmt->execute();
    }
    $dbResult = $pearDB->query(
        "SELECT nagios_id FROM cfg_nagios WHERE nagios_activate = '1'"
    );
    if (! $dbResult->rowCount()) {
        $dbResult2 = $pearDB->query(
            'SELECT MAX(nagios_id) FROM cfg_nagios'
        );
        $nagios_id = $dbResult2->fetch();
        $statement = $pearDB->prepare(
            "UPDATE cfg_nagios SET nagios_activate = '1' WHERE nagios_id = :nagios_id"
        );
        $statement->bindValue(':nagios_id', (int) $nagios_id['MAX(nagios_id)'], PDO::PARAM_INT);
        $statement->execute();
    }
    $dbResult->closeCursor();
}

// Duplicate Engine Configuration file in DB
function multipleNagiosInDB($nagios = [], $nbrDup = [])
{
    foreach ($nagios as $originalNagiosId => $value) {
        global $pearDB;

        $stmt = $pearDB->prepare('SELECT * FROM cfg_nagios WHERE nagios_id = :nagiosId LIMIT 1');
        $stmt->bindValue(':nagiosId', (int) $originalNagiosId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        unset($row['nagios_id']);
        $row['nagios_activate'] = '0';
        $stmt->closeCursor();

        $rowBks = [];
        $stmt = $pearDB->prepare('SELECT * FROM cfg_nagios_broker_module WHERE cfg_nagios_id = :nagiosId');
        $stmt->bindValue(':nagiosId', (int) $originalNagiosId, PDO::PARAM_INT);
        $stmt->execute();
        while ($rowBk = $stmt->fetch()) {
            $rowBks[] = $rowBk;
        }
        $stmt->closeCursor();

        $columns = array_keys($row);
        $placeholders = implode(', ', array_map(fn ($col) => ':' . $col, $columns));
        $insertStmt = $pearDB->prepare(
            'INSERT INTO cfg_nagios (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')'
        );

        $brokerInsertStmt = $pearDB->prepare(
            'INSERT INTO cfg_nagios_broker_module (`cfg_nagios_id`, `broker_module`)
            VALUES (:nagiosId, :brokerModule)'
        );

        $originalName = $row['nagios_name'];
        for ($i = 1; $i <= $nbrDup[$originalNagiosId]; $i++) {
            $nagios_name = $originalName . '_' . $i;
            $row['nagios_name'] = $nagios_name;

            if (testExistence($nagios_name)) {
                foreach ($columns as $col) {
                    $insertStmt->bindValue(':' . $col, $row[$col], $row[$col] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                }
                $insertStmt->execute();
                // Find the new last nagios_id once
                $dbResult = $pearDB->query('SELECT MAX(nagios_id) FROM cfg_nagios');
                $nagiosId = $dbResult->fetch();
                $dbResult->closeCursor();
                foreach ($rowBks as $keyBk => $valBk) {
                    if ($valBk['broker_module']) {
                        $brokerInsertStmt->bindValue(':nagiosId', (int) $nagiosId['MAX(nagios_id)'], PDO::PARAM_INT);
                        $brokerInsertStmt->bindValue(':brokerModule', $valBk['broker_module'], PDO::PARAM_STR);
                        $brokerInsertStmt->execute();
                    }
                }
                duplicateLoggerV2Cfg($pearDB, $originalNagiosId, $nagiosId['MAX(nagios_id)']);
            }
        }
    }
}

/**
 * @param CentreonDB $pearDB
 * @param int $originalNagiosId
 * @param int $duplicatedNagiosId
 */
function duplicateLoggerV2Cfg(CentreonDB $pearDB, int $originalNagiosId, int $duplicatedNagiosId): void
{
    $selectStmt = $pearDB->prepare(
        'SELECT * FROM cfg_nagios_logger WHERE cfg_nagios_id = :originalNagiosId LIMIT 1'
    );
    $selectStmt->bindValue(':originalNagiosId', $originalNagiosId, PDO::PARAM_INT);
    $selectStmt->execute();
    $row = $selectStmt->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        return;
    }
    unset($row['id']);
    $row['cfg_nagios_id'] = $duplicatedNagiosId;

    $columns = array_keys($row);
    $placeholders = implode(', ', array_map(fn ($col) => ':' . $col, $columns));
    $insertStmt = $pearDB->prepare(
        'INSERT INTO cfg_nagios_logger (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')'
    );
    foreach ($columns as $col) {
        $value = $row[$col];
        $insertStmt->bindValue(':' . $col, $value, $value === null ? PDO::PARAM_NULL : ($col === 'cfg_nagios_id' ? PDO::PARAM_INT : PDO::PARAM_STR));
    }
    $insertStmt->execute();
}

function updateNagiosInDB($nagios_id = null)
{
    if (! $nagios_id) {
        return;
    }
    updateNagios($nagios_id);
}

function insertNagiosInDB()
{
    return insertNagios();
}

/**
 * Calculate the sum of bitwise for a POST QuickForm array
 *
 * The array format
 *
 * array[key] => enable
 *  Key int the bit
 *  Enable 0|1 if the bit is activate
 *
 * if found the bit -1 (all) or 0 (none) activate return the value
 *
 * @param array $list The POST QuickForm table
 * @return int The bitwise
 */
function calculateBitwise($list)
{
    $bitwise = 0;
    foreach ($list as $bit => $value) {
        if ($value == 1) {
            if ($bit === -1 || $bit === 0) {
                return $bit;
            }
            $bitwise += $bit;
        }
    }

    return $bitwise;
}

/**
 * @param array $levels
 * @return int
 */
function calculateDebugLevel(array $levels): int
{
    $level = 0;
    foreach ($levels as $key => $value) {
        $level += (int) $key;
    }

    return $level;
}

/**
 * @param array $levels
 * @return string
 */
function implodeDebugLevel(array $levels): string
{
    return implode(',', array_keys($levels));
}

/**
 * @param array $macros
 * @return string
 */
function concatMacrosWhitelist(array $macros): string
{
    return trim(
        implode(
            ',',
            array_map(function ($macro) {
                return CentreonDB::escape($macro);
            }, $macros)
        )
    );
}

/**
 * @return array<string,mixed>
 */
function getNagiosCfgColumnsDetails(): array
{
    return [
        'additional_freshness_latency' => ['default' => null],
        'admin_email' => ['default' => null],
        'admin_pager' => ['default' => null],
        'auto_rescheduling_interval' => ['default' => 30],
        'auto_rescheduling_window' => ['default' => 180],
        'cached_host_check_horizon' => ['default' => null],
        'cached_service_check_horizon' => ['default' => null],
        'cfg_dir' => ['default' => null],
        'cfg_file' => ['default' => null],
        'command_check_interval' => ['default' => null],
        'command_file' => ['default' => null],
        'check_result_reaper_frequency' => ['default' => 5],
        'date_format' => ['default' => 'euro'],
        'debug_level' => ['callback' => 'calculateDebugLevel', 'default' => '0'],
        'debug_log_opt' => ['callback' => 'implodeDebugLevel', 'default' => '0'],
        'debug_file' => ['default' => null],
        'debug_verbosity' => ['default' => '2'],
        'event_broker_options' => ['callback' => 'calculateBitwise', 'default' => '-1'],
        'event_handler_timeout' => ['default' => 30],
        'external_command_buffer_slots' => ['default' => null],
        'global_host_event_handler' => ['default' => null],
        'global_service_event_handler' => ['default' => null],
        'high_host_flap_threshold' => ['default' => null],
        'high_service_flap_threshold' => ['default' => null],
        'host_check_timeout' => ['default' => 12],
        'host_freshness_check_interval' => ['default' => null],
        'host_inter_check_delay_method' => ['default' => null],
        'log_file' => ['default' => null],
        'low_host_flap_threshold' => ['default' => null],
        'low_service_flap_threshold' => ['default' => null],
        'macros_filter' => ['callback' => 'concatMacrosWhitelist', 'default' => null],
        'max_debug_file_size' => ['default' => null],
        'max_concurrent_checks' => ['default' => 0],
        'max_host_check_spread' => ['default' => 15],
        'max_service_check_spread' => ['default' => 15],
        'nagios_comment' => ['default' => null],
        'nagios_name' => ['default' => null],
        'nagios_server_id' => ['default' => null],
        'notification_timeout' => ['default' => 30],
        'use_timezone' => ['default' => null],
        'retention_update_interval' => ['default' => null],
        'service_check_timeout' => ['default' => 60],
        'service_freshness_check_interval' => ['default' => null],
        'service_inter_check_delay_method' => ['default' => null],
        'service_interleave_factor' => ['default' => 's'],
        'status_file' => ['default' => null],
        'status_update_interval' => ['default' => null],
        'state_retention_file' => ['default' => null],
        'sleep_time' => ['default' => null],
        'illegal_macro_output_chars' => ['default' => null],
        'illegal_object_name_chars' => ['default' => null],
        'instance_heartbeat_interval' => ['default' => '30'],
        'broker_module_cfg_file' => ['default' => null],
        // Radio inputs
        'accept_passive_host_checks' => ['isRadio' => true, 'default' => '1'],
        'accept_passive_service_checks' => ['isRadio' => true, 'default' => '1'],
        'auto_reschedule_checks' => ['isRadio' => true, 'default' => '0'],
        'check_external_commands' => ['isRadio' => true, 'default' => '1'],
        'check_for_orphaned_hosts' => ['isRadio' => true, 'default' => '0'],
        'check_for_orphaned_services' => ['isRadio' => true, 'default' => '1'],
        'check_host_freshness' => ['isRadio' => true, 'default' => '0'],
        'check_service_freshness' => ['isRadio' => true, 'default' => '1'],
        'enable_environment_macros' => ['isRadio' => true, 'default' => '0'],
        'enable_event_handlers' => ['isRadio' => true, 'default' => '1'],
        'enable_flap_detection' => ['isRadio' => true, 'default' => '0'],
        'enable_macros_filter' => ['isRadio' => true, 'default' => '0'],
        'enable_notifications' => ['isRadio' => true, 'default' => '1'],
        'enable_predictive_host_dependency_checks' => ['isRadio' => true, 'default' => '0'],
        'enable_predictive_service_dependency_checks' => ['isRadio' => true, 'default' => '0'],
        'host_down_disable_service_checks' => ['isRadio' => true, 'default' => '0'],
        'execute_host_checks' => ['isRadio' => true, 'default' => '1'],
        'execute_service_checks' => ['isRadio' => true, 'default' => '1'],
        'log_event_handlers' => ['isRadio' => true, 'default' => '1'],
        'log_external_commands' => ['isRadio' => true, 'default' => '1'],
        'log_host_retries' => ['isRadio' => true, 'default' => '1'],
        'log_notifications' => ['isRadio' => true, 'default' => '1'],
        'log_passive_checks' => ['isRadio' => true, 'default' => '1'],
        'log_pid' => ['isRadio' => true, 'default' => '0'],
        'log_service_retries' => ['isRadio' => true, 'default' => '1'],
        'nagios_activate' => ['isRadio' => true, 'default' => '0'],
        'passive_host_checks_are_soft' => ['isRadio' => true, 'default' => '0'],
        'retain_state_information' => ['isRadio' => true, 'default' => '1'],
        'soft_state_dependencies' => ['isRadio' => true, 'default' => '0'],
        'use_regexp_matching' => ['isRadio' => true, 'default' => '0'],
        'use_retained_program_state' => ['isRadio' => true, 'default' => '1'],
        'use_retained_scheduling_info' => ['isRadio' => true, 'default' => '1'],
        'use_syslog' => ['isRadio' => true, 'default' => '0'],
        'use_true_regexp_matching' => ['isRadio' => true, 'default' => '0'],
        'logger_version' => ['isRadio' => true, 'default' => 'log_v2_enabled'],
    ];
}

/**
 * @return string[]
 */
function getLoggerV2Columns(): array
{
    return [
        'log_v2_logger',
        'log_level_functions',
        'log_level_config',
        'log_level_events',
        'log_level_checks',
        'log_level_notifications',
        'log_level_eventbroker',
        'log_level_external_command',
        'log_level_commands',
        'log_level_downtimes',
        'log_level_comments',
        'log_level_macros',
        'log_level_process',
        'log_level_runtime',
        'log_level_otl',
    ];
}

/**
 * @param CentreonDB $pearDB
 * @param array $data
 * @param int $nagiosId
 */
function insertLoggerV2Cfg(CentreonDB $pearDB, array $data, int $nagiosId): void
{
    $loggerCfg = getLoggerV2Columns();

    $statement = $pearDB->prepare(
        'INSERT INTO cfg_nagios_logger (`cfg_nagios_id`, `' . implode('`, `', $loggerCfg) . '`)
        VALUES (:cfg_nagios_id, :' . implode(', :', $loggerCfg) . ')'
    );

    $statement->bindValue(':cfg_nagios_id', $nagiosId, PDO::PARAM_INT);
    foreach ($loggerCfg as $columnName) {
        $statement->bindValue(':' . $columnName, $data[$columnName] ?? null, PDO::PARAM_STR);
    }
    $statement->execute();
}

/**
 * @param CentreonDB $pearDB
 * @param array<string,mixed> $data
 * @param int $nagiosId
 */
function updateLoggerV2Cfg(CentreonDB $pearDB, array $data, int $nagiosId): void
{
    $loggerCfg = getLoggerV2Columns();

    $queryPieces = array_map(fn ($columnName) => "`{$columnName}` = :{$columnName}", $loggerCfg);
    $statement = $pearDB->prepare(
        'UPDATE cfg_nagios_logger SET ' . implode(', ', $queryPieces) . ' WHERE cfg_nagios_id = :cfg_nagios_id'
    );

    $statement->bindValue(':cfg_nagios_id', $nagiosId, PDO::PARAM_INT);
    foreach ($loggerCfg as $columnName) {
        $statement->bindValue(':' . $columnName, $data[$columnName] ?? null, PDO::PARAM_STR);
    }
    $statement->execute();
}

/**
 * Insert logger V2 config if doesn't exist, otherwise update it
 *
 * @param CentreonDB $pearDB
 * @param array<string,mixed> $data
 * @param int $nagiosId
 */
function insertOrUpdateLogger(CentreonDB $pearDB, array $data, int $nagiosId): void
{
    $statement = $pearDB->prepare('SELECT id FROM cfg_nagios_logger WHERE cfg_nagios_id = :cfg_nagios_id');
    $statement->bindValue(':cfg_nagios_id', $nagiosId, PDO::PARAM_INT);
    $statement->execute();

    if ($statement->fetch()) {
        updateLoggerV2Cfg($pearDB, $data, $nagiosId);
    } else {
        insertLoggerV2Cfg($pearDB, $data, $nagiosId);
    }
}

/**
 * This function is here to manage legacy encoded field while allowing to avoid this
 * bad design for specific fields : this is why these fields are hard coded here.
 *
 * @param string $value
 * @param string $columnName
 * @return string
 */
function encodeFieldNagios(string $value, string $columnName): string
{
    $notEncodedFields = [
        'illegal_macro_output_chars',
        'illegal_object_name_chars',
    ];

    return in_array($columnName, $notEncodedFields, true)
        ? $value
        : htmlentities($value, ENT_QUOTES, 'UTF-8');
}

function insertNagios($data = [], $brokerTab = [])
{
    global $form, $pearDB, $centreon;

    if (! count($data)) {
        $data = $form->getSubmitValues();
    }

    $nagiosColumns = getNagiosCfgColumnsDetails();

    $nagiosCfg = [];
    foreach ($data as $columnName => $rawValue) {
        if (! array_key_exists($columnName, $nagiosColumns)) {
            continue;
        }

        if (! empty($nagiosColumns[$columnName]['callback'])) {
            $value = isset($rawValue)
                ? ($nagiosColumns[$columnName]['callback'])($rawValue)
                : $nagiosColumns[$columnName]['default'];
        } elseif (! empty($nagiosColumns[$columnName]['isRadio'])) {
            $value = isset($rawValue[$columnName])
                ? encodeFieldNagios($rawValue[$columnName], $columnName)
                : $nagiosColumns[$columnName]['default'];
        } else {
            $value = isset($rawValue) && $rawValue !== ''
                ? encodeFieldNagios($rawValue, $columnName)
                : $nagiosColumns[$columnName]['default'];
        }
        $nagiosCfg[$columnName] = $value;
    }

    $statement = $pearDB->prepare(
        'INSERT INTO cfg_nagios (`' . implode('`, `', array_keys($nagiosCfg)) . '`)
        VALUES (:' . implode(', :', array_keys($nagiosCfg)) . ')'
    );

    array_walk(
        $nagiosCfg,
        fn ($value, $param, $statement) => $statement->bindValue(':' . $param, $value, PDO::PARAM_STR),
        $statement
    );

    $statement->execute();

    $dbResult = $pearDB->query('SELECT MAX(nagios_id) FROM cfg_nagios');
    $nagios_id = $dbResult->fetch();
    $dbResult->closeCursor();

    if (isset($nagiosCfg['logger_version']) && $nagiosCfg['logger_version'] === 'log_v2_enabled') {
        insertLoggerV2Cfg($pearDB, $data, $nagios_id['MAX(nagios_id)']);
    }

    if (isset($_REQUEST['in_broker'])) {
        $mainCfg = new CentreonConfigEngine($pearDB);
        $mainCfg->insertBrokerDirectives($nagios_id['MAX(nagios_id)'], $_REQUEST['in_broker']);
    }

    // Manage the case where you have to main.cfg on the same poller
    if (isset($data['nagios_activate']['nagios_activate']) && $data['nagios_activate']['nagios_activate']) {
        $statement = $pearDB->prepare(
            "UPDATE cfg_nagios SET nagios_activate = '0'
             WHERE nagios_id != :nagios_id
             AND nagios_server_id = :nagios_server_id"
        );
        $statement->bindValue(':nagios_id', (int) $nagios_id['MAX(nagios_id)'], PDO::PARAM_INT);
        $statement->bindValue(':nagios_server_id', (int) $data['nagios_server_id'], PDO::PARAM_INT);
        $statement->execute();
    }

    // Prepare value for changelog
    $fields = CentreonLogAction::prepareChanges($data);
    $centreon->CentreonLogAction->insertLog(
        'engine',
        $nagios_id['MAX(nagios_id)'],
        $pearDB->escape($data['nagios_name']),
        'a',
        $fields
    );

    return $nagios_id['MAX(nagios_id)'];
}

function updateNagios($nagiosId = null)
{
    global $form, $pearDB, $centreon;

    if (! $nagiosId) {
        return;
    }

    $data = [];
    $data = $form->getSubmitValues();
    $nagiosColumns = getNagiosCfgColumnsDetails();

    $nagiosCfg = [];
    foreach ($data as $columnName => $rawValue) {
        if (! array_key_exists($columnName, $nagiosColumns)) {
            continue;
        }

        if (! empty($nagiosColumns[$columnName]['callback'])) {
            $value = isset($rawValue)
                ? ($nagiosColumns[$columnName]['callback'])($rawValue)
                : $nagiosColumns[$columnName]['default'];
        } elseif (! empty($nagiosColumns[$columnName]['isRadio'])) {
            $value = isset($rawValue[$columnName])
                ? encodeFieldNagios($rawValue[$columnName], $columnName)
                : $nagiosColumns[$columnName]['default'];
        } else {
            $value = isset($rawValue) && $rawValue !== ''
                ? encodeFieldNagios($rawValue, $columnName)
                : $nagiosColumns[$columnName]['default'];
        }
        $nagiosCfg[$columnName] = $value;
    }

    $queryPieces = array_map(fn ($columnName) => "`{$columnName}` = :{$columnName}", array_keys($nagiosCfg));

    $statement = $pearDB->prepare(
        'UPDATE cfg_nagios SET ' . implode(', ', $queryPieces) . ' WHERE nagios_id = :nagios_id'
    );
    $statement->bindValue(':nagios_id', (int) $nagiosId, PDO::PARAM_INT);

    array_walk(
        $nagiosCfg,
        fn ($value, $param, $statement) => $statement->bindValue(':' . $param, $value, PDO::PARAM_STR),
        $statement
    );

    $statement->execute();

    if (isset($nagiosCfg['logger_version']) && $nagiosCfg['logger_version'] === 'log_v2_enabled') {
        insertOrUpdateLogger($pearDB, $data, $nagiosId);
    }

    $mainCfg = new CentreonConfigEngine($pearDB);
    if (isset($_REQUEST['in_broker'])) {
        $mainCfg->insertBrokerDirectives($nagiosId, $_REQUEST['in_broker']);
    } else {
        $mainCfg->insertBrokerDirectives($nagiosId);
    }

    if ($data['nagios_activate']['nagios_activate']) {
        enableNagiosInDB($nagiosId);
    }

    // Prepare value for changelog
    $fields = CentreonLogAction::prepareChanges($data);
    $centreon->CentreonLogAction->insertLog(
        'engine',
        $nagiosId,
        $pearDB->escape($data['nagios_name']),
        'c',
        $fields
    );
}
