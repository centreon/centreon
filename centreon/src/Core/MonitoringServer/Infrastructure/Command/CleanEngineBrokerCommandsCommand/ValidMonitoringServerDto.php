<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

namespace Core\MonitoringServer\Infrastructure\Command\CleanEngineBrokerCommandsCommand;

use Core\MonitoringServer\Model\MonitoringServer;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ValidMonitoringServerDto
{
    public function __construct(
        public int $id,
        public string $name,
        #[Assert\Regex(
            pattern: MonitoringServer::VALID_COMMAND_START_REGEX,
            message: 'Invalid command format.'
        )]
        public ?string $engineStartCommand = null,
        #[Assert\Regex(
            pattern: MonitoringServer::VALID_COMMAND_STOP_REGEX,
            message: 'Invalid command format.'
        )]
        public ?string $engineStopCommand = null,
        #[Assert\Regex(
            pattern: MonitoringServer::VALID_COMMAND_RESTART_REGEX,
            message: 'Invalid command format.'
        )]
        public ?string $engineRestartCommand = null,
        #[Assert\Regex(
            pattern: MonitoringServer::VALID_COMMAND_RELOAD_REGEX,
            message: 'Invalid command format.'
        )]
        public ?string $engineReloadCommand = null,
        #[Assert\Regex(
            pattern: MonitoringServer::VALID_COMMAND_RELOAD_REGEX,
            message: 'Invalid command format.'
        )]
        public ?string $brokerReloadCommand = null,
    ) {
    }

    public static function fromModel(MonitoringServer $monitoringServer): self
    {
        return new self(
            id: $monitoringServer->getId(),
            name: $monitoringServer->getName(),
            engineStartCommand: $monitoringServer->getEngineStartCommand(),
            engineStopCommand: $monitoringServer->getEngineStopCommand(),
            engineRestartCommand: $monitoringServer->getEngineRestartCommand(),
            engineReloadCommand: $monitoringServer->getEngineReloadCommand(),
            brokerReloadCommand: $monitoringServer->getBrokerReloadCommand()
        );
    }
}
