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

use Centreon\Domain\Repository\CfgCentreonBrokerRepository;
use Centreon\Infrastructure\Service\Exception\NotFoundException;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Characterization of CfgCentreonBrokerRepository::findBrokerConfigIdByPollerId() and findCentralBrokerConfigId(),
 * used by the remote server / poller wizard to find the broker configuration that receives a one-peer-retention input.
 *
 * Pinned current behavior:
 * - a configuration matches as soon as ANY of its input parameters has the value "5669", whatever the key
 *   (not only "port"); outputs are ignored;
 * - only active (config_activate = '1') cbd (daemon = 1) configurations are considered;
 * - findCentralBrokerConfigId() is global: it returns the first matching configuration of ANY "localhost" poller.
 *
 * findCentralBrokerConfigId() can therefore also return configurations created concurrently by other tests: when the
 * result belongs to a foreign poller, only the absence of our own non-matching configurations is asserted.
 */
#[Group('broker_io_characterization')]
final class BrokerLookupBrokerConfigIdTest extends KernelTestCase
{
    private const LOCALHOST_POLLER_ID = 9500;
    private const POLLER_ID = 9501;
    private const OTHER_POLLER_ID = 9502;

    private BrokerTestDatabase $database;

    private BrokerFlowFixture $flows;

    private BrokerLookupScope $scope;

    private CfgCentreonBrokerRepository $repository;

    protected function setUp(): void
    {
        $this->database = new BrokerTestDatabase();
        $this->flows = new BrokerFlowFixture($this->database);
        $this->scope = new BrokerLookupScope($this->database);
        $this->repository = new CfgCentreonBrokerRepository($this->database->configuration);
        $this->database->createPoller(self::POLLER_ID);
        $this->database->createPoller(self::OTHER_POLLER_ID);
    }

    protected function tearDown(): void
    {
        $this->database->cleanUp();
        parent::tearDown();
    }

    public function testInputListeningOnPort5669AddedThroughTheApiMatches(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'lookup-api-5669');
        $this->addIpv4Input($configId, 'input', 5669);

