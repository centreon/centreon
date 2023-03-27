<?php

/*
 * Copyright 2005 - 2019 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Centreon\Domain\Repository;

use Centreon\Domain\Entity\ContactGroup;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Domain\Repository\Traits\CheckListOfIdsTrait;
use Centreon\Infrastructure\CentreonLegacyDB\StatementCollector;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Centreon\Infrastructure\CentreonLegacyDB\Interfaces\PaginationRepositoryInterface;

class ContactGroupRepository extends AbstractRepositoryRDB implements PaginationRepositoryInterface
{
    use CheckListOfIdsTrait;

    /** @var int $resultCountForPagination */
    private int $resultCountForPagination = 0;

    /**
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * Check list of IDs
     *
     * @return bool
     */
    public function checkListOfIds(array $ids): bool
    {
        return $this->checkListOfIdsTrait($ids, ContactGroup::TABLE, ContactGroup::ENTITY_IDENTIFICATOR_COLUMN);
    }

    /**
     * {@inheritdoc}
     */
    public function getPaginationList($filters = null, int $limit = null, int $offset = null, $ordering = []): array
    {
        $collector = new StatementCollector();

        $sql = 'SELECT SQL_CALC_FOUND_ROWS * FROM `:db`.contactgroup';

        $isWhere = false;
        if ($filters !== null) {
            if ($filters['search'] ?? false) {
                $sql .= ' WHERE `cg_name` LIKE :search';
                $collector->addValue(':search', "%{$filters['search']}%");
                $isWhere = true;
            }
            if (
                array_key_exists('ids', $filters)
                && is_array($filters['ids'])
                && [] !== $filters['ids']
            ) {
                $idsListKey = [];
                foreach ($filters['ids'] as $x => $id) {
                    $key = ":id{$x}";
                    $idsListKey[] = $key;
                    $collector->addValue($key, $id, \PDO::PARAM_INT);
                    unset($x, $id);
                }
                $sql .= $isWhere ? ' AND' : ' WHERE';
                $sql .= ' `cg_id` IN (' . implode(',', $idsListKey) . ')';
            }
        }

        if ($ordering['field'] ?? false) {
            $sql .= ' ORDER BY `' . $ordering['field'] . '` ' . $ordering['order'];
        }

        if ($limit !== null) {
            $sql .= ' LIMIT :limit';
            $collector->addValue(':limit', $limit, \PDO::PARAM_INT);
        }

        if ($offset !== null) {
            $sql .= ' OFFSET :offset';
            $collector->addValue(':offset', $offset, \PDO::PARAM_INT);
        }
        $statement = $this->db->prepare($this->translateDbName($sql));
        $collector->bind($statement);
        $statement->execute();

        $foundRecords = $this->db->query('SELECT FOUND_ROWS()');

        if ($foundRecords !== false && ($total = $foundRecords->fetchColumn()) !== false) {
            $this->resultCountForPagination = $total;
        }
        $statement->setFetchMode(\PDO::FETCH_CLASS, ContactGroup::class);
        $result = $statement->fetchAll();

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaginationListTotal(): int
    {
        return $this->resultCountForPagination;
    }
}
