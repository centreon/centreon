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

namespace App\MonitoringConfiguration\Application\EventHandler;

use App\MonitoringConfiguration\Domain\Event\HostServicesDeploymentRequested;
use App\MonitoringConfiguration\Domain\Service\ServiceDeployer;
use App\Shared\Domain\Event\AsEventHandler;
use Psr\Log\LoggerInterface;

/**
 * A failure is logged and swallowed: the host is already committed, and legacy behaves the same,
 * deployment being a separate call there.
 */
#[AsEventHandler]
final readonly class DeployHostServicesEventHandler
{
    public function __construct(
        private ServiceDeployer $serviceDeployer,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(HostServicesDeploymentRequested $event): void
    {
        try {
            $this->serviceDeployer->deployFromTemplates($event->hostId, $event->requestedBy);
        } catch (\Throwable $exception) {
            $this->logger->error('Unable to deploy the services of the newly created host', [
                'host_id' => $event->hostId->value,
                'exception' => $exception,
            ]);
        }
    }
}
