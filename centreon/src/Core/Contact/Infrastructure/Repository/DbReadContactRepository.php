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

namespace Core\Contact\Infrastructure\Repository;

use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\Repository\AbstractRepositoryDRB;
use Core\Contact\Application\Repository\ReadContactRepositoryInterface;

class DbReadContactRepository extends AbstractRepositoryDRB implements ReadContactRepositoryInterface
{
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    public function findNamesByIds(int ...$ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $ids = array_unique($ids);

        $fields = '';
        foreach ($ids as $index => $id) {
            $fields .= ('' === $fields ? '' : ', ') . ':id_' . $index;
        }

        $select = <<<SQL
            SELECT
                `contact_id` as `id`,
                `contact_name` as `name`
            FROM
                `:db`.`contact`
            WHERE
                `contact_id` IN ({$fields})
            SQL;

        $statement = $this->db->prepare($this->translateDbName($select));
        foreach ($ids as $index => $id) {
            $statement->bindValue(':id_' . $index, $id, \PDO::PARAM_INT);
        }
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        // Retrieve data
        $names = [];
        foreach ($statement as $result) {
            /** @var array{ id: int, name: string } $result */
            $names[$result['id']] = $result;
        }

        return $names;
    }

    /**
     * @inheritDoc
     */
    public function exists(int $userId): bool
    {
        $request = $this->translateDbName(
            <<<'SQL'
                SELECT 1 FROM `:db`.contact
                WHERE contact_id = :userId
                SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':userId', $userId, \PDO::PARAM_INT);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function exist(array $userIds): array
    {
        $bind = [];
        foreach ($userIds as $key => $userId) {
            $bind[":user_{$key}"] = $userId;
        }
        if ($bind === []) {
            return [];
        }

        $contactIdsAsString = implode(', ', array_keys($bind));
        $request = $this->translateDbName(
            <<<SQL
                SELECT contact_id FROM `:db`.contact
                WHERE contact_id IN ({$contactIdsAsString})
                SQL
);
        $statement = $this->db->prepare($request);
        foreach ($bind as $key => $value) {
            $statement->bindValue($key, $value, \PDO::PARAM_INT);
        }
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_COLUMN, 0);
    }

    /**
     * @inheritDoc
     */
    public function findContactIdsByContactGroups(array $contactGroupIds): array
    {
        $bind = [];
        foreach ($contactGroupIds as $key => $contactGroupId) {
            $bind[":contactGroup_{$key}"] = $contactGroupId;
        }
        if ($bind === []) {
            return [];
        }
        $bindAsString = implode(', ', array_keys($bind));
        $request = <<<SQL
            SELECT
                contact_contact_id
            FROM
                `:db`.`contactgroup_contact_relation` cgcr
            WHERE
                cgcr.contactgroup_cg_id IN ({$bindAsString})
            SQL;

        $statement = $this->db->prepare($this->translateDbName($request));
        foreach ($bind as $token => $bindValue) {
            $statement->bindValue($token, $bindValue, \PDO::PARAM_INT);
        }
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_COLUMN, 0);
    }
}
