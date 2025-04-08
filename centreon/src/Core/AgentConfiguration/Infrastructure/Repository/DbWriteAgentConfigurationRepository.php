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

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Core\AgentConfiguration\Application\Repository\WriteAgentConfigurationRepositoryInterface;
use Core\AgentConfiguration\Domain\Model\AgentConfiguration;
use Core\AgentConfiguration\Domain\Model\NewAgentConfiguration;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Infrastructure\Repository\DatabaseRepository;
use Core\Common\Infrastructure\Repository\RepositoryTrait;

/**
 * Class
 *
 * @class DbWriteAgentConfigurationRepository
 * @package Core\AgentConfiguration\Infrastructure\Repository
 */
class DbWriteAgentConfigurationRepository extends DatabaseRepository implements
    WriteAgentConfigurationRepositoryInterface
{
    use RepositoryTrait;

    /**
     * @inheritDoc
     */
    public function add(NewAgentConfiguration $agentConfiguration): int
    {
        try {
            $query = $this->queryBuilder->insert('`:db`.`agent_configuration`')
                ->set('type', ':type')
                ->set('name', ':name')
                ->set('configuration', ':configuration')
                ->getQuery();

            $this->connection->insert(
                $this->translateDbName($query),
                QueryParameters::create([
                    QueryParameter::string('type', $agentConfiguration->getType()->value),
                    QueryParameter::string('name', $agentConfiguration->getName()),
                    QueryParameter::string(
                        'configuration',
                        json_encode($agentConfiguration->getConfiguration()->getData(), JSON_THROW_ON_ERROR)
                    ),
                ])
            );

            return (int) $this->connection->getLastInsertId();
        } catch (\Throwable $exception) {
            throw new RepositoryException(
                message: 'Error while inserting agent configuration',
                context: ['type' => $agentConfiguration->getType()->value, 'name' => $agentConfiguration->getName()],
                previous: $exception
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function update(AgentConfiguration $agentConfiguration): void
    {
        try {
            $query = $this->queryBuilder->update('`:db`.`agent_configuration`')
                ->set('name', ':name')
                ->set('configuration', ':configuration')
                ->where('id', ':id')
                ->getQuery();

            $this->connection->insert(
                $this->translateDbName($query),
                QueryParameters::create([
                    QueryParameter::int('id', $agentConfiguration->getId()),
                    QueryParameter::string('name', $agentConfiguration->getName()),
                    QueryParameter::string(
                        'configuration',
                        json_encode($agentConfiguration->getConfiguration()->getData(), JSON_THROW_ON_ERROR)
                    ),
                ])
            );
        } catch (\Throwable $exception) {
            throw new RepositoryException(
                message: 'Error while updating agent configuration',
                context: ['id' => $agentConfiguration->getId(), 'name' => $agentConfiguration->getName()],
                previous: $exception
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function delete(int $id): void
    {
        try {
            $query = $this->queryBuilder->delete('`:db`.`agent_configuration`')
                ->where('id', ':id')
                ->getQuery();

            $this->connection->delete(
                $this->translateDbName($query),
                QueryParameters::create([QueryParameter::int('id', $id)])
            );
        } catch (\Throwable $exception) {
            throw new RepositoryException(
                message: 'Error while deleting agent configuration',
                context: ['id' => $id],
                previous: $exception
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function linkToPollers(int $agentConfigurationId, array $pollerIds): void
    {
        try {
            $query = $this->queryBuilder->insert('`:db`.`ac_poller_relation`')
                ->set('ac_id', ':ac_id')
                ->set('poller_id', ':poller_id')
                ->getQuery();

            foreach ($pollerIds as $poller) {
                $pollerId = $poller;
                $this->connection->insert(
                    $this->translateDbName($query),
                    QueryParameters::create([
                        QueryParameter::int('ac_id', $agentConfigurationId),
                        QueryParameter::int('poller_id', $pollerId),
                    ])
                );
            }
        } catch (\Throwable $exception) {
            throw new RepositoryException(
                message: 'Error while linking agent configuration with pollers',
                context: ['agentConfigurationId' => $agentConfigurationId, 'pollerIds' => $pollerIds],
                previous: $exception
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function removePollers(int $agentConfigurationId): void
    {
        try {
            $query = $this->queryBuilder->delete('`:db`.`ac_poller_relation`')
                ->where('ac_id', ':ac_id')
                ->getQuery();

            $this->connection->delete(
                $this->translateDbName($query),
                QueryParameters::create([QueryParameter::int('ac_id', $agentConfigurationId),])
            );
        } catch (\Throwable $exception) {
            throw new RepositoryException(
                message: 'Error while removing pollers from agent configuration',
                context: ['ac_id' => $agentConfigurationId],
                previous: $exception
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function removePoller(int $agentConfigurationId, int $pollerId): void
    {
        try {
            $query = $this->queryBuilder->delete('`:db`.`ac_poller_relation`')
                ->where('ac_id', ':ac_id')
                ->where('poller_id', ':poller_id')
                ->getQuery();

            $this->connection->delete(
                $this->translateDbName($query),
                QueryParameters::create([
                    QueryParameter::int('ac_id', $agentConfigurationId),
                    QueryParameter::int('poller_id', $pollerId),
                ])
            );
        } catch (\Throwable $exception) {
            throw new RepositoryException(
                message: 'Error while removing a poller from agent configuration',
                context: ['ac_id' => $agentConfigurationId, 'poller_id' => $pollerId],
                previous: $exception
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function addBrokerDirective(string $module, array $pollerIds): void
    {
        try {
            $query = $this->queryBuilder->insert('`:db`.`cfg_nagios_broker_module`')
                ->set('bk_mod_id', ':bk_mod_id')
                ->set('cfg_nagios_id', ':cfg_nagios_id')
                ->set('broker_module', ':broker_module')
                ->getQuery();

            foreach ($pollerIds as $poller) {
                $pollerId = $poller;
                $this->connection->insert(
                    $this->translateDbName($query),
                    QueryParameters::create([
                        QueryParameter::null('bk_mod_id'),
                        QueryParameter::int('cfg_nagios_id', $pollerId),
                        QueryParameter::string('broker_module', $module),
                    ])
                );
            }
        } catch (\Exception $exception) {
            throw new RepositoryException(
                message: 'Error while adding broker directive in agent configuration',
                context: ['pollerIds' => $pollerIds, 'broker_module' => $module],
                previous: $exception
            );
        }
    }
}
