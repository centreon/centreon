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

namespace Core\AdditionalConnector\Infrastructure\Repository;

use Centreon\Infrastructure\DatabaseConnection;
use Core\AdditionalConnector\Application\Repository\ReadAdditionalConnectorRepositoryInterface;
use Core\AdditionalConnector\Domain\Model\AdditionalConnector;
use Core\AdditionalConnector\Domain\Model\Poller;
use Core\AdditionalConnector\Domain\Model\Type;
use Core\Common\Domain\TrimmedString;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\Repository\RepositoryTrait;

/**
 * @phpstan-type _AdditionalConnector array{
 *  id:int,
 *  type:string,
 *  name:string,
 *  description:null|string,
 *  parameters:string,
 *  created_at:int,
 *  updated_at:int,
 *  created_by:null|int,
 *  updated_by:null|int
 * }
 */
class DbReadAdditionalConnectorRepository extends AbstractRepositoryRDB implements ReadAdditionalConnectorRepositoryInterface
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
    public function existsByName(TrimmedString $name): bool
    {
        $request = $this->translateDbName(
            <<<'SQL'
                SELECT 1
                FROM `:db`.`additional_connector`
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
    public function find(int $accId): ?AdditionalConnector
    {
        $sql = <<<'SQL'
            SELECT *
            FROM `:db`.`additional_connector` acc
            WHERE acc.`id` = :id
            SQL;

        // Prepare SQL + bind values
        $statement = $this->db->prepare($this->translateDbName($sql));
        $statement->bindValue(':id', $accId, \PDO::PARAM_INT);
        $statement->execute();

        if ($result = $statement->fetch()) {
            /** @var _AdditionalConnector $result */
            return $this->createFromArray($result);
        }

        return null;
    }

    public function findAll(): array
    {
        $sql = <<<'SQL'
            SELECT *
            FROM `:db`.`additional_connector` acc
            SQL;

        // Prepare SQL + bind values
        $statement = $this->db->prepare($this->translateDbName($sql));
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        $additionalConnectors = [];
        foreach ($statement as $result) {
            /** @var _AdditionalConnector $result */
            $additionalConnectors[] = $this->createFromArray($result);
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
            JOIN `:db`.`additional_connector` acc
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
        $sql = <<<'SQL'
            SELECT
                rel.`poller_id` as id,
                ng.`name`
            FROM `:db`.`acc_poller_relation` rel
            JOIN `:db`.`nagios_server` ng
                ON rel.poller_id = ng.id
            WHERE rel.`acc_id` = :id
            SQL;

        // Prepare SQL + bind values
        $statement = $this->db->prepare($this->translateDbName($sql));
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
     * @param _AdditionalConnector $row
     *
     * @return AdditionalConnector
     */
    private function createFromArray(array $row): AdditionalConnector
    {
        /** @var array<string,mixed> $parameters */
        $parameters = json_decode(json: $row['parameters'], associative: true, flags: JSON_OBJECT_AS_ARRAY);

        $acc = new AdditionalConnector(
            id: $row['id'],
            name: $row['name'],
            type: Type::from($row['type']),
            createdBy: $row['created_by'],
            updatedBy: $row['updated_by'],
            createdAt: $this->timestampToDateTimeImmutable($row['created_at']),
            updatedAt: $this->timestampToDateTimeImmutable($row['updated_at']),
            parameters: $parameters,
        );

        if ($row['description'] !== null) {
            $acc->setDescription($row['description']);
        }

        return $acc;
    }
}
