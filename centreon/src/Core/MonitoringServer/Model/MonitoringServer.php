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

namespace Core\MonitoringServer\Model;

use Assert\Assertion;

class MonitoringServer
{
    public const ILLEGAL_CHARACTERS = '~!$%^&*"|\'<>?,()=';
    public const DEFAULT_ENGINE_START_COMMAND = 'systemctl start centengine';
    public const DEFAULT_ENGINE_STOP_COMMAND = 'systemctl stop centengine';
    public const DEFAULT_ENGINE_RESTART_COMMAND = 'systemctl restart centengine';
    public const DEFAULT_ENGINE_RELOAD_COMMAND = 'systemctl reload centengine';
    public const DEFAULT_BROKER_RELOAD_COMMAND = 'systemctl reload cbd';
    public const VALID_COMMAND_START_REGEX = '/^(?:service\s+[a-zA-Z0-9_-]+\s+start|systemctl\s+start\s+[a-zA-Z0-9_-]+)$/';
    public const VALID_COMMAND_STOP_REGEX = '/^(?:service\s+[a-zA-Z0-9_-]+\s+stop|systemctl\s+stop\s+[a-zA-Z0-9_-]+)$/';
    public const VALID_COMMAND_RESTART_REGEX = '/^(?:service\s+[a-zA-Z0-9_-]+\s+restart|systemctl\s+restart\s+[a-zA-Z0-9_-]+)$/';
    public const VALID_COMMAND_RELOAD_REGEX = '/^(?:service\s+[a-zA-Z0-9_-]+\s+reload|systemctl\s+reload\s+[a-zA-Z0-9_-]+)$/';

    public function __construct(
        private readonly int $id,
        private string $name,
        private ?string $engineStartCommand = null,
        private ?string $engineStopCommand = null,
        private ?string $engineRestartCommand = null,
        private ?string $engineReloadCommand = null,
        private ?string $brokerReloadCommand = null,
    ) {
        $this->name = trim($name);
        Assertion::notEmpty($this->name);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEngineStartCommand(): ?string
    {
        return $this->engineStartCommand;
    }

    public function getEngineStopCommand(): ?string
    {
        return $this->engineStopCommand;
    }

    public function getEngineRestartCommand(): ?string
    {
        return $this->engineRestartCommand;
    }

    public function getEngineReloadCommand(): ?string
    {
        return $this->engineReloadCommand;
    }

    public function getBrokerReloadCommand(): ?string
    {
        return $this->brokerReloadCommand;
    }

    public function update(
        string $name,
        ?string $engineStartCommand,
        ?string $engineStopCommand,
        ?string $engineReloadCommand,
        ?string $engineRestartCommand,
        ?string $brokerReloadCommand,
    ): void {
        Assertion::notEmpty($name);
        Assertion::nullOrRegex($engineStartCommand, self::VALID_COMMAND_START_REGEX);
        Assertion::nullOrRegex($engineStopCommand, self::VALID_COMMAND_STOP_REGEX);
        Assertion::nullOrRegex($engineReloadCommand, self::VALID_COMMAND_RELOAD_REGEX);
        Assertion::nullOrRegex($engineRestartCommand, self::VALID_COMMAND_RESTART_REGEX);
        Assertion::nullOrRegex($brokerReloadCommand, self::VALID_COMMAND_RELOAD_REGEX);
        $this->name = $name;
        $this->engineStartCommand = $engineStartCommand;
        $this->engineStopCommand = $engineStopCommand;
        $this->engineReloadCommand = $engineReloadCommand;
        $this->engineRestartCommand = $engineRestartCommand;
        $this->brokerReloadCommand = $brokerReloadCommand;
    }
}
