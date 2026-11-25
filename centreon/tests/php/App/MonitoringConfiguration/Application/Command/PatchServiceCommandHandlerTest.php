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

use App\MonitoringConfiguration\Application\Command\PatchServiceCommand;
use App\MonitoringConfiguration\Application\Command\PatchServiceCommandHandler;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Domain\Event\ServiceDisabled;
use App\MonitoringConfiguration\Domain\Event\ServiceEnabled;
use App\MonitoringConfiguration\Domain\Exception\ServiceNotFoundException;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceRepository;
use Tests\App\Security\Infrastructure\Double\TestActingUsers;
use Tests\App\Shared\Double\EventBusSpy;

final class PatchServiceCommandHandlerTest extends TestCase
{
    private FakeServiceRepository $serviceRepository;

    private EventBusSpy $eventBus;

    private PatchServiceCommandHandler $handler;

    protected function setUp(): void
    {
        $this->serviceRepository = new FakeServiceRepository();
        $this->eventBus = new EventBusSpy();

        $this->handler = new PatchServiceCommandHandler($this->serviceRepository, $this->eventBus);
    }

    public function testNotFound(): void
    {
        $this->expectException(ServiceNotFoundException::class);

        ($this->handler)(new PatchServiceCommand(new ServiceId(999), TestActingUsers::unrestricted(), activated: false));
    }

    public function testAServiceOutsideTheRestrictedActorScopeIsNotFoundAndUnchanged(): void
    {
        $service = $this->seedService(activated: true);
        $this->serviceRepository->outOfRestrictedScopeIds[] = $service->id()->value;

        try {
            ($this->handler)(new PatchServiceCommand($service->id(), TestActingUsers::restricted(7), activated: false));
            self::fail('A service the actor cannot see must be reported as not found.');
        } catch (ServiceNotFoundException) {
            self::assertTrue($service->activated);
            $this->assertNothingWrittenNorFired();
        }
    }

    public function testNoOpWhenTheActivationIsNotGiven(): void
    {
        $service = $this->seedService(activated: true);

        ($this->handler)(new PatchServiceCommand($service->id(), TestActingUsers::unrestricted()));

        self::assertTrue($service->activated);
        $this->assertNothingWrittenNorFired();
    }

    public function testNoOpWhenAnEnabledServiceIsEnabled(): void
    {
        $service = $this->seedService(activated: true);

        ($this->handler)(new PatchServiceCommand($service->id(), TestActingUsers::unrestricted(), activated: true));

        self::assertTrue($service->activated);
        $this->assertNothingWrittenNorFired();
    }

    public function testNoOpWhenADisabledServiceIsDisabled(): void
    {
        $service = $this->seedService(activated: false);

        ($this->handler)(new PatchServiceCommand($service->id(), TestActingUsers::unrestricted(), activated: false));

        self::assertFalse($service->activated);
        $this->assertNothingWrittenNorFired();
    }

    public function testEnables(): void
    {
        $service = $this->seedService(activated: false);

        ($this->handler)(new PatchServiceCommand($service->id(), TestActingUsers::unrestricted(4), activated: true));

        self::assertTrue($service->activated);
        self::assertSame([['id' => $service->id()->value, 'activated' => true]], $this->serviceRepository->activationUpdates);
        self::assertSame([], $this->eventBus->getDispatchedEvents(ServiceDisabled::class));
        $events = $this->eventBus->getDispatchedEvents(ServiceEnabled::class);
        self::assertCount(1, $events);
        self::assertSame($service, $events[0]->aggregate);
        self::assertSame(4, $events[0]->creatorId);
    }

    public function testDisables(): void
    {
        $service = $this->seedService(activated: true);

        ($this->handler)(new PatchServiceCommand($service->id(), TestActingUsers::unrestricted(4), activated: false));

        self::assertFalse($service->activated);
        self::assertSame([['id' => $service->id()->value, 'activated' => false]], $this->serviceRepository->activationUpdates);
        self::assertSame([], $this->eventBus->getDispatchedEvents(ServiceEnabled::class));
        $events = $this->eventBus->getDispatchedEvents(ServiceDisabled::class);
        self::assertCount(1, $events);
        self::assertSame($service, $events[0]->aggregate);
        self::assertSame(4, $events[0]->creatorId);
    }

    private function seedService(bool $activated): Service
    {
        $service = new Service(
            id: null,
            name: new ServiceName('ping'),
            hostId: new HostId(2),
            hostActivated: true,
            hostPollerId: new PollerId(3),
            activated: $activated,
        );
        $this->serviceRepository->add($service);

        return $service;
    }

    private function assertNothingWrittenNorFired(): void
    {
        self::assertSame([], $this->serviceRepository->activationUpdates);
        self::assertSame([], $this->eventBus->getDispatchedEvents(ServiceEnabled::class));
        self::assertSame([], $this->eventBus->getDispatchedEvents(ServiceDisabled::class));
    }
}
