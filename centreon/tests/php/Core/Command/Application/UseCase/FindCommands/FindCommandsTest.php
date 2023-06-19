<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Command\Domain\Model\Command;
use Core\Command\Application\Exception\CommandException;
use Core\Command\Application\Repository\ReadCommandRepositoryInterface;
use Core\Command\Application\UseCase\FindCommands\FindCommands;
use Core\Command\Application\UseCase\FindCommands\FindCommandsResponse;
use Core\Command\Domain\Model\CommandType;
use Core\Command\Infrastructure\Model\CommandTypeConverter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Tests\Core\Command\Infrastructure\API\FindCommands\FindCommandsPresenterStub;

beforeEach(closure: function (): void {
    $this->readCommandRepository = $this->createMock(ReadCommandRepositoryInterface::class);
    $this->contact = $this->createMock(ContactInterface::class);
    $this->presenter = new FindCommandsPresenterStub($this->createMock(PresenterFormatterInterface::class));

    $this->useCase = new FindCommands(
        $this->createMock(RequestParametersInterface::class),
        $this->readCommandRepository,
        $this->contact
    );
});

it('should present a ForbiddenResponse when the user has insufficient rights', function (): void {
    $this->contact
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

    $this->contact
        ->expects($this->atMost(8))
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_COMMANDS_CHECKS_R, false],
                [Contact::ROLE_CONFIGURATION_COMMANDS_CHECKS_RW, false],
                [Contact::ROLE_CONFIGURATION_COMMANDS_NOTIFICATIONS_R, false],
                [Contact::ROLE_CONFIGURATION_COMMANDS_NOTIFICATIONS_RW, false],
                [Contact::ROLE_CONFIGURATION_COMMANDS_MISCELLANEOUS_R, false],
                [Contact::ROLE_CONFIGURATION_COMMANDS_MISCELLANEOUS_RW, false],
                [Contact::ROLE_CONFIGURATION_COMMANDS_DISCOVERY_R, false],
                [Contact::ROLE_CONFIGURATION_COMMANDS_DISCOVERY_RW, false],
            ]
        );

    ($this->useCase)($this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(CommandException::accessNotAllowed()->getMessage());
});

it(
    'should present an ErrorResponse when an exception of type RequestParametersTranslatorException is thrown',
    function (): void {
        $this->contact
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);

        $exception = new RequestParametersTranslatorException('Error');

        $this->readCommandRepository
            ->expects($this->once())
            ->method('findByRequestParameterAndTypes')
            ->willThrowException($exception);

        ($this->useCase)($this->presenter);

        expect($this->presenter->response)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->response->getMessage())
            ->toBe($exception->getMessage());
    }
);

it(
    'should present an ErrorResponse when an exception of type Exception is thrown',
    function (): void {
        $this->contact
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);

        $exception = new \Exception('Error');

        $this->readCommandRepository
            ->expects($this->once())
            ->method('findByRequestParameterAndTypes')
            ->willThrowException($exception);

        ($this->useCase)($this->presenter);

        expect($this->presenter->response)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->response->getMessage())
            ->toBe(CommandException::errorWhileSearching($exception)->getMessage());
    }
);

it(
    'should present a FindCommandsResponse when everything has gone well',
    function (): void {
        $this->contact
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);

        $command = new Command(1, 'fake', 'line', CommandType::Check, true, true, true);

        $this->readCommandRepository
            ->expects($this->once())
            ->method('findByRequestParameterAndTypes')
            ->willReturn([$command]);

        ($this->useCase)($this->presenter);

        $commandsResponse = $this->presenter->response;
        expect($commandsResponse)->toBeInstanceOf(FindCommandsResponse::class);
        expect($commandsResponse->commands[0]->id)->toBe($command->getId());
        expect($commandsResponse->commands[0]->name)->toBe($command->getName());
        expect($commandsResponse->commands[0]->commandLine)->toBe($command->getCommandLine());
        expect($commandsResponse->commands[0]->type)->toBe($command->getType());
        expect($commandsResponse->commands[0]->isShellEnabled)->toBe($command->isShellEnabled());
        expect($commandsResponse->commands[0]->isLocked)->toBe($command->isLocked());
        expect($commandsResponse->commands[0]->isActivated)->toBe($command->isActivated());
    }
);
