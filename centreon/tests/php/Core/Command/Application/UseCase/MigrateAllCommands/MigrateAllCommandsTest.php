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

declare(strict_types = 1);

namespace Tests\Core\Command\Application\UseCase\MigrateAllCommands;

use Core\Command\Application\Repository\ReadCommandRepositoryInterface;
use Core\Command\Application\Repository\WriteCommandRepositoryInterface;
use Core\Command\Application\UseCase\MigrateAllCommands\CommandRecordedDto;
use Core\Command\Application\UseCase\MigrateAllCommands\MigrateAllCommands;
use Core\Command\Application\UseCase\MigrateAllCommands\MigrateAllCommandsResponse;
use Core\Command\Domain\Model\Command;
use Psr\Log\LoggerInterface;
use Tests\Core\Command\Infrastructure\Command\MigrateAllCommands\MigrateAllCommandsPresenterStub;

beforeEach(function (): void {
    $this->readCommandRepository = $this->createMock(ReadCommandRepositoryInterface::class);
    $this->writeCommandRepository = $this->createMock(WriteCommandRepositoryInterface::class);
    $this->logger = $this->createMock(LoggerInterface::class);

    $this->useCase = new MigrateAllCommands($this->readCommandRepository, $this->writeCommandRepository, $this->logger);
    $this->presenter = new MigrateAllCommandsPresenterStub();
});

it('should present a MigrateAllCommandsResponse', function (): void{
    $commandA = new Command(1, 'command-name A', 'command line A');
    $commandB = new Command(2, 'command-name B', 'command line B');
    $this->readCommandRepository
        ->expects($this->once())
        ->method('findAll')
        ->willReturn(new \ArrayIterator([$commandA, $commandB]));

    ($this->useCase)($this->presenter);
    $firstResponseCommand = $this->presenter->response->results->current();
    expect($this->presenter->response)
        ->toBeInstanceOf(MigrateAllCommandsResponse::class)
        ->and($firstResponseCommand->name)
        ->tobe('command-name A');

    $this->presenter->response->results->next();

    /** @var CommandRecordedDto $secondeResponseCommand */
    $secondeResponseCommand = $this->presenter->response->results->current();
    expect($this->presenter->response)
        ->toBeInstanceOf(MigrateAllCommandsResponse::class)
        ->and($secondeResponseCommand->name)
        ->tobe('command-name B');
});
