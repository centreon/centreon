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

use App\MonitoringConfiguration\Application\Command\CreateHostCommand;
use App\MonitoringConfiguration\Application\Command\CreateHostCommandHandler;
use App\MonitoringConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroup;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupName;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\BrokerInformation;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\ConnectorConfiguration;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\EngineInformation;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\GorgoneConfiguration;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\Poller;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerCommand;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerName;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerUid;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\TrapConfiguration;
use App\MonitoringConfiguration\Domain\Event\HostCreated;
use App\MonitoringConfiguration\Domain\Exception\HostAlreadyExistsException;
use App\MonitoringConfiguration\Domain\Exception\HostGroupNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\PollerNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\HostGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\MonitoringConfiguration\Domain\Repository\PollerRepository;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Event\EventBus;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostGroupRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakePollerRepository;
use Tests\App\Security\Infrastructure\Double\FakeResourceAccessRepository;
use Tests\App\Shared\Double\EventBusSpy;

final class CreateHostCommandHandlerTest extends KernelTestCase
{
    private CreateHostCommandHandler $handler;

    private FakeHostRepository $hostRepository;

    private FakePollerRepository $pollerRepository;

    private FakeHostGroupRepository $hostGroupRepository;

    private FakeResourceAccessRepository $resourceAccessRepository;

    private EventBusSpy $eventBus;

    /**
     * Boots the real container and swaps only the repositories and the event bus for fakes, so
     * the handler itself is built by Symfony's DI exactly as it is in production — catching a
     * wiring break (a constructor argument the service config no longer knows how to autowire)
     * that a hand-instantiated handler would silently miss.
     */
    protected function setUp(): void
    {
        $container = self::getContainer();

        $this->hostRepository = new FakeHostRepository();
        $this->pollerRepository = new FakePollerRepository();
        $this->hostGroupRepository = new FakeHostGroupRepository();
        $this->resourceAccessRepository = new FakeResourceAccessRepository();
        $this->eventBus = new EventBusSpy();

        $container->set(HostRepository::class, $this->hostRepository);
        $container->set(PollerRepository::class, $this->pollerRepository);
        $container->set(HostGroupRepository::class, $this->hostGroupRepository);
        $container->set(ResourceAccessRepository::class, $this->resourceAccessRepository);
        $container->set(EventBus::class, $this->eventBus);

        /** @var CreateHostCommandHandler $handler */
        $handler = $container->get(CreateHostCommandHandler::class);
        $this->handler = $handler;
    }

    public function testItCreatesTheHost(): void
    {
        $poller = $this->addPoller($this->pollerRepository, 1);

        ($this->handler)(new CreateHostCommand(
            name: new HostName('server-01'),
            address: new HostAddress('127.0.0.1'),
            pollerId: $poller->id(),
            hostGroupIds: new Collection([], HostGroupId::class),
            creatorId: 1,
        ));

        self::assertTrue($this->hostRepository->isNameUsedByHostOrTemplate(new HostName('server-01')));
    }

    public function testItRejectsADuplicateName(): void
    {
        $poller = $this->addPoller($this->pollerRepository, 1);

        $command = new CreateHostCommand(
            name: new HostName('server-01'),
            address: new HostAddress('127.0.0.1'),
            pollerId: $poller->id(),
            hostGroupIds: new Collection([], HostGroupId::class),
            creatorId: 1,
        );
        ($this->handler)($command);

        $this->expectException(HostAlreadyExistsException::class);

        ($this->handler)($command);
    }

    public function testItRejectsAnUnknownPoller(): void
    {
        $this->expectException(PollerNotFoundException::class);

        ($this->handler)(new CreateHostCommand(
            name: new HostName('server-01'),
            address: new HostAddress('127.0.0.1'),
            pollerId: new PollerId(404),
            hostGroupIds: new Collection([], HostGroupId::class),
            creatorId: 1,
        ));
    }

    public function testItRejectsAnUnknownHostGroup(): void
    {
        $poller = $this->addPoller($this->pollerRepository, 1);

        $this->expectException(HostGroupNotFoundException::class);

        ($this->handler)(new CreateHostCommand(
            name: new HostName('server-01'),
            address: new HostAddress('127.0.0.1'),
            pollerId: $poller->id(),
            hostGroupIds: new Collection([new HostGroupId(404)], HostGroupId::class),
            creatorId: 1,
        ));
    }

