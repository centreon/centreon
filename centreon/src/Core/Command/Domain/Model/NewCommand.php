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
use Core\CommandMacro\Domain\Model\NewCommandMacro;
use Core\Common\Domain\TrimmedString;
use Core\MonitoringServer\Model\MonitoringServer;

class NewCommand
{
    public const COMMAND_MAX_LENGTH = 65535;
    public const EXAMPLE_MAX_LENGTH = 255;
    public const NAME_MAX_LENGTH = 200;

    /**
     * @param TrimmedString $name
     * @param TrimmedString $commandLine
     * @param bool $isShellEnabled
     * @param CommandType $type
     * @param TrimmedString $argumentExample
     * @param Argument[] $arguments
     * @param NewCommandMacro[] $macros
     * @param null|int $connectorId
     * @param null|int $graphTemplateId
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        private readonly TrimmedString $name,
        private readonly TrimmedString $commandLine,
        private readonly bool $isShellEnabled = false,
        private readonly CommandType $type = CommandType::Check,
        private readonly TrimmedString $argumentExample = new TrimmedString(''),
        private readonly array $arguments = [],
        private readonly array $macros = [],
        private readonly ?int $connectorId = null,
        private readonly ?int $graphTemplateId = null,
    ) {
        Assertion::notEmptyString($name->value, 'NewCommand::name');
        Assertion::maxLength($name->value, self::NAME_MAX_LENGTH, 'NewCommand::name');
        Assertion::unauthorizedCharacters(
            $name->value,
            MonitoringServer::ILLEGAL_CHARACTERS,
            'NewCommand::name'
        );

        Assertion::notEmptyString($commandLine->value, 'NewCommand::commandLine');
        Assertion::maxLength($commandLine->value, self::COMMAND_MAX_LENGTH, 'NewCommand::commandLine');
        Assertion::maxLength($argumentExample->value, self::EXAMPLE_MAX_LENGTH, 'NewCommand::argumentExample');

        if ($connectorId !== null) {
            Assertion::positiveInt($connectorId, 'NewCommand::connectorId');
        }
        if ($graphTemplateId !== null) {
            Assertion::positiveInt($graphTemplateId, 'NewCommand::graphTemplateId');
        }
    }

    public function getName(): string
    {
        return $this->name->value;
    }

    public function getCommandLine(): string
    {
        return $this->commandLine->value;
    }

    public function getType(): CommandType
    {
        return $this->type;
    }

    public function isShellEnabled(): bool
    {
        return $this->isShellEnabled;
    }

    public function getArgumentExample(): string
    {
        return $this->argumentExample->value;
    }

    /**
     * @return Argument[]
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @return NewCommandMacro[]
     */
    public function getMacros(): array
    {
        return $this->macros;
    }

    public function getConnectorId(): ?int
    {
        return $this->connectorId;
    }

    public function getGraphTemplateId(): ?int
    {
        return $this->graphTemplateId;
    }

    /**
     * @param Command $command
     *
     * @throws AssertionFailedException
     *
     * @return NewCommand
     */
    public static function createFromCommand(Command $command): self
    {
        return new self(
            name: new TrimmedString($command->getName()),
            commandLine: new TrimmedString($command->getCommandLine()),
            isShellEnabled: $command->isShellEnabled(),
            type: $command->getType(),
            argumentExample: new TrimmedString($command->getArgumentExample()),
            arguments: $command->getArguments(),
            macros: array_map(
                fn(CommandMacro $macro) => NewCommandMacro::createFromMacro($macro),
                $command->getMacros()
            ),
            connectorId: $command->getConnector()?->getId(),
            graphTemplateId: $command->getGraphTemplate()?->getId(),
        );
    }
}
