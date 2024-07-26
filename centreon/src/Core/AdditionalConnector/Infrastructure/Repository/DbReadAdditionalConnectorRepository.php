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

namespace Core\AdditionalConnector\Infrastructure\Repository;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\AdditionalConnector\Application\Repository\ReadAdditionalConnectorRepositoryInterface;
use Core\AdditionalConnector\Domain\Model\AdditionalConnector;
use Core\AdditionalConnector\Domain\Model\Poller;
use Core\AdditionalConnector\Domain\Model\Type;
use Core\Common\Domain\TrimmedString;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\Repository\RepositoryTrait;
use Core\MonitoringServer\Infrastructure\Repository\MonitoringServerRepositoryTrait;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

/**
 * @phpstan-type _AdditionalConnector array{
 *  id:int,
 *  type:string,
 *  name:string,
 *  description:null|string,
 *  parameters:string,
 *  created_at:int,
 *  updated_at:int,
 *  created_by:null|int,
 *  updated_by:null|int
 * }
 */
class DbReadAdditionalConnectorRepository extends AbstractRepositoryRDB implements ReadAdditionalConnectorRepositoryInterface
{
    use RepositoryTrait, MonitoringServerRepositoryTrait;

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
    public function existsByName(TrimmedString $name): bool
    {
        $request = $this->translateDbName(
            <<<'SQL'
                SELECT 1
                FROM `:db`.`additional_connector`
                WHERE name = :name
                SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':name', $name->value, \PDO::PARAM_STR);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function find(int $accId): ?AdditionalConnector
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                SELECT *
                FROM `:db`.`additional_connector` acc
                WHERE acc.`id` = :id
                SQL
        ));
        $statement->bindValue(':id', $accId, \PDO::PARAM_INT);
        $statement->execute();

        if ($result = $statement->fetch()) {
            /** @var _AdditionalConnector $result */
            return $this->createFromArray($result);
        }

        return null;
    }

    public function findAll(): array
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                SELECT *
                FROM `:db`.`additional_connector` acc
                SQL
        ));
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        $additionalConnectors = [];
        foreach ($statement as $result) {
            /** @var _AdditionalConnector $result */
            $additionalConnectors[] = $this->createFromArray($result);
        }

        return $additionalConnectors;
    }

    /**
     * @inheritDoc
     */
    public function findPollersByType(Type $type): array
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                SELECT
                    rel.`poller_id` as id,
                    ng.`name`
                FROM `:db`.`acc_poller_relation` rel
                JOIN `:db`.`additional_connector` acc
                    ON rel.acc_id = acc.id
                JOIN `:db`.`nagios_server` ng
                    ON rel.poller_id = ng.id
                WHERE acc.`type` = :type
                SQL
        ));
        $statement->bindValue(':type', $type->value, \PDO::PARAM_STR);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        // Retrieve data
        $pollers = [];
        foreach ($statement as $result) {
            /** @var array{id:int,name:string} $result */
            $pollers[] = new Poller($result['id'], $result['name']);
        }

        return $pollers;
    }

    /**
     * @inheritDoc
     */
    public function findPollersByAccId(int $accId): array
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                SELECT
                    rel.`poller_id` as id,
                    ng.`name`
                FROM `:db`.`acc_poller_relation` rel
                JOIN `:db`.`nagios_server` ng
                    ON rel.poller_id = ng.id
                WHERE rel.`acc_id` = :id
                SQL
        ));
        $statement->bindValue(':id', $accId, \PDO::PARAM_INT);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        // Retrieve data
        $pollers = [];
        foreach ($statement as $result) {
            /** @var array{id:int,name:string} $result */
            $pollers[] = new Poller($result['id'], $result['name']);
        }

        return $pollers;
    }

    /**
     * @inheritDoc
     */
    public function findPollersByAccIdAndAccessGroups(int $accId, array $accessGroups): array
    {
        if ($accessGroups === []) {
            return [];
        }

        $accessGroupIds = array_map(
            static fn(AccessGroup $accessGroup): int => $accessGroup->getId(),
            $accessGroups
        );

        if (! $this->hasRestrictedAccessToMonitoringServers($accessGroupIds)) {
            return $this->findPollersByAccId($accId);
        }

        [$accessGroupsBindValues, $accessGroupIdsQuery] = $this->createMultipleBindQuery(
            array_map(fn (AccessGroup $accessGroup) => $accessGroup->getId(), $accessGroups),
            ':acl_'
        );

        $statement = $this->db->prepare($this->translateDbName(
            <<<SQL
                SELECT
                    rel.`poller_id` as id,
                    ng.`name`
                FROM `:db`.`acc_poller_relation` rel
                INNER JOIN `:db`.acl_resources_poller_relations arpr
                    ON rel.poller_id = arpr.poller_id
                JOIN `:db`.`nagios_server` ng
                    ON rel.poller_id = ng.id
                INNER JOIN `:db`.acl_res_group_relations argr
                    ON argr.acl_res_id = arpr.acl_res_id
                    AND argr.acl_group_id IN ({$accessGroupIdsQuery})
                WHERE rel.`acc_id` = :id
                SQL
        ));
        $statement->bindValue(':id', $accId, \PDO::PARAM_INT);
        foreach ($accessGroupsBindValues as $bindKey => $hostGroupId) {
            $statement->bindValue($bindKey, $hostGroupId, \PDO::PARAM_INT);
        }

        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        // Retrieve data
        $pollers = [];
        foreach ($statement as $result) {
            /** @var array{id:int,name:string} $result */
            $pollers[] = new Poller($result['id'], $result['name']);
        }

        return $pollers;
    }

    /**
     * @inheritDoc
     */
    public function findByRequestParameters(RequestParametersInterface $requestParameters): array
    {
        $sqlTranslator = new SqlRequestParametersTranslator($requestParameters);
        $sqlTranslator->setConcordanceArray([
            'name' => 'acc.name',
            'type' => 'acc.type',
            'poller.id' => 'rel.poller_id',
            'poller.name' => 'ns.name',
        ]);

        $request = <<<'SQL'
            SELECT SQL_CALC_FOUND_ROWS
                acc.*
            FROM `:db`.`additional_connector` acc
            LEFT JOIN `:db`.`acc_poller_relation` rel
                ON  acc.id = rel.acc_id
            INNER JOIN `:db`.`nagios_server` ns
                ON rel.poller_id = ns.id
            SQL;

        // Search
        $request .= $sqlTranslator->translateSearchParameterToSql();
        $request .= ' GROUP BY acc.name';

        // Sort
        $sortRequest = $sqlTranslator->translateSortParameterToSql();
        $request .= ! is_null($sortRequest)
            ? $sortRequest
            : ' ORDER BY acc.id ASC';

        // Pagination
        $request .= $sqlTranslator->translatePaginationToSql();
        $request = $this->translateDbName($request);

        $statement = $this->db->prepare($this->translateDbName($request));

        foreach ($sqlTranslator->getSearchValues() as $key => $data) {
            $type = key($data);
            if ($type !== null) {
                $value = $data[$type];
                $statement->bindValue($key, $value, $type);
            }
        }

        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        // Set total
        $result = $this->db->query('SELECT FOUND_ROWS()');
        if ($result !== false && ($total = $result->fetchColumn()) !== false) {
            $sqlTranslator->getRequestParameters()->setTotal((int) $total);
        }

        $additionalConnectors = [];
        foreach ($statement as $result) {
            /** @var _AdditionalConnector $result */
            $additionalConnectors[] = $this->createFromArray($result);
        }

        return $additionalConnectors;
    }

    /**
     * @inheritDoc
     */
    public function findByRequestParametersAndAccessGroups(
        RequestParametersInterface $requestParameters,
        array $accessGroups
    ): array {
        if ($accessGroups === []) {
            return [];
        }

        $accessGroupIds = array_map(
            static fn(AccessGroup $accessGroup): int => $accessGroup->getId(),
            $accessGroups
        );

        if (! $this->hasRestrictedAccessToMonitoringServers($accessGroupIds)) {
            return $this->findByRequestParameters($requestParameters);
        }

        $sqlTranslator = new SqlRequestParametersTranslator($requestParameters);
        $sqlTranslator->setConcordanceArray([
            'name' => 'acc.name',
            'type' => 'acc.type',
            'poller.id' => 'rel.poller_id',
            'poller.name' => 'ns.name',
        ]);

        [$accessGroupsBindValues, $accessGroupIdsQuery] = $this->createMultipleBindQuery(
            array_map(fn (AccessGroup $accessGroup) => $accessGroup->getId(), $accessGroups),
            ':acl_'
        );

        $request = <<<SQL
            SELECT SQL_CALC_FOUND_ROWS
                acc.*
            FROM `:db`.`additional_connector` acc
            LEFT JOIN `:db`.`acc_poller_relation` rel
                ON  acc.id = rel.acc_id
            INNER JOIN `:db`.`nagios_server` ns
                ON rel.poller_id = ns.id
            LEFT JOIN `:db`.acl_resources_poller_relations arpr
                ON ns.id = arpr.poller_id
            LEFT JOIN `:db`.acl_res_group_relations argr
                ON argr.acl_res_id = arpr.acl_res_id
                AND argr.acl_group_id IN ({$accessGroupIdsQuery})
            SQL;

        // Search
        $request .= $sqlTranslator->translateSearchParameterToSql();
        $request .= ' GROUP BY acc.name';

        // Sort
        $sortRequest = $sqlTranslator->translateSortParameterToSql();
        $request .= ! is_null($sortRequest)
            ? $sortRequest
            : ' ORDER BY acc.id ASC';

        // Pagination
        $request .= $sqlTranslator->translatePaginationToSql();
        $request = $this->translateDbName($request);

        $statement = $this->db->prepare($this->translateDbName($request));

        foreach ($sqlTranslator->getSearchValues() as $key => $data) {
            $type = key($data);
            if ($type !== null) {
                $value = $data[$type];
                $statement->bindValue($key, $value, $type);
            }
        }
        foreach ($accessGroupsBindValues as $bindKey => $hostGroupId) {
            $statement->bindValue($bindKey, $hostGroupId, \PDO::PARAM_INT);
        }

        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        // Set total
        $result = $this->db->query('SELECT FOUND_ROWS()');
        if ($result !== false && ($total = $result->fetchColumn()) !== false) {
            $sqlTranslator->getRequestParameters()->setTotal((int) $total);
        }

        $additionalConnectors = [];
        foreach ($statement as $result) {
            /** @var _AdditionalConnector $result */
            $additionalConnectors[] = $this->createFromArray($result);
        }

        return $additionalConnectors;
    }

    /**
     * @param _AdditionalConnector $row
     *
     * @return AdditionalConnector
     */
    private function createFromArray(array $row): AdditionalConnector
    {
        /** @var array<string,mixed> $parameters */
        $parameters = json_decode(json: $row['parameters'], associative: true, flags: JSON_OBJECT_AS_ARRAY);

        $acc = new AdditionalConnector(
            id: $row['id'],
            name: $row['name'],
            type: Type::from($row['type']),
            createdBy: $row['created_by'],
            updatedBy: $row['updated_by'],
            createdAt: $this->timestampToDateTimeImmutable($row['created_at']),
            updatedAt: $this->timestampToDateTimeImmutable($row['updated_at']),
            parameters: $parameters,
        );

        if ($row['description'] !== null) {
            $acc->setDescription($row['description']);
        }

        return $acc;
    }
}
