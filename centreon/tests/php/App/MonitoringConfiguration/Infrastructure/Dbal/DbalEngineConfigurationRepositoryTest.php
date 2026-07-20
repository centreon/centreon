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
use App\MonitoringConfiguration\Domain\Aggregate\EngineConfiguration\EngineConfiguration;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalEngineConfigurationRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalEngineConfigurationRepositoryTest extends KernelTestCase
{
    private DbalEngineConfigurationRepository $repository;

    private Connection $connection;

    protected function setUp(): void
    {
        /** @var DbalEngineConfigurationRepository $repository */
        $repository = self::getContainer()->get(DbalEngineConfigurationRepository::class);
        $this->repository = $repository;

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        $this->connection->insert('nagios_server', [
            'id' => 1,
            'name' => 'TestPoller',
            'localhost' => '0',
            'ns_activate' => '1',
            'ns_ip_address' => '192.168.1.100',
            'uid' => 100000000000001,
        ]);
    }

    public function testItInsertsCfgNagiosRow(): void
    {
        $cfg = EngineConfiguration::createDefault(new PollerId(1), 'TestPoller');

        $this->repository->add($cfg);

        $row = $this->connection->fetchAssociative(
            'SELECT * FROM cfg_nagios WHERE nagios_server_id = :id',
            ['id' => 1],
        );

        self::assertIsArray($row);
        self::assertSame('TestPoller', $row['nagios_name']);
        self::assertSame('1', $row['nagios_activate']);
        self::assertSame('1', $row['check_service_freshness']);
        self::assertSame('/etc/centreon-broker/testpoller-module.json', $row['broker_module_cfg_file']);
    }

    public function testItInsertsCfgNagiosLoggerRow(): void
    {
        $cfg = EngineConfiguration::createDefault(new PollerId(1), 'TestPoller');

        $this->repository->add($cfg);

        $cfgNagiosId = $cfg->id()->value;

        $row = $this->connection->fetchAssociative(
            'SELECT * FROM cfg_nagios_logger WHERE cfg_nagios_id = :id',
            ['id' => $cfgNagiosId],
        );

        self::assertIsArray($row);
        self::assertSame('file', $row['log_v2_logger']);
        self::assertSame('info', $row['log_level_config']);
        self::assertSame('err', $row['log_level_functions']);
    }

    public function testItInsertsCfgNagiosBrokerModuleRow(): void
    {
        $cfg = EngineConfiguration::createDefault(new PollerId(1), 'TestPoller');

        $this->repository->add($cfg);

        $cfgNagiosId = $cfg->id()->value;

        $row = $this->connection->fetchAssociative(
            'SELECT * FROM cfg_nagios_broker_module WHERE cfg_nagios_id = :id',
            ['id' => $cfgNagiosId],
        );

        self::assertIsArray($row);
        self::assertSame(BrokerOptions::MODULE_PATH, $row['broker_module']);
    }

    public function testItSetsAggregateId(): void
    {
        $cfg = EngineConfiguration::createDefault(new PollerId(1), 'TestPoller');

        $this->repository->add($cfg);

        self::assertGreaterThan(0, $cfg->id()->value);
    }
}
