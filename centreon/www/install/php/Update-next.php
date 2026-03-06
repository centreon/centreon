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
use Adaptation\Database\Connection\ValueObject\QueryParameter;
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
    $adminInfos = $pearDB->fetchAssociative(
        "SELECT `contact_id`, `contact_alias` FROM `contact` WHERE `contact_admin` = '1' LIMIT 1"
    );
    if ($adminInfos === false) {
        CentreonLog::create()->warning(
            CentreonLog::TYPE_BUSINESS_LOG,
            'No admin contact found, skipping default agent configuration deployment'
        );

        return;
    }

    $errorMessage = 'Error during default agent configuration deployment';
    $request = new DeployDefaultAgentConfigurationForPollerRequest(
        pollerId: (int) $centralId,
        creatorId: (int) $adminInfos['contact_id'],
        creatorName: $adminInfos['contact_alias'],
    );
    $deployAgentConfiguration($request);
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Successfully deployed default agent configuration to central poller",
    );
};

// TODO add your functions here

try {
    // DDL statements for real time database
    // TODO add your function calls to update the real time database structure here

    // DDL statements for configuration database
    // TODO add your function calls to update the configuration database structure here

    // Transactional queries for configuration database
    if (! $pearDB->isTransactionActive()) {
        $pearDB->startTransaction();
    }

    // TODO add your function calls to update the configuration database data here

    $pearDB->commitTransaction();

    try {
        $deployDefaultAgentConfiguration();
    } catch (Throwable $e) {
        CentreonLog::create()->warning(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: Default agent configuration deployment failed, it can be done manually",
            exception: $e
        );
    }
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
