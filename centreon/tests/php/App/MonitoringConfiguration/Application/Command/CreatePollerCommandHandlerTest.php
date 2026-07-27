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

use App\MonitoringConfiguration\Application\Command\CreatePollerCommand;
use App\MonitoringConfiguration\Application\Command\CreatePollerCommandHandler;
use App\MonitoringConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacro;
use App\MonitoringConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacroExpression;
use App\MonitoringConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\GorgoneCommunicationTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\Poller;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerName;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerTypeEnum;
use App\MonitoringConfiguration\Domain\Event\PollerCreated;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeGlobalMacroRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakePollerRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakePollerUidGenerator;
use Tests\App\Shared\Double\EventBusSpy;

final class CreatePollerCommandHandlerTest extends TestCase
{
    public function testCreatePoller(): void
    {
        $repository = new FakePollerRepository();
        $eventBus = new EventBusSpy();
        $uidGenerator = new FakePollerUidGenerator();
        $globalMacroRepository = new FakeGlobalMacroRepository();
        $handler = new CreatePollerCommandHandler($repository, $eventBus, $uidGenerator, $globalMacroRepository);

        $command = new CreatePollerCommand(
            name: new PollerName('MyPoller'),
            pollerType: PollerTypeEnum::VM,
            address: new PollerAddress('192.168.1.1'),
            creatorId: 1,
        );

        $poller = $handler($command);

        self::assertNotNull($repository->findOneByName(new PollerName('MyPoller')));
        self::assertSame('MyPoller', $poller->name->value);
        self::assertSame(PollerTypeEnum::VM, $poller->pollerType);
        self::assertSame('192.168.1.1', $poller->address->value);
        self::assertGreaterThan(0, $poller->uid->value);
        self::assertFalse($poller->isCentral);
        self::assertTrue($poller->isActivated);
        self::assertSame(GorgoneCommunicationTypeEnum::ZMQ, $poller->gorgoneConfiguration->communicationType);
    }

    public function testCreatePollerWithPullWssCommunicationType(): void
    {
        $repository = new FakePollerRepository();
        $eventBus = new EventBusSpy();
        $uidGenerator = new FakePollerUidGenerator();
        $globalMacroRepository = new FakeGlobalMacroRepository();
        $handler = new CreatePollerCommandHandler($repository, $eventBus, $uidGenerator, $globalMacroRepository);

        $command = new CreatePollerCommand(
            name: new PollerName('CloudPoller'),
            pollerType: PollerTypeEnum::VM,
            address: new PollerAddress('192.168.1.1'),
            creatorId: 1,
            gorgoneCommunicationType: GorgoneCommunicationTypeEnum::PullWss,
        );

        $poller = $handler($command);

        self::assertSame(GorgoneCommunicationTypeEnum::PullWss, $poller->gorgoneConfiguration->communicationType);
    }

    public function testCreatePollerWithDockerType(): void
    {
        $repository = new FakePollerRepository();
        $eventBus = new EventBusSpy();
        $uidGenerator = new FakePollerUidGenerator();
        $globalMacroRepository = new FakeGlobalMacroRepository();
        $handler = new CreatePollerCommandHandler($repository, $eventBus, $uidGenerator, $globalMacroRepository);

        $command = new CreatePollerCommand(
            name: new PollerName('DockerPoller'),
            pollerType: PollerTypeEnum::Docker,
            address: new PollerAddress('192.168.1.1'),
            creatorId: 1,
        );

        $poller = $handler($command);

        self::assertSame(PollerTypeEnum::Docker, $poller->pollerType);
    }

    public function testDispatchPollerCreatedEvent(): void
    {
        $repository = new FakePollerRepository();
        $eventBus = new EventBusSpy();
        $uidGenerator = new FakePollerUidGenerator();
        $globalMacroRepository = new FakeGlobalMacroRepository();
        $handler = new CreatePollerCommandHandler($repository, $eventBus, $uidGenerator, $globalMacroRepository);

        $poller = $handler(new CreatePollerCommand(
            name: new PollerName('MyPoller'),
            pollerType: PollerTypeEnum::VM,
            address: new PollerAddress('192.168.1.1'),
            creatorId: 42,
        ));

        $events = $eventBus->getDispatchedEvents(PollerCreated::class);
        self::assertCount(1, $events);
        self::assertSame($poller, $events[0]->aggregate);
        self::assertSame(42, $events[0]->creatorId);
    }

