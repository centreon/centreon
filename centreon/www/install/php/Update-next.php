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

require_once __DIR__ . '/../../../bootstrap.php';

$version = 'xx.xx.x';

$errorMessage = '';

/**
 * @var ConnectionInterface $pearDB
 * @var ConnectionInterface $pearDBO
 */

/** -------------------------------------- Host Group Topology -------------------------------------- */
$fixDuplicateHostGroupTopology = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to fix duplicate Host Groups topology';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: [topology] Fixing duplicate Host Groups menu entries",
    );

    $pearDB->update(
        <<<'SQL'
            UPDATE `topology`
            SET `topology_url` = '/configuration/hosts/groups',
                `is_react` = '1',
                `topology_show` = '1'
            WHERE `topology_page` = 60102
            SQL
    );

    // Remove duplicate topology entry 60105 introduced by 25.05 migration
    $pearDB->delete(
        <<<'SQL'
            DELETE FROM `topology`
            WHERE `topology_page` = 60105
            SQL
    );

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: [topology] Successfully removed duplicate Host Groups topology entry",
    );
};

/** -------------------------------------- Broker Instances CMA fields -------------------------------------- */
$updateInstancesTable = function () use ($pearDBO, &$errorMessage, $version): void {
    $errorMessage = 'Unable to add CMA certificate fields to broker instances table';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: [broker instances] Adding CMA certificate fields to broker instances table",
    );

    if (
        $pearDBO->columnExists(
            $pearDBO->getConnectionConfig()->getDatabaseNameConfiguration(),
            'instances',
            'cma_certificate_sha'
        )
        || $pearDBO->columnExists(
            $pearDBO->getConnectionConfig()->getDatabaseNameConfiguration(),
            'instances',
            'cma_certificate_cn'
        )
        || $pearDBO->columnExists(
            $pearDBO->getConnectionConfig()->getDatabaseNameConfiguration(),
            'instances',
            'cma_certificate_peremption'
        )
    ) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: [broker instances] CMA certificate fields already exist in broker instances table, skipping",
        );

        return;
    }

    $pearDBO->query(
        <<<'SQL'
            ALTER TABLE `instances`
            ADD COLUMN `cma_certificate_sha` VARCHAR(255) DEFAULT NULL COMMENT 'CMA certificate fingerprint',
            ADD COLUMN `cma_certificate_cn` VARCHAR(255) DEFAULT NULL COMMENT 'CMA certificate host name',
            ADD COLUMN `cma_certificate_peremption` INT(11) DEFAULT NULL COMMENT 'CMA certificate peremption timestamp'
            SQL
    );

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: [broker instances] Successfully added CMA certificate fields to broker instances table",
    );
};

/** -------------------------------------- Migrates dashboard widget metrics -------------------------------------- */
/**
 * Migrates dashboard widget metrics to add missing serviceId and serviceName fields.
 *
 * This migration is required because the commit f36af34415 changed the endpoints for
 * single metric widgets to use serviceId/serviceName, but existing dashboards don't
 * have these fields in their widget_settings.
 */
