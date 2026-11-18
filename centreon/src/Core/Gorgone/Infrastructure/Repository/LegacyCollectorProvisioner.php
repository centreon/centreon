<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

namespace Core\Gorgone\Infrastructure\Repository;

use Centreon\Domain\PlatformTopology\Interfaces\PlatformTopologyRepositoryInterface;
use Centreon\Domain\PlatformTopology\Interfaces\PlatformTopologyServiceInterface;
use Centreon\Domain\PlatformTopology\Model\PlatformPending;
use Centreon\Infrastructure\DatabaseConnection;
use CentreonRemote\Domain\Service\ConfigurationWizard\PollerConnectionConfigurationService;
use CentreonRemote\ServiceProvider;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Gorgone\Application\EngineConfiguration\EngineConfigurationBuilder;
use Core\Gorgone\Application\Repository\CollectorProvisionerInterface;
use Pimple\Container;

/**
 * Creates the poller of a collector with the services of the poller wizard: the platform topology
 * (its checks of names and addresses) and the poller connection service (engine, broker module
 * towards the central, resources, default agent configuration).
 */
final class LegacyCollectorProvisioner extends AbstractRepositoryRDB implements CollectorProvisionerInterface
{
    /**
     * @param DatabaseConnection $db
     * @param PlatformTopologyServiceInterface $platformTopologyService
     * @param PlatformTopologyRepositoryInterface $platformTopologyRepository
     * @param Container $legacyContainer
     */
    public function __construct(
        DatabaseConnection $db,
        private readonly PlatformTopologyServiceInterface $platformTopologyService,
        private readonly PlatformTopologyRepositoryInterface $platformTopologyRepository,
        private readonly Container $legacyContainer,
    ) {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function createPoller(
        string $name,
        string $address,
        string $brokerAddress,
        int $creatorId,
        string $creatorName,
    ): int {
        $central = $this->platformTopologyRepository->findTopLevelPlatform();
        if ($central === null || $central->getAddress() === null) {
            throw new \RuntimeException('No central in the platform topology');
        }

        // As the registration of a poller (POST /platform/topology): checks names and addresses.
        $platform = (new PlatformPending())
            ->setType(PlatformPending::TYPE_POLLER)
            ->setName($name)
            ->setHostname($name)
            ->setAddress($address)
            ->setParentAddress($central->getAddress());
        $this->platformTopologyService->addPendingPlatformToTopology($platform);

        // As the poller wizard (linkCentreonRemoteServer) for a poller linked to the central.
        $service = $this->legacyContainer[ServiceProvider::CENTREON_REMOTE_POLLER_CONNECTION_SERVICE];
        if (! $service instanceof PollerConnectionConfigurationService) {
            throw new \RuntimeException('Poller connection service not found');
        }
        $service->setName($name);
        $service->setServerIp($address);
        $service->setCentralIp($brokerAddress);
        $service->setOnePeerRetention(false);
        $service->isLinkedToCentralServer();
        $pollerId = $service->insert($creatorId, $creatorName);

        $registered = $this->platformTopologyRepository->findPlatformByAddress($address);
        if ($registered !== null) {
            $registered->setServerId($pollerId)->setPending(false);
            $this->platformTopologyService->updatePlatformParameters($registered);
        }
        $this->markRunByCentreonEngine($pollerId);

        return $pollerId;
    }

    /**
     * @inheritDoc
     */
    public function markRunByCentreonEngine(int $pollerId): void
    {
        $statement = $this->db->prepare($this->translateDbName(
            'UPDATE `:db`.`nagios_server` SET engine_name = :engine WHERE id = :id'
        ));
        $statement->bindValue(':engine', EngineConfigurationBuilder::ENGINE_NAME, \PDO::PARAM_STR);
        $statement->bindValue(':id', $pollerId, \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function findPollers(): array
    {
        $statement = $this->db->query($this->translateDbName(
            'SELECT id, name, ns_ip_address FROM `:db`.`nagios_server` ORDER BY id'
        ));
        $pollers = [];
        foreach ($statement !== false ? $statement->fetchAll(\PDO::FETCH_ASSOC) : [] as $row) {
            $pollers[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'address' => (string) $row['ns_ip_address'],
            ];
        }

        return $pollers;
    }

    /**
     * @inheritDoc
     */
    public function findCentralAddress(): ?string
    {
        return $this->platformTopologyRepository->findTopLevelPlatform()?->getAddress();
    }

    /**
     * @inheritDoc
     */
    public function findAdministrator(): array
    {
        $statement = $this->db->query($this->translateDbName(
            "SELECT contact_id, contact_alias FROM `:db`.`contact`
             WHERE contact_admin = '1' AND contact_activate = '1' ORDER BY contact_id LIMIT 1"
        ));
        $row = $statement !== false ? $statement->fetch(\PDO::FETCH_ASSOC) : false;
        if (! is_array($row)) {
            throw new \RuntimeException('No active administrator');
        }

        return [(int) $row['contact_id'], (string) $row['contact_alias']];
    }
}
