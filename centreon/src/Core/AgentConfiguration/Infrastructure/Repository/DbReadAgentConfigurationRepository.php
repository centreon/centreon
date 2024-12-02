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

namespace Core\AgentConfiguration\Infrastructure\Repository;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\AgentConfiguration\Application\Repository\ReadAgentConfigurationRepositoryInterface;
use Core\AgentConfiguration\Domain\Model\AgentConfiguration;
use Core\AgentConfiguration\Domain\Model\ConfigurationParameters\CmaConfigurationParameters;
use Core\AgentConfiguration\Domain\Model\ConfigurationParameters\TelegrafConfigurationParameters;
use Core\AgentConfiguration\Domain\Model\Poller;
use Core\AgentConfiguration\Domain\Model\Type;
use Core\Common\Domain\TrimmedString;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\Repository\RepositoryTrait;
use Core\MonitoringServer\Infrastructure\Repository\MonitoringServerRepositoryTrait;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

/**
 * @phpstan-type _AgentConfiguration array{
 *  id:int,
 *  type:string,
 *  name:string,
 *  configuration:string,
 * }
 */
class DbReadAgentConfigurationRepository extends AbstractRepositoryRDB implements ReadAgentConfigurationRepositoryInterface
{
    use RepositoryTrait, MonitoringServerRepositoryTrait;

