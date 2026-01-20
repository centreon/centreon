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

namespace Tests\Core\Command\Application\UseCase\UpdateCommand;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Command\Application\Exception\CommandException;
use Core\Command\Application\Repository\ReadCommandRepositoryInterface;
use Core\Command\Application\Repository\WriteCommandRepositoryInterface;
use Core\Command\Application\UseCase\UpdateCommand\UpdateCommand;
use Core\Command\Application\UseCase\UpdateCommand\UpdateCommandRequest;
use Core\Command\Application\UseCase\UpdateCommand\UpdateCommandValidator;
use Core\Command\Application\UseCase\UpdateCommand\ArgumentDto;
use Core\Command\Application\UseCase\UpdateCommand\MacroDto;
use Core\Command\Domain\Model\Argument;
use Core\Command\Domain\Model\Command;
use Core\Command\Domain\Model\CommandType;
use Core\CommandMacro\Domain\Model\CommandMacro;
use Core\CommandMacro\Domain\Model\CommandMacroType;
use Core\Common\Domain\SimpleEntity;
use Core\Common\Domain\TrimmedString;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Application\Common\UseCase\NoContentResponse;

beforeEach(closure: function (): void {
    $this->presenter = new DefaultPresenter($this->createMock(PresenterFormatterInterface::class));

    $this->useCase = new UpdateCommand(
        readCommandRepository: $this->readCommandRepository = $this->createMock(ReadCommandRepositoryInterface::class),
        writeCommandRepository: $this->writeCommandRepository = $this->createMock(WriteCommandRepositoryInterface::class),
        validator: $this->validation = $this->createMock(UpdateCommandValidator::class),
        user: $this->user = $this->createMock(ContactInterface::class),
    );

    $this->request = new UpdateCommandRequest();
    $this->request->id = 1;
    $this->request->name = 'command-name';
    $this->request->commandLine = '$ARG1, $_HOSTmacro-name$';
    $this->request->type = CommandType::Notification;
    $this->request->isShellEnabled = true;
    $this->request->argumentExample = 'argExample';
    $this->request->arguments = [new ArgumentDto(
        name: 'ARG1',
        description: 'arg-desc'
    )];
    $this->request->macros = [new MacroDto(
        type: CommandMacroType::Host,
        name: 'macro-name',
        description: 'macro-description'
    )];
    $this->request->connectorId = 1;
    $this->request->graphTemplateId = 2;

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
        ->expects($this->atMost(4))
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
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

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(CommandException::updateNotAllowed()->getMessage());
});

it(
    'should present a ForbiddenResponse when the user has insufficient rights on the required command type',
    function (): void {
         $this->user
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(false);
        $this->user
            ->expects($this->atMost(4))
            ->method('hasTopologyRole')
            ->willReturnMap(
                [
                    [Contact::ROLE_CONFIGURATION_COMMANDS_CHECKS_RW, false],
                    [Contact::ROLE_CONFIGURATION_COMMANDS_NOTIFICATIONS_RW, true],
                    [Contact::ROLE_CONFIGURATION_COMMANDS_MISCELLANEOUS_RW, true],
                    [Contact::ROLE_CONFIGURATION_COMMANDS_DISCOVERY_RW, true],
                ]
            );
        $this->readCommandRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($this->command);

        ($this->useCase)($this->request, $this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(ForbiddenResponse::class)
            ->and($this->presenter->getResponseStatus()->getMessage())
            ->toBe(CommandException::updateNotAllowed()->getMessage());
    }
);

it(
    'should present a ConflictResponse when an a request parameter is invalid',
    function (): void {
         $this->user
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(false);
        $this->user
            ->expects($this->atMost(4))
            ->method('hasTopologyRole')
            ->willReturnMap(
                [
                    [Contact::ROLE_CONFIGURATION_COMMANDS_CHECKS_RW, true],
                    [Contact::ROLE_CONFIGURATION_COMMANDS_NOTIFICATIONS_RW, true],
                    [Contact::ROLE_CONFIGURATION_COMMANDS_MISCELLANEOUS_RW, true],
                    [Contact::ROLE_CONFIGURATION_COMMANDS_DISCOVERY_RW, true],
                ]
            );
        $this->readCommandRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($this->command);

        $this->validation
            ->expects($this->once())
            ->method('assertNameDoesNotAlreadyExists')
            ->willThrowException(CommandException::nameAlreadyExists('invalid-name'));

        ($this->useCase)($this->request, $this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(ConflictResponse::class)
            ->and($this->presenter->getResponseStatus()->getMessage())
            ->toBe(CommandException::nameAlreadyExists('invalid-name')->getMessage());
    }
);

it(
    'should present an ErrorResponse when an exception of type Exception is thrown',
    function (): void {
        $this->user
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(false);
        $this->user
            ->expects($this->atMost(4))
            ->method('hasTopologyRole')
            ->willReturnMap(
                [
                    [Contact::ROLE_CONFIGURATION_COMMANDS_CHECKS_RW, true],
                    [Contact::ROLE_CONFIGURATION_COMMANDS_NOTIFICATIONS_RW, true],
                    [Contact::ROLE_CONFIGURATION_COMMANDS_MISCELLANEOUS_RW, true],
                    [Contact::ROLE_CONFIGURATION_COMMANDS_DISCOVERY_RW, true],
                ]
            );
        $this->readCommandRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($this->command);

        $this->validation->expects($this->once())->method('assertNameDoesNotAlreadyExists');
        $this->validation->expects($this->once())->method('assertAreValidArguments');
        $this->validation->expects($this->once())->method('assertAreValidMacros');
        $this->validation->expects($this->once())->method('assertIsValidConnector');
        $this->validation->expects($this->once())->method('assertIsValidGraphTemplate');

        $this->writeCommandRepository
            ->expects($this->once())
            ->method('update')
            ->willThrowException(new \Exception());

        ($this->useCase)($this->request, $this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->getResponseStatus()->getMessage())
            ->toBe(CommandException::errorWhileUpdating(new \Exception())->getMessage());
    }
);

it(
    'should present an UpdateCommandResponse when everything has gone well',
    function (): void {
        $this->user
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(false);
        $this->user
            ->expects($this->atMost(4))
            ->method('hasTopologyRole')
            ->willReturnMap(
                [
                    [Contact::ROLE_CONFIGURATION_COMMANDS_CHECKS_RW, true],
                    [Contact::ROLE_CONFIGURATION_COMMANDS_NOTIFICATIONS_RW, true],
                    [Contact::ROLE_CONFIGURATION_COMMANDS_MISCELLANEOUS_RW, true],
                    [Contact::ROLE_CONFIGURATION_COMMANDS_DISCOVERY_RW, true],
                ]
            );

        $this->readCommandRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($this->command);

        $this->validation->expects($this->once())->method('assertNameDoesNotAlreadyExists');
        $this->validation->expects($this->once())->method('assertAreValidArguments');
        $this->validation->expects($this->once())->method('assertAreValidMacros');
        $this->validation->expects($this->once())->method('assertIsValidConnector');
        $this->validation->expects($this->once())->method('assertIsValidGraphTemplate');
    
        $this->writeCommandRepository
            ->expects($this->once())
            ->method('update');


        ($this->useCase)($this->request, $this->presenter);
        
        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(NoContentResponse::class);
    }
);