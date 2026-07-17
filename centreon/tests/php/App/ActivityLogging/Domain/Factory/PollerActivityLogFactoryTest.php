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

namespace Tests\App\ActivityLogging\Domain\Factory;

use App\ActivityLogging\Domain\Aggregate\ActionEnum;
use App\ActivityLogging\Domain\Aggregate\Actor;
use App\ActivityLogging\Domain\Aggregate\ActorId;
use App\ActivityLogging\Domain\Aggregate\TargetTypeEnum;
use App\ActivityLogging\Domain\Factory\PollerActivityLogFactory;
use App\MonitoringConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\BrokerConfiguration;
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
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;

final class PollerActivityLogFactoryTest extends TestCase
{
    public function testCreate(): void
    {
        $factory = new PollerActivityLogFactory();

        $poller = new Poller(
            id: null,
            name: new PollerName('MyPoller'),
            address: new PollerAddress('192.168.1.1'),
            isCentral: false,
            isDefault: false,
            isActivated: true,
            pollerType: PollerTypeEnum::VM,
            uid: new PollerUid(123456789012345),
            globalMacros: new Collection([], GlobalMacro::class),
            gorgoneConfiguration: new GorgoneConfiguration(),
            engineConfiguration: new EngineInformation(),
            brokerConfiguration: new BrokerConfiguration(),
            connectorConfiguration: new ConnectorConfiguration(),
            trapConfiguration: new TrapConfiguration(),
            pollerCommands: new Collection([], PollerCommand::class),
        );

        $reflection = new \ReflectionProperty(AggregateRoot::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($poller, new PollerId(42));

        $firedAt = new \DateTimeImmutable();

        $activityLog = $factory->create(
            action: ActionEnum::Add,
            aggregate: $poller,
            firedBy: new Actor(id: new ActorId(1)),
            firedAt: $firedAt,
        );

        self::assertSame(ActionEnum::Add, $activityLog->action);
        self::assertSame(42, $activityLog->target->id->value);
        self::assertSame('MyPoller', $activityLog->target->name->value);
        self::assertSame(TargetTypeEnum::Poller, $activityLog->target->type);
        self::assertSame(1, $activityLog->actor->id->value);
        self::assertSame($firedAt, $activityLog->performedAt);
        self::assertSame([
            'ns_name' => 'MyPoller',
            'ns_ip_address' => '192.168.1.1',
            'ns_activate' => '1',
            'poller_type' => 'vm',
        ], $activityLog->details);
    }
}
