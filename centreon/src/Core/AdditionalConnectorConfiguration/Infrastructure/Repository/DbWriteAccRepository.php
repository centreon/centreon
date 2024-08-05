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

use Centreon\Infrastructure\DatabaseConnection;
use Core\AdditionalConnectorConfiguration\Application\Repository\WriteAccRepositoryInterface;
use Core\AdditionalConnectorConfiguration\Domain\Model\Acc;
use Core\AdditionalConnectorConfiguration\Domain\Model\NewAcc;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\Repository\RepositoryTrait;

class DbWriteAccRepository extends AbstractRepositoryRDB implements WriteAccRepositoryInterface
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
    public function add(NewAcc $acc): int
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                INSERT INTO `:db`.`additional_connector_configuration`
                    (type, name, description, parameters, created_by, created_at, updated_by, updated_at)
                VALUES (:type, :name, :description, :parameters, :createdBy, :createdAt, :createdBy, :createdAt)
                SQL
        ));

        $statement->bindValue(':type', $acc->getType()->value, \PDO::PARAM_STR);
        $statement->bindValue(':name', $acc->getName(), \PDO::PARAM_STR);
        $statement->bindValue(':description', $acc->getDescription(), \PDO::PARAM_STR);
        $statement->bindValue(':parameters', json_encode($acc->getParameters()->getEncryptedData()));
        $statement->bindValue(':createdBy', $acc->getCreatedBy(), \PDO::PARAM_INT);
        $statement->bindValue(':createdAt', $acc->getCreatedAt()->getTimestamp(), \PDO::PARAM_INT);
        $statement->execute();

        return (int) $this->db->lastInsertId();
    }

    /**
     * @inheritDoc
     */
    public function update(Acc $acc): void
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                UPDATE `:db`.`additional_connector_configuration`
                SET
                    `name` = :name,
                    `description` = :description,
                    `parameters` = :parameters,
                    `updated_by` = :updatedBy,
                    `updated_at` = :updatedAt
                WHERE
                    `id` = :id
                SQL
        ));

        $statement->bindValue(':id', $acc->getId(), \PDO::PARAM_INT);
        $statement->bindValue(':name', $acc->getName(), \PDO::PARAM_STR);
        $statement->bindValue(':description', $acc->getDescription(), \PDO::PARAM_STR);
        $statement->bindValue(':parameters', json_encode($acc->getParameters()->getEncryptedData()));
        $statement->bindValue(':updatedBy', $acc->getUpdatedBy(), \PDO::PARAM_INT);
        $statement->bindValue(':updatedAt', $acc->getUpdatedAt()->getTimestamp(), \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function delete(int $id): void
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                DELETE FROM `:db`.`additional_connector_configuration`
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
    public function linkToPollers(int $accId, array $pollers): void
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                INSERT INTO `:db`.`acc_poller_relation`
                    (acc_id, poller_id)
                VALUES (:acc_id, :poller_id)
                SQL
        ));

        $statement->bindValue(':acc_id', $accId, \PDO::PARAM_INT);
        foreach ($pollers as $pollerId) {
            $statement->bindValue(':poller_id', $pollerId, \PDO::PARAM_INT);
            $statement->execute();
        }
    }

    /**
     * @inheritDoc
     */
    public function removePollers(int $accId): void
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                DELETE FROM `:db`.`acc_poller_relation`
                WHERE acc_id = :acc_id
                SQL
        ));

        $statement->bindValue(':acc_id', $accId, \PDO::PARAM_INT);
        $statement->execute();
    }
}
