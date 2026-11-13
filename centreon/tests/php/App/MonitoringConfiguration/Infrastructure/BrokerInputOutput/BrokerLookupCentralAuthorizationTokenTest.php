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

namespace Tests\App\MonitoringConfiguration\Infrastructure\BrokerInputOutput;

use App\MonitoringConfiguration\Infrastructure\Dbal\DbalBrokerConfigurationRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Characterization of DbalBrokerConfigurationRepository::getCentralBbdoServerAuthorizationToken(), used when a poller
 * is created to copy the central bbdo_server token into the poller module output.
 *
 * Data is seeded through the autocommit test connection BEFORE the repository (Doctrine connection held in the DAMA
 * transaction) reads anything, so its snapshot contains it. The add() method of this repository is not pinned here:
 * its writes stay in the DAMA transaction and cannot be read back through a storage-agnostic reader (they are covered
 * by tests/php/App/MonitoringConfiguration/Infrastructure/Dbal/DbalBrokerConfigurationRepositoryTest.php).
 *
 * Pinned current behavior: the token is the "authorization" parameter of a bbdo_server INPUT (same flow) of an
 * active broker configuration of ANY "localhost" poller, daemon or module; an empty or missing token throws.
 * The lookup is global and takes the first match without order: when another "localhost" poller with an active
 * broker configuration is visible in the same snapshot (base data or concurrent tests), only the absence of a
 * wrong own token is asserted.
 */
#[Group('broker_io_characterization')]
final class BrokerLookupCentralAuthorizationTokenTest extends KernelTestCase
{
    private const LOCALHOST_POLLER_ID = 9540;
    private const POLLER_ID = 9541;
    private const TOKEN = 'lookup-central-token-9540';

    private BrokerTestDatabase $database;

    private BrokerFlowFixture $flows;

    protected function setUp(): void
    {
        $this->database = new BrokerTestDatabase();
        $this->flows = new BrokerFlowFixture($this->database);
        $this->database->createPoller(self::LOCALHOST_POLLER_ID, localhost: true);
        $this->database->createPoller(self::POLLER_ID);
    }

    protected function tearDown(): void
    {
        $this->database->cleanUp();
        parent::tearDown();
    }

    public function testTokenOfTheBbdoServerInputOfTheCentralBroker(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::LOCALHOST_POLLER_ID, 'lookup-token-central');
        $this->addBbdoServer($configId, 'input', self::TOKEN);

        $this->assertToken(self::TOKEN);
    }

    public function testModuleConfigurationIsAlsoSearched(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::LOCALHOST_POLLER_ID, 'lookup-token-module', daemon: false);
        $this->addBbdoServer($configId, 'input', self::TOKEN);

        $this->assertToken(self::TOKEN);
    }

    public function testTokenStoredByLegacyWritersIsFound(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::LOCALHOST_POLLER_ID, 'lookup-token-legacy');
        $this->flows->insertLegacyRows($configId, 'input', 3, BrokerFlowFixture::flatRows([
            'name' => 'central-broker-master-input',
            'port' => '5669',
            'transport_protocol' => 'gRPC',
            'authorization' => self::TOKEN,
            'type' => 'bbdo_server',
            'blockId' => '2_35',
        ]));

        $this->assertToken(self::TOKEN);
    }

    public function testBbdoServerOutputIsIgnored(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::LOCALHOST_POLLER_ID, 'lookup-token-output');
        $this->flows->insertLegacyRows($configId, 'output', 0, BrokerFlowFixture::flatRows([
            'name' => 'bbdo-server-output',
            'port' => '5669',
            'authorization' => self::TOKEN,
            'type' => 'bbdo_server',
            'blockId' => '1_35',
        ]));

        $this->assertToken(null);
    }

    public function testAuthorizationOfAnotherInputIsIgnored(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::LOCALHOST_POLLER_ID, 'lookup-token-other-flow');
        $this->addBbdoServer($configId, 'input', null);
        $this->flows->insertLegacyRows($configId, 'input', 5, BrokerFlowFixture::flatRows([
            'name' => 'bbdo-client-input',
            'host' => 'remote',
            'authorization' => self::TOKEN,
            'type' => 'bbdo_client',
            'blockId' => '2_36',
        ]));

        $this->assertToken(null);
    }

    public function testEmptyTokenIsNotFound(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::LOCALHOST_POLLER_ID, 'lookup-token-empty');
        $this->addBbdoServer($configId, 'input', null);

        $this->assertToken(null);
    }

    public function testInactiveConfigurationIsIgnored(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::LOCALHOST_POLLER_ID, 'lookup-token-inactive');
        $this->addBbdoServer($configId, 'input', self::TOKEN);
        (new BrokerLookupScope($this->database))->setBrokerConfigurationActivation($configId, false);

        $this->assertToken(null);
    }

    public function testNonLocalhostPollerIsIgnored(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'lookup-token-remote');
        $this->addBbdoServer($configId, 'input', self::TOKEN);

        $this->assertToken(null);
    }

    /**
     * @param 'input'|'output' $tag
     */
    private function addBbdoServer(int $configId, string $tag, ?string $token): void
    {
        $this->flows->addThroughApi($configId, $tag, 35, 'lookup-bbdo-server', [
            'host' => null,
            'port' => '5669',
            'transport_protocol' => 'gRPC',
            'authorization' => $token,
            'encryption' => 'no',
            'private_key' => null,
            'certificate' => null,
            'compression' => 'no',
            'retention' => 'no',
            'filters_category' => [],
        ]);
    }

    /**
     * @param string|null $expected the token found when no foreign data is visible, null when none must be found
     */
    private function assertToken(?string $expected): void
    {
        // Retrieved after seeding: the DAMA transaction snapshot is taken on its first read.
        /** @var DbalBrokerConfigurationRepository $repository */
        $repository = self::getContainer()->get(DbalBrokerConfigurationRepository::class);
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');

        try {
            $token = $repository->getCentralBbdoServerAuthorizationToken();
        } catch (\RuntimeException $exception) {
            self::assertSame('No BBDO server authorization token found on the central broker', $exception->getMessage());
            $token = null;
        }

        // Same connection and transaction, hence the same snapshot as the repository read.
        $foreignCount = $connection->fetchOne(
            <<<'SQL'
                SELECT COUNT(*) FROM cfg_centreonbroker cb
                JOIN nagios_server ns ON ns.id = cb.ns_nagios_server
                WHERE ns.localhost = '1' AND cb.config_activate = '1' AND ns.id NOT BETWEEN :first AND :last
                SQL,
            ['first' => BrokerLookupScope::FIRST_POLLER_ID, 'last' => BrokerLookupScope::LAST_POLLER_ID],
        );
        $foreignCentral = ! in_array($foreignCount, [0, '0'], true);

        if (! $foreignCentral) {
            self::assertSame($expected, $token);

            return;
        }
        if ($expected === null) {
            self::assertNotSame(self::TOKEN, $token, 'A token that must be ignored was returned');
        } else {
            // A foreign central broker may answer first: nothing more can be asserted on our own data.
            self::assertNotNull($token, 'A central token must be found');
        }
    }
}
