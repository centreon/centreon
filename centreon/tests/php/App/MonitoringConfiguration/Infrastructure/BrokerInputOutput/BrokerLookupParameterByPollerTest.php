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

use Centreon\Domain\Broker\BrokerConfiguration;
use Centreon\Infrastructure\Broker\BrokerRepositoryRDB;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Characterization of BrokerRepositoryRDB::findByMonitoringServerAndParameterName(), used by PlatformTopologyService
 * to read "one_peer_retention_mode" of the broker configurations of a poller.
 *
 * Pinned current behavior: one result per stored parameter with that key, in every input AND output of every
 * broker configuration of the poller (active or not, module or daemon). Multi-value parameters therefore return
 * one result per value (e.g. each filter category). Results are compared regardless of their order, which is
 * not defined.
 */
#[Group('broker_io_characterization')]
final class BrokerLookupParameterByPollerTest extends KernelTestCase
{
    private const POLLER_ID = 9520;
    private const OTHER_POLLER_ID = 9521;

    private BrokerTestDatabase $database;

    private BrokerFlowFixture $flows;

    private BrokerRepositoryRDB $repository;

    protected function setUp(): void
    {
        $this->database = new BrokerTestDatabase();
        $this->flows = new BrokerFlowFixture($this->database);
        $this->repository = new BrokerRepositoryRDB($this->database->connection);
        $this->database->createPoller(self::POLLER_ID);
        $this->database->createPoller(self::OTHER_POLLER_ID);
    }

    protected function tearDown(): void
    {
        $this->database->cleanUp();
        parent::tearDown();
    }

    public function testOnePeerRetentionModeOfEveryFlowOfEveryConfigurationOfThePoller(): void
    {
        $daemonId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'lookup-param-daemon');
        $moduleId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'lookup-param-module', daemon: false);
        $inactiveId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'lookup-param-inactive');
        (new BrokerLookupScope($this->database))->setBrokerConfigurationActivation($inactiveId, false);
        $otherPollerId = $this->database->createBrokerConfiguration(self::OTHER_POLLER_ID, 'lookup-param-other');

        $this->addIpv4($daemonId, 'input', 'yes');
        $this->addIpv4($daemonId, 'output', 'no');
        $this->addIpv4($moduleId, 'output', 'yes');
        $this->addIpv4($inactiveId, 'output', 'no');
        $this->addIpv4($otherPollerId, 'output', 'yes');
        // Base configuration of a module output, as stored by the poller wizard.
        $this->flows->insertLegacyRows($moduleId, 'output', 5, BrokerFlowFixture::flatRows([
            'name' => 'central-module-master-output',
            'port' => '5669',
            'host' => '',
            'one_peer_retention_mode' => 'yes',
            'type' => 'ipv4',
            'blockId' => '1_3',
        ]));

        self::assertSame(
            [
                [$daemonId, 'one_peer_retention_mode', 'no'],
                [$daemonId, 'one_peer_retention_mode', 'yes'],
                [$moduleId, 'one_peer_retention_mode', 'yes'],
                [$moduleId, 'one_peer_retention_mode', 'yes'],
                [$inactiveId, 'one_peer_retention_mode', 'no'],
            ],
            $this->find(self::POLLER_ID, 'one_peer_retention_mode'),
        );
        self::assertSame(
            [[$otherPollerId, 'one_peer_retention_mode', 'yes']],
            $this->find(self::OTHER_POLLER_ID, 'one_peer_retention_mode'),
        );
    }

    public function testUnknownKeyOrPollerWithoutFlowReturnsNothing(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'lookup-param-none');
        $this->addIpv4($configId, 'output', 'no');
        $this->database->createBrokerConfiguration(self::OTHER_POLLER_ID, 'lookup-param-empty');

        self::assertSame([], $this->find(self::POLLER_ID, 'not_a_broker_parameter'));
        self::assertSame([], $this->find(self::OTHER_POLLER_ID, 'one_peer_retention_mode'));
    }

    public function testMetaParametersAreReturnedLikeAnyOther(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'lookup-param-meta');
        $this->addIpv4($configId, 'output', 'no');

        self::assertSame([[$configId, 'type', 'ipv4']], $this->find(self::POLLER_ID, 'type'));
        self::assertSame([[$configId, 'blockId', '1_3']], $this->find(self::POLLER_ID, 'blockId'));
        self::assertSame([[$configId, 'name', 'lookup-output-no']], $this->find(self::POLLER_ID, 'name'));
        self::assertSame([[$configId, 'port', '5669']], $this->find(self::POLLER_ID, 'port'));
    }

    public function testMultiValueParametersReturnOneResultPerValue(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'lookup-param-multi');
        $this->addIpv4($configId, 'output', 'no', ['neb', 'storage']);

        self::assertSame(
            [[$configId, 'category', 'neb'], [$configId, 'category', 'storage']],
            $this->find(self::POLLER_ID, 'category'),
        );
        self::assertSame([[$configId, 'filters', '']], $this->find(self::POLLER_ID, 'filters'));
    }

    public function testEmptyValuesAreReturned(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'lookup-param-empty-value');
        $this->flows->insertLegacyRows($configId, 'output', 0, BrokerFlowFixture::flatRows([
            'name' => 'empty-retention',
            'one_peer_retention_mode' => '',
            'type' => 'ipv4',
            'blockId' => '1_3',
        ]));

        self::assertSame([[$configId, 'one_peer_retention_mode', '']], $this->find(self::POLLER_ID, 'one_peer_retention_mode'));
    }

    /**
     * @param 'input'|'output' $tag
     * @param list<string> $categories
     */
    private function addIpv4(int $configId, string $tag, string $onePeerRetention, array $categories = []): void
    {
        $this->flows->addThroughApi($configId, $tag, 3, sprintf('lookup-%s-%s', $tag, $onePeerRetention), [
            'port' => 5669,
            'host' => $tag === 'output' ? null : 'localhost',
            'protocol' => 'bbdo',
            'tls' => 'no',
            'private_key' => null,
            'tls_hostname' => null,
            'public_cert' => null,
            'ca_certificate' => null,
            'negotiation' => 'yes',
            'one_peer_retention_mode' => $onePeerRetention,
            'filters_category' => $categories,
        ]);
    }

    /**
     * @return list<array{int|null, string|null, string|null}> sorted results
     */
    private function find(int $pollerId, string $key): array
    {
        $results = array_map(
            static fn (BrokerConfiguration $configuration): array => [
                $configuration->getId(),
                $configuration->getConfigurationKey(),
                $configuration->getConfigurationValue(),
            ],
            $this->repository->findByMonitoringServerAndParameterName($pollerId, $key),
        );
        sort($results);

        return $results;
    }
}
