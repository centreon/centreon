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

use App\MonitoringConfiguration\Application\Command\DeleteHostTemplateServiceCommand;
use App\MonitoringConfiguration\Application\Command\DeleteHostTemplateServiceCommandHandler;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Domain\Event\ServiceDeleted;
use App\MonitoringConfiguration\Domain\Event\ServiceVaultPurgeRequested;
use App\Security\Domain\Aggregate\UserId;
use App\Shared\Application\Command\NonTransactionalCommand;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceRepository;
use Tests\App\Shared\Double\EventBusSpy;

final class DeleteHostTemplateServiceCommandHandlerTest extends TestCase
{
    private FakeServiceRepository $serviceRepository;

    private EventBusSpy $eventBus;

    private DeleteHostTemplateServiceCommandHandler $handler;

    protected function setUp(): void
    {
        $this->serviceRepository = new FakeServiceRepository();
        $this->eventBus = new EventBusSpy();
        $this->handler = new DeleteHostTemplateServiceCommandHandler($this->serviceRepository, $this->eventBus);
    }

    /**
     * Each service is its own unit: the transaction middleware rolls back only this service on failure.
     */
    public function testTheCommandIsTransactional(): void
    {
        self::assertFalse((new \ReflectionClass(DeleteHostTemplateServiceCommand::class))->implementsInterface(NonTransactionalCommand::class));
    }

    public function testItDeletesTheServiceAndFiresServiceDeletedForTheActor(): void
    {
        $service = $this->seedService(hostId: 2);

        ($this->handler)(new DeleteHostTemplateServiceCommand($service->id(), new HostId(2), new UserId(4)));

        self::assertArrayNotHasKey($service->id()->value, $this->serviceRepository->services);
        // The service as loaded before deletion: name, host and poller to flag.
        $deleted = $this->eventBus->getDispatchedEvents(ServiceDeleted::class);
        self::assertCount(1, $deleted);
        self::assertSame($service, $deleted[0]->aggregate);
        self::assertSame(4, $deleted[0]->creatorId);
    }

    public function testItRequestsABestEffortVaultPurge(): void
    {
        $service = $this->seedService(hostId: 2);

        ($this->handler)(new DeleteHostTemplateServiceCommand($service->id(), new HostId(2), new UserId(4)));

        $purges = $this->eventBus->getDispatchedEvents(ServiceVaultPurgeRequested::class);
        self::assertCount(1, $purges);
        self::assertSame($service, $purges[0]->service);
        self::assertTrue($purges[0]->bestEffort);
    }

    public function testAServiceNoLongerOnTheHostIsIgnored(): void
    {
        $onAnotherHost = $this->seedService(hostId: 3);

        ($this->handler)(new DeleteHostTemplateServiceCommand($onAnotherHost->id(), new HostId(2), new UserId(4)));
        ($this->handler)(new DeleteHostTemplateServiceCommand(new ServiceId($onAnotherHost->id()->value + 1), new HostId(2), new UserId(4)));

        self::assertArrayHasKey($onAnotherHost->id()->value, $this->serviceRepository->services);
        self::assertTrue($this->eventBus->shouldNotHaveDispatched(ServiceDeleted::class));
        self::assertTrue($this->eventBus->shouldNotHaveDispatched(ServiceVaultPurgeRequested::class));
    }

    private function seedService(int $hostId): Service
    {
        $service = new Service(
            id: null,
            name: new ServiceName('ping'),
            hostId: new HostId($hostId),
            hostActivated: true,
            hostPollerId: new PollerId(3),
        );
        $this->serviceRepository->add($service);

        return $service;
    }
}
