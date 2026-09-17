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

namespace Tests\App\Shared\Infrastructure\Dbal\Double;

use App\Shared\Domain\Repository\Pagination;
use App\Shared\Domain\Repository\SearchableCriteria;
use App\Shared\Domain\Repository\SearchCondition;
use App\Shared\Domain\Repository\SortableCriteria;
use App\Shared\Infrastructure\Dbal\DbalCriteriaApplierTrait;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Exposes the otherwise private {@see DbalCriteriaApplierTrait} methods so the
 * trait can be exercised in isolation.
 */
final class CriteriaApplierProbe
{
    use DbalCriteriaApplierTrait;

    public function search(QueryBuilder $queryBuilder, string $alias, SearchableCriteria&SortableCriteria $criteria): void
    {
        $this->applySearch($queryBuilder, $alias, $criteria);
    }

    public function searchCondition(QueryBuilder $queryBuilder, string $alias, string $column, SearchCondition $condition, int $index): void
    {
        $this->applySearchCondition($queryBuilder, $alias, $column, $condition, $index);
    }

    public function sort(QueryBuilder $queryBuilder, string $alias, SortableCriteria $criteria, ?string $fallbackColumn = null): void
    {
        $this->applySort($queryBuilder, $alias, $criteria, $fallbackColumn);
    }

    public function paginate(QueryBuilder $queryBuilder, Pagination $pagination): void
    {
        $this->applyPagination($queryBuilder, $pagination);
    }

    public function count(QueryBuilder $queryBuilder, string $countExpression): int
    {
        return $this->countMatching($queryBuilder, $countExpression);
    }
}