    public function __construct(
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
                FROM `:db`.`agent_configuration`
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
    public function find(int $agentConfigurationId): ?AgentConfiguration
    {
        $sql = <<<'SQL'
            SELECT *
            FROM `:db`.`agent_configuration` ac
            WHERE ac.`id` = :id
            SQL;

        // Prepare SQL + bind values
        $statement = $this->db->prepare($this->translateDbName($sql));
        $statement->bindValue(':id', $agentConfigurationId, \PDO::PARAM_INT);
        $statement->execute();

        if ($result = $statement->fetch()) {
            /** @var _AgentConfiguration $result */
            return $this->createFromArray($result);
        }

        return null;
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
            FROM `:db`.`ac_poller_relation` rel
            JOIN `:db`.`agent_configuration` ac
                ON rel.ac_id = ac.id
            JOIN `:db`.`nagios_server` ng
                ON rel.poller_id = ng.id
            WHERE ac.`type` = :type
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
    public function findPollersByAcId(int $agentConfigurationId): array
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                SELECT
                    rel.`poller_id` as id,
                    ng.`name`
                FROM `:db`.`ac_poller_relation` rel
                JOIN `:db`.`nagios_server` ng
                    ON rel.poller_id = ng.id
                WHERE rel.`ac_id` = :id
                SQL
        ));
        $statement->bindValue(':id', $agentConfigurationId, \PDO::PARAM_INT);
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
    public function findPollersWithBrokerDirective(string $module): array
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                SELECT
                    `cfg_nagios_id`
                FROM `:db`.`cfg_nagios_broker_module`
                WHERE `broker_module` = :module
                SQL
        ));
        $statement->bindValue(':module', $module, \PDO::PARAM_STR);
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * @inheritDoc
     */
    public function findAllByRequestParameters(RequestParametersInterface $requestParameters): array
    {
        $sqlTranslator = new SqlRequestParametersTranslator($requestParameters);
        $sqlTranslator->setConcordanceArray([
            'name' => 'ac.name',
            'type' => 'ac.type',
            'poller.id' => 'rel.poller_id',
        ]);

        $request = <<<'SQL'
            SELECT SQL_CALC_FOUND_ROWS
                ac.id,
                ac.name,
                ac.type,
                ac.configuration
            FROM `:db`.`agent_configuration` ac
            INNER JOIN `:db`.`ac_poller_relation` rel
                ON ac.id = rel.ac_id
            SQL;

        // Search
        $request .= $sqlTranslator->translateSearchParameterToSql();
        $request .= ' GROUP BY ac.name';

        // Sort
        $sortRequest = $sqlTranslator->translateSortParameterToSql();
        $request .= ! is_null($sortRequest)
            ? $sortRequest
            : ' ORDER BY ac.id ASC';

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

        $result = $this->db->query('SELECT FOUND_ROWS()');
        if ($result !== false && ($total = $result->fetchColumn()) !== false) {
            $sqlTranslator->getRequestParameters()->setTotal((int) $total);
        }

        $agentConfigurations = [];
        foreach ($statement as $result) {
            /** @var _AgentConfiguration $result */
            $agentConfigurations[] = $this->createFromArray($result);
        }

        return $agentConfigurations;
    }

    /**
     * @inheritDoc
     */
    public function findAllByRequestParametersAndAccessGroups(
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
            return $this->findAllByRequestParameters($requestParameters);
        }

        $sqlTranslator = new SqlRequestParametersTranslator($requestParameters);
        $sqlTranslator->setConcordanceArray([
            'name' => 'ac.name',
            'type' => 'ac.type',
            'poller.id' => 'rel.poller_id',
        ]);

        [$accessGroupsBindValues, $accessGroupIdsQuery] = $this->createMultipleBindQuery(
            array_map(fn (AccessGroup $accessGroup) => $accessGroup->getId(), $accessGroups),
            ':acl_'
        );

        $request = <<<SQL
                SELECT
                    ac.id,
                    ac.name,
                    ac.type,
                    ac.configuration
                FROM `:db`.`agent_configuration` ac
                INNER JOIN `:db`.`ac_poller_relation` rel
                    ON ac.id = rel.ac_id
                INNER JOIN `:db`.acl_resources_poller_relations arpr
                    ON  rel.poller_id = arpr.poller_id
                INNER JOIN `:db`.acl_res_group_relations argr
                    ON argr.acl_res_id = arpr.acl_res_id
                    AND argr.acl_group_id IN ({$accessGroupIdsQuery})
            SQL;

        // Search
        $request .= $search = $sqlTranslator->translateSearchParameterToSql();
        $request .= $search !== null
            ? ' AND '
            : ' WHERE ';
        $request .= ' ac.id NOT IN (
            SELECT rel.ac_id
            FROM `ac_poller_relation` rel
            LEFT JOIN acl_resources_poller_relations arpr ON rel.poller_id = arpr.poller_id
            LEFT JOIN acl_res_group_relations argr ON argr.acl_res_id = arpr.acl_res_id
            WHERE argr.acl_group_id IS NULL
        )';
        $request .= ' GROUP BY ac.name';

        // Sort
        $sortRequest = $sqlTranslator->translateSortParameterToSql();
        $request .= ! is_null($sortRequest)
            ? $sortRequest
            : ' ORDER BY ac.id ASC';

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

        $agentConfigurations = [];
        foreach ($statement as $result) {
            /** @var _AgentConfiguration $result */
            $agentConfigurations[] = $this->createFromArray($result);
        }

        return $agentConfigurations;
    }

    /**
     * @inheritDoc
     */
    public function findByPollerId(int $pollerId): ?AgentConfiguration
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                SELECT
                    ac.id,
                    ac.name,
                    ac.type,
                    ac.configuration
                FROM `:db`.`agent_configuration` ac
                JOIN `:db`.`ac_poller_relation` rel
                    ON rel.ac_id = ac.id
                WHERE rel.poller_id = :id
                SQL
        ));
        $statement->bindValue(':id', $pollerId, \PDO::PARAM_INT);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        if ($result = $statement->fetch()) {
            /** @var _AgentConfiguration $result */
            return $this->createFromArray($result);
        }

        return null;
    }

    /**
     * @param _AgentConfiguration $row
     *
     * @throws \Throwable
     *
     * @return AgentConfiguration
     */
    private function createFromArray(array $row): AgentConfiguration
    {
        /** @var array<string,mixed> $configuration */
        $configuration = json_decode(
            json: $row['configuration'],
            associative: true,
            depth: JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR
        );
        $type = Type::from($row['type']);

        return new AgentConfiguration(
            id: $row['id'],
            name: $row['name'],
            type: $type,
            configuration: match ($type->value) {
                Type::TELEGRAF->value => new TelegrafConfigurationParameters($configuration),
                Type::CMA->value => new CmaConfigurationParameters($configuration)
            }
        );
    }
}
