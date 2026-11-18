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

use App\MonitoringConfiguration\Application\Command\CreateBrokerConfigurationCommandHandler;
use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerConfiguration;
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
use App\MonitoringConfiguration\Domain\Factory\BrokerConfigurationFactory;
use App\MonitoringConfiguration\Domain\Repository\BrokerConfigurationRepository;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Event\EventBus;
use App\Shared\Domain\VaultInterface;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeVault;
use Tests\App\Shared\ClearsInstalledPlatformRows;

final class PollerCreatedBrokerConfigurationTest extends KernelTestCase
{
    use ClearsInstalledPlatformRows;
    private const ON_PREM_CENTRAL_ADDRESS = '10.0.0.1';
    private const CLOUD_CENTRAL_ADDRESS = 'staging.euwest1.centreon.click/funky-donkey';
    private const CLOUD_BROKER_HOST = 'broker-funky-donkey-staging.euwest1.centreon.click';
    private const CENTRAL_TOKEN = 'central-token';

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

        // Keep the broker event flow hermetic: the handler now depends on VaultInterface, whose
        // real implementation boots the legacy kernel. The on-prem flow never touches the vault.
        self::getContainer()->set(VaultInterface::class, new FakeVault());

        $this->clearInstalledPlatformRows($this->connection, 'cfg_resource_instance_relations', 'cfg_resource', 'cfg_centreonbroker', 'nagios_server');

