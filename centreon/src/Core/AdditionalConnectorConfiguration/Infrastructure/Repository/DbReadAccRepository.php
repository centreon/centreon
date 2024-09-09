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

namespace Core\AdditionalConnectorConfiguration\Infrastructure\Repository;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\AdditionalConnectorConfiguration\Application\Repository\ReadAccRepositoryInterface;
use Core\AdditionalConnectorConfiguration\Domain\Model\Acc;
use Core\AdditionalConnectorConfiguration\Domain\Model\Poller;
use Core\AdditionalConnectorConfiguration\Domain\Model\Type;
use Core\AdditionalConnectorConfiguration\Domain\Model\VmWareV6\VmWareV6Parameters;
use Core\Common\Domain\TrimmedString;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\Repository\RepositoryTrait;
use Core\MonitoringServer\Infrastructure\Repository\MonitoringServerRepositoryTrait;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Security\Interfaces\EncryptionInterface;

/**
 * @phpstan-type _Acc array{
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
class DbReadAccRepository extends AbstractRepositoryRDB implements ReadAccRepositoryInterface
{
    use RepositoryTrait, MonitoringServerRepositoryTrait;

    public function __construct(
        private readonly EncryptionInterface $encryption,
        DatabaseConnection $db
    )
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
                FROM `:db`.`additional_connector_configuration`
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
    public function find(int $accId): ?Acc
    {
        $sql = <<<'SQL'
            SELECT *
            FROM `:db`.`additional_connector_configuration` acc
            WHERE acc.`id` = :id
            SQL;

        // Prepare SQL + bind values
        $statement = $this->db->prepare($this->translateDbName($sql));
        $statement->bindValue(':id', $accId, \PDO::PARAM_INT);
        $statement->execute();

        if ($result = $statement->fetch()) {
            /** @var _Acc $result */
            return $this->createFromArray($result);
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function findAll(): array
    {
        $sql = <<<'SQL'
            SELECT *
            FROM `:db`.`additional_connector_configuration` acc
            SQL;

        // Prepare SQL + bind values
        $statement = $this->db->prepare($this->translateDbName($sql));
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        $additionalConnectors = [];
        foreach ($statement as $result) {
            /** @var _Acc $result */
            $additionalConnectors[] = $this->createFromArray($result);
        }

        return $additionalConnectors;
    }

    /**
     * @inheritDoc
     */
    public function findPollersByType(Type $type): array
    {
        $sql = <<<'SQL'
            SELECT
                rel.`poller_id` as id,
                ng.`name`
            FROM `:db`.`acc_poller_relation` rel
            JOIN `:db`.`additional_connector_configuration` acc
                ON rel.acc_id = acc.id
            JOIN `:db`.`nagios_server` ng
                ON rel.poller_id = ng.id
            WHERE acc.`type` = :type
            SQL;

        // Prepare SQL + bind values
        $statement = $this->db->prepare($this->translateDbName($sql));
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
    public function findAvailablePollersByType(
        Type $type,
        ?RequestParametersInterface $requestParameters = null
    ): array {

        $sqlTranslator = $requestParameters ? new SqlRequestParametersTranslator($requestParameters) : null;

        $request = <<<'SQL'
            SELECT SQL_CALC_FOUND_ROWS
                ng.`id`,
                ng.`name`
            FROM `:db`.`nagios_server` ng
            LEFT JOIN `:db`.`acc_poller_relation` rel
                ON rel.poller_id = ng.id
            LEFT JOIN `:db`.`additional_connector_configuration` acc
                ON rel.acc_id = acc.id
            SQL;

        // Search
        $request .= $search = $sqlTranslator?->translateSearchParameterToSql();
        $request .= $search !== null
            ? ' AND (acc.`type` != :type OR acc.`type` IS NULL)'
            : ' WHERE (acc.`type` != :type OR acc.`type` IS NULL)';

        // Sort
        $sortRequest = $sqlTranslator?->translateSortParameterToSql();
        $request .= ! is_null($sortRequest)
            ? $sortRequest
            : ' ORDER BY ng.id ASC';

        // Pagination
        $request .= $sqlTranslator?->translatePaginationToSql();

        $statement = $this->db->prepare($this->translateDbName($request));

        $statement->bindValue(':type', $type->value, \PDO::PARAM_STR);
        if ($sqlTranslator !== null) {
            foreach ($sqlTranslator->getSearchValues() as $key => $data) {
                $type = key($data);
                if ($type !== null) {
                    $value = $data[$type];
                    $statement->bindValue($key, $value, $type);
                }
            }
        }

        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        if ($sqlTranslator !== null) {
            // Set total
            $result = $this->db->query('SELECT FOUND_ROWS()');
            if ($result !== false && ($total = $result->fetchColumn()) !== false) {
                $sqlTranslator->getRequestParameters()->setTotal((int) $total);
            }
        }

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
    public function findAvailablePollersByTypeAndAccessGroup(
        Type $type,
        array $accessGroups,
        ?RequestParametersInterface $requestParameters = null
    ): array
    {
        if ($accessGroups === []) {
            return [];
        }

        $accessGroupIds = array_map(
            static fn(AccessGroup $accessGroup): int => $accessGroup->getId(),
            $accessGroups
        );

        if (! $this->hasRestrictedAccessToMonitoringServers($accessGroupIds)) {
            return $this->findAvailablePollersByType($type, $requestParameters);
        }

        [$accessGroupsBindValues, $accessGroupIdsQuery] = $this->createMultipleBindQuery(
            array_map(fn (AccessGroup $accessGroup) => $accessGroup->getId(), $accessGroups),
            ':acl_'
        );

        $sqlTranslator = $requestParameters ? new SqlRequestParametersTranslator($requestParameters) : null;

        $request = <<<SQL
            SELECT SQL_CALC_FOUND_ROWS
                ng.`id`,
                ng.`name`
            FROM `:db`.`nagios_server` ng
            LEFT JOIN `:db`.`acc_poller_relation` rel
                ON rel.poller_id = ng.id
            LEFT JOIN `:db`.`additional_connector_configuration` acc
                ON rel.acc_id = acc.id
            INNER JOIN `:db`.acl_resources_poller_relations arpr
                    ON ng.id = arpr.poller_id
            INNER JOIN `:db`.acl_res_group_relations argr
                ON argr.acl_res_id = arpr.acl_res_id
                AND argr.acl_group_id IN ({$accessGroupIdsQuery})
            SQL;

        // Search
        $request .= $search = $sqlTranslator?->translateSearchParameterToSql();
        $request .= $search !== null
            ? ' AND (acc.`type` != :type OR acc.`type` IS NULL)'
            : ' WHERE (acc.`type` != :type OR acc.`type` IS NULL)';

        // Sort
        $sortRequest = $sqlTranslator?->translateSortParameterToSql();
        $request .= ! is_null($sortRequest)
            ? $sortRequest
            : ' ORDER BY ng.id ASC';

        // Pagination
        $request .= $sqlTranslator?->translatePaginationToSql();

        $statement = $this->db->prepare($this->translateDbName($request));

        $statement->bindValue(':type', $type->value, \PDO::PARAM_STR);
        foreach ($accessGroupsBindValues as $bindKey => $hostGroupId) {
            $statement->bindValue($bindKey, $hostGroupId, \PDO::PARAM_INT);
        }
        if ($sqlTranslator !== null) {
            foreach ($sqlTranslator->getSearchValues() as $key => $data) {
                $type = key($data);
                if ($type !== null) {
                    $value = $data[$type];
                    $statement->bindValue($key, $value, $type);
                }
            }
        }

        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        if ($sqlTranslator !== null) {
            // Set total
            $result = $this->db->query('SELECT FOUND_ROWS()');
            if ($result !== false && ($total = $result->fetchColumn()) !== false) {
                $sqlTranslator->getRequestParameters()->setTotal((int) $total);
            }
        }

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
            FROM `:db`.`additional_connector_configuration` acc
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
            /** @var _Acc $result */
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
            FROM `:db`.`additional_connector_configuration` acc
            INNER JOIN `:db`.`acc_poller_relation` rel
                ON  acc.id = rel.acc_id
            INNER JOIN `:db`.`nagios_server` ns
                ON rel.poller_id = ns.id
            INNER JOIN `:db`.acl_resources_poller_relations arpr
                ON ns.id = arpr.poller_id
            INNER JOIN `:db`.acl_res_group_relations argr
                ON argr.acl_res_id = arpr.acl_res_id
                AND argr.acl_group_id IN ({$accessGroupIdsQuery})
            SQL;

        // Search
        $request .= $search = $sqlTranslator->translateSearchParameterToSql();
        $request .= $search !== null
            ? ' AND '
            : ' WHERE ';
        $request .= ' acc.id NOT IN (
            SELECT rel.acc_id
            FROM `acc_poller_relation` rel
            LEFT JOIN acl_resources_poller_relations arpr ON rel.poller_id = arpr.poller_id
            LEFT JOIN acl_res_group_relations argr ON argr.acl_res_id = arpr.acl_res_id
            WHERE argr.acl_group_id IS NULL
        )';
        $request .= ' GROUP BY acc.name';

        // Sort
        $sortRequest = $sqlTranslator->translateSortParameterToSql();
        $request .= ! is_null($sortRequest)
            ? $sortRequest
            : ' ORDER BY acc.id ASC';

        // Pagination
        $request .= $sqlTranslator->translatePaginationToSql();

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
            /** @var _Acc $result */
            $additionalConnectors[] = $this->createFromArray($result);
        }

        return $additionalConnectors;
    }

    /**
     * @inheritDoc
     */
    public function findByPollerAndType(int $pollerId, string $type): ?Acc
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                SELECT
                    acc.*
                FROM `:db`.`additional_connector_configuration` acc
                JOIN `:db`.`acc_poller_relation` rel
                    ON acc.id = rel.acc_id
                WHERE rel.poller_id = :poller_id
                AND  acc.type = :type
                LIMIT 1
                SQL
        ));

        $statement->bindValue(':poller_id', $pollerId, \PDO::PARAM_INT);
        $statement->bindValue(':type', $type, \PDO::PARAM_STR);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        foreach ($statement as $result) {
            /** @var _Acc $result */
            return $this->createFromArray($result);
        }

        return null;
    }

    /**
     * @param _Acc $row
     *
     * @return Acc
     */
    private function createFromArray(array $row): Acc
    {
        /** @var array<string,mixed> $parameters */
        $parameters = json_decode(json: $row['parameters'], associative: true, flags: JSON_OBJECT_AS_ARRAY);
        $type = Type::from($row['type']);

        return new Acc(
            id: $row['id'],
            name: $row['name'],
            type: $type,
            createdBy: $row['created_by'],
            updatedBy: $row['updated_by'],
            createdAt: $this->timestampToDateTimeImmutable($row['created_at']),
            updatedAt: $this->timestampToDateTimeImmutable($row['updated_at']),
            description: $this->emptyStringAsNull($row['description'] ?? ''),
            parameters: match ($type->value) {
                Type::VMWARE_V6->value => (new VmWareV6Parameters($this->encryption, $parameters, true)),
            }
        );
    }
}
