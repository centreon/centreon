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

namespace Centreon\Tests\Resources\Mock;

use Centreon\Infrastructure\CentreonLegacyDB\Interfaces\PaginationRepositoryInterface;
use Centreon\Infrastructure\CentreonLegacyDB\ServiceEntityRepository;
use Centreon\Infrastructure\CentreonLegacyDB\StatementCollector;
use PDO;

/**
 * Mock of repository class
 */
class RepositoryPaginationMock extends ServiceEntityRepository implements PaginationRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public static function entityClass(): string
    {
        return EntityMock::class;
    }

    /**
     * {@inheritDoc}
     */
    public function getPaginationList($filters = null, ?int $limit = null, ?int $offset = null, $ordering = []): array
    {
        $sql = 'SELECT SQL_CALC_FOUND_ROWS t.* '
            . 'FROM `' . $this->getClassMetadata()->getTableName() . '` AS `t`';

        $collector = new StatementCollector();

        $isWhere = false;

        if ($filters !== null) {
            if (array_key_exists('search', $filters) && $filters['search']) {
                $sql .= ' WHERE `name_column` LIKE :search';
                $collector->addValue(':search', "%{$filters['search']}%");
                $isWhere = true;
            }
        }

        if (! empty($ordering['field'])) {
            $sql .= ' ORDER BY `' . $ordering['field'] . '` ' . $ordering['order'];
        }

        if ($limit !== null) {
            $sql .= ' LIMIT :limit';
            $collector->addValue(':limit', $limit, PDO::PARAM_INT);

            if ($offset !== null) {
                $sql .= ' OFFSET :offset';
                $collector->addValue(':offset', $offset, PDO::PARAM_INT);
            }
        }

        $stmt = $this->db->prepare($sql);
        $collector->bind($stmt);

        $stmt->execute();

        $result = [];

        while ($row = $stmt->fetch()) {
            $result[] = $this->getEntityPersister()->load($row);
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function getPaginationListTotal(): int
    {
        return $this->db->numberRows();
    }
}
