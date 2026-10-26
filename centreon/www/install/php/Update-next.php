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
use Core\Command\Domain\Model\Command;

require_once __DIR__ . '/../../../bootstrap.php';

$version = 'xx.xx.x';

$errorMessage = '';

/**
 * @var ConnectionInterface $pearDB
 * @var ConnectionInterface $pearDBO
 */

// TODO add your functions here

$removeDuplicateHostGroupRelations = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to remove the duplicate host group relations';
    LoggerUpgrade::create()->info($version, 'Removing the duplicate host group relations');

    // Saving a host group as a non-admin user used to insert again every kept host,
    // so the same host/host group pair could be stored several times. Keep the oldest row.
    $deletedRows = $pearDB->delete(
        <<<'SQL'
            DELETE duplicate
            FROM `hostgroup_relation` duplicate
            INNER JOIN `hostgroup_relation` kept
                ON kept.`hostgroup_hg_id` = duplicate.`hostgroup_hg_id`
                AND kept.`host_host_id` = duplicate.`host_host_id`
                AND kept.`hgr_id` < duplicate.`hgr_id`
            SQL
    );

    LoggerUpgrade::create()->info($version, "Successfully removed {$deletedRows} duplicate host group relations");
};

/**
 * Hosts, services and their templates checked by a Centreon Monitoring Agent command had their freshness
 * threshold forced to 120s, which triggers too many re-checks: move the ones still at 120s to the new value.
 * The command filter mirrors Command::isCentreonMonitoringAgentCommand().
 */
$updateCentreonMonitoringAgentFreshnessThreshold = function () use ($pearDB, &$errorMessage, $version): void {
    $queryParameters = QueryParameters::create([
        QueryParameter::int('previousThreshold', 120),
        QueryParameter::int('newThreshold', Command::CENTREON_MONITORING_AGENT_FRESHNESS_THRESHOLD),
    ]);

    $errorMessage = 'Unable to update the freshness threshold of hosts using a Centreon Monitoring Agent command';
    LoggerUpgrade::create()->info($version, 'Updating the freshness threshold of hosts using a Centreon Monitoring Agent command');

    $updatedHosts = $pearDB->update(
        <<<'SQL'
            UPDATE `host` h
            INNER JOIN `command` c
                ON c.`command_id` = h.`command_command_id`
            SET h.`host_freshness_threshold` = :newThreshold
            WHERE c.`command_locked` = 1
                AND (c.`command_name` LIKE BINARY '%Centreon-Monitoring-Agent%' OR c.`command_name` LIKE BINARY '%-CMA-%')
                AND h.`host_freshness_threshold` = :previousThreshold
            SQL,
        $queryParameters
    );

    LoggerUpgrade::create()->info($version, "Updated the freshness threshold of {$updatedHosts} host(s) and host template(s)");

    $errorMessage = 'Unable to update the freshness threshold of services using a Centreon Monitoring Agent command';
    LoggerUpgrade::create()->info($version, 'Updating the freshness threshold of services using a Centreon Monitoring Agent command');

    $updatedServices = $pearDB->update(
        <<<'SQL'
            UPDATE `service` s
            INNER JOIN `command` c
                ON c.`command_id` = s.`command_command_id`
            SET s.`service_freshness_threshold` = :newThreshold
            WHERE c.`command_locked` = 1
                AND (c.`command_name` LIKE BINARY '%Centreon-Monitoring-Agent%' OR c.`command_name` LIKE BINARY '%-CMA-%')
                AND s.`service_freshness_threshold` = :previousThreshold
            SQL,
        $queryParameters
    );

    LoggerUpgrade::create()->info($version, "Updated the freshness threshold of {$updatedServices} service(s) and service template(s)");
};

/**
 * Add the new Services page (React), dark-launched behind the `services_configuration` feature flag.
 * 60299 is a temporary page id: the legacy Services by host page (60201) stays in place.
 */
$addServicesConfigurationTopology = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to add the new Services page to the topology';
    LoggerUpgrade::create()->info($version, 'Adding the new Services page to the topology');

    $insertedRows = $pearDB->insert(
        <<<'SQL'
            INSERT INTO `topology` (`topology_name`, `topology_url`, `readonly`, `is_react`, `topology_parent`, `topology_page`, `topology_order`, `topology_group`, `topology_feature_flag`)
            SELECT 'Services by host', '/configuration/services', '1', '1', 602, 60299, 10, 1, 'services_configuration'
            FROM DUAL
            WHERE NOT EXISTS (SELECT 1 FROM `topology` WHERE `topology_page` = 60299)
            SQL
    );

    LoggerUpgrade::create()->info($version, "Added {$insertedRows} new Services page topology row(s)");
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
    $removeDuplicateHostGroupRelations();
    $updateCentreonMonitoringAgentFreshnessThreshold();
    $addServicesConfigurationTopology();

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
