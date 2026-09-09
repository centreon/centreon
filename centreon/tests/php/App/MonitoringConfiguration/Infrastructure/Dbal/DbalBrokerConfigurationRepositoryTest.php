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

use App\MonitoringConfiguration\Domain\Aggregate\Poller\CentralAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Factory\BrokerConfigurationFactory;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalBrokerConfigurationRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalBrokerConfigurationRepositoryTest extends KernelTestCase
{
    private const POLLER_ID = 2;
    private const ON_PREM_CENTRAL_ADDRESS = '10.0.0.1';
    private const CLOUD_CENTRAL_ADDRESS = 'staging.euwest1.centreon.click/funky-donkey';
    private const CLOUD_BROKER_HOST = 'broker-funky-donkey-staging.euwest1.centreon.click';

    private DbalBrokerConfigurationRepository $repository;

    private BrokerConfigurationFactory $factory;

    private Connection $connection;

    protected function setUp(): void
    {
        /** @var DbalBrokerConfigurationRepository $repository */
        $repository = self::getContainer()->get(DbalBrokerConfigurationRepository::class);
        $this->repository = $repository;

        $this->factory = new BrokerConfigurationFactory();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        $this->connection->insert('nagios_server', [
            'id' => self::POLLER_ID,
            'name' => 'TestPoller',
            'localhost' => '0',
            'ns_activate' => '1',
            'ns_ip_address' => '192.168.1.100',
            'uid' => 100000000000002,
        ]);
    }

    public function testItInsertsCfgCentreonBrokerModuleRow(): void
    {
        $config = $this->factory->createDefault(
            new PollerId(self::POLLER_ID),
            'TestPoller',
            false,
            new CentralAddress(self::ON_PREM_CENTRAL_ADDRESS),
        );

        $this->repository->add($config);

        $row = $this->connection->fetchAssociative(
            'SELECT * FROM cfg_centreonbroker WHERE ns_nagios_server = :id',
            ['id' => self::POLLER_ID],
        );

        self::assertIsArray($row);
        self::assertSame('testpoller-module', $row['config_name']);
        self::assertSame('testpoller-module.json', $row['config_filename']);
        self::assertSame('0', $row['config_write_timestamp']);
        self::assertSame('0', $row['config_write_thread_id']);
        self::assertSame('1', $row['config_activate']);
        self::assertSame('1', $row['stats_activate']);
        self::assertSame(0, $row['daemon']);
        self::assertSame(100000, $row['event_queue_max_size']);
        self::assertSame('', $row['command_file']);
        self::assertSame('/var/lib/centreon-engine', $row['cache_directory']);
        self::assertSame('/var/log/centreon-broker', $row['log_directory']);
        self::assertSame('3.1.0', $row['bbdo_version']);
    }

    public function testItInsertsIpv4CentralModuleOutputFlowOnPrem(): void
    {
        $config = $this->factory->createDefault(
            new PollerId(self::POLLER_ID),
            'TestPoller',
            false,
            new CentralAddress(self::ON_PREM_CENTRAL_ADDRESS),
        );

        $this->repository->add($config);
        $configId = $config->id()->value;

        $rows = $this->connection->fetchAllAssociative(
            "SELECT config_key, config_value, config_group_id FROM cfg_centreonbroker_info
             WHERE config_id = :id AND config_group = 'output'",
            ['id' => $configId],
        );

        self::assertCount(18, $rows);

        $params = [];
        foreach ($rows as $row) {
            $params[$row['config_key']] = $row['config_value'];
            self::assertSame(0, $row['config_group_id']);
        }

        self::assertSame('ipv4', $params['type']);
        self::assertSame('1_3', $params['blockId']);
        self::assertSame(self::ON_PREM_CENTRAL_ADDRESS, $params['host']);
        self::assertSame('5669', $params['port']);
        self::assertSame('no', $params['one_peer_retention_mode']);
    }

    public function testItInsertsBbdoClientCentralModuleOutputFlowOnCloud(): void
    {
        $config = $this->factory->createDefault(
            new PollerId(self::POLLER_ID),
            'TestPoller',
            true,
            new CentralAddress(self::CLOUD_CENTRAL_ADDRESS),
            'central-token',
        );

        $this->repository->add($config);
        $configId = $config->id()->value;

        $rows = $this->connection->fetchAllAssociative(
            "SELECT config_key, config_value FROM cfg_centreonbroker_info
             WHERE config_id = :id AND config_group = 'output'",
            ['id' => $configId],
        );

        self::assertCount(12, $rows);

        $params = [];
        foreach ($rows as $row) {
            $params[$row['config_key']] = $row['config_value'];
        }

        self::assertSame('bbdo_client', $params['type']);
        self::assertSame('1_36', $params['blockId']);
        self::assertSame(self::CLOUD_BROKER_HOST, $params['host']);
        self::assertSame('443', $params['port']);
        self::assertSame('gRPC', $params['transport_protocol']);
        self::assertSame('central-token', $params['authorization']);
        self::assertSame('yes', $params['encryption']);
    }

    public function testGetCentralBbdoServerAuthorizationTokenReturnsTheInputToken(): void
    {
        $this->seedCentralBrokerWithBbdoServerInput('super-secret-token');

        self::assertSame('super-secret-token', $this->repository->getCentralBbdoServerAuthorizationToken());
    }

    public function testGetCentralBbdoServerAuthorizationTokenThrowsWhenNoToken(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->repository->getCentralBbdoServerAuthorizationToken();
    }

    public function testItInsertsBrokerLogRowsWithResolvedIds(): void
    {
        $config = $this->factory->createDefault(
            new PollerId(self::POLLER_ID),
            'TestPoller',
            false,
            new CentralAddress(self::ON_PREM_CENTRAL_ADDRESS),
        );

        $this->repository->add($config);
        $configId = $config->id()->value;

        $rows = $this->connection->fetchAllKeyValue(
            'SELECT id_log, id_level FROM cfg_centreonbroker_log WHERE id_centreonbroker = :id',
            ['id' => $configId],
        );

        self::assertCount(18, $rows);
        // cb_log: core=1, sql=3 ; cb_log_level: error=3, info=5
        self::assertEquals(5, $rows[1]);
        self::assertEquals(3, $rows[3]);
    }

    public function testItThrowsWhenBrokerLogCategoryHasNoMatchingRow(): void
    {
        // Remove the seeded cb_log row the default config relies on, so the name->id lookup misses.
        $this->connection->delete('cb_log', ['name' => 'core']);

        $config = $this->factory->createDefault(
            new PollerId(self::POLLER_ID),
            'TestPoller',
            false,
            new CentralAddress(self::ON_PREM_CENTRAL_ADDRESS),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown broker log category "core"');

        $this->repository->add($config);
    }

    public function testItThrowsWhenBrokerLogLevelHasNoMatchingRow(): void
    {
        // Remove the seeded cb_log_level row the default config relies on, so the name->id lookup misses.
        $this->connection->delete('cb_log_level', ['name' => 'error']);

        $config = $this->factory->createDefault(
            new PollerId(self::POLLER_ID),
            'TestPoller',
            false,
            new CentralAddress(self::ON_PREM_CENTRAL_ADDRESS),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown broker log level "error"');

        $this->repository->add($config);
    }

    public function testItSetsAggregateId(): void
    {
        $config = $this->factory->createDefault(
            new PollerId(self::POLLER_ID),
            'TestPoller',
            false,
            new CentralAddress(self::ON_PREM_CENTRAL_ADDRESS),
        );

        $this->repository->add($config);

        self::assertGreaterThan(0, $config->id()->value);
    }

    /**
     * Seeds a central server + its `-broker` config carrying a bbdo_server input with an
     * authorization token, matching the shape read by getCentralBbdoServerAuthorizationToken().
     */
    private function seedCentralBrokerWithBbdoServerInput(string $token): void
    {
        $this->connection->insert('nagios_server', [
            'id' => 1,
            'name' => 'Central',
            'localhost' => '1',
            'ns_activate' => '1',
            'ns_ip_address' => '127.0.0.1',
            'uid' => 100000000000001,
        ]);
        $this->connection->insert('cfg_centreonbroker', [
            'config_name' => 'central-broker',
            'config_filename' => 'central-broker.json',
            'config_activate' => '1',
            'ns_nagios_server' => 1,
            // daemon = 0 mirrors an HA platform; the token must still be found (no daemon filter).
            'daemon' => 0,
        ]);
        $configId = (int) $this->connection->lastInsertId();

        $inputRows = [
            ['config_key' => 'type', 'config_value' => 'bbdo_server'],
            ['config_key' => 'authorization', 'config_value' => $token],
        ];
        foreach ($inputRows as $row) {
            $this->connection->insert('cfg_centreonbroker_info', [
                'config_id' => $configId,
                'config_key' => $row['config_key'],
                'config_value' => $row['config_value'],
                'config_group' => 'input',
                'config_group_id' => 0,
                'grp_level' => 0,
            ]);
        }
    }
}
