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

namespace Tests\Core\Command\Domain\Model;

use Centreon\Domain\Common\Assertion\AssertionException;
use Core\Command\Domain\Model\Argument;
use Core\Command\Domain\Model\CommandType;
use Core\Command\Domain\Model\NewCommand;
use Core\CommandMacro\Domain\Model\CommandMacroType;
use Core\CommandMacro\Domain\Model\NewCommandMacro;
use Core\Common\Domain\TrimmedString;
use Core\MonitoringServer\Model\MonitoringServer;

it('should return properly set instance', function (): void {
    $command = new NewCommand(
        name: new TrimmedString('command-name'),
        commandLine: new TrimmedString('commandline'),
        type: CommandType::Check,
        isShellEnabled: false,
        argumentExample: new TrimmedString('argExample'),
        arguments: [new Argument(new TrimmedString('ARG1'), new TrimmedString('arg-desc'))],
        macros: [new NewCommandMacro(CommandMacroType::Host, 'macro-name')],
        connectorId: 1,
        graphTemplateId: 2,
    );

    expect($command->getName())->toBe('command-name')
        ->and($command->getCommandLine())->toBe('commandline')
        ->and($command->getType())->toBe(CommandType::Check)
        ->and($command->isShellEnabled())->toBe(false)
        ->and($command->getArgumentExample())->toBe('argExample')
        ->and($command->getArguments()[0]->getName())->toBe('ARG1')
        ->and($command->getMacros()[0]->getName())->toBe('macro-name')
        ->and($command->getConnectorId())->toBe(1)
        ->and($command->getGraphTemplateId())->toBe(2);
});

it('should throw an exception when name is empty', function (): void {
    new NewCommand(
        name: new TrimmedString(''),
        commandLine: new TrimmedString('commandline'),
        type: CommandType::Check
    );
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::notEmptyString('NewCommand::name')->getMessage()
);

it('should throw an exception when name is too long', function (): void {
    new NewCommand(
        name: new TrimmedString(str_repeat('a', NewCommand::NAME_MAX_LENGTH + 1)),
        commandLine: new TrimmedString('commandline'),
        type: CommandType::Check
    );
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::maxLength(
        str_repeat('a', NewCommand::NAME_MAX_LENGTH + 1),
        NewCommand::NAME_MAX_LENGTH + 1,
        NewCommand::NAME_MAX_LENGTH,
        'NewCommand::name'
    )->getMessage()
);

it('should throw an exception when name contains invalid characters', function (): void {
    new NewCommand(
        name: new TrimmedString('command-name-' . MonitoringServer::ILLEGAL_CHARACTERS[0] . '-test'),
        commandLine: new TrimmedString('commandline'),
        type: CommandType::Check
    );
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::unauthorizedCharacters(
        'command-name-' . MonitoringServer::ILLEGAL_CHARACTERS[0] . '-test',
        MonitoringServer::ILLEGAL_CHARACTERS[0],
        'NewCommand::name'
    )->getMessage()
);

it('should throw an exception when command line is empty', function (): void {
    new NewCommand(
        name: new TrimmedString('command-line'),
        commandLine: new TrimmedString(''),
        type: CommandType::Check
    );
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::notEmptyString('NewCommand::commandLine')->getMessage()
);

it('should throw an exception when command line is too long', function (): void {
    new NewCommand(
        name: new TrimmedString('command-name'),
        commandLine: new TrimmedString(str_repeat('a', NewCommand::COMMAND_MAX_LENGTH + 1)),
        type: CommandType::Check
    );
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::maxLength(
        str_repeat('a', NewCommand::COMMAND_MAX_LENGTH + 1),
        NewCommand::COMMAND_MAX_LENGTH + 1,
        NewCommand::COMMAND_MAX_LENGTH,
        'NewCommand::commandLine'
    )->getMessage()
);

it('should throw an exception when argument example is too long', function (): void {
    new NewCommand(
        name: new TrimmedString('command-name'),
        commandLine: new TrimmedString('commandLine'),
        type: CommandType::Check,
        argumentExample: new TrimmedString(str_repeat('a', NewCommand::EXAMPLE_MAX_LENGTH + 1)),
    );
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::maxLength(
        str_repeat('a', NewCommand::EXAMPLE_MAX_LENGTH + 1),
        NewCommand::EXAMPLE_MAX_LENGTH + 1,
        NewCommand::EXAMPLE_MAX_LENGTH,
        'NewCommand::argumentExample'
    )->getMessage()
);

it('should throw an exception when connector ID is < 1', function (): void {
    new NewCommand(
        name: new TrimmedString('command-name'),
        commandLine: new TrimmedString('commandline'),
        type: CommandType::Check,
        connectorId: 0
    );
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::positiveInt(0, 'NewCommand::connectorId')->getMessage()
);

it('should throw an exception when graph template ID is < 1', function (): void {
    new NewCommand(
        name: new TrimmedString('command-name'),
        commandLine: new TrimmedString('commandline'),
        type: CommandType::Check,
        graphTemplateId: 0
    );
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::positiveInt(0, 'NewCommand::graphTemplateId')->getMessage()
);

