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

namespace App\MonitoringConfiguration\Application\Command;

use App\MonitoringConfiguration\Domain\Service\ServiceDeployer;
use App\Shared\Application\Command\AsCommandHandler;

/**
 * Failures propagate: an explicit deployment request must be able to fail. The host-creation path
 * swallows them instead, see DeployHostServicesEventHandler.
 */
#[AsCommandHandler]
final readonly class DeployHostServicesCommandHandler
{
    public function __construct(
        private ServiceDeployer $serviceDeployer,
    ) {
    }

    public function __invoke(DeployHostServicesCommand $command): void
    {
        $this->serviceDeployer->deployFromTemplates($command->hostId, $command->requestedBy);
    }
}
