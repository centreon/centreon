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

namespace Tests\Core\Command\Application\UseCase\GetCommand;



use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Command\Application\UseCase\GetCommand\GetCommand;
use Core\Command\Domain\Model\Command;
use Core\Command\Application\Repository\ReadCommandRepositoryInterface;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Command\Application\Exception\CommandException;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Command\Domain\Model\CommandType;
use Core\Command\Domain\Model\Argument;
use Core\Common\Domain\TrimmedString;
use Core\CommandMacro\Domain\Model\CommandMacro;
use Core\CommandMacro\Domain\Model\CommandMacroType;
use Core\Common\Domain\SimpleEntity;
use Centreon\Domain\Contact\Contact;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Tests\Core\Command\Infrastructure\API\GetCommand\GetCommandPresenterStub;
use Core\Command\Application\UseCase\GetCommand\GetCommandResponse;



beforeEach(function (): void {
    $this->useCase = new GetCommand(
        $this->readCommandRepository = $this->createMock(ReadCommandRepositoryInterface::class),
        $this->user = $this->createMock(ContactInterface::class),
    );



    $this->presenter = new GetCommandPresenterStub($this->createMock(PresenterFormatterInterface::class));

    $this->command = new Command(
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
            id: 2,
            name: new TrimmedString('graphTemplate-name'),
            objectName: 'graphTemplate'
        ),
    );

});

it('should present a ForbiddenResponse when the user has insufficient rights', function (): void {

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);
    $this->user
        ->expects($this->atMost(8))
        ->method('hasTopologyRole')
        ->willReturn(false);
    
    $this->readCommandRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->command);

    ($this->useCase)($this->presenter, $this->command->getId());

    expect($this->presenter->response)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(CommandException::accessNotAllowed()->getMessage());
});

it(
    'should present a ForbiddenResponse when the user has insufficient rights on the required command type',
    function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);
    $this->user
        ->expects($this->atMost(8))
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_COMMANDS_CHECKS_R, false],
                [Contact::ROLE_CONFIGURATION_COMMANDS_NOTIFICATIONS_R, true],
                [Contact::ROLE_CONFIGURATION_COMMANDS_MISCELLANEOUS_R, true],
                [Contact::ROLE_CONFIGURATION_COMMANDS_DISCOVERY_R, true],
                [Contact::ROLE_CONFIGURATION_COMMANDS_CHECKS_RW, false],
                [Contact::ROLE_CONFIGURATION_COMMANDS_NOTIFICATIONS_RW, false],
                [Contact::ROLE_CONFIGURATION_COMMANDS_MISCELLANEOUS_RW, false],
                [Contact::ROLE_CONFIGURATION_COMMANDS_DISCOVERY_RW, false],
            ]
        );
    
    $this->readCommandRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->command);

    ($this->useCase)($this->presenter, $this->command->getId());

    expect($this->presenter->response)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(CommandException::accessNotAllowed()->getMessage());

});

it('should present an ErrorResponse when an exception is thrown', function (): void {
    $this->readCommandRepository
        ->expects($this->once())
        ->method('findById')
        ->willThrowException(new \Exception());


    ($this->useCase)($this->presenter, $this->command->getId());

    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->response?->getMessage())
        ->toBe(CommandException::errorWhileRetrieving()->getMessage());
});

it('should present an NotFoundResponse; when command is not found', function (): void {
    $this->readCommandRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn(null);
    

    ($this->useCase)($this->presenter, $this->command->getId());

    expect($this->presenter->response)
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($this->presenter->response?->getMessage())
        ->toBe("Command not found");
});

it('should present a command; when command is successfully getted', function (): void {
    $this->user
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(false);
    $this->user
        ->expects($this->atMost(8))
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_COMMANDS_CHECKS_R, true],
                [Contact::ROLE_CONFIGURATION_COMMANDS_NOTIFICATIONS_R, true],
                [Contact::ROLE_CONFIGURATION_COMMANDS_MISCELLANEOUS_R, true],
                [Contact::ROLE_CONFIGURATION_COMMANDS_DISCOVERY_R, true],
                [Contact::ROLE_CONFIGURATION_COMMANDS_CHECKS_RW, false],
                [Contact::ROLE_CONFIGURATION_COMMANDS_NOTIFICATIONS_RW, false],
                [Contact::ROLE_CONFIGURATION_COMMANDS_MISCELLANEOUS_RW, false],
                [Contact::ROLE_CONFIGURATION_COMMANDS_DISCOVERY_RW, false],
            ]
        );
    $this->readCommandRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->command);


    ($this->useCase)($this->presenter, $this->command->getId());

    $response = $this->presenter->response;
    expect($response)
        ->toBeInstanceOf(GetCommandResponse::class)
        ->and($response->id)->toBe($this->command->getId())
        ->and($response->name)->toBe($this->command->getName())
        ->and($response->commandLine)->toBe($this->command->getCommandLine())
        ->and($response->type)->toBe($this->command->getType())
        ->and($response->isShellEnabled)->toBe($this->command->isShellEnabled())
        ->and($response->isLocked)->toBe($this->command->isLocked())
        ->and($response->isActivated)->toBe($this->command->isActivated())
        ->and($response->argumentExample)->tobe($this->command->getArgumentExample())
        ->and($response->connector)->toBe([
            'id' => $this->command->getConnector()->getId(),
            'name' => $this->command->getConnector()->getName(),
        ])
        ->and($response->graphTemplate)->toBe([
            'id' => $this->command->getGraphTemplate()->getId(),
            'name' => $this->command->getGraphTemplate()->getName(),
        ])
        ->and($response->arguments)->tobe([[
            'name' => $this->command->getArguments()[0]->getName(),
            'description' => $this->command->getArguments()[0]->getDescription(),
        ]])
        ->and($response->macros)->tobe([[
            'name' => $this->command->getMacros()[0]->getName(),
            'description' => $this->command->getMacros()[0]->getDescription(),
            'type' => $this->command->getMacros()[0]->getType(),
        ]]);
});