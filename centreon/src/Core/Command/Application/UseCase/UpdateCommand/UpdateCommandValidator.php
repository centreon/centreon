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

namespace Core\Command\Application\UseCase\UpdateCommand;

use Centreon\Domain\Log\LoggerTrait;
use Core\Command\Application\Exception\CommandException;
use Core\Command\Application\Repository\ReadCommandRepositoryInterface;
use Core\CommandMacro\Domain\Model\CommandMacroType;
use Core\Command\Domain\Model\Command;
use Core\Common\Domain\TrimmedString;
use Core\Connector\Application\Repository\ReadConnectorRepositoryInterface;
use Core\GraphTemplate\Application\Repository\ReadGraphTemplateRepositoryInterface;

class UpdateCommandValidator
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadCommandRepositoryInterface $readCommandRepository,
        private readonly ReadConnectorRepositoryInterface $readConnectorRepository,
        private readonly ReadGraphTemplateRepositoryInterface $readGraphTemplateRepository,
    ) {
    }

    /**
     * Assert that the host group name is not already used.
     *
     * @param Command $command
     * @param string $commandName
     *
     * @throws CommandException|\Throwable
     */
    public function assertNameDoesNotAlreadyExists(Command $command, string $commandName): void
    {
        $commandName = new TrimmedString($commandName);
        if ($command->getName() != (string) $commandName && $this->readCommandRepository->existsByName($commandName)) {
            throw CommandException::nameAlreadyExists((string) $commandName);
        }
    }

    /**
     * Assert that the defined arguments are present in the command_line and matching the required pattern ('/\$(?<args>ARG\d+)\$/').
     *
     * @param UpdateCommandRequest $request
     *
     * @throws \Throwable
     */
    public function assertAreValidArguments(UpdateCommandRequest $request): void
    {
        $argumentNames = array_unique(array_map(fn (ArgumentDto $arg) => $arg->name, $request->arguments));

        preg_match_all('/\$(?<args>ARG\d+)\$/', $request->commandLine, $matches);
        $commandArguments = array_unique($matches['args']);

        $invalidArguments = [];
        foreach ($argumentNames as $argumentName) {
            if (! in_array($argumentName, $commandArguments, true)) {
                $invalidArguments[] = $argumentName;
            }
        }

        if ($invalidArguments !== []) {
            throw CommandException::invalidArguments($invalidArguments);
        }
    }

    /**
     * Assert the defined macros are present in the command_line in the format required for their defined type.
     * '/(\$_HOST(?<macros_h>\w+)\$)/' => for host macros
     * '/(\$_SERVICE(?<macros_s>\w+)\$)/' => for service macros.
     *
     * @param UpdateCommandRequest $request
     *
     * @throws \Throwable
     */
    public function assertAreValidMacros(UpdateCommandRequest $request): void
    {
        preg_match_all(
            '/(\$_HOST(?<macros_h>\w+)\$)|(\$_SERVICE(?<macros_s>\w+)\$)/',
            $request->commandLine,
            $matches
        );
        $commandHostMacros = array_unique($matches['macros_h']);
        $commandServiceMacros = array_unique($matches['macros_s']);

        $invalidMacros = [];
        foreach ($request->macros as $macro) {
            if (
                $macro->type === CommandMacroType::Host
                && ! in_array($macro->name, $commandHostMacros, true)
            ) {
                $invalidMacros[] = $macro->name;
            }
            if (
                $macro->type === CommandMacroType::Service
                && ! in_array($macro->name, $commandServiceMacros, true)
            ) {
                $invalidMacros[] = $macro->name;
            }
        }

        if ($invalidMacros !== []) {
            throw CommandException::invalidMacros($invalidMacros);
        }
    }

    public function assertIsValidConnector(UpdateCommandRequest $request): void
    {
        if (
            $request->connectorId !== null
            && ! $this->readConnectorRepository->exists($request->connectorId)
        ) {
            throw CommandException::idDoesNotExist('connectorId', $request->connectorId);
        }
    }

    public function assertIsValidGraphTemplate(UpdateCommandRequest $request): void
    {
        if (
            $request->graphTemplateId !== null
            && ! $this->readGraphTemplateRepository->exists($request->graphTemplateId)
        ) {
            throw CommandException::idDoesNotExist('graphTemplateId', $request->graphTemplateId);
        }
    }
}