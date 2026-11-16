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

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ConnectionInterface;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Adaptation\Log\LoggerUpgrade;

require_once __DIR__ . '/../../../bootstrap.php';

$version = 'xx.xx.x';

$errorMessage = '';

/**
 * @var ConnectionInterface $pearDB
 * @var ConnectionInterface $pearDBO
 */

// TODO add your functions here

/**
 * Graph data source (Administration > Parameters > Data): RRD files by default, VictoriaMetrics optional.
 */
$insertMetricsDataSourceOptions = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to insert the graph data source options';
    LoggerUpgrade::create()->info($version, 'Inserting the graph data source options');

    $defaults = [
        'metrics_data_source' => 'rrd',
        'victoria_metrics_url' => '',
        'victoria_metrics_user' => '',
        'victoria_metrics_password' => '',
    ];
    foreach ($defaults as $key => $value) {
        $exists = $pearDB->fetchOne(
            'SELECT 1 FROM `options` WHERE `key` = :key',
            QueryParameters::create([QueryParameter::string('key', $key)])
        );
        if ($exists === false) {
            $pearDB->insert(
                'INSERT INTO `options` (`key`, `value`) VALUES (:key, :value)',
                QueryParameters::create([QueryParameter::string('key', $key), QueryParameter::string('value', $value)])
            );
        }
    }
};

/**
 * Broker output "victoria_metrics" offered in Configuration > Pollers > Broker configuration, with the
 * keys of the Broker factory (broker/http_tsdb/src/factory.cc and broker/victoria_metrics/src/factory.cc).
 */
$createBrokerOutputVictoriaMetrics = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to create the Broker output victoria_metrics';
    LoggerUpgrade::create()->info($version, 'Creating the Broker output victoria_metrics');

    $moduleId = $pearDB->fetchOne("SELECT `cb_module_id` FROM `cb_module` WHERE `name` = 'VictoriaMetrics'");
    if ($moduleId === false) {
        $pearDB->insert(
            <<<'SQL'
                INSERT INTO `cb_module` (`name`, `libname`, `loading_pos`, `is_bundle`, `is_activated`)
                VALUES ('VictoriaMetrics', 'victoria_metrics.so', 22, 0, 1)
                SQL
        );
        $moduleId = $pearDB->fetchOne("SELECT `cb_module_id` FROM `cb_module` WHERE `name` = 'VictoriaMetrics'");
    }

    $typeId = $pearDB->fetchOne("SELECT `cb_type_id` FROM `cb_type` WHERE `type_shortname` = 'victoria_metrics'");
    if ($typeId === false) {
        $pearDB->insert(
            <<<'SQL'
                INSERT INTO `cb_type` (`type_name`, `type_shortname`, `cb_module_id`)
                VALUES ('Storage - VictoriaMetrics', 'victoria_metrics', :module_id)
                SQL,
            QueryParameters::create([QueryParameter::int('module_id', (int) $moduleId)])
        );
        $typeId = $pearDB->fetchOne("SELECT `cb_type_id` FROM `cb_type` WHERE `type_shortname` = 'victoria_metrics'");
    }
    $typeId = (int) $typeId;

    $tagRelation = $pearDB->fetchOne(
        'SELECT 1 FROM `cb_tag_type_relation` WHERE `cb_type_id` = :type_id AND `cb_tag_id` = 1',
        QueryParameters::create([QueryParameter::int('type_id', $typeId)])
    );
    if ($tagRelation === false) {
        $pearDB->insert(
            'INSERT INTO `cb_tag_type_relation` (`cb_tag_id`, `cb_type_id`, `cb_type_uniq`) VALUES (1, :type_id, 0)',
            QueryParameters::create([QueryParameter::int('type_id', $typeId)])
        );
    }

    // Fields of this output only (the others are shared with the InfluxDB output and the BBDO streams)
    $newFields = [
        'http_target' => ['HTTP write target', 'Path of the write endpoint (default: /write; Broker adds precision=s)'],
        'http_delete_target' => [
            'HTTP delete target',
            'Path of the endpoint that deletes the series of a rebuilt or removed graph '
                . '(default: /api/v1/admin/tsdb/delete_series)',
        ],
        'http_series_target' => [
            'HTTP series target',
            'Path of the endpoint that lists the series of a metric to rebuild (default: /api/v1/series)',
        ],
    ];
    $fieldIds = [];
    foreach ($newFields as $fieldName => [$displayName, $description]) {
        $fieldId = $pearDB->fetchOne(
            'SELECT `cb_field_id` FROM `cb_field` WHERE `fieldname` = :name',
            QueryParameters::create([QueryParameter::string('name', $fieldName)])
        );
        if ($fieldId === false) {
            $pearDB->insert(
                <<<'SQL'
                    INSERT INTO `cb_field` (`fieldname`, `displayname`, `description`, `fieldtype`, `external`)
                    VALUES (:name, :display_name, :description, 'text', NULL)
                    SQL,
                QueryParameters::create([
                    QueryParameter::string('name', $fieldName),
                    QueryParameter::string('display_name', $displayName),
                    QueryParameter::string('description', $description),
                ])
            );
            $fieldId = $pearDB->fetchOne(
                'SELECT `cb_field_id` FROM `cb_field` WHERE `fieldname` = :name',
                QueryParameters::create([QueryParameter::string('name', $fieldName)])
            );
        }
        $fieldIds[$fieldName] = (int) $fieldId;
    }

    // Shared fields, found through the outputs that already use them
    $findSharedField = function (string $typeName, string $fieldName, ?string $groupName = null) use ($pearDB): int {
        $fieldId = $pearDB->fetchOne(
            <<<'SQL'
                SELECT f.`cb_field_id`
                FROM `cb_field` f
                INNER JOIN `cb_type_field_relation` r ON r.`cb_field_id` = f.`cb_field_id`
                INNER JOIN `cb_type` t ON t.`cb_type_id` = r.`cb_type_id`
                LEFT JOIN `cb_fieldgroup` g ON g.`cb_fieldgroup_id` = f.`cb_fieldgroup_id`
                WHERE t.`type_shortname` = :type_name
                    AND f.`fieldname` = :field_name
                    AND COALESCE(g.`groupname`, '') = :group_name
                LIMIT 1
                SQL,
            QueryParameters::create([
                QueryParameter::string('type_name', $typeName),
                QueryParameter::string('field_name', $fieldName),
                QueryParameter::string('group_name', $groupName ?? ''),
            ])
        );
        if ($fieldId === false) {
            throw new RuntimeException("Broker field {$fieldName} of the {$typeName} output not found");
        }

        return (int) $fieldId;
    };

    // [field id, is required], in display order
    $relations = [
        [$findSharedField('influxdb', 'db_host'), 1],
        [$findSharedField('influxdb', 'db_port'), 0],
        [$findSharedField('bbdo_server', 'encryption'), 0],
        [$findSharedField('influxdb', 'db_user'), 0],
        [$findSharedField('influxdb', 'db_password'), 0],
        [$fieldIds['http_target'], 0],
        [$fieldIds['http_delete_target'], 0],
        [$fieldIds['http_series_target'], 0],
        [$findSharedField('graphite', 'queries_per_transaction'), 0],
    ];
    foreach (['metrics_column', 'status_column'] as $groupName) {
        foreach (['name', 'value', 'type', 'is_tag'] as $fieldName) {
            $relations[] = [$findSharedField('influxdb', $fieldName, $groupName), 0];
        }
    }
    $hasRelations = $pearDB->fetchOne(
        'SELECT 1 FROM `cb_type_field_relation` WHERE `cb_type_id` = :type_id',
        QueryParameters::create([QueryParameter::int('type_id', $typeId)])
    );
    if ($hasRelations === false) {
        foreach ($relations as $order => [$fieldId, $isRequired]) {
            $pearDB->insert(
                <<<'SQL'
                    INSERT INTO `cb_type_field_relation` (`cb_type_id`, `cb_field_id`, `is_required`, `order_display`)
                    VALUES (:type_id, :field_id, :is_required, :order_display)
                    SQL,
                QueryParameters::create([
                    QueryParameter::int('type_id', $typeId),
                    QueryParameter::int('field_id', $fieldId),
                    QueryParameter::int('is_required', $isRequired),
                    QueryParameter::int('order_display', $order + 1),
                ])
            );
        }
    }

    LoggerUpgrade::create()->info($version, 'Broker output victoria_metrics is available');
};

