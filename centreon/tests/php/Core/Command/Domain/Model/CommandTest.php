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
use Core\Command\Domain\Model\Command;
use Core\Command\Domain\Model\CommandType;
use Core\CommandMacro\Domain\Model\CommandMacro;
use Core\CommandMacro\Domain\Model\CommandMacroType;
use Core\Common\Domain\SimpleEntity;
use Core\Common\Domain\TrimmedString;
use Core\MonitoringServer\Model\MonitoringServer;

it('should return properly set instance', function (): void {
    $command = new Command(
        id: 1,
        name: 'command-name',
        commandLine: 'commandline',
        type: CommandType::Check,
        isShellEnabled: true,
        isActivated: false,
        isLocked: true,
        argumentExample: 'argExample',
        arguments: [new Argument(
            name: new TrimmedString('ARG1'),
            description: new TrimmedString('arg-desc')
        )],
        macros: [new CommandMacro(
            commandId: 1,
            type: CommandMacroType::Host,
            name: 'macro-name'
        )],
        connector: new SimpleEntity(
            id: 1,
            name: new TrimmedString('connector-name'),
            objectName: 'connector'
        ),
        graphTemplate: new SimpleEntity(
            id: 1,
            name: new TrimmedString('graphTemplate-name'),
            objectName: 'graphTemplate'
        ),
    );

    expect($command->getId())->toBe(1)
        ->and($command->getName())->toBe('command-name')
        ->and($command->getCommandLine())->toBe('commandline')
        ->and($command->getType())->toBe(CommandType::Check)
        ->and($command->isShellEnabled())->toBe(true)
        ->and($command->isActivated())->toBe(false)
        ->and($command->isLocked())->toBe(true)
        ->and($command->getArgumentExample())->toBe('argExample')
        ->and($command->getArguments()[0]->getName())->toBe('ARG1')
        ->and($command->getMacros()[0]->getName())->toBe('macro-name')
        ->and($command->getConnector()->getName())->toBe('connector-name')
        ->and($command->getGraphTemplate()->getName())->toBe('graphTemplate-name');
});

it('should throw an exception when ID is < 0', function (): void {
    new Command(
        id: 0,
        name: 'command-name',
        commandLine: 'commandline'
    );
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::positiveInt(0, 'Command::id')->getMessage()
);

it('should throw an exception when name is empty', function (): void {
    new Command(
        id: 1,
        name: '',
        commandLine: 'commandline',
        type: CommandType::Check
    );
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::notEmptyString('Command::name')->getMessage()
);

it('should throw an exception when name is too long', function (): void {
    new Command(
        id: 1,
        name: str_repeat('a', Command::NAME_MAX_LENGTH + 1),
        commandLine: 'commandline',
        type: CommandType::Check
    );
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::maxLength(
        str_repeat('a', Command::NAME_MAX_LENGTH + 1),
        Command::NAME_MAX_LENGTH + 1,
        Command::NAME_MAX_LENGTH,
        'Command::name'
    )->getMessage()
);

it('should throw an exception when name contains invalid characters', function (): void {
    new Command(
        id: 1,
        name: 'command-name-' . MonitoringServer::ILLEGAL_CHARACTERS[0] . '-test',
        commandLine: 'commandline',
        type: CommandType::Check
    );
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::unauthorizedCharacters(
        'command-name-' . MonitoringServer::ILLEGAL_CHARACTERS[0] . '-test',
        MonitoringServer::ILLEGAL_CHARACTERS[0],
        'Command::name'
    )->getMessage()
);

it('should throw an exception when command line is empty', function (): void {
    new Command(
        id: 1,
        name: 'command-line',
        commandLine: '',
        type: CommandType::Check
    );
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::notEmptyString('Command::commandLine')->getMessage()
);

it('should throw an exception when command line is too long', function (): void {
    new Command(
        id: 1,
        name: 'command-name',
        commandLine: str_repeat('a', Command::COMMAND_MAX_LENGTH + 1),
        type: CommandType::Check
    );
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::maxLength(
        str_repeat('a', Command::COMMAND_MAX_LENGTH + 1),
        Command::COMMAND_MAX_LENGTH + 1,
        Command::COMMAND_MAX_LENGTH,
        'Command::commandLine'
    )->getMessage()
);

it('should throw an exception when argument example is too long', function (): void {
    new Command(
        id: 1,
        name: 'command-name',
        commandLine: 'commandLine',
        type: CommandType::Check,
        argumentExample: str_repeat('a', Command::EXAMPLE_MAX_LENGTH + 1),
    );
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::maxLength(
        str_repeat('a', Command::EXAMPLE_MAX_LENGTH + 1),
        Command::EXAMPLE_MAX_LENGTH + 1,
        Command::EXAMPLE_MAX_LENGTH,
        'Command::argumentExample'
    )->getMessage()
);
