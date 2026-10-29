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

use App\MonitoringConfiguration\Application\Command\DeleteServiceCommand;
use App\MonitoringConfiguration\Application\Command\DeleteServiceCommandHandler;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Domain\Event\ServiceDeleted;
use App\MonitoringConfiguration\Domain\Event\ServiceVaultPurgeRequested;
use App\MonitoringConfiguration\Domain\Exception\ServiceNotFoundException;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceRepository;
use Tests\App\Security\Infrastructure\Double\TestActingUsers;
use Tests\App\Shared\Double\EventBusSpy;

final class DeleteServiceCommandHandlerTest extends TestCase
{
    private FakeServiceRepository $serviceRepository;

    private EventBusSpy $eventBus;

    private DeleteServiceCommandHandler $handler;

    protected function setUp(): void
    {
        $this->serviceRepository = new FakeServiceRepository();
        $this->eventBus = new EventBusSpy();

        $this->handler = new DeleteServiceCommandHandler($this->serviceRepository, $this->eventBus);
    }

    public function testNotFound(): void
    {
        $this->expectException(ServiceNotFoundException::class);

        ($this->handler)(new DeleteServiceCommand(new ServiceId(999), TestActingUsers::unrestricted()));
    }

    public function testAServiceOutsideTheRestrictedActorScopeIsNotFoundAndKept(): void
    {
        $service = $this->seedService();
        $this->serviceRepository->outOfRestrictedScopeIds[] = $service->id()->value;

        try {
            ($this->handler)(new DeleteServiceCommand($service->id(), TestActingUsers::restricted(7)));
            self::fail('A service the actor cannot see must be reported as not found.');
        } catch (ServiceNotFoundException) {
            self::assertArrayHasKey($service->id()->value, $this->serviceRepository->services);
            self::assertSame([], $this->firedEvents());
        }
    }

    public function testFiresEvents(): void
    {
        $service = $this->seedService();

        ($this->handler)(new DeleteServiceCommand($service->id(), TestActingUsers::unrestricted(4)));

        self::assertArrayNotHasKey($service->id()->value, $this->serviceRepository->services);

        // The deleted service, as loaded before its deletion: it still knows its name and host (activity log)
        // and the poller to flag, which the database can no longer tell.
        $deleted = $this->eventBus->getDispatchedEvents(ServiceDeleted::class);
        self::assertCount(1, $deleted);
        self::assertSame($service, $deleted[0]->aggregate);
        self::assertSame(4, $deleted[0]->creatorId);

        $purges = $this->eventBus->getDispatchedEvents(ServiceVaultPurgeRequested::class);
        self::assertCount(1, $purges);
        self::assertSame($service, $purges[0]->service);
    }

    private function seedService(): Service
    {
        $service = new Service(
            id: null,
            name: new ServiceName('ping'),
            hostId: new HostId(2),
            hostActivated: true,
            hostPollerId: new PollerId(3),
        );
        $this->serviceRepository->add($service);

        return $service;
    }

    /**
     * @return list<object>
     */
    private function firedEvents(): array
    {
        return [
            ...$this->eventBus->getDispatchedEvents(ServiceDeleted::class),
            ...$this->eventBus->getDispatchedEvents(ServiceVaultPurgeRequested::class),
        ];
    }
}
