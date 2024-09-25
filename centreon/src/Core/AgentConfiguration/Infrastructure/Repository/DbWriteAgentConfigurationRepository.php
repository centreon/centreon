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
use Core\AgentConfiguration\Application\Repository\WriteAgentConfigurationRepositoryInterface;
use Core\AgentConfiguration\Domain\Model\AgentConfiguration;
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
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                INSERT INTO `:db`.`agent_configuration`
                    (type, name, configuration)
                VALUES (:type, :name, :configuration)
                SQL
        ));

        $statement->bindValue(':type', $agentConfiguration->getType()->value, \PDO::PARAM_STR);
        $statement->bindValue(':name', $agentConfiguration->getName(), \PDO::PARAM_STR);
        $statement->bindValue(':configuration', json_encode($agentConfiguration->getConfiguration()->getData()));
        $statement->execute();

        return (int) $this->db->lastInsertId();
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
    public function linkToPollers(int $agentConfigurationId, array $pollers): void
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                INSERT INTO `:db`.`ac_poller_relation`
                    (ac_id, poller_id)
                VALUES (:ac_id, :poller_id)
                SQL
        ));

        $pollerId = null;
        $statement->bindValue(':ac_id', $agentConfigurationId, \PDO::PARAM_INT);
        $statement->bindParam(':poller_id', $pollerId, \PDO::PARAM_INT);
        foreach ($pollers as $poller) {
            $pollerId = $poller;
            $statement->execute();
        }
    }

    /**
     * @inheritDoc
     */
    public function removePollers(int $agentConfigurationId): void
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                DELETE FROM `:db`.`ac_poller_relation`
                WHERE ac_id = :ac_id
                SQL
        ));

        $statement->bindValue(':ac_id', $agentConfigurationId, \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function removePoller(int $agentConfigurationId, int $pollerId): void
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                DELETE FROM `:db`.`ac_poller_relation`
                WHERE ac_id = :ac_id
                    AND poller_id = :poller_id
                SQL
        ));

        $statement->bindValue(':ac_id', $agentConfigurationId, \PDO::PARAM_INT);
        $statement->bindValue(':poller_id', $pollerId, \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function addBrokerModuleDirective(string $module, array $pollerIds): void
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                INSERT INTO `:db`.`cfg_nagios_broker_module`
                (bk_mod_id,cfg_nagios_id, broker_module)
                VALUES (null, :pollerId, :module)
                SQL
        ));

        $pollerId = null;
        $statement->bindValue(':module', $module, \PDO::PARAM_STR);
        $statement->bindParam(':pollerId', $pollerId, \PDO::PARAM_INT);
        foreach ($pollerIds as $poller) {
            $pollerId = $poller;
            $statement->execute();
        }
    }
}