try {
    LoggerUpgrade::create()->info($version, "Starting upgrade script for version {$version}");

    // DDL statements for real time database
    // TODO add your function calls to update the real time database structure here

    // DDL statements for configuration database
    // TODO add your function calls to update the configuration database structure here

    // Transactional queries for configuration database
    $errorMessage = 'Unable to start the configuration database transaction';
    if (! $pearDB->isTransactionActive()) {
        $pearDB->startTransaction();
    }

    // TODO add your function calls to update the configuration database data here
    $insertMetricsDataSourceOptions();
    $createBrokerOutputVictoriaMetrics();

    $errorMessage = 'Unable to commit the configuration database transaction';
    $pearDB->commitTransaction();

    LoggerUpgrade::create()->info($version, "Upgrade script for version {$version} completed");

} catch (Throwable $throwable) {
    try {
        if ($pearDB->isTransactionActive()) {
            LoggerUpgrade::create()->info($version, "Rolling back transaction after error: {$errorMessage}");
            $pearDB->rollBackTransaction();
        }
    } catch (ConnectionException $rollbackException) {
        LoggerUpgrade::create()->stepFailure(
            $version,
            'php_script_rollback',
            "UPGRADE - {$version}: error while rolling back the upgrade operation for : {$errorMessage}",
            $rollbackException
        );

        throw new RuntimeException(
            message: "UPGRADE - {$version}: " . $errorMessage,
            previous: $throwable
        );
    }

    throw new RuntimeException(
        message: "UPGRADE - {$version}: " . $errorMessage,
        previous: $throwable
    );
}