$migrateWidgetMetricsWithServiceInfo = function () use ($pearDB, $pearDBO, &$errorMessage, $version): void {
    $errorMessage = 'Unable to retrieve dashboard panels';

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Starting migration of dashboard widget metrics to add serviceId and serviceName"
    );

    // Widget types that use metrics and need migration
    $widgetTypesToMigrate = [
        '/widgets/singlemetric',
        '/widgets/graph',
        '/widgets/topbottom',
        '/widgets/metriccapacityplanning',
    ];

    $widgetTypesPlaceholders = implode(
        ',',
        array_map(fn ($key) => ':widget_type_' . $key, array_keys($widgetTypesToMigrate))
    );

    // Retrieve all dashboard panels with metric-related widgets
    $queryParams = [];
    foreach ($widgetTypesToMigrate as $key => $widgetType) {
        $queryParams[] = QueryParameter::string('widget_type_' . $key, $widgetType);
    }

    $panels = $pearDB->fetchAllAssociative(
        <<<SQL
            SELECT id, widget_settings
            FROM dashboard_panel
            WHERE widget_type IN ({$widgetTypesPlaceholders})
            SQL,
        QueryParameters::create($queryParams)
    );

    if ($panels === []) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: No dashboard panels with metric widgets found, skipping migration"
        );

        return;
    }

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Found " . count($panels) . ' dashboard panels to check for migration'
    );

    // Decode panels & collect metric IDs to migrate
    $decodedPanels = [];
    $metricIds = [];

    foreach ($panels as $panel) {
        $panelId = (int) $panel['id'];

        try {
            $settings = json_decode($panel['widget_settings'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            CentreonLog::create()->warning(
                logTypeId: CentreonLog::TYPE_UPGRADE,
                message: "UPGRADE - {$version}: Unable to decode widget_settings for panel {$panelId}, skipping"
            );
            continue;
        }

        if (! isset($settings['data']['metrics']) || ! is_array($settings['data']['metrics'])) {
            continue;
        }

        $decodedPanels[] = [
            'id' => $panelId,
            'settings' => $settings,
        ];

        foreach ($settings['data']['metrics'] as $metric) {
            if (
                ! isset($metric['serviceId'], $metric['serviceName'])
                && isset($metric['id'])
            ) {
                $metricIds[(int) $metric['id']] = true;
            }
        }
    }

    if ($metricIds === []) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: No metrics require migration, skipping"
        );

        return;
    }

    // Fetch only required metrics from centreon_storage
    $errorMessage = 'Unable to retrieve metrics information from centreon_storage';

    $metricPlaceholders = [];
    $metricParams = [];

    foreach (array_keys($metricIds) as $i => $metricId) {
        $metricPlaceholders[] = ':metric_id_' . $i;
        $metricParams[] = QueryParameter::int('metric_id_' . $i, $metricId);
    }

    $metricsCache = [];
    $metricIdPlaceholders = implode(',', $metricPlaceholders);
    $query = <<<'SQL'
        SELECT
            m.metric_id,
            i.service_id,
            i.service_description
        FROM metrics m
        INNER JOIN index_data i ON m.index_id = i.id
        SQL;
    $query .= " WHERE m.metric_id IN ({$metricIdPlaceholders})";
    $metricsData = $pearDBO->fetchAllAssociative(
        $query,
        QueryParameters::create($metricParams)
    );

    foreach ($metricsData as $row) {
        $metricsCache[(int) $row['metric_id']] = [
            'serviceId' => (int) $row['service_id'],
            'serviceName' => $row['service_description'],
        ];
    }

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Built cache with " . count($metricsCache) . ' metrics'
    );

    // Update dashboard panels
    $updatedPanelsCount = 0;

    foreach ($decodedPanels as $panel) {
        $panelId = $panel['id'];
        $settings = $panel['settings'];
        $needsUpdate = false;

        foreach ($settings['data']['metrics'] as $key => $metric) {
            // Skip if already has serviceId and serviceName
            if (isset($metric['serviceId'], $metric['serviceName'])) {
                continue;
            }

            // Get the metric ID
            $metricId = $metric['id'] ?? null;
            if ($metricId === null || ! isset($metricsCache[$metricId])) {
                continue;
            }

            $settings['data']['metrics'][$key]['serviceId'] = $metricsCache[$metricId]['serviceId'];
            $settings['data']['metrics'][$key]['serviceName'] = $metricsCache[$metricId]['serviceName'];
            $needsUpdate = true;
        }

        if (! $needsUpdate) {
            continue;
        }

        $errorMessage = "Unable to update dashboard panel {$panelId}";

        try {
            $updatedSettings = json_encode($settings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            CentreonLog::create()->warning(
                logTypeId: CentreonLog::TYPE_UPGRADE,
                message: "UPGRADE - {$version}: Unable to encode updated widget_settings for panel {$panelId}, skipping"
            );
            continue;
        }

        $pearDB->update(
            <<<'SQL'
                UPDATE dashboard_panel
                SET widget_settings = :widget_settings
                WHERE id = :id
                SQL,
            QueryParameters::create([
                QueryParameter::string('widget_settings', $updatedSettings),
                QueryParameter::int('id', $panelId),
            ])
        );

        $updatedPanelsCount++;
    }

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Successfully updated {$updatedPanelsCount} dashboard panels with serviceId and serviceName"
    );
};

try {
    // DDL statements for real time database
    $updateInstancesTable();

    // DDL statements for configuration database
    // TODO add your function calls to update the configuration database structure here

    // Transactional queries for configuration database
    if (! $pearDB->isTransactionActive()) {
        $pearDB->startTransaction();
    }

    $fixDuplicateHostGroupTopology();
    $migrateWidgetMetricsWithServiceInfo();

    $pearDB->commitTransaction();

} catch (Throwable $throwable) {
    CentreonLog::create()->error(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: " . $errorMessage,
        exception: $throwable
    );

    try {
        if ($pearDB->isTransactionActive()) {
            $pearDB->rollBackTransaction();
        }
    } catch (ConnectionException $rollbackException) {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: error while rolling back the upgrade operation for : {$errorMessage}",
            exception: $rollbackException
        );

        throw new RuntimeException(
            message: "UPGRADE - {$version}: error while rolling back the upgrade operation for : {$errorMessage}",
            previous: $rollbackException
        );
    }

    throw new RuntimeException(
        message: "UPGRADE - {$version}: " . $errorMessage,
        previous: $throwable
    );
}
