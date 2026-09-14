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

namespace Tests\App\MonitoringConfiguration\Application\EventHandler;

use App\MonitoringConfiguration\Application\EventHandler\FlagPollerChangedEventHandler;
use App\MonitoringConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
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
use App\MonitoringConfiguration\Domain\Event\PollerCreated;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakePollerRepository;

final class FlagPollerChangedEventHandlerTest extends TestCase
{
    public function testItFlagsTheHostsPollerAsChanged(): void
    {
        $pollerRepository = new FakePollerRepository();
        $handler = new FlagPollerChangedEventHandler($pollerRepository);

        $host = $this->createHost(pollerId: 5);
        $handler(new HostCreated($host, 1));

        self::assertSame([$host], $pollerRepository->flaggedResources);
    }

    public function testItDoesNothingForAnAggregateThatIsNotPollerScoped(): void
    {
        $pollerRepository = new FakePollerRepository();
        $handler = new FlagPollerChangedEventHandler($pollerRepository);

        $handler(new PollerCreated($this->createPoller(), 1));

        self::assertSame([], $pollerRepository->flaggedResources);
    }

    private function createHost(int $pollerId): Host
    {
        $host = new Host(
            id: null,
            name: new HostName('server-01'),
            alias: null,
            address: new HostAddress('127.0.0.1'),
            activated: true,
            pollerId: new PollerId($pollerId),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
        );

        $reflection = new \ReflectionProperty(AggregateRoot::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($host, new HostId(1));

        return $host;
    }

    private function createPoller(): Poller
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
        $reflection->setValue($poller, new PollerId(1));

        return $poller;
    }
}
