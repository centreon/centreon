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

use App\MonitoringConfiguration\Application\Command\DuplicateCommandsCommand;
use App\MonitoringConfiguration\Application\Command\DuplicateCommandsCommandHandler;
use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandComment;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandLine;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandName;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;

use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeCommandRepository;
use Tests\App\Shared\Double\EventBusSpy;

final class DuplicateCommandsCommandHandlerTest extends TestCase
{
    private FakeCommandRepository $repository;

    private EventBusSpy $eventBus;

    private DuplicateCommandsCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new FakeCommandRepository();
        $this->eventBus = new EventBusSpy();
        $this->handler = new DuplicateCommandsCommandHandler($this->repository, $this->eventBus);
    }

    public function testDuplicateCommands(): void
    {
        $originalCommand = $this->createTestCommand(1, 'check_ping');
        $this->repository->add($originalCommand);

        $command = new DuplicateCommandsCommand(
            commandIds: [$originalCommand->id()->value],
            duplicatedBy: 1,
            allowedTypes: ['Check']
        );

        $result = ($this->handler)($command);

        self::assertArrayHasKey('duplicated', $result);
        self::assertCount(1, $result['duplicated']);

        $duplicatedCommand = $this->repository->findOneByName($result['duplicated'][0]->name);
        self::assertNotNull($duplicatedCommand);
        self::assertSame($originalCommand->type, $duplicatedCommand->type);
        self::assertSame($originalCommand->commandLine, $duplicatedCommand->commandLine);
        self::assertSame($originalCommand->isShellEnabled, $duplicatedCommand->isShellEnabled);
        self::assertSame($originalCommand->isActivated, $duplicatedCommand->isActivated);
        self::assertSame($originalCommand->isFromMonitoringConnector, $duplicatedCommand->isFromMonitoringConnector);
        self::assertSame($originalCommand->connector(), $duplicatedCommand->connector());
        self::assertSame($originalCommand->comment, $duplicatedCommand->comment);
    }

    public function testDuplicateCommandsReturnsErrorWhenOriginalCommandNotFound(): void
    {
        $command = new DuplicateCommandsCommand(
            commandIds: [999],
            duplicatedBy: 42,
            allowedTypes: ['Check']
        );

        $result = ($this->handler)($command);

        self::assertArrayHasKey('missing', $result);
        self::assertContains(999, $result['missing']);
    }

    public function testDuplicateCommandsReturnsAccessDeniedWhenTypeNotAllowed(): void
    {
        $originalCommand = $this->createTestCommand(1, 'notify_admin', CommandTypeEnum::Notification);
        $this->repository->add($originalCommand);

        $command = new DuplicateCommandsCommand(
            commandIds: [$originalCommand->id()->value],
            duplicatedBy: 42,
            allowedTypes: ['Check']
        );

        $result = ($this->handler)($command);

        self::assertArrayHasKey('access_denied', $result);
        self::assertContains($originalCommand->id()->value, $result['access_denied']);
    }

    public function testDuplicateCommandsSucceedsWhenTypeIsAllowed(): void
    {
        $originalCommand = $this->createTestCommand(1, 'notify_admin', CommandTypeEnum::Notification);
        $this->repository->add($originalCommand);

        $command = new DuplicateCommandsCommand(
            commandIds: [$originalCommand->id()->value],
            duplicatedBy: 42,
            allowedTypes: ['Check', 'Notification']
        );

        $result = ($this->handler)($command);

        self::assertArrayHasKey('duplicated', $result);
        self::assertCount(1, $result['duplicated']);

        $duplicatedCommand = $result['duplicated'][0];
        self::assertNotNull($duplicatedCommand);
        self::assertSame(CommandTypeEnum::Notification, $duplicatedCommand->type);
        self::assertStringStartsWith('notify_admin_', $duplicatedCommand->name->value);
    }

    public function testDuplicateCommandWithEmptyAllowedTypesReturnsAccessDenied(): void
    {
        $originalCommand = $this->createTestCommand(1, 'check_http');
        $this->repository->add($originalCommand);

        $command = new DuplicateCommandsCommand(
            commandIds: [$originalCommand->id()->value],
            duplicatedBy: 42,
            allowedTypes: []
        );

        $result = ($this->handler)($command);

        self::assertArrayHasKey('access_denied', $result);
        self::assertContains($originalCommand->id()->value, $result['access_denied']);
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
