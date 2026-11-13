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

use Centreon\Domain\Entity\CfgCentreonBrokerInfo;
use Centreon\Domain\Repository\CfgCentreonBrokerInfoRepository;
use Centreon\Domain\Repository\CfgCentreonBrokerRepository;
use Centreon\Domain\Service\BrokerConfigurationService;
use CentreonRemote\Domain\Resources\RemoteConfig\InputFlowOnePeerRetention;
use Core\Broker\Infrastructure\Repository\DbReadBrokerInputOutputRepository;
use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Characterization of the broker flow writer used by the remote server / poller wizard:
 * CfgCentreonBrokerInfoRepository::getNewConfigGroupId() and add(), driven by BrokerConfigurationService::addFlow()
 * with the InputFlowOnePeerRetention template.
 *
 * Writes are verified through storage-agnostic readers only (Core read repository and the legacy configuration
 * generation), never by reading the storage directly.
 *
 * Pinned current behavior:
 * - getNewConfigGroupId() is MAX(id) + 1 per configuration and per group, 0 when the group is empty, and keeps gaps;
 *   the broker API numbers its first flow 1 instead (MAX + 1 with MAX = NULL cast to 0);
 * - addFlow() stores the template as a plain ipv4 input (no filters), which makes the configuration match the
 *   "any input value equal to 5669" lookup of CfgCentreonBrokerRepository.
 *
 * CfgCentreonBrokerInfoRepository::export() has no caller and returns raw storage rows: it is not pinned.
 */
#[Group('broker_io_characterization')]
final class BrokerLookupWizardFlowTest extends KernelTestCase
{
    private const POLLER_ID = 9510;
    private const OTHER_POLLER_ID = 9511;

    private BrokerTestDatabase $database;

    private BrokerFlowFixture $flows;

    private CfgCentreonBrokerInfoRepository $brokerInfoRepository;

    private BrokerConfigurationService $service;

    protected function setUp(): void
    {
        $this->database = new BrokerTestDatabase();
        $this->flows = new BrokerFlowFixture($this->database);
        $this->brokerInfoRepository = new CfgCentreonBrokerInfoRepository($this->database->configuration);
        $this->service = new BrokerConfigurationService();
        $this->service->setBrokerInfoRepository($this->brokerInfoRepository);

        $this->database->createPoller(self::POLLER_ID);
        $this->database->createPoller(self::OTHER_POLLER_ID);
    }

    protected function tearDown(): void
    {
        $this->database->cleanUp();
        parent::tearDown();
    }

    public function testNewConfigGroupIdOfAnEmptyGroupIsZero(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'lookup-empty');

