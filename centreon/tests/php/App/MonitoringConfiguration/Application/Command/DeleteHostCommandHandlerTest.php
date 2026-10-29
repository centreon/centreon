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

use App\MonitoringConfiguration\Application\Command\DeleteHostCommand;
use App\MonitoringConfiguration\Application\Command\DeleteHostCommandHandler;
use App\MonitoringConfiguration\Application\EventHandler\FlagPollerChangedEventHandler;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Domain\Event\HostDeleted;
use App\MonitoringConfiguration\Domain\Event\HostVaultPurgeRequested;
use App\MonitoringConfiguration\Domain\Event\ServiceDeleted;
use App\MonitoringConfiguration\Domain\Event\ServiceVaultPurgeRequested;
use App\MonitoringConfiguration\Domain\Exception\HostNotFoundException;
use App\Shared\Domain\Aggregate\AclScopedInterface;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakePollerRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceRepository;
use Tests\App\Security\Infrastructure\Double\TestActingUsers;
use Tests\App\Shared\Double\EventBusSpy;

final class DeleteHostCommandHandlerTest extends TestCase
{
    private FakeHostRepository $hostRepository;

    private FakeServiceRepository $serviceRepository;

    private EventBusSpy $eventBus;

    private DeleteHostCommandHandler $handler;

    protected function setUp(): void
    {
        $this->hostRepository = new FakeHostRepository();
        $this->serviceRepository = new FakeServiceRepository();
        $this->eventBus = new EventBusSpy();

        $this->handler = new DeleteHostCommandHandler(
            $this->hostRepository,
            $this->serviceRepository,
            $this->eventBus,
        );
    }

    public function testItThrowsWhenTheHostDoesNotExist(): void
    {
        $this->expectException(HostNotFoundException::class);

        ($this->handler)(new DeleteHostCommand(new HostId(999), actor: TestActingUsers::unrestricted(1)));
    }

    public function testItThrowsWhenTheHostIsOutsideTheRestrictedDeleterScope(): void
    {
        $host = $this->host();
        $this->hostRepository->add($host);
        $this->hostRepository->resourceAccess->accessibleHostIds = new Collection([], HostId::class);

        $this->expectException(HostNotFoundException::class);

        try {
            ($this->handler)(new DeleteHostCommand($host->id(), actor: TestActingUsers::restricted(7)));
        } finally {
            self::assertNotNull($this->hostRepository->findOne($host->id(), TestActingUsers::unrestricted()));
        }
    }

    public function testAnUnrestrictedDeleterIsNotScopedByAcl(): void
    {
        $host = $this->host();
        $this->hostRepository->add($host);
        $this->hostRepository->resourceAccess->accessibleHostIds = new Collection([], HostId::class);

        ($this->handler)(new DeleteHostCommand($host->id(), actor: TestActingUsers::unrestricted(1)));

        self::assertNull($this->hostRepository->findOne($host->id(), TestActingUsers::unrestricted()));
    }

    public function testItDeletesTheHostAndRequestsItsVaultPurge(): void
    {
        $host = $this->host();
        $this->hostRepository->add($host);

        ($this->handler)(new DeleteHostCommand($host->id(), actor: TestActingUsers::unrestricted(1)));

        self::assertNull($this->hostRepository->findOne($host->id(), TestActingUsers::unrestricted()));
        self::assertTrue($this->eventBus->shouldHaveDispatched(HostDeleted::class));
        self::assertTrue($this->eventBus->shouldHaveDispatched(HostVaultPurgeRequested::class));
        self::assertFalse($this->eventBus->shouldHaveDispatched(ServiceVaultPurgeRequested::class));
    }

