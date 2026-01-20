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
                    (type, name, description, created_by, created_at, updated_by, updated_at)
                VALUES (:type, :name, :description, :createdBy, :createdAt, :createdBy, :createdAt)
                SQL
        ));

        $statement->bindValue(':type', $acc->getType()->value, \PDO::PARAM_STR);
        $statement->bindValue(':name', $acc->getName(), \PDO::PARAM_STR);
        $statement->bindValue(':description', $acc->getDescription(), \PDO::PARAM_STR);
        $statement->bindValue(':createdBy', $acc->getCreatedBy(), \PDO::PARAM_INT);
        $statement->bindValue(':createdAt', $acc->getCreatedAt()->getTimestamp(), \PDO::PARAM_INT);
        $statement->execute();

        $accId = (int) $this->db->lastInsertId();

        if ($acc->getType()->value === Type::VMWARE_V6->value) {
            $parameters = $acc->getParameters()->getEncryptedData();
            $configStatement = $this->db->prepare($this->translateDbName(
                <<<'SQL'
                    INSERT INTO `:db`.`acc_configuration`
                        (acc_id, port, created_at, updated_at)
                    VALUES (:acc_id, :port, :created_at, :updated_at)
                    SQL
            ));
            $configStatement->bindValue(':acc_id', $accId, \PDO::PARAM_INT);
            $configStatement->bindValue(':port', $parameters['port'] ?? 443, \PDO::PARAM_INT);
            $configStatement->bindValue(':created_at', $acc->getCreatedAt()->getTimestamp(), \PDO::PARAM_INT);
            $configStatement->bindValue(':updated_at', $acc->getCreatedAt()->getTimestamp(), \PDO::PARAM_INT);
            $configStatement->execute();

            $configId = (int) $this->db->lastInsertId();

            if (isset($parameters['vcenters']) && is_array($parameters['vcenters'])) {
                $this->insertConfigurationItems(
                    $configId,
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
                    `description` = :description,
                    `updated_by` = :updatedBy,
                    `updated_at` = :updatedAt
                WHERE
                    `id` = :id
                SQL
        ));

        $statement->bindValue(':id', $acc->getId(), \PDO::PARAM_INT);
        $statement->bindValue(':name', $acc->getName(), \PDO::PARAM_STR);
        $statement->bindValue(':description', $acc->getDescription(), \PDO::PARAM_STR);
        $statement->bindValue(':updatedBy', $acc->getUpdatedBy(), \PDO::PARAM_INT);
        $statement->bindValue(':updatedAt', $acc->getUpdatedAt()->getTimestamp(), \PDO::PARAM_INT);
        $statement->execute();

        if ($acc->getType()->value === Type::VMWARE_V6->value) {
            $parameters = $acc->getParameters()->getEncryptedData();
            // get config id
            $configIdStatement = $this->db->prepare($this->translateDbName(
                <<<'SQL'
                    SELECT id FROM `:db`.`acc_configuration` WHERE acc_id = :acc_id
                    SQL
            ));
            $configIdStatement->bindValue(':acc_id', $acc->getId(), \PDO::PARAM_INT);
            $configIdStatement->execute();
            $configId = (int) $configIdStatement->fetchColumn();
            if ($configId === 0) {
                $createConfigStatement = $this->db->prepare($this->translateDbName(
                    <<<'SQL'
                        INSERT INTO `:db`.`acc_configuration`
                            (acc_id, port, created_at, updated_at)
                        VALUES (:acc_id, :port, :created_at, :updated_at)
                        SQL
                ));
                $createConfigStatement->bindValue(':acc_id', $acc->getId(), \PDO::PARAM_INT);
                $createConfigStatement->bindValue(':port', $parameters['port'] ?? 443, \PDO::PARAM_INT);
                $createConfigStatement->bindValue(':created_at', $acc->getUpdatedAt()->getTimestamp(), \PDO::PARAM_INT);
                $createConfigStatement->bindValue(':updated_at', $acc->getUpdatedAt()->getTimestamp(), \PDO::PARAM_INT);
                $createConfigStatement->execute();
                $configId = (int) $this->db->lastInsertId();
            }

            // update config
            $configStatement = $this->db->prepare($this->translateDbName(
                <<<'SQL'
                    UPDATE `:db`.`acc_configuration`
                    SET port = :port, updated_at = :updated_at
                    WHERE acc_id = :acc_id
                    SQL
            ));
            $configStatement->bindValue(':acc_id', $acc->getId(), \PDO::PARAM_INT);
            $configStatement->bindValue(':port', $parameters['port'] ?? 443, \PDO::PARAM_INT);
            $configStatement->bindValue(':updated_at', $acc->getUpdatedAt()->getTimestamp(), \PDO::PARAM_INT);
            $configStatement->execute();

            // get existing configuration items (vcenters)
            $existingVcentersStatement = $this->db->prepare($this->translateDbName(
                <<<'SQL'
                    SELECT id, name, url, username, password, created_at
                    FROM `:db`.`acc_configuration_item`
                    WHERE acc_conf_id = :acc_conf_id
                    SQL
            ));
            $existingVcentersStatement->bindValue(':acc_conf_id', $configId, \PDO::PARAM_INT);
            $existingVcentersStatement->execute();
            $existingVcenters = [];
            foreach ($existingVcentersStatement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $existingVcenters[$row['name']] = $row;
            }

            $incomingVcenters = $parameters['vcenters'] ?? [];
            $incomingNames = array_column($incomingVcenters, 'name');

            // delete removed vcenters
            $toDelete = array_diff(array_keys($existingVcenters), $incomingNames);
            if ($toDelete !== []) {
                $idsToDelete = array_map(fn ($name) => $existingVcenters[$name]['id'], $toDelete);
                $placeholders = implode(',', array_fill(0, count($idsToDelete), '?'));
                $deleteStatement = $this->db->prepare($this->translateDbName(
                    <<<SQL
                        DELETE FROM `:db`.`acc_configuration_item` WHERE id IN ({$placeholders})
                        SQL
                ));
                $deleteStatement->execute(array_values($idsToDelete));
            }

            // update or insert vcenters
            if (! empty($incomingVcenters)) {
                $this->upsertConfigurationItems(
                    $configId,
                    $incomingVcenters,
                    $acc->getUpdatedAt()->getTimestamp(),
                    $existingVcenters,
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
     * @param int $configId
     * @param array<array{name:string,url:string,username:null,password:null}> $vcenters
     * @param int $createdAt
     * @param int $updatedAt
     */
    private function insertConfigurationItems(
        int $configId,
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
            $params[] = $configId;
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
            "INSERT INTO `:db`.`acc_configuration_item`
                (acc_conf_id, name, url, username, password, created_at, updated_at)
            VALUES {$valuesString}"
        ));

        $statement->execute($params);
    }

    /**
     * Update or insert configuration items (vcenters) for a given ACC configuration.
     *
     * @param int $configId
     * @param array<array{name:string,url:string,username:null,password:null}> $vcenters
     * @param int $updatedAt
     * @param array<string, array{id:int, name:string, url:string, username:string, password:string, created_at:int}> $existingVcenters
     */
    private function upsertConfigurationItems(
        int $configId,
        array $vcenters,
        int $updatedAt,
        array $existingVcenters,
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

            $createdAt = $existingVcenters[$vcenterName]['created_at'] ?? $updatedAt;

            $params[] = $configId;
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
            "INSERT INTO `:db`.`acc_configuration_item`
                (acc_conf_id, name, url, username, password, created_at, updated_at)
            VALUES {$valuesString}
            AS new_item
            ON DUPLICATE KEY UPDATE
                url = new_item.url,
                username = new_item.username,
                password = new_item.password,
                updated_at = new_item.updated_at"
        ));

        $statement->execute($params);
    }
}
