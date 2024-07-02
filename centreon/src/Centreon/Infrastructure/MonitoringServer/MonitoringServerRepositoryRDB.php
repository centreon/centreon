<?php

/*
 * Copyright 2005 - 2020 Centreon (https://www.centreon.com/)
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

namespace Centreon\Infrastructure\MonitoringServer;

use Centreon\Domain\Entity\EntityCreator;
use Centreon\Domain\MonitoringServer\Interfaces\MonitoringServerRepositoryInterface;
use Centreon\Domain\MonitoringServer\MonitoringServer;
use Centreon\Domain\MonitoringServer\MonitoringServerResource;
use Centreon\Domain\RequestParameters\RequestParameters;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\Repository\AbstractRepositoryDRB;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Common\Infrastructure\Repository\SqlMultipleBindTrait;
use Core\MonitoringServer\Infrastructure\Repository\MonitoringServerRepositoryTrait;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

/**
 * This class is designed to manage the repository of the monitoring servers
 *
 * @package Centreon\Infrastructure\MonitoringServer
 */
class MonitoringServerRepositoryRDB extends AbstractRepositoryDRB implements MonitoringServerRepositoryInterface
{
    use MonitoringServerRepositoryTrait, SqlMultipleBindTrait;

    /**
     * @var SqlRequestParametersTranslator
     */
    private $sqlRequestTranslator;

    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * Initialized by the dependency injector.
     *
     * @param SqlRequestParametersTranslator $sqlRequestTranslator
     */
    public function setSqlRequestTranslator(SqlRequestParametersTranslator $sqlRequestTranslator): void
    {
        $this->sqlRequestTranslator = $sqlRequestTranslator;
        $this->sqlRequestTranslator
            ->getRequestParameters()
            ->setConcordanceStrictMode(
                RequestParameters::CONCORDANCE_MODE_STRICT
            );
    }