    public function testItDeletesExclusivelyLinkedServicesAndFiresTheirEvents(): void
    {
        $host = $this->host();
        $this->hostRepository->add($host);
        $service = $this->service($host->id(), macros: [
            new ServiceMacro(new ServiceMacroName('token'), 'secret::vault::monitoring/services/uuid-2::_SERVICETOKEN', isPassword: true),
        ]);
        $this->serviceRepository->add($service);

        ($this->handler)(new DeleteHostCommand($host->id(), actor: TestActingUsers::unrestricted(1)));

        self::assertCount(0, $this->serviceRepository->findExclusivelyLinkedToHostId($host->id()));
        self::assertTrue($this->eventBus->shouldHaveDispatched(ServiceDeleted::class, times: 1));
        self::assertTrue($this->eventBus->shouldHaveDispatched(ServiceVaultPurgeRequested::class, times: 1));
    }

    public function testItLeavesServicesLinkedToAnotherHostUntouched(): void
    {
        $host = $this->host();
        $this->hostRepository->add($host);
        $otherHostId = new HostId($host->id()->value + 1);
        $unrelatedService = $this->service($otherHostId);
        $this->serviceRepository->add($unrelatedService);

        ($this->handler)(new DeleteHostCommand($host->id(), actor: TestActingUsers::unrestricted(1)));

        self::assertCount(1, $this->serviceRepository->findExclusivelyLinkedToHostId($otherHostId));
        self::assertFalse($this->eventBus->shouldHaveDispatched(ServiceDeleted::class));
        self::assertFalse($this->eventBus->shouldHaveDispatched(ServiceVaultPurgeRequested::class));
    }

    public function testDeletingAnEnabledHostAlsoFlagsAclAndThePollerForEachCascadedService(): void
    {
        $host = $this->host();
        $this->hostRepository->add($host);
        $service = $this->service($host->id(), hostActivated: true);
        $this->serviceRepository->add($service);

        ($this->handler)(new DeleteHostCommand($host->id(), actor: TestActingUsers::unrestricted(1)));

        // The cascaded service is ACL-scoped, so the generic ACL handler flags once more for it (same tables as
        // the host's own flag), and it still names the poller of its enabled host after its row is gone.
        $deleted = $this->eventBus->getDispatchedEvents(ServiceDeleted::class);
        self::assertCount(1, $deleted);
        self::assertInstanceOf(AclScopedInterface::class, $deleted[0]->aggregate);
        self::assertSame([$service, $host], $this->resourcesWhosePollerIsFlagged());
    }

    public function testDeletingADisabledHostFlagsNoPollerForItsCascadedServices(): void
    {
        $host = $this->host();
        $this->hostRepository->add($host);
        $this->serviceRepository->add($this->service($host->id(), hostActivated: false));

        ($this->handler)(new DeleteHostCommand($host->id(), actor: TestActingUsers::unrestricted(1)));

        self::assertTrue($this->eventBus->shouldHaveDispatched(ServiceDeleted::class, times: 1));
        self::assertSame([$host], $this->resourcesWhosePollerIsFlagged());
    }

    /**
     * Runs the deletion events fired through the generic poller handler: the services' events first, then
     * the host's.
     *
     * @return list<object>
     */
    private function resourcesWhosePollerIsFlagged(): array
    {
        $pollerRepository = new FakePollerRepository();
        $handler = new FlagPollerChangedEventHandler($pollerRepository);
        foreach ([
            ...$this->eventBus->getDispatchedEvents(ServiceDeleted::class),
            ...$this->eventBus->getDispatchedEvents(HostDeleted::class),
        ] as $event) {
            $handler($event);
        }

        return $pollerRepository->flaggedResources;
    }

    private function host(): Host
    {
        return new Host(
            id: null,
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
     * @param list<ServiceMacro> $macros
     */
    private function service(HostId $hostId, array $macros = [], bool $hostActivated = true): Service
    {
        return new Service(
            id: null,
            name: new ServiceName('web-check'),
            hostId: $hostId,
            hostActivated: $hostActivated,
            hostPollerId: new PollerId(1),
            macros: $macros,
        );
    }
}
