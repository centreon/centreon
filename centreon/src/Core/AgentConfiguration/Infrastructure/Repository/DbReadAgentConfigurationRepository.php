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

use Centreon\Infrastructure\DatabaseConnection;
use Core\AgentConfiguration\Application\Repository\ReadAgentConfigurationRepositoryInterface;
use Core\AgentConfiguration\Domain\Model\AgentConfiguration;
use Core\AgentConfiguration\Domain\Model\ConfigurationParameters\TelegrafConfigurationParameters;
use Core\AgentConfiguration\Domain\Model\Poller;
use Core\AgentConfiguration\Domain\Model\Type;
use Core\Common\Domain\TrimmedString;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\Repository\RepositoryTrait;
use Core\MonitoringServer\Infrastructure\Repository\MonitoringServerRepositoryTrait;

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
     * @param _AgentConfiguration $row
     *
     * @return AgentConfiguration
     */
    private function createFromArray(array $row): AgentConfiguration
    {
        /** @var array<string,mixed> $configuration */
        $configuration = json_decode(json: $row['configuration'], associative: true, flags: JSON_OBJECT_AS_ARRAY);
        $type = Type::from($row['type']);

        return new AgentConfiguration(
            id: $row['id'],
            name: $row['name'],
            type: $type,
            configuration: match ($type->value) {
                Type::TELEGRAF->value => (new TelegrafConfigurationParameters($configuration)),
                default => throw new \Exception('This error should never happen')
            }
        );
    }
}
