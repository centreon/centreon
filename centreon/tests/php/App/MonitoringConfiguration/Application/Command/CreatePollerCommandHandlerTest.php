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
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerName;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerTypeEnum;
use App\MonitoringConfiguration\Domain\Event\PollerCreated;
use App\MonitoringConfiguration\Domain\Exception\PollerAlreadyExistsException;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakePollerRepository;
use Tests\App\Shared\Double\EventBusSpy;

final class CreatePollerCommandHandlerTest extends TestCase
{
    public function testCreatePoller(): void
    {
        $repository = new FakePollerRepository();
        $eventBus = new EventBusSpy();
        $handler = new CreatePollerCommandHandler($repository, $eventBus);

        $command = new CreatePollerCommand(
            name: new PollerName('MyPoller'),
            pollerType: PollerTypeEnum::VM,
            creatorId: 1,
        );

        $poller = $handler($command);

        self::assertNotNull($repository->findOneByName(new PollerName('MyPoller')));
        self::assertSame('MyPoller', $poller->name->value);
        self::assertSame(PollerTypeEnum::VM, $poller->pollerType);
        self::assertNotNull($poller->uuid);
        self::assertFalse($poller->isCentral);
        self::assertTrue($poller->isActivated);
    }

    public function testCreatePollerDefaultsAddressToName(): void
    {
        $repository = new FakePollerRepository();
        $eventBus = new EventBusSpy();
        $handler = new CreatePollerCommandHandler($repository, $eventBus);

        $command = new CreatePollerCommand(
            name: new PollerName('MyPoller'),
            pollerType: PollerTypeEnum::VM,
            creatorId: 1,
        );

        $poller = $handler($command);

        self::assertSame('MyPoller', $poller->address->value);
    }

    public function testCreatePollerWithExplicitAddress(): void
    {
        $repository = new FakePollerRepository();
        $eventBus = new EventBusSpy();
        $handler = new CreatePollerCommandHandler($repository, $eventBus);

        $command = new CreatePollerCommand(
            name: new PollerName('MyPoller'),
            pollerType: PollerTypeEnum::VM,
            creatorId: 1,
            address: new PollerAddress('192.168.1.100'),
        );

        $poller = $handler($command);

        self::assertSame('192.168.1.100', $poller->address->value);
    }

    public function testCannotCreatePollerWithSameName(): void
    {
        $repository = new FakePollerRepository();
        $eventBus = new EventBusSpy();
        $handler = new CreatePollerCommandHandler($repository, $eventBus);

        $command = new CreatePollerCommand(
            name: new PollerName('MyPoller'),
            pollerType: PollerTypeEnum::VM,
            creatorId: 1,
        );

        $handler($command);

        $this->expectException(PollerAlreadyExistsException::class);

        $handler($command);
    }

    public function testCreatePollerGeneratesUuidV7(): void
    {
        $repository = new FakePollerRepository();
        $eventBus = new EventBusSpy();
        $handler = new CreatePollerCommandHandler($repository, $eventBus);

        $command = new CreatePollerCommand(
            name: new PollerName('MyPoller'),
            pollerType: PollerTypeEnum::VM,
            creatorId: 1,
        );

        $poller = $handler($command);

        self::assertNotNull($poller->uuid);
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $poller->uuid->value
        );
    }

    public function testCannotCreatePollerWithSameAddress(): void
    {
        $repository = new FakePollerRepository();
        $eventBus = new EventBusSpy();
        $handler = new CreatePollerCommandHandler($repository, $eventBus);

        $handler(new CreatePollerCommand(
            name: new PollerName('Poller1'),
            pollerType: PollerTypeEnum::VM,
            creatorId: 1,
            address: new PollerAddress('192.168.1.100'),
        ));

        $this->expectException(PollerAlreadyExistsException::class);

        $handler(new CreatePollerCommand(
            name: new PollerName('Poller2'),
            pollerType: PollerTypeEnum::VM,
            creatorId: 1,
            address: new PollerAddress('192.168.1.100'),
        ));
    }

    public function testCannotCreatePollerWithSameDefaultAddress(): void
    {
        $repository = new FakePollerRepository();
        $eventBus = new EventBusSpy();
        $handler = new CreatePollerCommandHandler($repository, $eventBus);

        $handler(new CreatePollerCommand(
            name: new PollerName('SameName'),
            pollerType: PollerTypeEnum::VM,
            creatorId: 1,
        ));

        $this->expectException(PollerAlreadyExistsException::class);

        $handler(new CreatePollerCommand(
            name: new PollerName('OtherPoller'),
            pollerType: PollerTypeEnum::VM,
            creatorId: 1,
            address: new PollerAddress('SameName'),
        ));
    }

    public function testCreatePollerWithDockerType(): void
    {
        $repository = new FakePollerRepository();
        $eventBus = new EventBusSpy();
        $handler = new CreatePollerCommandHandler($repository, $eventBus);

        $command = new CreatePollerCommand(
            name: new PollerName('DockerPoller'),
            pollerType: PollerTypeEnum::Docker,
            creatorId: 1,
        );

        $poller = $handler($command);

        self::assertSame(PollerTypeEnum::Docker, $poller->pollerType);
    }

    public function testDispatchPollerCreatedEvent(): void
    {
        $repository = new FakePollerRepository();
        $eventBus = new EventBusSpy();
        $handler = new CreatePollerCommandHandler($repository, $eventBus);

        $handler(new CreatePollerCommand(
            name: new PollerName('MyPoller'),
            pollerType: PollerTypeEnum::VM,
            creatorId: 1,
        ));

        self::assertTrue($eventBus->shouldHaveDispatched(PollerCreated::class));
    }
}
