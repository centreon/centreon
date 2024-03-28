<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Core\Command\Domain\Model;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;
use Core\CommandMacro\Domain\Model\CommandMacro;
use Core\Common\Domain\SimpleEntity;
use Core\MonitoringServer\Model\MonitoringServer;

class Command
{
    public const COMMAND_MAX_LENGTH = NewCommand::COMMAND_MAX_LENGTH;
    public const EXAMPLE_MAX_LENGTH = NewCommand::EXAMPLE_MAX_LENGTH;
    public const NAME_MAX_LENGTH = NewCommand::NAME_MAX_LENGTH;

    /**
     * @param int $id
     * @param string $name
     * @param string $commandLine
     * @param bool $isShellEnabled
     * @param bool $isActivated
     * @param string $argumentExample
     * @param Argument[] $arguments
     * @param CommandMacro[] $macros
     * @param null|SimpleEntity $connector
     * @param null|SimpleEntity $graphTemplate
     * @param CommandType $type
     * @param bool $isLocked
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        private readonly int $id,
        private string $name,
        private string $commandLine,
        private bool $isShellEnabled = false,
        private bool $isActivated = true,
        private string $argumentExample = '',
        private array $arguments = [],
        private array $macros = [],
        private ?SimpleEntity $connector = null,
        private ?SimpleEntity $graphTemplate = null,
        private readonly CommandType $type = CommandType::Check,
        private readonly bool $isLocked = false,
    ) {
        Assertion::positiveInt($id, 'Command::id');

        Assertion::notEmptyString($name, 'Command::name');
        Assertion::maxLength($name, self::NAME_MAX_LENGTH, 'Command::name');
        Assertion::unauthorizedCharacters(
            $name,
            MonitoringServer::ILLEGAL_CHARACTERS,
            'Command::name'
        );

        Assertion::notEmptyString($commandLine, 'Command::commandLine');
        Assertion::maxLength($commandLine, self::COMMAND_MAX_LENGTH, 'Command::commandLine');
        Assertion::maxLength($argumentExample, self::EXAMPLE_MAX_LENGTH, 'Command::argumentExample');
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCommandLine(): string
    {
        return $this->commandLine;
    }

    public function getType(): CommandType
    {
        return $this->type;
    }

    public function isShellEnabled(): bool
    {
        return $this->isShellEnabled;
    }

    public function isActivated(): bool
    {
        return $this->isActivated;
    }

    public function isLocked(): bool
    {
        return $this->isLocked;
    }

    public function getArgumentExample(): string
    {
        return $this->argumentExample;
    }

    /**
     * @return Argument[]
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @return CommandMacro[]
     */
    public function getMacros(): array
    {
        return $this->macros;
    }

    public function getConnector(): ?SimpleEntity
    {
        return $this->connector;
    }

    public function getGraphTemplate(): ?SimpleEntity
    {
        return $this->graphTemplate;
    }

    /**
     * @param Argument[] $arguments
     */
    public function setArguments(array $arguments): void
    {
        $this->arguments = $arguments;
    }

    /**
     * @param CommandMacro[] $macros
     */
    public function setMacros(array $macros): void
    {
        $this->macros = $macros;
    }
}
