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

use Adaptation\Database\Connection\Collection\BatchInsertParameters;
use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Centreon\Infrastructure\DatabaseConnection;
use Core\AgentConfiguration\Application\Repository\WriteAgentConfigurationRepositoryInterface;
use Core\AgentConfiguration\Domain\Model\AgentConfiguration;
use Core\AgentConfiguration\Domain\Model\ConnectionModeEnum;
use Core\AgentConfiguration\Domain\Model\NewAgentConfiguration;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\Repository\RepositoryTrait;

class DbWriteAgentConfigurationRepository  extends AbstractRepositoryRDB implements WriteAgentConfigurationRepositoryInterface
{
    use RepositoryTrait;

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
    public function add(NewAgentConfiguration $agentConfiguration): int
    {
        $queryBuilder = $this->db->createQueryBuilder();

        $query = $this->translateDbName(
            $queryBuilder
                ->insert('agent_configuration')
                ->values(
                    [
                        'name' => ':name',
                        'type' => ':type',
                        'connection_mode' => ':connection_mode',
                        'configuration' => ':configuration',
                    ]
                )
                ->getQuery()
        );

        $queryParameters = QueryParameters::create([
            QueryParameter::string(':name', $agentConfiguration->getName()),
            QueryParameter::string(':type', $agentConfiguration->getType()->value),
            QueryParameter::string(
                ':connection_mode',
                match ($agentConfiguration->getConnectionMode()) {
                    ConnectionModeEnum::NO_TLS => 'no_tls',
                    ConnectionModeEnum::SECURE => 'secure',
                    default => throw new \InvalidArgumentException('Invalid connection mode')
                }
            ),
            QueryParameter::string(':configuration', json_encode($agentConfiguration->getConfiguration()->getData())),
        ]);

        $this->db->executeStatement($query, $queryParameters);

        return (int) $this->db->getLastInsertId();
    }

    /**
     * @inheritDoc
     */
    public function update(AgentConfiguration $agentConfiguration): void
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                UPDATE `:db`.`agent_configuration`
                SET
                    `name` = :name,
                    `configuration` = :configuration
                WHERE
                    `id` = :id
                SQL
        ));

        $statement->bindValue(':id', $agentConfiguration->getId(), \PDO::PARAM_INT);
        $statement->bindValue(':name', $agentConfiguration->getName(), \PDO::PARAM_STR);
        $statement->bindValue(':configuration', json_encode($agentConfiguration->getConfiguration()->getData()));
        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function delete(int $id): void
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                DELETE FROM `:db`.`agent_configuration`
                WHERE
                    `id` = :id
                SQL
        ));

        $statement->bindValue(':id', $id, \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function linkToPollers(int $agentConfigurationId, array $pollerIds): void
    {
        $batchQueryParameters = [];
        foreach ($pollerIds as $pollerId) {
            $batchQueryParameters[] = QueryParameters::create([
                QueryParameter::int('ac_id', $agentConfigurationId),
                QueryParameter::int('poller_id', $pollerId),
            ]);
        }

        $this->db->batchInsert(
            'ac_poller_relation',
            ['ac_id', 'poller_id'],
            BatchInsertParameters::create($batchQueryParameters)
        );
    }

    /**
     * @inheritDoc
     */
    public function removePollers(int $agentConfigurationId): void
    {
        $queryBuilder = $this->db->createQueryBuilder();
        $query = $this->translateDbName(
            $queryBuilder
                ->delete('ac_poller_relation')
                ->where('ac_id = :ac_id')
                ->getQuery()
        );

        $queryParameters = QueryParameters::create([
            QueryParameter::int('ac_id', $agentConfigurationId),
        ]);
        $this->db->executeStatement($query, $queryParameters);
    }

    /**
     * @inheritDoc
     */
    public function removePoller(int $agentConfigurationId, int $pollerId): void
    {
        $queryBuilder = $this->db->createQueryBuilder();
        $expressionBuilder = $this->db->createExpressionBuilder();
        $eq1 = $expressionBuilder->equal('ac_id', ':ac_id');
        $eq2 = $expressionBuilder->equal('poller_id', ':poller_id');
        $and = $expressionBuilder->and($eq1, $eq2);
        $query = $this->translateDbName(
            $queryBuilder
                ->delete('ac_poller_relation')
                ->where($and)
                ->getQuery()
        );

        $queryParameters = QueryParameters::create([
            QueryParameter::int(':ac_id', $agentConfigurationId),
            QueryParameter::int(':poller_id', $pollerId),
        ]);

        $this->db->executeStatement($query, $queryParameters);
    }

    /**
     * @inheritDoc
     */
    public function addBrokerDirective(string $module, array $pollerIds): void
    {
        $batchQueryParameters = [];
        foreach ($pollerIds as $pollerId) {
            $batchQueryParameters[] = QueryParameters::create([
                QueryParameter::null('bk_mod_id'),
                QueryParameter::int('cfg_nagios_id', $pollerId),
                QueryParameter::string('broker_module', $module),
            ]);
        }

        $this->db->batchInsert(
            'cfg_nagios_broker_module',
            ['bk_mod_id', 'cfg_nagios_id', 'broker_module'],
            BatchInsertParameters::create($batchQueryParameters)
        );
    }
}
