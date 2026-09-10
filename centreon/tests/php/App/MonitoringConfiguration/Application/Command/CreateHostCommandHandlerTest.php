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
use App\Security\Domain\Aggregate\UserId;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostGroupRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakePollerRepository;
use Tests\App\Security\Infrastructure\Double\FakeResourceAccessRepository;
use Tests\App\Shared\Double\EventBusSpy;

final class CreateHostCommandHandlerTest extends TestCase
{
    public function testItCreatesTheHost(): void
    {
        $hostRepository = new FakeHostRepository();
        $pollerRepository = new FakePollerRepository();
        $poller = $this->addPoller($pollerRepository, 1);
        $handler = new CreateHostCommandHandler($hostRepository, $pollerRepository, new FakeHostGroupRepository(), new FakeResourceAccessRepository(), new EventBusSpy());

        $handler(new CreateHostCommand(
            name: new HostName('server-01'),
            address: new HostAddress('127.0.0.1'),
            pollerId: $poller->id(),
            hostGroupIds: new Collection([], HostGroupId::class),
            creatorId: 1,
        ));

        self::assertTrue($hostRepository->existsByName(new HostName('server-01')));
    }

    public function testItRejectsADuplicateName(): void
    {
        $hostRepository = new FakeHostRepository();
        $pollerRepository = new FakePollerRepository();
        $poller = $this->addPoller($pollerRepository, 1);
        $handler = new CreateHostCommandHandler($hostRepository, $pollerRepository, new FakeHostGroupRepository(), new FakeResourceAccessRepository(), new EventBusSpy());

        $command = new CreateHostCommand(
            name: new HostName('server-01'),
            address: new HostAddress('127.0.0.1'),
            pollerId: $poller->id(),
            hostGroupIds: new Collection([], HostGroupId::class),
            creatorId: 1,
        );
        $handler($command);

        $this->expectException(HostAlreadyExistsException::class);

        $handler($command);
    }

    public function testItRejectsAnUnknownPoller(): void
    {
        $handler = new CreateHostCommandHandler(new FakeHostRepository(), new FakePollerRepository(), new FakeHostGroupRepository(), new FakeResourceAccessRepository(), new EventBusSpy());

        $this->expectException(PollerNotFoundException::class);

        $handler(new CreateHostCommand(
            name: new HostName('server-01'),
            address: new HostAddress('127.0.0.1'),
            pollerId: new PollerId(404),
            hostGroupIds: new Collection([], HostGroupId::class),
            creatorId: 1,
        ));
    }

    public function testItRejectsAnUnknownHostGroup(): void
    {
        $pollerRepository = new FakePollerRepository();
        $poller = $this->addPoller($pollerRepository, 1);
        $handler = new CreateHostCommandHandler(new FakeHostRepository(), $pollerRepository, new FakeHostGroupRepository(), new FakeResourceAccessRepository(), new EventBusSpy());

        $this->expectException(HostGroupNotFoundException::class);

        $handler(new CreateHostCommand(
            name: new HostName('server-01'),
            address: new HostAddress('127.0.0.1'),
            pollerId: $poller->id(),
            hostGroupIds: new Collection([new HostGroupId(404)], HostGroupId::class),
            creatorId: 1,
        ));
    }

    public function testItAcceptsAnExistingHostGroup(): void
    {
        $hostRepository = new FakeHostRepository();
        $pollerRepository = new FakePollerRepository();
        $poller = $this->addPoller($pollerRepository, 1);
        $hostGroupRepository = new FakeHostGroupRepository();
        $hostGroupRepository->hostGroups[5] = new HostGroup(id: new HostGroupId(5), name: new HostGroupName('Linux servers'));
        $handler = new CreateHostCommandHandler($hostRepository, $pollerRepository, $hostGroupRepository, new FakeResourceAccessRepository(), new EventBusSpy());

        $handler(new CreateHostCommand(
            name: new HostName('server-01'),
            address: new HostAddress('127.0.0.1'),
            pollerId: $poller->id(),
            hostGroupIds: new Collection([new HostGroupId(5)], HostGroupId::class),
            creatorId: 1,
        ));

        self::assertTrue($hostRepository->existsByName(new HostName('server-01')));
    }

    public function testItDispatchesHostCreated(): void
    {
        $pollerRepository = new FakePollerRepository();
        $poller = $this->addPoller($pollerRepository, 1);
        $eventBus = new EventBusSpy();
        $handler = new CreateHostCommandHandler(new FakeHostRepository(), $pollerRepository, new FakeHostGroupRepository(), new FakeResourceAccessRepository(), $eventBus);

        $handler(new CreateHostCommand(
            name: new HostName('server-01'),
            address: new HostAddress('127.0.0.1'),
            pollerId: $poller->id(),
            hostGroupIds: new Collection([], HostGroupId::class),
            creatorId: 1,
        ));

        self::assertTrue($eventBus->shouldHaveDispatched(HostCreated::class));
    }

    public function testARestrictedViewerCanCreateAHostOnAnAccessiblePoller(): void
    {
        $hostRepository = new FakeHostRepository();
        $pollerRepository = new FakePollerRepository();
        $poller = $this->addPoller($pollerRepository, 1);
        $resourceAccessRepository = new FakeResourceAccessRepository();
        $resourceAccessRepository->unrestrictedPollerAccess = true;
        // this specific poller is accessible
        $handler = new CreateHostCommandHandler($hostRepository, $pollerRepository, new FakeHostGroupRepository(), $resourceAccessRepository, new EventBusSpy());

        $handler(new CreateHostCommand(
            name: new HostName('server-01'),
            address: new HostAddress('127.0.0.1'),
            pollerId: $poller->id(),
            hostGroupIds: new Collection([], HostGroupId::class),
            creatorId: 1,
            viewerId: new UserId(7),
        ));

        self::assertTrue($hostRepository->existsByName(new HostName('server-01')));
    }

    /**
     * A restricted viewer referencing a poller outside their own ACL scope gets the same
     * not-found error as a truly nonexistent poller — legacy does not distinguish the two,
     * to avoid leaking existence information.
     */
    public function testARestrictedViewerCannotCreateAHostOnAnInaccessiblePoller(): void
    {
        $pollerRepository = new FakePollerRepository();
        $poller = $this->addPoller($pollerRepository, 1);
        $resourceAccessRepository = new FakeResourceAccessRepository();
        $resourceAccessRepository->unrestrictedPollerAccess = false;

        $handler = new CreateHostCommandHandler(new FakeHostRepository(), $pollerRepository, new FakeHostGroupRepository(), $resourceAccessRepository, new EventBusSpy());

        $this->expectException(PollerNotFoundException::class);

        $handler(new CreateHostCommand(
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
        $pollerRepository = new FakePollerRepository();
        $poller = $this->addPoller($pollerRepository, 1);
        $hostGroupRepository = new FakeHostGroupRepository();
        $hostGroupRepository->hostGroups[5] = new HostGroup(id: new HostGroupId(5), name: new HostGroupName('Linux servers'));
        $resourceAccessRepository = new FakeResourceAccessRepository();
        // Restricted to a different set of host groups than the one being requested (5).
        $resourceAccessRepository->accessibleHostGroupIds = new Collection([new HostGroupId(9)], HostGroupId::class);

        $handler = new CreateHostCommandHandler(new FakeHostRepository(), $pollerRepository, $hostGroupRepository, $resourceAccessRepository, new EventBusSpy());

        $this->expectException(HostGroupNotFoundException::class);

        $handler(new CreateHostCommand(
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