    /**
     * @inheritDoc
     */
    public function findLocalServer(): ?MonitoringServer
    {
        $request = $this->translateDbName(
            'SELECT * FROM `:db`.nagios_server WHERE localhost = \'1\' AND ns_activate = \'1\''
        );
        $statement = $this->db->query($request);
        if ($statement !== false && ($result = $statement->fetch(\PDO::FETCH_ASSOC)) !== false) {
            /**
             * @var MonitoringServer $server
             */
            $server = EntityCreator::createEntityByArray(
                MonitoringServer::class,
                $result
            );
            if ((int) $result['last_restart'] === 0) {
                $server->setLastRestart(null);
            }
            return $server;
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    public function findServersWithRequestParameters(): array
    {
        $this->sqlRequestTranslator->setConcordanceArray([
            'id' => 'id',
            'name' => 'name',
            'is_localhost' => 'localhost',
            'address' => 'ns_ip_address',
            'is_activate' => 'ns_activate'
        ]);

        // Search
        $searchRequest = $this->sqlRequestTranslator->translateSearchParameterToSql();

        // Sort
        $sortRequest = $this->sqlRequestTranslator->translateSortParameterToSql();

        // Pagination
        $paginationRequest = $this->sqlRequestTranslator->translatePaginationToSql();

        return $this->findServers($searchRequest, $sortRequest, $paginationRequest);
    }

    /**
     * @inheritDoc
     */
    public function findServersWithRequestParametersAndAccessGroups(array $accessGroups): array
    {
        $this->sqlRequestTranslator->setConcordanceArray([
            'id' => 'id',
            'name' => 'name',
            'is_localhost' => 'localhost',
            'address' => 'ns_ip_address',
            'is_activate' => 'ns_activate'
        ]);

        // Search
        $searchRequest = $this->sqlRequestTranslator->translateSearchParameterToSql();

        // Sort
        $sortRequest = $this->sqlRequestTranslator->translateSortParameterToSql();

        // Pagination
        $paginationRequest = $this->sqlRequestTranslator->translatePaginationToSql();

        return $this->findServers($searchRequest, $sortRequest, $paginationRequest, $accessGroups);
    }

    /**
     * @inheritDoc
     */
    public function findServersWithoutRequestParameters(): array
    {
        return $this->findServers(null, null, null);
    }

    /**
     * Find servers.
     *
     * @param string|null $searchRequest Search request
     * @param string|null $sortRequest Sort request
     * @param string|null $paginationRequest Pagination request
     * @param AccessGroup[] $accessGroups
     *
     * @throws \Exception
     *
     * @return MonitoringServer[]
     *
     */
    private function findServers(
        ?string $searchRequest,
        ?string $sortRequest,
        ?string $paginationRequest,
        array $accessGroups = []
    ): array {
        $aclMonitoringServersRequest = '';
        $searchRequest ??= '';
        $sortRequest ??= ' ORDER BY id DESC';
        $paginationRequest ??= '';

        $bindValues = [];

        if ($accessGroups !== []) {
            $accessGroupIds = array_map(
                fn($accessGroup) => $accessGroup->getId(),
                $accessGroups
            );

            if ($this->hasRestrictedAccessToMonitoringServers($accessGroupIds)) {
                [$bindValues, $bindQuery] = $this->createMultipleBindQuery($accessGroupIds, ':acl_group_id_');

                $aclMonitoringServersRequest = <<<SQL
                    INNER JOIN `:db`.acl_resources_poller_relations arpr
                        ON arpr.poller_id = id
                    INNER JOIN `:db`.acl_resources res
                        ON res.acl_res_id = arpr.acl_res_id
                    INNER JOIN `:db`.acl_res_group_relations argr
                        ON argr.acl_res_id = res.acl_res_id
                    WHERE argr.acl_group_id IN ({$bindQuery})
                    SQL;

                $searchRequest = str_replace('WHERE', 'AND', $searchRequest);
            }
        }

        $request = $this->translateDbName(
            <<<SQL
                SELECT SQL_CALC_FOUND_ROWS * FROM `:db`.nagios_server
                {$aclMonitoringServersRequest}
                {$searchRequest}
                {$sortRequest}
                {$paginationRequest}
                SQL
        );

        $statement = $this->db->prepare($request);

        foreach ($this->sqlRequestTranslator->getSearchValues() as $key => $data) {
            $type = key($data);
            $value = $data[$type];
            $statement->bindValue($key, $value, $type);
        }

        foreach ($bindValues as $bindParam => $bindValue) {
            $statement->bindValue($bindParam, $bindValue, \PDO::PARAM_INT);
        }

        $statement->execute();

        $result = $this->db->query('SELECT FOUND_ROWS()');
        if ($result !== false && ($total = $result->fetchColumn()) !== false) {
            $this->sqlRequestTranslator->getRequestParameters()->setTotal((int) $total);
        }

        $servers = [];
        while (false !== ($result = $statement->fetch(\PDO::FETCH_ASSOC))) {
            /**
             * @var MonitoringServer $server
             */
            $server = EntityCreator::createEntityByArray(
                MonitoringServer::class,
                $result
            );
            if ((int) $result['last_restart'] === 0) {
                $server->setLastRestart(null);
            }
            $servers[] = $server;
        }
        return $servers;
    }

    /**
     * @inheritDoc
     */
    public function findServer(int $monitoringServerId): ?MonitoringServer
    {
        $request = $this->translateDbName('SELECT * FROM `:db`.nagios_server WHERE id = :server_id');
        $statement = $this->db->prepare($request);
        $statement->bindValue(':server_id', $monitoringServerId, \PDO::PARAM_INT);
        $statement->execute();

        if (($record = $statement->fetch(\PDO::FETCH_ASSOC)) !== false) {
            /**
             * @var MonitoringServer $server
             */
            $server = EntityCreator::createEntityByArray(
                MonitoringServer::class,
                $record
            );
            if ((int) $record['last_restart'] === 0) {
                $server->setLastRestart(null);
            }
            return $server;
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    public function findByIdAndAccessGroups(int $monitoringServerId, array $accessGroups): ?MonitoringServer
    {
        if ($accessGroups === []) {
            return null;
        }

        $accessGroupIds = array_map(
            fn($accessGroup) => $accessGroup->getId(),
            $accessGroups
        );

        if (! $this->hasRestrictedAccessToMonitoringServers($accessGroupIds)) {
            return $this->findServer($monitoringServerId);
        }

        [$bindValues, $bindQuery] = $this->createMultipleBindQuery($accessGroupIds, ':acl_group_id_');

        $request = $this->translateDbName(
            <<<SQL
                SELECT * FROM `:db`.nagios_server
                INNER JOIN `:db`.acl_resources_poller_relations arpr
                    ON arpr.poller_id = id
                INNER JOIN `:db`.acl_resources res
                    ON res.acl_res_id = arpr.acl_res_id
                INNER JOIN `:db`.acl_res_group_relations argr
                    ON argr.acl_res_id = res.acl_res_id
                WHERE argr.acl_group_id IN ({$bindQuery})
                    AND id = :server_id
                SQL
        );

        $statement = $this->db->prepare($request);

        $statement->bindValue(':server_id', $monitoringServerId, \PDO::PARAM_INT);
        foreach ($bindValues as $bindParam => $bindValue) {
            $statement->bindValue($bindParam, $bindValue, \PDO::PARAM_INT);
        }

        $statement->execute();

        if (($record = $statement->fetch(\PDO::FETCH_ASSOC)) !== false) {
            /** @var MonitoringServer $server */
            $server = EntityCreator::createEntityByArray(
                MonitoringServer::class,
                $record
            );
            if ((int) $record['last_restart'] === 0) {
                $server->setLastRestart(null);
            }

            return $server;
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function findServerByName(string $monitoringServerName): ?MonitoringServer
    {
        $request = $this->translateDbName('SELECT * FROM `:db`.nagios_server WHERE name = :server_name');
        $statement = $this->db->prepare($request);
        $statement->bindValue(':server_name', $monitoringServerName, \PDO::PARAM_STR);
        $statement->execute();

        if (($record = $statement->fetch(\PDO::FETCH_ASSOC)) !== false) {
            /**
             * @var MonitoringServer $server
             */
            $server = EntityCreator::createEntityByArray(
                MonitoringServer::class,
                $record
            );
            if ((int) $record['last_restart'] === 0) {
                $server->setLastRestart(null);
            }
            return $server;
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    public function findResource(int $monitoringServerId, string $resourceName): ?MonitoringServerResource
    {
        $request = $this->translateDbName(
            'SELECT resource.* FROM `:db`.cfg_resource resource
            INNER JOIN `:db`.cfg_resource_instance_relations rel
                ON rel.resource_id = resource.resource_id
            WHERE rel.instance_id = :monitoring_server_id
            AND resource.resource_name = :resource_name'
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':monitoring_server_id', $monitoringServerId, \PDO::PARAM_INT);
        $statement->bindValue(':resource_name', $resourceName, \PDO::PARAM_STR);
        $statement->execute();

        if (($record = $statement->fetch(\PDO::FETCH_ASSOC)) !== false) {
            return (new MonitoringServerResource())
                ->setId((int) $record['resource_id'])
                ->setName($record['resource_name'])
                ->setComment($record['resource_comment'])
                ->setIsActivate($record['resource_activate'] === '1')
                ->setPath($record['resource_line']);
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    public function notifyConfigurationChanged(MonitoringServer $monitoringServer): void
    {
        if ($monitoringServer->getId() !== null) {
            $request = $this->translateDbName(
                'UPDATE `:db`.nagios_server SET updated = "1" WHERE id = :server_id'
            );
            $statement = $this->db->prepare($request);
            $statement->bindValue(':server_id', $monitoringServer->getId(), \PDO::PARAM_INT);
            $statement->execute();
        } elseif ($monitoringServer->getName() !== null) {
            $request = $this->translateDbName(
                'UPDATE `:db`.nagios_server SET updated = "1" WHERE name = :server_name'
            );
            $statement = $this->db->prepare($request);
            $statement->bindValue(':server_name', $monitoringServer->getName(), \PDO::PARAM_STR);
            $statement->execute();
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteServer(int $monitoringServerId): void
    {
        $statement = $this->db->prepare($this->translateDbName("DELETE FROM `:db`.nagios_server WHERE id = :id"));
        $statement->bindValue(':id', $monitoringServerId, \PDO::PARAM_INT);
        $statement->execute();
    }
}
