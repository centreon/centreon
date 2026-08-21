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

use App\MonitoringConfiguration\Application\Command\CreateBrokerConfigurationCommand;
use App\MonitoringConfiguration\Application\Command\CreateEngineConfigurationCommand;
use App\MonitoringConfiguration\Application\EventHandler\CreatePollerConfigurationsEventHandler;
use App\MonitoringConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\BrokerInformation;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\CentralAddress;
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
    private const CENTRAL_ADDRESS = '10.1.2.3';
    private const CLOUD_CENTRAL_ADDRESS = 'staging.euwest1.centreon.click/funky-donkey';

    public function testItDispatchesConfigurationCommands(): void
    {
        $commands = $this->handlePollerCreated(42, 'My Poller');

        $engineCommands = array_values(array_filter(
            $commands,
            static fn (object $command): bool => $command instanceof CreateEngineConfigurationCommand
        ));

        self::assertCount(1, $engineCommands);
        self::assertSame(42, $engineCommands[0]->pollerId->value);
        self::assertSame('My Poller', $engineCommands[0]->pollerName);
    }

    public function testItDispatchesCreateBrokerConfigurationCommand(): void
    {
        $commands = $this->handlePollerCreated(42, 'My Poller');

        $brokerCommands = array_values(array_filter(
            $commands,
            static fn (object $command): bool => $command instanceof CreateBrokerConfigurationCommand
        ));

        self::assertCount(1, $brokerCommands);
        self::assertSame(42, $brokerCommands[0]->pollerId->value);
        self::assertSame('My Poller', $brokerCommands[0]->pollerName);
        // The poller's central address is forwarded so the broker output host is not empty.
        self::assertSame(self::CENTRAL_ADDRESS, $brokerCommands[0]->centralAddress->value);
    }

    /**
     * On cloud the central address carries the platform base path, from which the broker gateway
     * host is derived: nothing may flatten it to a bare host on the way to the command.
     */
    public function testItForwardsACentralAddressCarryingABasePathUnchanged(): void
    {
        $commands = $this->handlePollerCreated(42, 'My Poller', self::CLOUD_CENTRAL_ADDRESS);

        $brokerCommands = array_values(array_filter(
            $commands,
            static fn (object $command): bool => $command instanceof CreateBrokerConfigurationCommand
        ));

        self::assertCount(1, $brokerCommands);
        self::assertSame(self::CLOUD_CENTRAL_ADDRESS, $brokerCommands[0]->centralAddress->value);
    }

    public function testItRejectsAPollerWithoutCentralAddress(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('A newly created poller must carry a central address.');

        $this->handlePollerCreated(42, 'My Poller', null);
    }

    /**
     * @return list<object> the commands dispatched on the command bus
     */
    private function handlePollerCreated(
        int $pollerId,
        string $pollerName,
        ?string $centralAddress = self::CENTRAL_ADDRESS,
    ): array {
        $poller = $this->createPoller($pollerId, $pollerName, $centralAddress);
        $event = new PollerCreated($poller, 1);

        $dispatchedCommands = [];
        $commandBus = $this->createMock(CommandBus::class);
        $commandBus->method('execute')
            ->willReturnCallback(static function (object $command) use (&$dispatchedCommands): mixed {
                $dispatchedCommands[] = $command;

                return null;
            });

        $handler = new CreatePollerConfigurationsEventHandler($commandBus);
        $handler($event);

        return $dispatchedCommands;
    }

    private function createPoller(int $pollerId, string $pollerName, ?string $centralAddress): Poller
    {
        $poller = new Poller(
            id: null,
            name: new PollerName($pollerName),
            address: new PollerAddress('127.0.0.1'),
            centralAddress: $centralAddress !== null ? new CentralAddress($centralAddress) : null,
            isCentral: false,
            isDefault: false,
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
        $reflection->setValue($poller, new PollerId($pollerId));

        return $poller;
    }
}
