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
use App\MonitoringConfiguration\Domain\Aggregate\Poller\GorgoneCommunicationTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerName;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerTypeEnum;
use App\MonitoringConfiguration\Domain\Event\PollerCreated;
use PHPUnit\Framework\TestCase;
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
        $handler = new CreatePollerCommandHandler($repository, $eventBus, $uidGenerator);

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
        $handler = new CreatePollerCommandHandler($repository, $eventBus, $uidGenerator);

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
        $handler = new CreatePollerCommandHandler($repository, $eventBus, $uidGenerator);

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
        $handler = new CreatePollerCommandHandler($repository, $eventBus, $uidGenerator);

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

    public function testCentralAddressIsThreadedToEvent(): void
    {
        $repository = new FakePollerRepository();
        $eventBus = new EventBusSpy();
        $uidGenerator = new FakePollerUidGenerator();
        $handler = new CreatePollerCommandHandler($repository, $eventBus, $uidGenerator);

        $handler(new CreatePollerCommand(
            name: new PollerName('MyPoller'),
            pollerType: PollerTypeEnum::VM,
            address: new PollerAddress('192.168.1.1'),
            creatorId: 1,
            centralAddress: '10.0.0.1',
        ));

        $events = $eventBus->getDispatchedEvents(PollerCreated::class);
        self::assertCount(1, $events);
        self::assertSame('10.0.0.1', $events[0]->centralAddress);
    }
}
