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

namespace Tests\App\MonitoringConfiguration\Application\Command;

use App\MonitoringConfiguration\Application\Command\DeleteCommandCommand;
use App\MonitoringConfiguration\Application\Command\DeleteCommandCommandHandler;
use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandComment;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandLine;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandName;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Domain\Event\CommandDeleted;
use App\MonitoringConfiguration\Domain\Exception\CommandNotFoundException;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeCommandRepository;
use Tests\App\Shared\Double\EventBusSpy;

final class DeleteCommandCommandHandlerTest extends TestCase
{
    private FakeCommandRepository $repository;

    private EventBusSpy $eventBus;

    private DeleteCommandCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new FakeCommandRepository();
        $this->eventBus = new EventBusSpy();
        $this->handler = new DeleteCommandCommandHandler(
            $this->repository,
            $this->eventBus
        );
    }

    public function testDeleteCommand(): void
    {
        $command = new Command(
            id: new CommandId(1),
            name: new CommandName('original name'),
            commandLine: new CommandLine('original command'),
            type: CommandTypeEnum::Check,
            isShellEnabled: false,
            isActivated: true,
            comment: new CommandComment('original comment'),
            isFromMonitoringConnector: false,
            connector: null,
        );

        $this->repository->add($command);

        $deleteCommand = new DeleteCommandCommand(
            id: new CommandId($command->id()->value),
            type: CommandTypeEnum::Notification,
            updatedBy: 1,
        );

        ($this->handler)($deleteCommand);

        self::assertNull($this->repository->findOneByName($command->name));
        self::assertTrue($this->eventBus->shouldHaveDispatched(CommandDeleted::class));
    }

    public function testDeleteNonExistingCommand(): void
    {
        $this->expectException(CommandNotFoundException::class);

        $deleteCommand = new DeleteCommandCommand(
            id: new CommandId(999),
            type: CommandTypeEnum::Notification,
            updatedBy: 1,
        );

        ($this->handler)($deleteCommand);
    }
}
