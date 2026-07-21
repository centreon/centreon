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

use Centreon\Infrastructure\DatabaseConnection;
use Core\AdditionalConnectorConfiguration\Application\Repository\WriteAccRepositoryInterface;
use Core\AdditionalConnectorConfiguration\Domain\Model\Acc;
use Core\AdditionalConnectorConfiguration\Domain\Model\NewAcc;
use Core\AdditionalConnectorConfiguration\Domain\Model\Type;
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
                    (type, port, name, description, created_by, created_at, updated_by, updated_at)
                VALUES (:type, :port, :name, :description, :createdBy, :createdAt, :createdBy, :createdAt)
                SQL
        ));

        $statement->bindValue(':type', $acc->getType()->value, \PDO::PARAM_STR);
        $statement->bindValue(':port', $acc->getParameters()->getEncryptedData()['port'] ?? 443, \PDO::PARAM_INT);
        $statement->bindValue(':name', $acc->getName(), \PDO::PARAM_STR);
        $statement->bindValue(':description', $acc->getDescription(), \PDO::PARAM_STR);
        $statement->bindValue(':createdBy', $acc->getCreatedBy(), \PDO::PARAM_INT);
        $statement->bindValue(':createdAt', $acc->getCreatedAt()->getTimestamp(), \PDO::PARAM_INT);
        $statement->execute();

        $accId = (int) $this->db->lastInsertId();

        if ($acc->getType()->value === Type::VMWARE_V6->value) {
            $parameters = $acc->getParameters()->getEncryptedData();
            if (isset($parameters['vcenters']) && is_array($parameters['vcenters'])) {
                $this->insertConfigurationItems(
                    $accId,
                    $parameters['vcenters'],
                    $acc->getCreatedAt()->getTimestamp(),
                    $acc->getCreatedAt()->getTimestamp()
                );
            }
        }

        return $accId;
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
                    `port` = :port,
                    `description` = :description,
                    `updated_by` = :updatedBy,
                    `updated_at` = :updatedAt
                WHERE
                    `id` = :id
                SQL
        ));

        $statement->bindValue(':id', $acc->getId(), \PDO::PARAM_INT);
        $statement->bindValue(':name', $acc->getName(), \PDO::PARAM_STR);
        $statement->bindValue(':port', $acc->getParameters()->getEncryptedData()['port'] ?? 443, \PDO::PARAM_INT);
        $statement->bindValue(':description', $acc->getDescription(), \PDO::PARAM_STR);
        $statement->bindValue(':updatedBy', $acc->getUpdatedBy(), \PDO::PARAM_INT);
        $statement->bindValue(':updatedAt', $acc->getUpdatedAt()->getTimestamp(), \PDO::PARAM_INT);
        $statement->execute();

        if ($acc->getType()->value === Type::VMWARE_V6->value) {
            $parameters = $acc->getParameters()->getEncryptedData();
            // get existing configuration items (vcenters)
            $existingVcentersStatement = $this->db->prepare($this->translateDbName(
                <<<'SQL'
                    SELECT id, name, url, username, password, created_at
                    FROM `:db`.`acc_item`
                    WHERE acc_id = :acc_id
                    SQL
            ));
            $existingVcentersStatement->bindValue(':acc_id', $acc->getId(), \PDO::PARAM_INT);
            $existingVcentersStatement->execute();
            $existingVcenters = [];
            foreach ($existingVcentersStatement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $existingVcenters[(int) $row['id']] = $row;
            }

            $incomingVcenters = $parameters['vcenters'] ?? [];
            $incomingVcentersById = [];
            $newVcenters = [];
            foreach ($incomingVcenters as $vcenter) {
                if (isset($vcenter['id'])) {
                    $incomingVcentersById[(int) $vcenter['id']] = $vcenter;
                } else {
                    $newVcenters[] = $vcenter;
                }
            }

            // delete removed vcenters
            $toDelete = array_diff_key($existingVcenters, $incomingVcentersById);
            if ($toDelete !== []) {
                $idsToDelete = array_keys($toDelete);
                $placeholders = implode(',', array_fill(0, count($idsToDelete), '?'));
                $deleteStatement = $this->db->prepare($this->translateDbName(
                    <<<SQL
                        DELETE FROM `:db`.`acc_item` WHERE id IN ({$placeholders})
                        SQL
                ));
                $deleteStatement->execute($idsToDelete);
            }

            // update or insert vcenters
            if ($incomingVcentersById !== []) {
                $this->updateConfigurationItems(
                    $acc->getId(),
                    $incomingVcentersById,
                    $acc->getUpdatedAt()->getTimestamp(),
                );
            }

            // insert new vcenters
            if ($newVcenters !== []) {
                $this->insertConfigurationItems(
                    $acc->getId(),
                    $newVcenters,
                    $acc->getUpdatedAt()->getTimestamp(),
                    $acc->getUpdatedAt()->getTimestamp()
                );
            }
        }
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

    /**
     * Insert configuration items (vcenters) for a given ACC configuration.
     *
     * @param int $accId
     * @param array<array{name:string,url:string,username:null,password:null}> $vcenters
     * @param int $createdAt
     * @param int $updatedAt
     */
    private function insertConfigurationItems(
        int $accId,
        array $vcenters,
        int $createdAt,
        int $updatedAt,
    ): void {
        if ($vcenters === []) {
            return;
        }

        $params = [];
        $validVcenterCount = 0;

        foreach ($vcenters as $vcenter) {
            $vcenterName = $vcenter['name'] ?? '';
            $vcenterUrl = $vcenter['url'] ?? '';
            $vcenterUsername = $vcenter['username'] ?? '';
            $vcenterPassword = $vcenter['password'] ?? '';

            if (empty($vcenterName) || empty($vcenterUrl) || empty($vcenterUsername) || empty($vcenterPassword)) {
                continue;
            }
            $params[] = $accId;
            $params[] = $vcenterName;
            $params[] = $vcenterUrl;
            $params[] = $vcenterUsername;
            $params[] = $vcenterPassword;
            $params[] = $createdAt;
            $params[] = $updatedAt;
            $validVcenterCount++;
        }

        if ($validVcenterCount === 0) {
            return;
        }

        $valuesString = implode(', ', array_fill(0, $validVcenterCount, '(?, ?, ?, ?, ?, ?, ?)'));
        $statement = $this->db->prepare($this->translateDbName(
            "INSERT INTO `:db`.`acc_item`
                (acc_id, name, url, username, password, created_at, updated_at)
            VALUES {$valuesString}"
        ));

        $statement->execute($params);
    }

    /**
     * Update existing configuration items (vcenters) by ID.
     *
     * @param int $accId
     * @param array<int, array{id:int,name:string,url:string,username:string,password:string}> $vcenters
     * @param int $updatedAt
     */
    private function updateConfigurationItems(
        int $accId,
        array $vcenters,
        int $updatedAt,
    ): void {
        if ($vcenters === []) {
            return;
        }

        $updateStatement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                UPDATE `:db`.`acc_item`
                SET
                    name = :name,
                    url = :url,
                    username = :username,
                    password = :password,
                    updated_at = :updated_at
                WHERE
                    id = :id AND acc_id = :acc_id
                SQL
        ));

        foreach ($vcenters as $id => $vcenter) {
            $vcenterName = $vcenter['name'] ?? '';
            $vcenterUrl = $vcenter['url'] ?? '';
            $vcenterUsername = $vcenter['username'] ?? '';
            $vcenterPassword = $vcenter['password'] ?? '';

            if (empty($vcenterName) || empty($vcenterUrl) || empty($vcenterUsername) || empty($vcenterPassword)) {
                continue;
            }

            $updateStatement->bindValue(':id', $id, \PDO::PARAM_INT);
            $updateStatement->bindValue(':acc_id', $accId, \PDO::PARAM_INT);
            $updateStatement->bindValue(':name', $vcenterName, \PDO::PARAM_STR);
            $updateStatement->bindValue(':url', $vcenterUrl, \PDO::PARAM_STR);
            $updateStatement->bindValue(':username', $vcenterUsername, \PDO::PARAM_STR);
            $updateStatement->bindValue(':password', $vcenterPassword, \PDO::PARAM_STR);
            $updateStatement->bindValue(':updated_at', $updatedAt, \PDO::PARAM_INT);
            $updateStatement->execute();
        }
    }
}
