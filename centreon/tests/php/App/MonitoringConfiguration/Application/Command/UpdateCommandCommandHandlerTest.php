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

use App\MonitoringConfiguration\Application\Command\UpdateCommandCommand;
use App\MonitoringConfiguration\Application\Command\UpdateCommandCommandHandler;
use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandComment;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandLine;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandName;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Connector\ConnectorId;
use App\MonitoringConfiguration\Domain\Event\CommandUpdated;
use App\MonitoringConfiguration\Domain\Exception\CommandAlreadyExistsException;
use App\MonitoringConfiguration\Domain\Exception\CommandCanNotBeUpdatedException;
use App\MonitoringConfiguration\Domain\Exception\CommandNotFoundException;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeCommandRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeConnectorRepository;
use Tests\App\Shared\Double\EventBusSpy;

final class UpdateCommandCommandHandlerTest extends TestCase
{
    private FakeConnectorRepository $connectorRepository;

    private FakeCommandRepository $commandRepository;

    private EventBusSpy $eventBus;

    private UpdateCommandCommandHandler $handler;

    protected function setUp(): void
    {
        $this->commandRepository = new FakeCommandRepository();
        $this->connectorRepository = new FakeConnectorRepository();
        $this->eventBus = new EventBusSpy();
        $this->handler = new UpdateCommandCommandHandler(
            $this->commandRepository,
            $this->connectorRepository,
            $this->eventBus
        );
    }

    public function testUpdateCommand(): void
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

        $this->commandRepository->add($command);

        $updateCommand = new UpdateCommandCommand(
            id: new CommandId($command->id()->value),
            name: new CommandName('updated name'),
            commandLine: new CommandLine('updated command'),
            type: CommandTypeEnum::Notification,
            isShellEnabled: true,
            isActivated: false,
            comment: new CommandComment('updated comment'),
            connectorId: new ConnectorId(1),
            updatedBy: 1,
        );

        $updatedCommand = ($this->handler)($updateCommand);

        self::assertSame('updated name', $updatedCommand->name->value);
        self::assertSame('updated command', $updatedCommand->commandLine->value);
        self::assertSame(CommandTypeEnum::Notification, $updatedCommand->type);
        self::assertTrue($updatedCommand->isShellEnabled);
        self::assertFalse($updatedCommand->isActivated);
        self::assertSame('updated comment', $updatedCommand->comment?->value);
        self::assertTrue($this->eventBus->shouldHaveDispatched(CommandUpdated::class));
    }

    public function testCannotUpdateNonExistingCommand(): void
    {
        $this->expectException(CommandNotFoundException::class);

        $updateCommand = new UpdateCommandCommand(
            id: new CommandId(999),
            name: new CommandName('Non-existent'),
            commandLine: new CommandLine('updated command'),
            type: CommandTypeEnum::Notification,
            isShellEnabled: true,
            isActivated: false,
            comment: new CommandComment('updated comment'),
            connectorId: new ConnectorId(1),
            updatedBy: 1,
        );

        ($this->handler)($updateCommand);
    }

    // test update command with existing name
    public function testCannotUpdateCommandWithExistingName(): void
    {
        // add a command with the name to test uniqueness
        $existingCommand = new Command(
            id: new CommandId(2),
            name: new CommandName('existing name'),
            commandLine: new CommandLine('existing command'),
            type: CommandTypeEnum::Check,
            isShellEnabled: false,
            isActivated: true,
            comment: new CommandComment('existing comment'),
            isFromMonitoringConnector: false,
            connector: null,
        );
        $this->commandRepository->add($existingCommand);
        // add the command to update
        $commandToUpdate = new Command(
            id: new CommandId(3),
            name: new CommandName('original name'),
            commandLine: new CommandLine('original command'),
            type: CommandTypeEnum::Check,
            isShellEnabled: false,
            isActivated: true,
            comment: new CommandComment('original comment'),
            isFromMonitoringConnector: false,
            connector: null,
        );
        $this->commandRepository->add($commandToUpdate);
        // attempt to update the command with the existing name
        $updateCommand = new UpdateCommandCommand(
            id: new CommandId($commandToUpdate->id()->value),
            name: new CommandName('existing name'),
            commandLine: new CommandLine('updated command'),
            type: CommandTypeEnum::Notification,
            isShellEnabled: true,
            isActivated: false,
            comment: new CommandComment('updated comment'),
            connectorId: new ConnectorId(1),
            updatedBy: 1,
        );
        $this->expectException(CommandAlreadyExistsException::class);

        ($this->handler)($updateCommand);
    }

    public function testCannotUpdateCommandFromMonitoringConnector(): void
    {
        $command = new Command(
            id: new CommandId(2),
            name: new CommandName('connector command'),
            commandLine: new CommandLine('connector command line'),
            type: CommandTypeEnum::Check,
            isShellEnabled: false,
            isActivated: true,
            comment: new CommandComment('connector comment'),
            isFromMonitoringConnector: true,
            connector: null,
        );

        $this->commandRepository->add($command);

        $updateCommand = new UpdateCommandCommand(
            id: new CommandId($command->id()->value),
            name: new CommandName('updated name'),
            commandLine: new CommandLine('updated command'),
            type: CommandTypeEnum::Notification,
            isShellEnabled: true,
            isActivated: false,
            comment: new CommandComment('updated comment'),
            connectorId: new ConnectorId(1),
            updatedBy: 1,
        );

        $this->expectException(CommandCanNotBeUpdatedException::class);

        ($this->handler)($updateCommand);
    }

    public function testDispatchUpdatedEvent(): void
    {
        $command = new Command(
            id: new CommandId(1),
            name: new CommandName('original name'),
            commandLine: new CommandLine('command'),
            type: CommandTypeEnum::Check,
            isShellEnabled: false,
            isActivated: true,
            comment: new CommandComment('comment'),
            isFromMonitoringConnector: false,
            connector: null,
        );

        $this->commandRepository->add($command);

        $updateCommand = new UpdateCommandCommand(
            id: new CommandId($command->id()->value),
            name: new CommandName('updated name'),
            commandLine: new CommandLine('updated command'),
            type: CommandTypeEnum::Notification,
            isShellEnabled: true,
            isActivated: false,
            comment: new CommandComment('updated comment'),
            connectorId: new ConnectorId(1),
            updatedBy: 1,
        );

        ($this->handler)($updateCommand);

        self::assertTrue($this->eventBus->shouldHaveDispatched(CommandUpdated::class));
    }
}
