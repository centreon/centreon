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

namespace Core\Broker\Infrastructure\Repository;

use Centreon\Infrastructure\DatabaseConnection;
use Core\Broker\Application\Repository\WriteBrokerInputOutputRepositoryInterface;
use Core\Broker\Domain\Model\BrokerInputOutput;
use Core\Broker\Domain\Model\NewBrokerInputOutput;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;

class DbWriteBrokerInputOutputRepository extends AbstractRepositoryRDB implements WriteBrokerInputOutputRepositoryInterface
{
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
    public function add(NewBrokerInputOutput $inputOutput, int $brokerId): int
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                INSERT INTO `:db`.`cfg_broker_input_output`
                    (config_id, tag, type_id, type_name, name, parameters)
                VALUES
                    (:brokerId, :tag, :typeId, :typeName, :name, :parameters)
                SQL
        ));

        $statement->bindValue(':brokerId', $brokerId, \PDO::PARAM_INT);
        $statement->bindValue(':tag', $inputOutput->getTag(), \PDO::PARAM_STR);
        $statement->bindValue(':typeId', $inputOutput->getType()->id, \PDO::PARAM_INT);
        $statement->bindValue(':typeName', $inputOutput->getType()->name, \PDO::PARAM_STR);
        $statement->bindValue(':name', $inputOutput->getName(), \PDO::PARAM_STR);
        $statement->bindValue(
            ':parameters',
            json_encode($inputOutput->getParameters(), JSON_THROW_ON_ERROR),
            \PDO::PARAM_STR
        );

        $statement->execute();

        return (int) $this->db->lastInsertId();
    }

    /**
     * @inheritDoc
     */
    public function update(BrokerInputOutput $inputOutput, int $brokerId): void
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                UPDATE `:db`.`cfg_broker_input_output`
                SET
                    name       = :name,
                    type_id    = :typeId,
                    type_name  = :typeName,
                    parameters = :parameters
                WHERE id = :id AND config_id = :brokerId
                SQL
        ));

        $statement->bindValue(':id', $inputOutput->getId(), \PDO::PARAM_INT);
        $statement->bindValue(':brokerId', $brokerId, \PDO::PARAM_INT);
        $statement->bindValue(':typeId', $inputOutput->getType()->id, \PDO::PARAM_INT);
        $statement->bindValue(':typeName', $inputOutput->getType()->name, \PDO::PARAM_STR);
        $statement->bindValue(':name', $inputOutput->getName(), \PDO::PARAM_STR);
        $statement->bindValue(
            ':parameters',
            json_encode($inputOutput->getParameters(), JSON_THROW_ON_ERROR),
            \PDO::PARAM_STR
        );

        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function delete(int $brokerId, string $tag, int $inputOutputId): void
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                DELETE FROM `:db`.`cfg_broker_input_output`
                WHERE id = :id AND config_id = :brokerId AND tag = :tag
                SQL
        ));

        $statement->bindValue(':id', $inputOutputId, \PDO::PARAM_INT);
        $statement->bindValue(':brokerId', $brokerId, \PDO::PARAM_INT);
        $statement->bindValue(':tag', $tag, \PDO::PARAM_STR);

        $statement->execute();
    }
}