        self::assertSame($configId, $this->repository->findBrokerConfigIdByPollerId(self::POLLER_ID));
    }

    public function testInputListeningOnPort5669StoredByTheBaseConfigurationMatches(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'lookup-legacy-5669');
        $this->flows->insertLegacyRows($configId, 'input', 0, BrokerFlowFixture::flatRows([
            'name' => 'central-broker-master-input',
            'port' => '5669',
            'retry_interval' => '15',
            'type' => 'ipv4',
            'blockId' => '2_3',
        ]));

        self::assertSame($configId, $this->repository->findBrokerConfigIdByPollerId(self::POLLER_ID));
    }

    public function testInputHavingAnotherParameterEqualTo5669Matches(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'lookup-other-key-5669');
        $this->flows->insertLegacyRows($configId, 'input', 0, BrokerFlowFixture::flatRows([
            'name' => 'not-listening-on-5669',
            'port' => '5670',
            'retry_interval' => '5669',
            'type' => 'ipv4',
            'blockId' => '2_3',
        ]));

        self::assertSame($configId, $this->repository->findBrokerConfigIdByPollerId(self::POLLER_ID));
    }

    public function testInputNamed5669Matches(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'lookup-name-5669');
        $this->flows->addThroughApi($configId, 'input', 3, '5669', $this->ipv4Parameters(5670));

        self::assertSame($configId, $this->repository->findBrokerConfigIdByPollerId(self::POLLER_ID));
    }

    public function testValueMustBeExactly5669(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'lookup-not-5669');
        $this->addIpv4Input($configId, 'input', 15669);
        $this->flows->insertLegacyRows($configId, 'input', 5, BrokerFlowFixture::flatRows([
            'name' => 'leading-zero',
            'port' => '05669',
            'type' => 'ipv4',
            'blockId' => '2_3',
        ]));

        $this->assertNoBrokerConfigForPoller(self::POLLER_ID);
    }

    public function testOutputWith5669DoesNotMatch(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'lookup-output-5669');
        $this->addIpv4Input($configId, 'output', 5669);

        $this->assertNoBrokerConfigForPoller(self::POLLER_ID);
    }

    public function testInactiveConfigurationDoesNotMatch(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'lookup-inactive');
        $this->addIpv4Input($configId, 'input', 5669);
        $this->scope->setBrokerConfigurationActivation($configId, false);

        $this->assertNoBrokerConfigForPoller(self::POLLER_ID);
    }

    public function testModuleConfigurationDoesNotMatch(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'lookup-module', daemon: false);
        $this->addIpv4Input($configId, 'input', 5669);

        $this->assertNoBrokerConfigForPoller(self::POLLER_ID);
    }

    public function testOnlyTheRequestedPollerIsSearched(): void
    {
        $otherConfigId = $this->database->createBrokerConfiguration(self::OTHER_POLLER_ID, 'lookup-other-poller');
        $this->addIpv4Input($otherConfigId, 'input', 5669);
        $this->database->createBrokerConfiguration(self::POLLER_ID, 'lookup-no-flow');

        $this->assertNoBrokerConfigForPoller(self::POLLER_ID);
        self::assertSame($otherConfigId, $this->repository->findBrokerConfigIdByPollerId(self::OTHER_POLLER_ID));
    }

    public function testMatchingConfigurationIsFoundAmongNonMatchingOnes(): void
    {
        $moduleId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'lookup-many-module', daemon: false);
        $this->addIpv4Input($moduleId, 'input', 5669);
        $outputOnlyId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'lookup-many-output');
        $this->addIpv4Input($outputOnlyId, 'output', 5669);
        $matchingId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'lookup-many-matching');
        $this->addIpv4Input($matchingId, 'input', 5670);
        $this->addIpv4Input($matchingId, 'input', 5669);

        self::assertSame($matchingId, $this->repository->findBrokerConfigIdByPollerId(self::POLLER_ID));
    }

    public function testCentralLookupFindsTheActiveDaemonConfigurationOfALocalhostPoller(): void
    {
        $this->database->createPoller(self::LOCALHOST_POLLER_ID, localhost: true);
        $configId = $this->database->createBrokerConfiguration(self::LOCALHOST_POLLER_ID, 'lookup-central');
        $this->addIpv4Input($configId, 'input', 5669);

        $this->assertCentralLookup(expectedOwnConfigId: $configId, ownConfigIds: [$configId]);
    }

    public function testCentralLookupMatchesAnyInputValueEqualTo5669(): void
    {
        $this->database->createPoller(self::LOCALHOST_POLLER_ID, localhost: true);
        $configId = $this->database->createBrokerConfiguration(self::LOCALHOST_POLLER_ID, 'lookup-central-other-key');
        $this->flows->insertLegacyRows($configId, 'input', 0, BrokerFlowFixture::flatRows([
            'name' => 'central-input',
            'port' => '5670',
            'buffering_timeout' => '5669',
            'type' => 'ipv4',
            'blockId' => '2_3',
        ]));

        $this->assertCentralLookup(expectedOwnConfigId: $configId, ownConfigIds: [$configId]);
    }

    public function testCentralLookupIgnoresNonMatchingConfigurations(): void
    {
        $this->database->createPoller(self::LOCALHOST_POLLER_ID, localhost: true);
        $remoteId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'lookup-central-remote');
        $this->addIpv4Input($remoteId, 'input', 5669);
        $outputId = $this->database->createBrokerConfiguration(self::LOCALHOST_POLLER_ID, 'lookup-central-output');
        $this->addIpv4Input($outputId, 'output', 5669);
        $moduleId = $this->database->createBrokerConfiguration(self::LOCALHOST_POLLER_ID, 'lookup-central-module', daemon: false);
        $this->addIpv4Input($moduleId, 'input', 5669);
        $inactiveId = $this->database->createBrokerConfiguration(self::LOCALHOST_POLLER_ID, 'lookup-central-inactive');
        $this->addIpv4Input($inactiveId, 'input', 5669);
        $this->scope->setBrokerConfigurationActivation($inactiveId, false);

        $this->assertCentralLookup(expectedOwnConfigId: null, ownConfigIds: [$remoteId, $outputId, $moduleId, $inactiveId]);
    }

    /**
     * @param 'input'|'output' $tag
     */
    private function addIpv4Input(int $configId, string $tag, int $port): void
    {
        $this->flows->addThroughApi($configId, $tag, 3, sprintf('lookup-%s-%d', $tag, $port), $this->ipv4Parameters($port));
    }

    /**
     * @return array<string, mixed>
     */
    private function ipv4Parameters(int $port): array
    {
        return [
            'port' => $port,
            'host' => null,
            'protocol' => 'bbdo',
            'tls' => 'no',
            'private_key' => null,
            'tls_hostname' => null,
            'public_cert' => null,
            'ca_certificate' => null,
            'negotiation' => 'yes',
            'one_peer_retention_mode' => 'no',
            'filters_category' => [],
        ];
    }

    private function assertNoBrokerConfigForPoller(int $pollerId): void
    {
        try {
            $configId = $this->repository->findBrokerConfigIdByPollerId($pollerId);
        } catch (NotFoundException $exception) {
            self::assertSame('Poller broker config id not found', $exception->getMessage());

            return;
        }
        self::fail(sprintf('Broker configuration %d unexpectedly found for poller %d', $configId, $pollerId));
    }

    /**
     * @param int|null $expectedOwnConfigId the configuration to find when no foreign data interferes, null for none
     * @param list<int> $ownConfigIds every configuration created by the test
     */
    private function assertCentralLookup(?int $expectedOwnConfigId, array $ownConfigIds): void
    {
        $foreignBefore = $this->scope->foreignLocalhostPollerExists();
        try {
            $found = $this->repository->findCentralBrokerConfigId();
        } catch (NotFoundException $exception) {
            self::assertSame('Central broker config id not found', $exception->getMessage());
            self::assertNull($expectedOwnConfigId, 'The central broker configuration was not found');

            return;
        }
        $foreign = $foreignBefore || $this->scope->foreignLocalhostPollerExists();

        if (in_array($found, $ownConfigIds, true)) {
            self::assertSame($expectedOwnConfigId, $found);

            return;
        }

        $pollerId = $this->scope->pollerOfBrokerConfiguration($found);
        self::assertTrue(
            $foreign && ($pollerId === null || ! BrokerLookupScope::isOwnPoller($pollerId)),
            sprintf('Unexpected central broker configuration %d (poller %s)', $found, $pollerId ?? 'deleted'),
        );
    }
}
