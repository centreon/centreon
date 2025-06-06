<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\MonitoringServer\Infrastructure\Repository;

use Assert\AssertionFailedException;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\Repository\SqlMultipleBindTrait;
use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use Core\MonitoringServer\Model\MonitoringServer;
use Utility\SqlConcatenator;

/**
 * @phpstan-type MSResultSet array{
 *     id: int,
 *     name: string
 * }
 */
class DbReadMonitoringServerRepository extends AbstractRepositoryRDB implements ReadMonitoringServerRepositoryInterface
{
    use MonitoringServerRepositoryTrait, LoggerTrait, SqlMultipleBindTrait;

    /**
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function exists(int $monitoringServerId): bool
    {
        $this->debug('Check existence of monitoring server with ID #' . $monitoringServerId);

        $request = $this->translateDbName(
            <<<'SQL'
                SELECT 1
                FROM `:db`.`nagios_server`
                WHERE `id` = :monitoringServerId
                SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':monitoringServerId', $monitoringServerId, \PDO::PARAM_INT);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function existsByAccessGroups(int $monitoringServerId, array $accessGroups): bool
    {
        $this->debug(
            'Check existence of monitoring server with',
            ['id' => $monitoringServerId, 'access_groups' => $accessGroups]
        );

        if ($accessGroups === []) {
            $this->debug('Access groups array is empty');

            return false;
        }

        $accessGroupIds = array_map(
            fn($accessGroup) => $accessGroup->getId(),
            $accessGroups
        );

        if (! $this->hasRestrictedAccessToMonitoringServers($accessGroupIds)) {
            return $this->exists($monitoringServerId);
        }

        [$bindValues, $bindQuery] = $this->createMultipleBindQuery($accessGroupIds, ':access_group_id_');

        $request = $this->translateDbName(
            <<<SQL
                SELECT 1
                FROM `:db`.`nagios_server` ns
                INNER JOIN `:db`.`acl_resources_poller_relations` arpr
                    ON arpr.`poller_id` = ns.`id`
                INNER JOIN `:db`.`acl_res_group_relations` argr
                    ON argr.`acl_res_id` = arpr.`acl_res_id`
                INNER JOIN `:db`.`acl_groups` ag
                    ON ag.`acl_group_id` = argr.`acl_group_id`
                WHERE `id` = :monitoring_server_id
                    AND ag.`acl_group_id` IN ({$bindQuery})
                SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':monitoring_server_id', $monitoringServerId, \PDO::PARAM_INT);
        foreach ($bindValues as $bindParam => $bindValue) {
            $statement->bindValue($bindParam, $bindValue, \PDO::PARAM_INT);
        }
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function exist(array $monitoringServerIds): array
    {
        [$bindValues, $bindQuery] = $this->createMultipleBindQuery(
            $monitoringServerIds,
            ':monitoringServerIds_'
        );

        $statement = $this->db->prepare($this->translateDbName(
            <<<SQL
                SELECT id
                FROM `:db`.`nagios_server`
                WHERE `id` IN ({$bindQuery})
                SQL
        ));

        foreach ($bindValues as $bindParam => $bindValue) {
            $statement->bindValue($bindParam, $bindValue, \PDO::PARAM_INT);
        }
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * @inheritDoc
     */
    public function existByAccessGroups(array $monitoringServerIds, array $accessGroups): array
    {
         if ($accessGroups === []) {

            return [];
        }

        $accessGroupIds = array_map(
            fn($accessGroup) => $accessGroup->getId(),
            $accessGroups
        );

        if (! $this->hasRestrictedAccessToMonitoringServers($accessGroupIds)) {
            return $this->exist($monitoringServerIds);
        }

        [$bindValuesPollerIds, $bindQueryPollerIds] = $this->createMultipleBindQuery(
            $monitoringServerIds,
            ':monitoringServerIds_'
        );
        [$bindValuesACLs, $bindQueryACLs] = $this->createMultipleBindQuery($accessGroupIds, ':accessGroupIds_');

        $statement = $this->db->prepare($this->translateDbName(
            <<<SQL
                SELECT arpr.`poller_id`
                FROM `:db`.`acl_resources_poller_relations` arpr
                INNER JOIN `:db`.`acl_res_group_relations` argr
                    ON argr.`acl_res_id` = arpr.`acl_res_id`
                WHERE arpr.`poller_id` IN ({$bindQueryPollerIds})
                    AND argr.`acl_group_id` IN ({$bindQueryACLs})
                SQL
        ));

        $bindValues = [...$bindValuesPollerIds, ...$bindValuesACLs];
        foreach ($bindValues as $bindParam => $bindValue) {
            $statement->bindValue($bindParam, $bindValue, \PDO::PARAM_INT);
        }
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function findByHost(int $hostId): ?MonitoringServer
    {
        $request = $this->translateDbName(
            <<<'SQL'
                SELECT `id`, `name`
                FROM `:db`.`nagios_server` ns
                INNER JOIN `:db`.`ns_host_relation` ns_hrel
                    ON ns_hrel.nagios_server_id = ns.id
                WHERE ns_hrel.host_host_id = :host_id
                SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':host_id', $hostId, \PDO::PARAM_INT);
        $statement->execute();

        /** @var MSResultSet|false */
        $data = $statement->fetch(\PDO::FETCH_ASSOC);

        return $data ? $this->createMonitoringServerFromArray($data) : null;
    }

    /**
     * @inheritDoc
     */
    public function findByIds(array $ids): array
    {
        if ($ids === []) {

            return $ids;
        }

        $concatenator = new SqlConcatenator();
        $concatenator->defineSelect(
            <<<'SQL'
                SELECT
                    `id`,
                    `name`
                FROM `:db`.`nagios_server` ns
                WHERE ns.id IN (:ids)
                SQL
        );
        $concatenator->storeBindValueMultiple(':ids', $ids, \PDO::PARAM_INT);
        $statement = $this->db->prepare($this->translateDbName($concatenator->__toString()));
        $concatenator->bindValuesToStatement($statement);

        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        $monitoringServers = [];
        foreach ($statement as $row) {
            /** @var MSResultSet $row */
            $monitoringServers[] = $this->createMonitoringServerFromArray($row);
        }

        return $monitoringServers;
    }

    /**
     * @inheritDoc
     */
    public function findByHostsIds(array $hostIds): array
    {
        if (empty($hostIds)) {
            return [];
        }

        [$bindValues, $bindQuery] = $this->createMultipleBindQuery($hostIds, ':host_id_');

        $request = $this->translateDbName(
            <<<SQL
                SELECT DISTINCT(phr.nagios_server_id)
                FROM `:db`.`ns_host_relation` phr
                JOIN `:db`.`host` ON host.host_id = phr.host_host_id
                WHERE host.host_activate = '1'
                    AND phr.host_host_id IN ({$bindQuery})
                SQL
        );

        $statement = $this->db->prepare($request);

        foreach ($bindValues as $bindParam => $bindValue) {
            $statement->bindValue($bindParam, $bindValue, \PDO::PARAM_INT);
        }
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * @inheritDoc
     */
    public function findCentralByIds(array $ids): ?MonitoringServer
    {
        if (empty($ids)) {
            return null;
        }

        [$bindValues, $bindQuery] = $this->createMultipleBindQuery($ids, ':poller_id_');

        $statement = $this->db->prepare($this->translateDbName(
            <<<SQL
                SELECT
                    ng.`id`,
                    ng.`name`
                FROM `:db`.`nagios_server` ng
                WHERE ng.`id` IN ({$bindQuery})
                    AND ng.`localhost` = '1'
                    AND NOT EXISTS (
                        SELECT 1
                        FROM `:db`.`remote_servers` rs
                        WHERE rs.server_id = ng.id
                    )
                SQL
        ));

        foreach ($bindValues as $bindParam => $bindValue) {
            $statement->bindValue($bindParam, $bindValue, \PDO::PARAM_INT);
        }

        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        /** @var MSResultSet|false */
        $data = $statement->fetch(\PDO::FETCH_ASSOC);

        return $data ? $this->createMonitoringServerFromArray($data) : null;
    }

    /**
     * @param MSResultSet $result
     *
     * @throws AssertionFailedException
     *
     * @return MonitoringServer
     */
    private function createMonitoringServerFromArray(array $result): MonitoringServer
    {
        return new MonitoringServer(
            id: $result['id'],
            name: $result['name']
        );
    }
}
