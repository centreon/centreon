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

use App\MonitoringConfiguration\Application\Command\CreateEngineConfigurationCommand;
use App\MonitoringConfiguration\Application\Command\LinkGlobalMacrosToPollerCommand;
use App\MonitoringConfiguration\Application\EventHandler\CreatePollerConfigurationsEventHandler;
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
use App\MonitoringConfiguration\Domain\Event\PollerCreated;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;

final class CreatePollerConfigurationsEventHandlerTest extends TestCase
{
    public function testItDispatchesConfigurationCommands(): void
    {
        $poller = $this->createPoller(42, 'My Poller');
        $event = new PollerCreated($poller, 1);

        $dispatched = [];
        $commandBus = $this->createMock(CommandBus::class);
        $commandBus->expects(self::exactly(2))
            ->method('execute')
            ->willReturnCallback(static function (object $command) use (&$dispatched): void {
                $dispatched[] = $command;
            });

        $handler = new CreatePollerConfigurationsEventHandler($commandBus);
        $handler($event);

        self::assertInstanceOf(CreateEngineConfigurationCommand::class, $dispatched[0]);
        self::assertSame(42, $dispatched[0]->pollerId->value);
        self::assertSame('My Poller', $dispatched[0]->pollerName);

        self::assertInstanceOf(LinkGlobalMacrosToPollerCommand::class, $dispatched[1]);
        self::assertSame(42, $dispatched[1]->pollerId->value);
    }

    private function createPoller(int $pollerId, string $pollerName): Poller
    {
        $poller = new Poller(
            id: null,
            name: new PollerName($pollerName),
            address: new PollerAddress('127.0.0.1'),
            isCentral: false,
            isDefault: false,
            isActivated: true,
            pollerType: PollerTypeEnum::VM,
            uid: new PollerUid(123456789012345),
            globalMacros: new Collection([], GlobalMacro::class),
            gorgoneConfiguration: new GorgoneConfiguration(),
            engineInformation: new EngineInformation(),
            brokerConfiguration: new BrokerConfiguration(),
            connectorConfiguration: new ConnectorConfiguration(),
            trapConfiguration: new TrapConfiguration(),
            pollerCommands: new Collection([], PollerCommand::class),
        );

        $reflection = new \ReflectionProperty(AggregateRoot::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($poller, new PollerId($pollerId));

        return $poller;
    }
}
