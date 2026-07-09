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
 *  created_at:int,
 *  updated_at:int,
 *  created_by:null|int,
 *  updated_by:null|int,
 *  port?:int,
 *  vcenter_id?:int
 * }
 */
class DbReadAccRepository extends AbstractRepositoryRDB implements ReadAccRepositoryInterface
{
    use RepositoryTrait;
    use MonitoringServerRepositoryTrait;

    public function __construct(
        private readonly EncryptionInterface $encryption,
        DatabaseConnection $db,
    ) {
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
            SELECT acc.*, item.id as vcenter_id,
                   item.name as vcenter_name, item.url as vcenter_url,
                   item.username as vcenter_username, item.password as vcenter_password
            FROM `:db`.`additional_connector_configuration` acc
            LEFT JOIN `:db`.`acc_item` item ON acc.id = item.acc_id
            WHERE acc.`id` = :id
            SQL;

        $statement = $this->db->prepare($this->translateDbName($sql));
        $statement->bindValue(':id', $accId, \PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        if (empty($rows)) {
            return null;
        }

        return $this->createFromRows($rows);
    }

    /**
     * @inheritDoc
     */
    public function findAll(): array
    {
        $sql = <<<'SQL'
            SELECT acc.*, item.id as vcenter_id,
                item.name as vcenter_name, item.url as vcenter_url,
                item.username as vcenter_username, item.password as vcenter_password
            FROM `:db`.`additional_connector_configuration` acc
            LEFT JOIN `:db`.`acc_item` item ON acc.id = item.acc_id
            SQL;

        $statement = $this->db->prepare($this->translateDbName($sql));
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        $allRows = $statement->fetchAll();

        $groupedByAcc = [];
        foreach ($allRows as $row) {
            $groupedByAcc[$row['id']][] = $row;
        }

        $additionalConnectors = [];
        foreach ($groupedByAcc as $rows) {
            $additionalConnectors[] = $this->createFromRows($rows);
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

        $searchSql = $sqlTranslator->translateSearchParameterToSql();

        // Count query for total
        $countRequest = <<<'SQL_WRAP'
            SELECT COUNT(DISTINCT acc.id)
            FROM `:db`.`additional_connector_configuration` acc
            LEFT JOIN `:db`.`acc_poller_relation` rel
                ON  acc.id = rel.acc_id
            INNER JOIN `:db`.`nagios_server` ns
                ON rel.poller_id = ns.id
            SQL_WRAP;
        $countRequest .= $searchSql;

        $countStatement = $this->db->prepare($this->translateDbName($countRequest));
        foreach ($sqlTranslator->getSearchValues() as $key => $data) {
            $type = key($data);
            if ($type !== null) {
                $value = $data[$type];
                $countStatement->bindValue($key, $value, $type);
            }
        }
        $countStatement->execute();
        $total = (int) $countStatement->fetchColumn();
        $sqlTranslator->getRequestParameters()->setTotal($total);

        // First query: Get paginated ACC IDs only
        $idsRequest = <<<'SQL_WRAP'
            SELECT DISTINCT acc.id, acc.name, acc.type, rel.poller_id, ns.name as poller_name
            FROM `:db`.`additional_connector_configuration` acc
            LEFT JOIN `:db`.`acc_poller_relation` rel
                ON  acc.id = rel.acc_id
            INNER JOIN `:db`.`nagios_server` ns
                ON rel.poller_id = ns.id
            SQL_WRAP;

        // Search
        $idsRequest .= $searchSql;

        // Sort
        $sortRequest = $sqlTranslator->translateSortParameterToSql();
        $idsRequest .= ! is_null($sortRequest)
            ? $sortRequest
            : ' ORDER BY acc.id ASC';

        // Pagination
        $idsRequest .= $sqlTranslator->translatePaginationToSql();

        $idsStatement = $this->db->prepare($this->translateDbName($idsRequest));

        foreach ($sqlTranslator->getSearchValues() as $key => $data) {
            $type = key($data);
            if ($type !== null) {
                $value = $data[$type];
                $idsStatement->bindValue($key, $value, $type);
            }
        }

        $idsStatement->execute();
        $accIds = $idsStatement->fetchAll(\PDO::FETCH_COLUMN);

        if (empty($accIds)) {
            return [];
        }

        // Second query: Fetch full data for the paginated ACC IDs
        [$bindValues, $idsQuery] = $this->createMultipleBindQuery($accIds, ':acc_id_');

        $dataRequest = <<<SQL
            SELECT acc.*, item.id as vcenter_id,
                item.name as vcenter_name, item.url as vcenter_url,
                item.username as vcenter_username, item.password as vcenter_password
            FROM `:db`.`additional_connector_configuration` acc
            LEFT JOIN `:db`.`acc_item` item ON acc.id = item.acc_id
            WHERE acc.id IN ({$idsQuery})
            ORDER BY acc.id ASC
            SQL;

        $statement = $this->db->prepare($this->translateDbName($dataRequest));
        foreach ($bindValues as $bindKey => $accId) {
            $statement->bindValue($bindKey, $accId, \PDO::PARAM_INT);
        }

        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        $allRows = $statement->fetchAll();

        $groupedByAcc = [];
        foreach ($allRows as $row) {
            $groupedByAcc[$row['id']][] = $row;
        }

        $additionalConnectors = [];
        foreach ($groupedByAcc as $rows) {
            $additionalConnectors[] = $this->createFromRows($rows);
        }

        return $additionalConnectors;
    }

    /**
     * @inheritDoc
     */
    public function findByRequestParametersAndAccessGroups(
        RequestParametersInterface $requestParameters,
        array $accessGroups,
    ): array {
        if ($accessGroups === []) {
            return [];
        }

        $accessGroupIds = array_map(
            static fn (AccessGroup $accessGroup): int => $accessGroup->getId(),
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

        $searchSql = $sqlTranslator->translateSearchParameterToSql();

        // Count query for total
        $countRequest = <<<SQL
            SELECT COUNT(DISTINCT acc.id)
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

        $countRequest .= $searchSql;
        $countRequest .= $searchSql !== null
            ? ' AND '
            : ' WHERE ';
        $countRequest .= ' acc.id NOT IN (
            SELECT rel.acc_id
            FROM `acc_poller_relation` rel
            LEFT JOIN acl_resources_poller_relations arpr ON rel.poller_id = arpr.poller_id
            LEFT JOIN acl_res_group_relations argr ON argr.acl_res_id = arpr.acl_res_id
            WHERE argr.acl_group_id IS NULL
        )';

        $countStatement = $this->db->prepare($this->translateDbName($countRequest));
        foreach ($sqlTranslator->getSearchValues() as $key => $data) {
            $type = key($data);
            if ($type !== null) {
                $value = $data[$type];
                $countStatement->bindValue($key, $value, $type);
            }
        }
        foreach ($accessGroupsBindValues as $bindKey => $hostGroupId) {
            $countStatement->bindValue($bindKey, $hostGroupId, \PDO::PARAM_INT);
        }
        $countStatement->execute();
        $total = (int) $countStatement->fetchColumn();
        $sqlTranslator->getRequestParameters()->setTotal($total);

        // First query: Get paginated ACC IDs only
        $idsRequest = <<<SQL
            SELECT DISTINCT acc.id, acc.name, acc.type, rel.poller_id, ns.name as poller_name
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
        $idsRequest .= $searchSql;
        $idsRequest .= $searchSql !== null
            ? ' AND '
            : ' WHERE ';
        $idsRequest .= ' acc.id NOT IN (
            SELECT rel.acc_id
            FROM `acc_poller_relation` rel
            LEFT JOIN acl_resources_poller_relations arpr ON rel.poller_id = arpr.poller_id
            LEFT JOIN acl_res_group_relations argr ON argr.acl_res_id = arpr.acl_res_id
            WHERE argr.acl_group_id IS NULL
        )';

        // Sort
        $sortRequest = $sqlTranslator->translateSortParameterToSql();
        $idsRequest .= ! is_null($sortRequest)
            ? $sortRequest
            : ' ORDER BY acc.id ASC';

        // Pagination
        $idsRequest .= $sqlTranslator->translatePaginationToSql();

        $idsStatement = $this->db->prepare($this->translateDbName($idsRequest));

        foreach ($sqlTranslator->getSearchValues() as $key => $data) {
            $type = key($data);
            if ($type !== null) {
                $value = $data[$type];
                $idsStatement->bindValue($key, $value, $type);
            }
        }
        foreach ($accessGroupsBindValues as $bindKey => $hostGroupId) {
            $idsStatement->bindValue($bindKey, $hostGroupId, \PDO::PARAM_INT);
        }

        $idsStatement->execute();
        $accIds = $idsStatement->fetchAll(\PDO::FETCH_COLUMN);

        if (empty($accIds)) {
            return [];
        }

        [$bindValues, $idsQuery] = $this->createMultipleBindQuery($accIds, ':acc_id_');

        $dataRequest = <<<SQL
            SELECT acc.*, item.id as vcenter_id,
                item.name as vcenter_name, item.url as vcenter_url,
                item.username as vcenter_username, item.password as vcenter_password
            FROM `:db`.`additional_connector_configuration` acc
            LEFT JOIN `:db`.`acc_item` item ON acc.id = item.acc_id
            WHERE acc.id IN ({$idsQuery})
            ORDER BY acc.id ASC
            SQL;

        $statement = $this->db->prepare($this->translateDbName($dataRequest));
        foreach ($bindValues as $bindKey => $accId) {
            $statement->bindValue($bindKey, $accId, \PDO::PARAM_INT);
        }

        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        $allRows = $statement->fetchAll();

        $groupedByAcc = [];
        foreach ($allRows as $row) {
            $groupedByAcc[$row['id']][] = $row;
        }

        $additionalConnectors = [];
        foreach ($groupedByAcc as $rows) {
            $additionalConnectors[] = $this->createFromRows($rows);
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
                    acc.*, item.id as vcenter_id,
                    item.name as vcenter_name, item.url as vcenter_url,
                    item.username as vcenter_username, item.password as vcenter_password
                FROM `:db`.`additional_connector_configuration` acc
                LEFT JOIN `:db`.`acc_item` item ON acc.id = item.acc_id
                JOIN `:db`.`acc_poller_relation` rel
                    ON acc.id = rel.acc_id
                WHERE rel.poller_id = :poller_id
                AND  acc.type = :type
                SQL
        ));

        $statement->bindValue(':poller_id', $pollerId, \PDO::PARAM_INT);
        $statement->bindValue(':type', $type, \PDO::PARAM_STR);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        $rows = $statement->fetchAll();
        if (empty($rows)) {
            return null;
        }

        return $this->createFromRows($rows);
    }

