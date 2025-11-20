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

use App\MonitoringConfiguration\Application\Command\DuplicateCommandCommand;
use App\MonitoringConfiguration\Application\Command\DuplicateCommandCommandHandler;
use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandComment;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandLine;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandName;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Domain\Exception\CommandAccessDeniedException;
use App\MonitoringConfiguration\Domain\Exception\CommandNotFoundException;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeCommandRepository;
use Tests\App\Shared\Double\EventBusSpy;

final class DuplicateCommandCommandHandlerTest extends TestCase
{
    private FakeCommandRepository $repository;

    private EventBusSpy $eventBus;

    private DuplicateCommandCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new FakeCommandRepository();
        $this->eventBus = new EventBusSpy();
        $this->handler = new DuplicateCommandCommandHandler($this->repository, $this->eventBus);
    }

    public function testDuplicateCommand(): void
    {
        $originalCommand = $this->createTestCommand(1, 'check_ping');
        $this->repository->add($originalCommand);

        $command = new DuplicateCommandCommand(
            commandId: $originalCommand->id()->value,
            duplicatedBy: 1,
            allowedTypes: ['Check']
        );

        $result = ($this->handler)($command);

        self::assertSame($originalCommand, $result);

        $duplicatedCommand = $this->repository->findOneByName(new CommandName('check_ping_1'));
        self::assertNotNull($duplicatedCommand);
        self::assertSame($originalCommand->type, $duplicatedCommand->type);
        self::assertSame($originalCommand->commandLine, $duplicatedCommand->commandLine);
        self::assertSame($originalCommand->isShellEnabled, $duplicatedCommand->isShellEnabled);
        self::assertSame($originalCommand->isActivated, $duplicatedCommand->isActivated);
        self::assertSame($originalCommand->isFromMonitoringConnector, $duplicatedCommand->isFromMonitoringConnector);
        self::assertSame($originalCommand->connector, $duplicatedCommand->connector);
        self::assertSame($originalCommand->comment, $duplicatedCommand->comment);
    }

    public function testDuplicateCommandGeneratesUniqueNameWhenConflictExists(): void
    {
        $originalCommand = $this->createTestCommand(1, 'check_disk');
        $firstDuplicate = $this->createTestCommand(2, 'check_disk_1');
        $this->repository->add($originalCommand);
        $this->repository->add($firstDuplicate);

        $command = new DuplicateCommandCommand(
            commandId: $originalCommand->id()->value,
            duplicatedBy: 1,
            allowedTypes: ['Check']
        );

        ($this->handler)($command);

        $duplicatedCommand = $this->repository->findOneByName(new CommandName('check_disk_2'));
        self::assertNotNull($duplicatedCommand);
    }

    public function testDuplicateCommandGeneratesTimestampNameWhenManyConflictsExist(): void
    {
        $originalCommand = $this->createTestCommand(1, 'check_load');
        $this->repository->add($originalCommand);

        for ($counter = 1; $counter <= 1000; $counter++) {
            $duplicate = $this->createTestCommand($counter + 1, "check_load_{$counter}");
            $this->repository->add($duplicate);
        }

        $command = new DuplicateCommandCommand(
            commandId: $originalCommand->id()->value,
            duplicatedBy: 1,
            allowedTypes: ['Check']
        );

        ($this->handler)($command);

        $commands = $this->repository->commands;
        $lastCommand = array_slice($commands, -1)[0];
        self::assertStringStartsWith('check_load_copy_', $lastCommand->name->value);
    }

    public function testDuplicateCommandThrowsExceptionWhenOriginalCommandNotFound(): void
    {
        $this->expectException(CommandNotFoundException::class);

        $command = new DuplicateCommandCommand(
            commandId: 999,
            duplicatedBy: 42,
            allowedTypes: ['Check']
        );

        ($this->handler)($command);
    }

    public function testDuplicateCommandThrowsAccessDeniedExceptionWhenTypeNotAllowed(): void
    {
        $originalCommand = $this->createTestCommand(1, 'notify_admin', CommandTypeEnum::Notification);
        $this->repository->add($originalCommand);

        $this->expectException(CommandAccessDeniedException::class);
        $this->expectExceptionMessage('You are not allowed to duplicate this command');

        $command = new DuplicateCommandCommand(
            commandId: $originalCommand->id()->value,
            duplicatedBy: 42,
            allowedTypes: ['Check']
        );

        ($this->handler)($command);
    }

    public function testDuplicateCommandSucceedsWhenTypeIsAllowed(): void
    {
        $originalCommand = $this->createTestCommand(1, 'notify_admin', CommandTypeEnum::Notification);
        $this->repository->add($originalCommand);

        $command = new DuplicateCommandCommand(
            commandId: $originalCommand->id()->value,
            duplicatedBy: 42,
            allowedTypes: ['Check', 'Notification']
        );

        $result = ($this->handler)($command);

        self::assertSame($originalCommand, $result);

        $duplicatedCommand = $this->repository->findOneByName(new CommandName('notify_admin_1'));
        self::assertNotNull($duplicatedCommand);
        self::assertSame(CommandTypeEnum::Notification, $duplicatedCommand->type);
    }

    public function testDuplicateCommandWithEmptyAllowedTypesThrowsAccessDenied(): void
    {
        $originalCommand = $this->createTestCommand(1, 'check_http');
        $this->repository->add($originalCommand);

        $this->expectException(CommandAccessDeniedException::class);

        $command = new DuplicateCommandCommand(
            commandId: $originalCommand->id()->value,
            duplicatedBy: 42,
            allowedTypes: []
        );

        ($this->handler)($command);
    }

    private function createTestCommand(
        int $id,
        string $name,
        CommandTypeEnum $type = CommandTypeEnum::Check,
    ): Command {
        return new Command(
            id: new CommandId($id),
            name: new CommandName($name),
            type: $type,
            commandLine: new CommandLine('$USER1$/check_something -H $HOSTADDRESS$'),
            isShellEnabled: false,
            isActivated: true,
            isFromMonitoringConnector: false,
            connector: null,
            comment: new CommandComment('Test command for duplication'),
        );
    }
}
