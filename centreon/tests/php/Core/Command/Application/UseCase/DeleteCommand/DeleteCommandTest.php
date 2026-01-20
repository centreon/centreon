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

namespace Tests\Core\Command\Application\UseCase\DeleteCommand;



use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Command\Application\UseCase\DeleteCommand\DeleteCommand;
use Core\Command\Domain\Model\Command;
use Core\Command\Application\Repository\ReadCommandRepositoryInterface;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Command\Application\Repository\WriteCommandRepositoryInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Command\Application\Exception\CommandException;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Command\Domain\Model\CommandType;
use Core\Command\Domain\Model\Argument;
use Core\Common\Domain\TrimmedString;
use Core\CommandMacro\Domain\Model\CommandMacro;
use Core\CommandMacro\Domain\Model\CommandMacroType;
use Core\Common\Domain\SimpleEntity;
use Centreon\Domain\Contact\Contact;
use Core\Application\Common\UseCase\ForbiddenResponse;



beforeEach(function (): void {
    $this->useCase = new DeleteCommand(
        $this->readCommandRepository = $this->createMock(ReadCommandRepositoryInterface::class),
        $this->writeCommandRepository = $this->createMock(WriteCommandRepositoryInterface::class),
        $this->user = $this->createMock(ContactInterface::class),
    );



    $this->presenter = new DefaultPresenter($this->createMock(PresenterFormatterInterface::class));

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
        ->willReturn(false);

    $this->readCommandRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->command);

    ($this->useCase)($this->command->getId(), $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(CommandException::deleteNotAllowed()->getMessage());
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

    ($this->useCase)($this->command->getId(), $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(CommandException::deleteNotAllowed()->getMessage());

});

it('should present an ErrorResponse when an exception is thrown', function (): void {
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

    $this->writeCommandRepository
        ->expects($this->once())
        ->method('delete')
        ->willThrowException(new \Exception());


    ($this->useCase)($this->command->getId(), $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe(CommandException::errorWhileDeletingCommand()->getMessage());
});

it('should present an NotFoundResponse; when command is not found', function (): void {
    $this->readCommandRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn(null);


    ($this->useCase)($this->command->getId(), $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe("Command not found");
});

it('should present an NotContentResponse; when command is successfully deleted', function (): void {
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


    $this->writeCommandRepository
        ->expects($this->once())
        ->method('delete');

    ($this->useCase)($this->command->getId(), $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(NoContentResponse::class);
});