    /**
     * @param array<_Acc> $rows
     *
     * @return Acc
     */
    private function createFromRows(array $rows): Acc
    {
        $row = $rows[0];
        $type = Type::from($row['type']);

        $parameters = match ($type->value) {
            Type::VMWARE_V6->value => $this->buildVmwareParameters($rows),
            default => [],
        };

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

    /**
     * @param array<int, array<string, mixed>> $rows
     *
     * @return array<string, mixed>
     */
    private function buildVmwareParameters(array $rows): array
    {
        $port = (int) ($rows[0]['port'] ?? 443);
        $vcenters = [];

        foreach ($rows as $row) {
            $vcenterName = $row['vcenter_name'] ?? '';
            $vcenterUrl = $row['vcenter_url'] ?? '';
            $vcenterUsername = $row['vcenter_username'] ?? '';
            $vcenterPassword = $row['vcenter_password'] ?? '';

            if (empty($vcenterName) || empty($vcenterUrl) || empty($vcenterUsername) || empty($vcenterPassword)) {
                continue;
            }

            $vcenters[] = [
                'id' => $row['vcenter_id'],
                'name' => $vcenterName,
                'url' => $vcenterUrl,
                'username' => $vcenterUsername,
                'password' => $vcenterPassword,
            ];
        }

        return [
            'port' => $port,
            'vcenters' => $vcenters,
        ];
    }
}