        $this->connection->insert('nagios_server', [
            'id' => 1,
            'name' => 'Central',
            'localhost' => '1',
            'ns_activate' => '1',
            'ns_ip_address' => self::ON_PREM_CENTRAL_ADDRESS,
            'uid' => 100000000000001,
        ]);
        $this->connection->insert('nagios_server', [
            'id' => 2,
            'name' => 'My Poller',
            'localhost' => '0',
            'ns_activate' => '1',
            'ns_ip_address' => '192.168.1.100',
            'uid' => 100000000000002,
        ]);
    }

    public function testPollerCreatedEventTriggersBrokerConfigurationCreation(): void
    {
        $poller = $this->createPoller(2, 'My Poller');

        $this->eventBus->fire(new PollerCreated($poller, 1));

        $cfg = $this->connection->fetchAssociative(
            'SELECT * FROM cfg_centreonbroker WHERE ns_nagios_server = :id',
            ['id' => 2],
        );

        self::assertIsArray($cfg);
        self::assertSame('my-poller-module', $cfg['config_name']);
        self::assertSame(0, $cfg['daemon']);

        $configId = $cfg['config_id'];

        self::assertIsInt($configId);
        $flows = $this->fetchOutputFlows($configId);
        self::assertCount(1, $flows);
        self::assertCount(18, $flows[0]);

        $host = $flows[0]['host'];
        // The broker output dials the Central at the address supplied at poller creation.
        self::assertSame(self::ON_PREM_CENTRAL_ADDRESS, $host);

        $logCount = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM cfg_centreonbroker_log WHERE id_centreonbroker = :id',
            ['id' => $configId],
        );
        self::assertEquals(18, $logCount);
    }

    /**
     * The cloud counterpart, driven end to end through the real CentralAddress value object: the
     * BBDO Client output must carry the derived gateway host, not the web address it comes from.
     */
    public function testCloudPollerCreatedEventPersistsBbdoClientOutputWithBrokerGatewayHost(): void
    {
        $this->seedCentralBrokerWithBbdoServerInput(self::CENTRAL_TOKEN);
        $this->forceCloudPlatform();

        $poller = $this->createPoller(2, 'My Poller', self::CLOUD_CENTRAL_ADDRESS);

        $this->eventBus->fire(new PollerCreated($poller, 1));

        $cfg = $this->connection->fetchAssociative(
            'SELECT * FROM cfg_centreonbroker WHERE ns_nagios_server = :id',
            ['id' => 2],
        );

        self::assertIsArray($cfg);
        self::assertSame('my-poller-module', $cfg['config_name']);

        self::assertIsInt($cfg['config_id']);
        $flows = $this->fetchOutputFlows($cfg['config_id']);
        self::assertCount(1, $flows);
        $params = $flows[0];
        self::assertCount(12, $params);

        self::assertSame('bbdo_client', $params['type']);
        self::assertSame(self::CLOUD_BROKER_HOST, $params['host']);
        self::assertSame('443', $params['port']);
        self::assertSame('gRPC', $params['transport_protocol']);
        self::assertSame(self::CENTRAL_TOKEN, $params['authorization']);
    }

    public function testPollerIsRolledBackWhenBrokerConfigurationCreationFails(): void
    {
        self::getContainer()->set(
            BrokerConfigurationRepository::class,
            new class () implements BrokerConfigurationRepository {
                public function add(BrokerConfiguration $brokerConfiguration): void
                {
                    throw new \RuntimeException('Simulated broker configuration failure');
                }

                public function getCentralBbdoServerAuthorizationToken(): string
                {
                    return 'unused-on-prem';
                }
            },
        );

        $this->connection->beginTransaction();

        try {
            $this->connection->insert('nagios_server', [
                'id' => 3,
                'name' => 'Rollback Test Poller',
                'localhost' => '0',
                'ns_activate' => '1',
                'ns_ip_address' => '192.168.1.200',
                'uid' => 100000000000003,
            ]);

            $poller = $this->createPoller(3, 'Rollback Test Poller');

            $this->eventBus->fire(new PollerCreated($poller, 1));

            $this->connection->commit();
            self::fail('Expected broker configuration creation to throw');
        } catch (\RuntimeException) {
            $this->connection->rollBack();
        }

        $pollerRow = $this->connection->fetchAssociative(
            'SELECT * FROM nagios_server WHERE id = :id',
            ['id' => 3],
        );
        self::assertFalse($pollerRow);

        $cfgRow = $this->connection->fetchAssociative(
            'SELECT * FROM cfg_centreonbroker WHERE ns_nagios_server = :id',
            ['id' => 3],
        );
        self::assertFalse($cfgRow);
    }

    /**
     * `isCloudPlatform` is a constructor argument autowired from the IS_CLOUD_PLATFORM env var, so
     * the platform is forced by replacing the handler itself — the same technique as the
     * VaultInterface / BrokerConfigurationRepository replacements. Must run before the event is
     * fired: the test container refuses to replace an already-initialized service.
     */
    private function forceCloudPlatform(): void
    {
        $container = self::getContainer();

        /** @var BrokerConfigurationRepository $repository */
        $repository = $container->get(BrokerConfigurationRepository::class);

        $container->set(
            CreateBrokerConfigurationCommandHandler::class,
            new CreateBrokerConfigurationCommandHandler(
                $repository,
                new BrokerConfigurationFactory(),
                new FakeVault(),
                true,
            ),
        );
    }

    /**
     * Adds a `-broker` config on the Central carrying a bbdo_server input with an authorization
     * token, matching the shape read by getCentralBbdoServerAuthorizationToken(). The Central's
     * nagios_server row is already inserted by setUp().
     */
    private function seedCentralBrokerWithBbdoServerInput(string $token): void
    {
        $this->connection->insert('cfg_centreonbroker', [
            'config_name' => 'central-broker',
            'config_filename' => 'central-broker.json',
            'config_activate' => '1',
            'ns_nagios_server' => 1,
            'daemon' => 0,
        ]);
        $configId = (int) $this->connection->lastInsertId();

        $this->connection->insert('cfg_centreonbroker_input_output', [
            'config_id' => $configId,
            'tag' => 'input',
            'input_output_id' => 0,
            'name' => '',
            'type' => 'bbdo_server',
            'parameters' => json_encode(['authorization' => $token], JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * Reads the output flows of a broker configuration from `cfg_centreonbroker_input_output` and
     * flattens each of them into its key => value pairs, the `type`/`blockId`/`name` meta values
     * included (former one-row-per-key encoding).
     *
     * @return array<int, array<string, string>> flattened parameters indexed by input_output_id
     */
    private function fetchOutputFlows(int $configId): array
    {
        /** @var list<array{input_output_id: int, name: string, type_id: int, type: string, parameters: string}> $records */
        $records = $this->connection->fetchAllAssociative(
            "SELECT input_output_id, name, type_id, type, parameters FROM cfg_centreonbroker_input_output
             WHERE config_id = :id AND tag = 'output'
             ORDER BY input_output_id",
            ['id' => $configId],
        );

        $flows = [];
        foreach ($records as $record) {
            /** @var array<string, string> $parameters */
            $parameters = json_decode($record['parameters'], true, flags: JSON_THROW_ON_ERROR);
            $flows[$record['input_output_id']] = [
                'type' => $record['type'],
                'name' => $record['name'],
                'blockId' => '1_' . $record['type_id'],
                ...$parameters,
            ];
        }

        return $flows;
    }

    private function createPoller(
        int $pollerId,
        string $pollerName,
        string $centralAddress = self::ON_PREM_CENTRAL_ADDRESS,
    ): Poller {
        $poller = new Poller(
            id: null,
            name: new PollerName($pollerName),
            address: new PollerAddress('192.168.1.100'),
            centralAddress: new CentralAddress($centralAddress),
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
