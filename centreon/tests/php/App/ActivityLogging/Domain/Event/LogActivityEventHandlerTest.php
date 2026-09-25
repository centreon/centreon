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

namespace Tests\App\ActivityLogging\Domain\Event;

use App\ActivityLogging\Domain\Aggregate\ActionEnum;
use App\ActivityLogging\Domain\Aggregate\ActivityLog;
use App\ActivityLogging\Domain\Aggregate\TargetTypeEnum;
use App\ActivityLogging\Domain\Event\LogActivityEventHandler;
use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandLine;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandName;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceCategory\ServiceCategory;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceCategory\ServiceCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceCategory\ServiceCategoryName;
use App\MonitoringConfiguration\Domain\Event\CommandDeleted;
use App\MonitoringConfiguration\Domain\Event\CommandUpdated;
use App\MonitoringConfiguration\Domain\Event\HostDisabled;
use App\MonitoringConfiguration\Domain\Event\HostEnabled;
use App\MonitoringConfiguration\Domain\Event\ServiceCategoryCreated;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Tests\App\ActivityLogging\Infrastructure\Double\FakeActivityLogFactory;
use Tests\App\ActivityLogging\Infrastructure\Double\FakeActivityLogRepository;

final class LogActivityEventHandlerTest extends TestCase
{
    public function testCreateActivityLogOnCreation(): void
    {
        $repository = new FakeActivityLogRepository();

        $handler = new LogActivityEventHandler($repository, $this->createContainer([
            ServiceCategory::class => new FakeActivityLogFactory(),
        ]));

        $aggregate = new ServiceCategory(
            id: new ServiceCategoryId(1),
            name: new ServiceCategoryName('NAME'),
            alias: new ServiceCategoryName('ALIAS'),
            activated: true,
        );

        $handler(new ServiceCategoryCreated(
            aggregate: $aggregate,
            creatorId: 2,
            firedAt: $firedAt = new \DateTimeImmutable(),
        ));

        $activityLog = reset($repository->activityLogs);

        self::assertInstanceof(ActivityLog::class, $activityLog);
        self::assertSame(ActionEnum::Add, $activityLog->action);
        self::assertSame(2, $activityLog->actor->id->value);
        self::assertSame(1, $activityLog->target->id->value);
        self::assertSame('NAME', $activityLog->target->name->value);
        self::assertSame(TargetTypeEnum::ServiceCategory, $activityLog->target->type);
        self::assertEquals($firedAt, $activityLog->performedAt);
    }

    public function testCreateActivityLogOnUpdate(): void
    {
        $repository = new FakeActivityLogRepository();

        $handler = new LogActivityEventHandler($repository, $this->createContainer([
            Command::class => new FakeActivityLogFactory(),
        ]));

        $aggregate = new Command(
            id: new CommandId(1),
            name: new CommandName('NAME'),
            type: CommandTypeEnum::Check,
            commandLine: new CommandLine('/bin/true'),
            isShellEnabled: false,
            isActivated: true,
            isFromMonitoringConnector: false,
            connector: null,
            comment: null,
        );

        $handler(new CommandUpdated(
            aggregate: $aggregate,
            creatorId: 2,
            firedAt: $firedAt = new \DateTimeImmutable(),
        ));

        $activityLog = reset($repository->activityLogs);

        self::assertInstanceof(ActivityLog::class, $activityLog);
        self::assertSame(ActionEnum::Update, $activityLog->action);
        self::assertSame(2, $activityLog->actor->id->value);
        self::assertSame(1, $activityLog->target->id->value);
        self::assertSame('NAME', $activityLog->target->name->value);
        self::assertSame(TargetTypeEnum::Command, $activityLog->target->type);
        self::assertEquals($firedAt, $activityLog->performedAt);
    }

    public function testCreateActivityLogOnDelete(): void
    {
        $repository = new FakeActivityLogRepository();

        $handler = new LogActivityEventHandler($repository, $this->createContainer([
            Command::class => new FakeActivityLogFactory(),
        ]));

        $aggregate = new Command(
            id: new CommandId(1),
            name: new CommandName('NAME'),
            type: CommandTypeEnum::Check,
            commandLine: new CommandLine('/bin/true'),
            isShellEnabled: false,
            isActivated: true,
            isFromMonitoringConnector: false,
            connector: null,
            comment: null,
        );

        $handler(new CommandDeleted(
            aggregate: $aggregate,
            creatorId: 2,
            firedAt: $firedAt = new \DateTimeImmutable(),
        ));

        $activityLog = reset($repository->activityLogs);

        self::assertInstanceof(ActivityLog::class, $activityLog);
        self::assertSame(ActionEnum::Delete, $activityLog->action);
        self::assertSame(2, $activityLog->actor->id->value);
        self::assertSame(1, $activityLog->target->id->value);
        self::assertSame('NAME', $activityLog->target->name->value);
        self::assertSame(TargetTypeEnum::Command, $activityLog->target->type);
        self::assertEquals($firedAt, $activityLog->performedAt);
    }

    public function testCreateActivityLogOnEnable(): void
    {
        $repository = new FakeActivityLogRepository();

        $handler = new LogActivityEventHandler($repository, $this->createContainer([
            Host::class => new FakeActivityLogFactory(),
        ]));

        // HostEnabled extends AggregateUpdated: this guards the match-arm ordering in the handler
        // (Enable/Disable must be matched before the generic AggregateUpdated -> Update arm).
        $handler(new HostEnabled(
            aggregate: $this->host(),
            creatorId: 2,
            firedAt: $firedAt = new \DateTimeImmutable(),
        ));

        $activityLog = reset($repository->activityLogs);

        self::assertInstanceof(ActivityLog::class, $activityLog);
        self::assertSame(ActionEnum::Enable, $activityLog->action);
        self::assertEquals($firedAt, $activityLog->performedAt);
    }

    public function testCreateActivityLogOnDisable(): void
    {
        $repository = new FakeActivityLogRepository();

        $handler = new LogActivityEventHandler($repository, $this->createContainer([
            Host::class => new FakeActivityLogFactory(),
        ]));

        $handler(new HostDisabled(
            aggregate: $this->host(),
            creatorId: 2,
            firedAt: $firedAt = new \DateTimeImmutable(),
        ));

        $activityLog = reset($repository->activityLogs);

        self::assertInstanceof(ActivityLog::class, $activityLog);
        self::assertSame(ActionEnum::Disable, $activityLog->action);
        self::assertEquals($firedAt, $activityLog->performedAt);
    }

    private function host(): Host
    {
        return new Host(
            id: new HostId(1),
            name: new HostName('server-01'),
            alias: null,
            address: new HostAddress('127.0.0.1'),
            activated: true,
            pollerId: new PollerId(1),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
        );
    }

    /**
     * @param array<string, object> $factories
     */
    private function createContainer(array $factories = []): ContainerInterface
    {
        return new class ($factories) implements ContainerInterface {
            /**
             * @param array<string, object> $factories
             */
            public function __construct(
                private array $factories,
            ) {
            }

            public function has(string $id): bool
            {
                return isset($this->factories[$id]);
            }

            public function get(string $id): object
            {
                return $this->factories[$id];
            }
        };
    }
}
