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

namespace Tests\App\MonitoringConfiguration\Infrastructure\Dbal;

use App\MonitoringConfiguration\Domain\Aggregate\EngineConfiguration\BrokerOptions;
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
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerUuid;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\TrapConfiguration;
use App\MonitoringConfiguration\Domain\Event\PollerCreated;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Event\EventBus;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PollerCreatedEngineConfigurationTest extends KernelTestCase
{
    private Connection $connection;

    private EventBus $eventBus;

    protected function setUp(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        /** @var EventBus $eventBus */
        $eventBus = self::getContainer()->get(EventBus::class);
        $this->eventBus = $eventBus;

        $this->connection->insert('nagios_server', [
            'id' => 1,
            'name' => 'My Poller',
            'localhost' => '0',
            'ns_activate' => '1',
            'ns_ip_address' => '192.168.1.100',
        ]);
    }

    public function testPollerCreatedEventTriggersEngineConfigurationCreation(): void
    {
        $poller = $this->createPoller(1, 'My Poller');

        $this->eventBus->fire(new PollerCreated($poller, 1));

        $cfgNagios = $this->connection->fetchAssociative(
            'SELECT * FROM cfg_nagios WHERE nagios_server_id = :id',
            ['id' => 1],
        );

        self::assertIsArray($cfgNagios);
        self::assertSame('My Poller', $cfgNagios['nagios_name']);
        self::assertSame('1', $cfgNagios['nagios_activate']);
        self::assertSame('1', $cfgNagios['check_service_freshness']);
        self::assertSame('/etc/centreon-broker/my-poller-module.json', $cfgNagios['broker_module_cfg_file']);

        $cfgNagiosId = $cfgNagios['nagios_id'];

        $cfgLogger = $this->connection->fetchAssociative(
            'SELECT * FROM cfg_nagios_logger WHERE cfg_nagios_id = :id',
            ['id' => $cfgNagiosId],
        );

        self::assertIsArray($cfgLogger);
        self::assertSame('file', $cfgLogger['log_v2_logger']);
        self::assertSame('info', $cfgLogger['log_level_config']);
        self::assertSame('err', $cfgLogger['log_level_functions']);

        $cfgBrokerModule = $this->connection->fetchAssociative(
            'SELECT * FROM cfg_nagios_broker_module WHERE cfg_nagios_id = :id',
            ['id' => $cfgNagiosId],
        );

        self::assertIsArray($cfgBrokerModule);
        self::assertSame(BrokerOptions::MODULE_PATH, $cfgBrokerModule['broker_module']);
    }

    private function createPoller(int $pollerId, string $pollerName): Poller
    {
        $poller = new Poller(
            id: null,
            name: new PollerName($pollerName),
            address: new PollerAddress('192.168.1.100'),
            isCentral: false,
            isDefault: false,
            isActivated: true,
            pollerType: PollerTypeEnum::VM,
            uuid: new PollerUuid('01234567-0123-7890-abcd-0123456789ab'),
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
        $reflection->setValue($poller, new PollerId($pollerId));

        return $poller;
    }
}
