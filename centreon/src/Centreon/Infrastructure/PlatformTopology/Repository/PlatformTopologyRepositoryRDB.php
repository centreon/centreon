<?php

/*
 *
 * Copyright 2005 - 2021 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Centreon\Infrastructure\PlatformTopology\Repository;

use Centreon\Domain\PlatformTopology\Interfaces\PlatformInterface;
use Centreon\Domain\PlatformTopology\Interfaces\PlatformTopologyRepositoryInterface;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\PlatformTopology\Repository\Model\PlatformTopologyFactoryRDB;
use Centreon\Infrastructure\Repository\AbstractRepositoryDRB;
use Core\Common\Infrastructure\Repository\SqlMultipleBindTrait;

/**
 * This class is designed to manage the repository of the platform topology requests
 *
 * @package Centreon\Infrastructure\PlatformTopology
 *
 * @phpstan-type _platformTopology array{
 *  id: int,
 *  address:string,
 *  hostname: string|null,
 *  name: string,
 *  type: string,
 *  parent_id: int|null,
 *  pending: string|null,
 *  server_id: int|null
 * }
 */
class PlatformTopologyRepositoryRDB extends AbstractRepositoryDRB implements PlatformTopologyRepositoryInterface
{
    use SqlMultipleBindTrait;

    /**
     * PlatformTopologyRepositoryRDB constructor.
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function addPlatformToTopology(PlatformInterface $platform): void
    {
        $statement = $this->db->prepare(
            $this->translateDbName("
                INSERT INTO `:db`.platform_topology
                    (`address`, `name`, `type`, `parent_id`, `server_id`, `hostname`, `pending`)
                VALUES (:address, :name, :type, :parentId, :serverId, :hostname, :pendingStatus)
            ")
        );
        $statement->bindValue(':address', $platform->getAddress(), \PDO::PARAM_STR);
        $statement->bindValue(':name', $platform->getName(), \PDO::PARAM_STR);
        $statement->bindValue(':type', $platform->getType(), \PDO::PARAM_STR);
        $statement->bindValue(':parentId', $platform->getParentId(), \PDO::PARAM_INT);
        $statement->bindValue(':serverId', $platform->getServerId(), \PDO::PARAM_INT);
        $statement->bindValue(':hostname', $platform->getHostname(), \PDO::PARAM_STR);
        $statement->bindValue(':pendingStatus', ($platform->isPending() ? '1' : '0'), \PDO::PARAM_STR);
        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function findPlatformByName(string $serverName): ?PlatformInterface
    {
        $statement = $this->db->prepare(
            $this->translateDbName('
                SELECT * FROM `:db`.platform_topology
                WHERE `name` = :name
            ')
        );
        $statement->bindValue(':name', $serverName, \PDO::PARAM_STR);
        $statement->execute();

        $platform = null;

        if ($result = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /** @var _platformTopology $result */
            $platform = PlatformTopologyFactoryRDB::create($result);
        }

        return $platform;
    }

    /**
     * @inheritDoc
     */
    public function findPlatformByAddress(string $serverAddress): ?PlatformInterface
    {
        $statement = $this->db->prepare(
            $this->translateDbName('
                SELECT * FROM `:db`.platform_topology
                WHERE `address` = :address
            ')
        );
        $statement->bindValue(':address', $serverAddress, \PDO::PARAM_STR);
        $statement->execute();

        $platform = null;

        if ($result = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /** @var _platformTopology $result */
            $platform = PlatformTopologyFactoryRDB::create($result);
        }

        return $platform;
    }

    /**
     * @inheritDoc
     */
    public function findTopLevelPlatformByType(string $serverType): ?PlatformInterface
    {
        $statement = $this->db->prepare(
            $this->translateDbName('
                SELECT * FROM `:db`.platform_topology
                WHERE `type` = :type AND `parent_id` IS NULL
            ')
        );
        $statement->bindValue(':type', $serverType, \PDO::PARAM_STR);
        $statement->execute();

        $platform = null;

        if ($result = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /** @var _platformTopology $result */
            $platform = PlatformTopologyFactoryRDB::create($result);
        }

        return $platform;
    }

    /**
     * @inheritDoc
     */
    public function findLocalMonitoringIdFromName(string $serverName): ?PlatformInterface
    {
        $statement = $this->db->prepare(
            $this->translateDbName('
                SELECT `id` FROM `:db`.nagios_server
                WHERE `localhost` = \'1\' AND ns_activate = \'1\' AND `name` = :name collate utf8_bin
            ')
        );
        $statement->bindValue(':name', $serverName, \PDO::PARAM_STR);
        $statement->execute();

        $platform = null;

        if ($result = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /** @var int[] $result */
            $platform = PlatformTopologyFactoryRDB::create($result);
        }

        return $platform;
    }

    /**
     * @inheritDoc
     */
    public function getPlatformTopology(): array
    {
        $statement = $this->db->query('SELECT * FROM `platform_topology`');

        $platformTopology = [];
        if ($statement !== false) {
            foreach ($statement as $topology) {
                /** @var _platformTopology $topology */
                $platform = PlatformTopologyFactoryRDB::create($topology);
                $platformTopology[] = $platform;
            }
        }

        return $platformTopology;
    }

    /**
     * @inheritDoc
     */
    public function getPlatformTopologyByAccessGroupIds(array $accessGroupIds): array
    {
        if ([] === $accessGroupIds) {

            return [];
        }

        if (! $this->hasRestrictedAccessToPlatforms($accessGroupIds)) {
            return $this->getPlatformTopology();
        }

        [$bindValues, $bindQuery] = $this->createMultipleBindQuery($accessGroupIds, ':access_group_id');

        $statement = $this->db->prepare($this->translateDbName(
            <<<SQL
                SELECT *
                FROM `platform_topology` pt
                JOIN `:db`.`acl_resources_poller_relations` arpr
                    ON pt.server_id = arpr.poller_id
                JOIN `:db`.`acl_res_group_relations` argr
                    ON arpr.acl_res_id = argr.acl_res_id
                WHERE argr.acl_group_id IN ({$bindQuery})
                SQL
        ));

        foreach ($bindValues as $key => $value) {
            $statement->bindValue($key, $value, \PDO::PARAM_INT);
        }

        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        $platformTopology = [];
        foreach ($statement as $topology) {
            /** @var _platformTopology $topology */
            $platform = PlatformTopologyFactoryRDB::create($topology);
            $platformTopology[] = $platform;
        }

        return $platformTopology;
    }


    /**
     * @inheritDoc
     */
    public function findPlatform(int $platformId): ?PlatformInterface
    {
        $statement = $this->db->prepare('SELECT * FROM `platform_topology` WHERE id = :platformId');
        $statement->bindValue(':platformId', $platformId, \PDO::PARAM_INT);
        $statement->execute();

        $platform = null;
        if ($result = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /** @var _platformTopology $result */
            $platform = PlatformTopologyFactoryRDB::create($result);
        }

        return $platform;
    }

    /**
     * @inheritDoc
     */
    public function deletePlatform(int $serverId): void
    {
        $statement = $this->db->prepare(
            $this->translateDbName('DELETE FROM `:db`.`platform_topology` WHERE id = :serverId')
        );
        $statement->bindValue(':serverId', $serverId, \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function findTopLevelPlatform(): ?PlatformInterface
    {
        $statement = $this->db->prepare(
            $this->translateDbName('
                SELECT * FROM `:db`.platform_topology
                WHERE `parent_id` IS NULL
            ')
        );
        $statement->execute();

        $platform = null;

        if ($result = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /** @var _platformTopology $result */
            $platform = PlatformTopologyFactoryRDB::create($result);
        }

        return $platform;
    }

    /**
     * @inheritDoc
     */
    public function findChildrenPlatformsByParentId(int $parentId): array
    {
        $statement = $this->db->prepare(
            $this->translateDbName('SELECT * FROM `:db`.`platform_topology` WHERE parent_id = :parentId')
        );
        $statement->bindValue(':parentId', $parentId, \PDO::PARAM_INT);
        $statement->execute();

        $childrenPlatforms = [];
        if ($result = $statement->fetchAll(\PDO::FETCH_ASSOC)) {
            foreach ($result as $platform) {
                /** @var _platformTopology $platform */
                $childrenPlatforms[] = PlatformTopologyFactoryRDB::create($platform);
            }
        }

        return $childrenPlatforms;
    }

    /**
     * @inheritDoc
     */
    public function updatePlatformParameters(PlatformInterface $platform): void
    {
        $statement = $this->db->prepare(
            $this->translateDbName(
                "UPDATE `:db`.`platform_topology` SET
                `address` = :address,
                `hostname` = :hostname,
                `name` = :name,
                `type` = :type,
                `parent_id` = :parentId,
                `server_id` = :serverId,
                `pending` = :pendingStatus
                WHERE id = :id"
            )
        );
        $statement->bindValue(':address', $platform->getAddress(), \PDO::PARAM_STR);
        $statement->bindValue(':hostname', $platform->getHostname(), \PDO::PARAM_STR);
        $statement->bindValue(':name', $platform->getName(), \PDO::PARAM_STR);
        $statement->bindValue(':type', $platform->getType(), \PDO::PARAM_STR);
        $statement->bindValue(':parentId', $platform->getParentId(), \PDO::PARAM_INT);
        $statement->bindValue(':serverId', $platform->getServerId(), \PDO::PARAM_INT);
        $statement->bindValue(':id', $platform->getId(), \PDO::PARAM_INT);
        $statement->bindValue(':pendingStatus', ($platform->isPending() ? '1' : '0'), \PDO::PARAM_STR);
        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function findCentralRemoteChildren(): array
    {
        $central = $this->findTopLevelPlatformByType('central');
        $remoteChildren = [];
        if ($central !== null) {
            $statement = $this->db->prepare(
                $this->translateDbName(
                    "SELECT * FROM `:db`.platform_topology WHERE `type` = 'remote' AND `parent_id` = :parentId"
                )
            );
            $statement->bindValue(':parentId', $central->getId(), \PDO::PARAM_INT);
            $statement->execute();

            if ($result = $statement->fetchAll(\PDO::FETCH_ASSOC)) {
                foreach ($result as $platform) {
                    /** @var _platformTopology $platform */
                    $remoteChildren[] = PlatformTopologyFactoryRDB::create($platform);
                }
            }
        }

        return $remoteChildren;
    }

    /**
     * @inheritDoc
     */
    public function hasRestrictedAccessToPlatforms(array $accessGroupIds): bool
    {
        if ([] === $accessGroupIds) {

            return false;
        }

        [$bindValues, $bindQuery] = $this->createMultipleBindQuery($accessGroupIds, ':access_group_id');

        $statement = $this->db->prepare($this->translateDbName(
            <<<SQL
                SELECT 1
                FROM `:db`.`acl_res_group_relations` argr
                JOIN `:db`.`acl_resources_poller_relations` arpr
                    ON arpr.acl_res_id = argr.acl_res_id
                WHERE argr.acl_group_id IN ({$bindQuery})
                SQL
        ));

        foreach ($bindValues as $key => $value) {
            $statement->bindValue($key, $value, \PDO::PARAM_INT);
        }

        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function hasAccessToPlatform(array $accessGroupIds, int $platformId): bool
    {
        if ([] === $accessGroupIds) {

            return false;
        }

        if (! $this->hasRestrictedAccessToPlatforms($accessGroupIds)) {
            return true;
        }

        [$bindValues, $bindQuery] = $this->createMultipleBindQuery($accessGroupIds, ':access_group_id');

        $statement = $this->db->prepare($this->translateDbName(
            <<<SQL
                SELECT 1
                FROM `:db`.`acl_resources_poller_relations` arpr
                JOIN `:db`.`acl_res_group_relations` argr
                    ON arpr.acl_res_id = argr.acl_res_id
                WHERE argr.acl_group_id IN ({$bindQuery})
                    AND arpr.poller_id = :platformId
                SQL
        ));
        $statement->bindValue(':platformId', $platformId, \PDO::PARAM_INT);
        foreach ($bindValues as $key => $value) {
            $statement->bindValue($key, $value, \PDO::PARAM_INT);
        }

        $statement->execute();

        return (bool) $statement->fetchColumn();
    }
}
