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

namespace CentreonUser\Domain\Repository;

use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use CentreonUser\Domain\Entity\Timeperiod;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Domain\Repository\Traits\CheckListOfIdsTrait;
use Centreon\Infrastructure\CentreonLegacyDB\StatementCollector;
use Centreon\Infrastructure\CentreonLegacyDB\Interfaces\PaginationRepositoryInterface;

class TimeperiodRepository extends AbstractRepositoryRDB implements PaginationRepositoryInterface
{
    use CheckListOfIdsTrait;

    /**
     * @var int $resultCountForPagination
     */
    private int $resultCountForPagination = 0;

    /**
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * {@inheritdoc}
     */
    public static function entityClass(): string
    {
        return Timeperiod::class;
    }

    /**
     * Check list of IDs
     *
     * @param int[] $ids
     * @return bool
     */
    public function checkListOfIds(array $ids): bool
    {
        return $this->checkListOfIdsTrait(
            $ids,
            TimePeriod::TABLE,
            TimePeriod::ENTITY_IDENTIFICATOR_COLUMN
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return Timeperiod
     */
    private function createTimeperiodFromArray(array $data): Timeperiod
    {
        $timeperiod = new Timeperiod();
        $timeperiod->setId((int) $data['tp_id']);
        $timeperiod->setName($data['tp_name']);
        $timeperiod->setAlias($data['tp_alias']);

        return $timeperiod;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaginationList($filters = null, int $limit = null, int $offset = null, $ordering = []): array
    {
        $sql = 'SELECT SQL_CALC_FOUND_ROWS `tp_id`, `tp_name`, `tp_alias` '
            . 'FROM `:db`.`timeperiod`';

        $collector = new StatementCollector();

        $isWhere = false;
        if ($filters !== null) {
            if (
                array_key_exists('search', $filters)
                && $filters['search']
            ) {
                $sql .= ' WHERE (`tp_name` LIKE :search OR `tp_alias` LIKE :search)';
                $collector->addValue(':search', "%{$filters['search']}%");
                $isWhere = true;
            }

            if (
                array_key_exists('ids', $filters)
                && is_array($filters['ids'])
            ) {
                $idsListKey = [];
                foreach ($filters['ids'] as $x => $id) {
                    $key = ":id{$x}";
                    $idsListKey[] = $key;
                    $collector->addValue($key, $id, \PDO::PARAM_INT);

                    unset($x, $id);
                }

                $sql .= $isWhere ? ' AND' : ' WHERE';
                $sql .= ' `tp_id` IN (' . implode(',', $idsListKey) . ')';
            }
        }

        if (! empty($ordering['field'])) {
            $sql .= ' ORDER BY `' . $ordering['field'] . '` ' . $ordering['order'];
        }

        if ($limit !== null) {
            $sql .= ' LIMIT :limit';
            $collector->addValue(':limit', $limit, \PDO::PARAM_INT);

            if ($offset !== null) {
                $sql .= ' OFFSET :offset';
                $collector->addValue(':offset', $offset, \PDO::PARAM_INT);
            }
        }

        $statement = $this->db->prepare($this->translateDbName($sql));
        $collector->bind($statement);

        $statement->execute();

        $foundRecords = $this->db->query('SELECT FOUND_ROWS()');

        if ($foundRecords !== false && ($total = $foundRecords->fetchColumn()) !== false) {
            $this->resultCountForPagination = $total;
        }

        $result = [];

        while ($record = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $result[] = $this->createTimeperiodFromArray($record);
        }

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
