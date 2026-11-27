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

declare(strict_types=1);

namespace Core\RemoteChannel\Infrastructure;

use Core\RemoteChannel\Application\ConfigurationDeployerInterface;
use Core\RemoteChannel\Domain\Model\DeploymentAction;
use Core\RemoteChannel\Infrastructure\Broker\BrokerConfigurationDeployer;
use Core\RemoteChannel\Infrastructure\Gorgone\GorgoneConfigurationDeployer;

/**
 * Splits the pollers by channel (nagios_server.remote_channel).
 */
final class RoutingConfigurationDeployer implements ConfigurationDeployerInterface
{
    public function __construct(
        private readonly GorgoneConfigurationDeployer $gorgoneDeployer,
        private readonly BrokerConfigurationDeployer $brokerDeployer,
    ) {
    }

    public function deploy(array $targets, DeploymentAction $action, bool $withFiles): array
    {
        $gorgone = array_values(array_filter($targets, static fn ($target): bool => ! $target->isBroker()));
        $broker = array_values(array_filter($targets, static fn ($target): bool => $target->isBroker()));

        $failures = [];
        if ($gorgone !== []) {
            $failures += $this->gorgoneDeployer->deploy($gorgone, $action, $withFiles);
        }
        if ($broker !== []) {
            $failures += $this->brokerDeployer->deploy($broker, $action, $withFiles);
        }

        return $failures;
    }
}