    public function testItAcceptsAnExistingHostGroup(): void
    {
        $poller = $this->addPoller($this->pollerRepository, 1);
        $this->hostGroupRepository->hostGroups[5] = new HostGroup(id: new HostGroupId(5), name: new HostGroupName('Linux servers'));

        ($this->handler)(new CreateHostCommand(
            name: new HostName('server-01'),
            address: new HostAddress('127.0.0.1'),
            pollerId: $poller->id(),
            hostGroupIds: new Collection([new HostGroupId(5)], HostGroupId::class),
            creatorId: 1,
        ));

        self::assertTrue($this->hostRepository->isNameUsedByHostOrTemplate(new HostName('server-01')));
    }

    public function testItDispatchesHostCreated(): void
    {
        $poller = $this->addPoller($this->pollerRepository, 1);

        ($this->handler)(new CreateHostCommand(
            name: new HostName('server-01'),
            address: new HostAddress('127.0.0.1'),
            pollerId: $poller->id(),
            hostGroupIds: new Collection([], HostGroupId::class),
            creatorId: 1,
        ));

        self::assertTrue($this->eventBus->shouldHaveDispatched(HostCreated::class));
    }

    public function testARestrictedViewerCanCreateAHostOnAnAccessiblePoller(): void
    {
        $poller = $this->addPoller($this->pollerRepository, 1);
        // this specific poller is accessible
        $this->resourceAccessRepository->unrestrictedPollerAccess = true;

        ($this->handler)(new CreateHostCommand(
            name: new HostName('server-01'),
            address: new HostAddress('127.0.0.1'),
            pollerId: $poller->id(),
            hostGroupIds: new Collection([], HostGroupId::class),
            creatorId: 1,
            viewerId: new UserId(7),
        ));

        self::assertTrue($this->hostRepository->isNameUsedByHostOrTemplate(new HostName('server-01')));
    }

    /**
     * A restricted viewer referencing a poller outside their own ACL scope gets the same
     * not-found error as a truly nonexistent poller — legacy does not distinguish the two,
     * to avoid leaking existence information.
     */
    public function testARestrictedViewerCannotCreateAHostOnAnInaccessiblePoller(): void
    {
        $poller = $this->addPoller($this->pollerRepository, 1);
        $this->resourceAccessRepository->unrestrictedPollerAccess = false;

        $this->expectException(PollerNotFoundException::class);

        ($this->handler)(new CreateHostCommand(
            name: new HostName('server-01'),
            address: new HostAddress('127.0.0.1'),
            pollerId: $poller->id(),
            hostGroupIds: new Collection([], HostGroupId::class),
            creatorId: 1,
            viewerId: new UserId(7),
        ));
    }

    public function testARestrictedViewerCannotReferenceAHostGroupOutsideTheirAccessibleScope(): void
    {
        $poller = $this->addPoller($this->pollerRepository, 1);
        $this->hostGroupRepository->hostGroups[5] = new HostGroup(id: new HostGroupId(5), name: new HostGroupName('Linux servers'));
        // Restricted to a different set of host groups than the one being requested (5).
        $this->resourceAccessRepository->accessibleHostGroupIds = new Collection([new HostGroupId(9)], HostGroupId::class);

        $this->expectException(HostGroupNotFoundException::class);

        ($this->handler)(new CreateHostCommand(
            name: new HostName('server-01'),
            address: new HostAddress('127.0.0.1'),
            pollerId: $poller->id(),
            hostGroupIds: new Collection([new HostGroupId(5)], HostGroupId::class),
            creatorId: 1,
            viewerId: new UserId(7),
        ));
    }

    private function addPoller(FakePollerRepository $repository, int $id): Poller
    {
        $poller = new Poller(
            id: null,
            name: new PollerName('Central'),
            address: new PollerAddress('127.0.0.1'),
            isCentral: true,
            isDefault: true,
            isActivated: true,
            pollerType: PollerTypeEnum::VM,
            uid: new PollerUid(123456789012345),
            globalMacros: new Collection([], GlobalMacro::class),
            gorgoneConfiguration: new GorgoneConfiguration(),
            engineInformation: new EngineInformation(),
            brokerInformation: new BrokerInformation(),
            connectorConfiguration: new ConnectorConfiguration(),
            trapConfiguration: new TrapConfiguration(),
            pollerCommands: new Collection([], PollerCommand::class),
        );

        $reflection = new \ReflectionProperty(AggregateRoot::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($poller, new PollerId($id));

        $repository->pollers[$id] = $poller;

        return $poller;
    }
}