        self::assertSame(0, $this->brokerInfoRepository->getNewConfigGroupId($configId, 'input'));
        self::assertSame(0, $this->brokerInfoRepository->getNewConfigGroupId($configId, 'output'));
        self::assertSame(0, $this->brokerInfoRepository->getNewConfigGroupId($configId, 'logger'));
    }

    public function testNewConfigGroupIdFollowsTheHighestIdAndKeepsGaps(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'lookup-gaps');
        $this->flows->insertLegacyRows($configId, 'input', 0, BrokerFlowFixture::flatRows([
            'name' => 'input-0', 'port' => '5670', 'type' => 'ipv4', 'blockId' => '2_3',
        ]));
        $this->flows->insertLegacyRows($configId, 'input', 4, BrokerFlowFixture::flatRows([
            'name' => 'input-4', 'port' => '5671', 'type' => 'ipv4', 'blockId' => '2_3',
        ]));

        self::assertSame(5, $this->brokerInfoRepository->getNewConfigGroupId($configId, 'input'));
        self::assertSame(0, $this->brokerInfoRepository->getNewConfigGroupId($configId, 'output'));
    }

    public function testNewConfigGroupIdIsComputedPerConfiguration(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'lookup-per-config');
        $otherConfigId = $this->database->createBrokerConfiguration(self::OTHER_POLLER_ID, 'lookup-per-config-other');
        $this->flows->insertLegacyRows($otherConfigId, 'output', 7, BrokerFlowFixture::flatRows([
            'name' => 'output-7', 'port' => '5670', 'type' => 'ipv4', 'blockId' => '1_3',
        ]));

        self::assertSame(0, $this->brokerInfoRepository->getNewConfigGroupId($configId, 'output'));
        self::assertSame(8, $this->brokerInfoRepository->getNewConfigGroupId($otherConfigId, 'output'));
    }

    public function testApiNumbersItsFirstFlowOneWhereTheWizardWouldUseZero(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'lookup-api-first');

        $firstApiId = $this->flows->addThroughApi($configId, 'output', 3, 'api-output', [
            'port' => 5670,
            'host' => 'localhost',
            'protocol' => 'bbdo',
            'tls' => 'no',
            'private_key' => null,
            'tls_hostname' => null,
            'public_cert' => null,
            'ca_certificate' => null,
            'negotiation' => 'yes',
            'one_peer_retention_mode' => 'no',
            'filters_category' => [],
        ]);

        self::assertSame(1, $firstApiId);
        self::assertSame(2, $this->brokerInfoRepository->getNewConfigGroupId($configId, 'output'));
    }

    public function testAddFlowStoresTheOnePeerRetentionInputAfterTheExistingInputs(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'lookup-wizard');
        $this->flows->insertLegacyRows($configId, 'input', 0, BrokerFlowFixture::flatRows([
            'name' => 'central-broker-master-input',
            'port' => '5669',
            'protocol' => 'bbdo',
            'type' => 'ipv4',
            'blockId' => '2_3',
        ]));

        $this->service->addFlow(
            $configId,
            'input',
            InputFlowOnePeerRetention::getConfiguration('lookup-poller', '10.20.30.40'),
        );

        $readRepository = new DbReadBrokerInputOutputRepository($this->database->connection);
        $stored = $readRepository->findByIdAndBrokerId('input', 1, $configId);
        self::assertNotNull($stored);
        $generator = new LegacyBrokerConfigGenerator($this->database, $this->notEncryptionReady());
        $files = $generator->generate(self::POLLER_ID, localhost: false);

        JsonSnapshot::assertMatches('lookup_wizard_one_peer_retention_input', [
            'read-model' => [
                'id' => $stored->getId(),
                'tag' => $stored->getTag(),
                'type' => ['id' => $stored->getType()->id, 'name' => $stored->getType()->name],
                'name' => $stored->getName(),
                'parameters' => $stored->getParameters(),
            ],
            'generated-input' => $this->generatedFlow($files, 'lookup-wizard.json', 'input', 'connection-to-lookup-poller'),
            'php-warnings' => $generator->warnings(),
        ]);
        self::assertSame(2, $this->brokerInfoRepository->getNewConfigGroupId($configId, 'input'));
    }

    public function testAddFlowMakesAConfigurationDiscoverableByTheWizardLookup(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'lookup-wizard-remote');
        $lookup = new CfgCentreonBrokerRepository($this->database->configuration);

        $this->service->addFlow(
            $configId,
            'input',
            InputFlowOnePeerRetention::getConfiguration('lookup-linked-poller', '10.20.30.41'),
        );

        self::assertSame($configId, $lookup->findBrokerConfigIdByPollerId(self::POLLER_ID));
        $stored = (new DbReadBrokerInputOutputRepository($this->database->connection))
            ->findByIdAndBrokerId('input', 0, $configId);
        self::assertNotNull($stored);
        self::assertSame('connection-to-lookup-linked-poller', $stored->getName());
    }

    public function testAddFlowAppliesTheGroupToEveryEntity(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'lookup-wizard-entities');
        $entities = [new CfgCentreonBrokerInfo('name', 'entity-flow'), new CfgCentreonBrokerInfo('port', '5672')];
        $entities[1]->setConfigGroup('output');
        $entities[1]->setConfigId(1);

        $this->service->addFlow($configId, 'input', $entities);

        foreach ($entities as $entity) {
            self::assertSame($configId, $entity->getConfigId());
            self::assertSame('input', $entity->getConfigGroup());
            self::assertSame(0, $entity->getConfigGroupId());
        }
        self::assertSame(1, $this->brokerInfoRepository->getNewConfigGroupId($configId, 'input'));
        self::assertSame(0, $this->brokerInfoRepository->getNewConfigGroupId($configId, 'output'));
    }

    private function notEncryptionReady(): ReadMonitoringServerRepositoryInterface
    {
        $monitoringServers = $this->createStub(ReadMonitoringServerRepositoryInterface::class);
        $monitoringServers->method('isEncryptionReady')->willReturn(false);

        return $monitoringServers;
    }

    /**
     * @param array<string, array<mixed>> $files
     *
     * @return array<mixed>|null
     */
    private function generatedFlow(array $files, string $file, string $group, string $name): ?array
    {
        $broker = $files[$file]['centreonBroker'] ?? null;
        $flows = is_array($broker) ? ($broker[$group] ?? []) : [];
        foreach (is_array($flows) ? $flows : [] as $flow) {
            if (is_array($flow) && ($flow['name'] ?? null) === $name) {
                return $flow;
            }
        }

        return null;
    }
}
