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

use Adaptation\Database\Connection\ConnectionInterface;
use Adaptation\Database\Connection\Exception\ConnectionException;
use App\Kernel;
use Core\AgentConfiguration\Application\UseCase\DeployDefaultAgentConfigurationForPoller\{
    DeployDefaultAgentConfigurationForPoller,
    DeployDefaultAgentConfigurationForPollerRequest
};

require_once __DIR__ . '/../../../bootstrap.php';

$version = 'xx.xx.x';

$errorMessage = '';

/**
 * @var ConnectionInterface $pearDB
 * @var ConnectionInterface $pearDBO
 */
$deployDefaultAgentConfiguration = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to deploy default agent configuration to central poller';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Deploying default agent configuration to central poller",
    );
    $kernel = Kernel::createForWeb();
    $deployAgentConfiguration = $kernel->getContainer()
        ->get(DeployDefaultAgentConfigurationForPoller::class);
    if (! $deployAgentConfiguration instanceof DeployDefaultAgentConfigurationForPoller) {
        CentreonLog::create()->warning(
            CentreonLog::TYPE_BUSINESS_LOG,
            'DeployDefaultAgentConfigurationForPoller service not found, skipping default agent configuration deployment'
        );

        return;
    }

    $errorMessage = 'Unable to find central poller to deploy default agent configuration';
    $centralId = $pearDB->fetchOne(
        "SELECT `id` FROM `nagios_server` WHERE `is_default` = 1 AND `localhost` = '1'"
    );
    if ($centralId === false) {
        CentreonLog::create()->warning(
            CentreonLog::TYPE_BUSINESS_LOG,
            'Default central poller not found, skipping default agent configuration deployment'
        );

        return;
    }

    $errorMessage = 'Unable to find admin contact to deploy default agent configuration';
    $adminInfos = $pearDB->fetchAllAssociative(
        "SELECT `contact_id`, `contact_alias` FROM `contact` WHERE `contact_admin` = '1' LIMIT 1"
    );
    if ($adminInfos === []) {
        CentreonLog::create()->warning(
            CentreonLog::TYPE_BUSINESS_LOG,
            'No admin contact found, skipping default agent configuration deployment'
        );

        return;
    }

    $errorMessage = 'Error during default agent configuration deployment';
    $request = new DeployDefaultAgentConfigurationForPollerRequest(
        pollerId: $centralId,
        creatorId: $adminInfos[0]['contact_id'],
        creatorName: $adminInfos[0]['contact_alias'],
    );
    $deployAgentConfiguration($request);
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Successfully deployed default agent configuration to central poller",
    );
};

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
    $deployDefaultAgentConfiguration();

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