    public function testPollerReceivesGlobalMacros(): void
    {
        $macro = new GlobalMacro(
            id: new GlobalMacroId(1),
            name: new GlobalMacroName('$USER1$'),
            expression: new GlobalMacroExpression('/usr/lib/nagios/plugins'),
            comment: null,
            isPassword: false,
            activated: true,
            pollers: new Collection([], Poller::class),
        );

        $repository = new FakePollerRepository();
        $eventBus = new EventBusSpy();
        $uidGenerator = new FakePollerUidGenerator();
        $globalMacroRepository = new FakeGlobalMacroRepository([$macro]);
        $handler = new CreatePollerCommandHandler($repository, $eventBus, $uidGenerator, $globalMacroRepository);

        $poller = $handler(new CreatePollerCommand(
            name: new PollerName('MyPoller'),
            pollerType: PollerTypeEnum::VM,
            address: new PollerAddress('192.168.1.1'),
            creatorId: 1,
        ));

        self::assertCount(1, $poller->globalMacros);
        self::assertCount(1, $macro->pollers);
        self::assertSame($poller, $macro->pollers->toArray()[0]);
    }

    public function testPollerReceivesMultipleGlobalMacros(): void
    {
        $macroOne = new GlobalMacro(
            id: new GlobalMacroId(1),
            name: new GlobalMacroName('$USER1$'),
            expression: new GlobalMacroExpression('/usr/lib/nagios/plugins'),
            comment: null,
            isPassword: false,
            activated: true,
            pollers: new Collection([], Poller::class),
        );
        $macroTwo = new GlobalMacro(
            id: new GlobalMacroId(2),
            name: new GlobalMacroName('$USER2$'),
            expression: new GlobalMacroExpression('/usr/lib64/nagios/plugins'),
            comment: null,
            isPassword: false,
            activated: true,
            pollers: new Collection([], Poller::class),
        );
        $macroThree = new GlobalMacro(
            id: new GlobalMacroId(3),
            name: new GlobalMacroName('$USER3$'),
            expression: new GlobalMacroExpression('/usr/local/nagios/plugins'),
            comment: null,
            isPassword: false,
            activated: true,
            pollers: new Collection([], Poller::class),
        );

        $repository = new FakePollerRepository();
        $eventBus = new EventBusSpy();
        $uidGenerator = new FakePollerUidGenerator();
        $globalMacroRepository = new FakeGlobalMacroRepository([$macroOne, $macroTwo, $macroThree]);
        $handler = new CreatePollerCommandHandler($repository, $eventBus, $uidGenerator, $globalMacroRepository);

        $poller = $handler(new CreatePollerCommand(
            name: new PollerName('MyPoller'),
            pollerType: PollerTypeEnum::VM,
            address: new PollerAddress('192.168.1.1'),
            creatorId: 1,
        ));

        self::assertCount(3, $poller->globalMacros);
        self::assertCount(1, $macroOne->pollers);
        self::assertCount(1, $macroTwo->pollers);
        self::assertCount(1, $macroThree->pollers);
    }

    public function testDuplicateMacroNamesAreDeduplicatedByFirstOccurrence(): void
    {
        $firstUser1 = new GlobalMacro(
            id: new GlobalMacroId(1),
            name: new GlobalMacroName('$USER1$'),
            expression: new GlobalMacroExpression('/usr/lib/nagios/plugins'),
            comment: null,
            isPassword: false,
            activated: true,
            pollers: new Collection([], Poller::class),
        );
        $duplicateUser1 = new GlobalMacro(
            id: new GlobalMacroId(10),
            name: new GlobalMacroName('$USER1$'),
            expression: new GlobalMacroExpression('/opt/other/plugins'),
            comment: null,
            isPassword: false,
            activated: true,
            pollers: new Collection([], Poller::class),
        );
        $user2 = new GlobalMacro(
            id: new GlobalMacroId(2),
            name: new GlobalMacroName('$USER2$'),
            expression: new GlobalMacroExpression('/usr/lib64/nagios/plugins'),
            comment: null,
            isPassword: false,
            activated: true,
            pollers: new Collection([], Poller::class),
        );

        $repository = new FakePollerRepository();
        $eventBus = new EventBusSpy();
        $uidGenerator = new FakePollerUidGenerator();
        $globalMacroRepository = new FakeGlobalMacroRepository([$firstUser1, $duplicateUser1, $user2]);
        $handler = new CreatePollerCommandHandler($repository, $eventBus, $uidGenerator, $globalMacroRepository);

        $poller = $handler(new CreatePollerCommand(
            name: new PollerName('MyPoller'),
            pollerType: PollerTypeEnum::VM,
            address: new PollerAddress('192.168.1.1'),
            creatorId: 1,
        ));

        self::assertCount(2, $poller->globalMacros);
        self::assertCount(1, $firstUser1->pollers);
        self::assertCount(0, $duplicateUser1->pollers);
        self::assertCount(1, $user2->pollers);
    }
}
