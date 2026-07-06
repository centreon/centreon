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

namespace App\Shared\Infrastructure\Dbal;

use App\Shared\Domain\Repository\Pagination;
use App\Shared\Domain\Repository\SearchableCriteria;
use App\Shared\Domain\Repository\SearchCondition;
use App\Shared\Domain\Repository\SearchOperatorEnum;
use App\Shared\Domain\Repository\SortableCriteria;
use App\Shared\Domain\Repository\SortDirectionEnum;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Webmozart\Assert\Assert;

/**
 * Infrastructure counterpart to the domain listing criteria contracts
 * ({@see SearchableCriteria}, {@see SortableCriteria}, {@see PaginableCriteria}).
 *
 * Centralizes the operator-to-SQL translation, the field-to-column mapping and the
 * sort / pagination / count boilerplate so each Dbal listing repository only has to
 * supply its base query, table alias and count expression.
 */
trait DbalCriteriaApplierTrait
{
    /**
     * Apply each search condition of the criteria as a WHERE clause, mapping the
     * API field to its SQL column via {@see SortableCriteria::getFieldMapping()}.
     * Unmapped fields are skipped.
     */
    private function applySearch(QueryBuilder $queryBuilder, string $alias, SearchableCriteria&SortableCriteria $criteria): void
    {
        $mapping = $criteria->getFieldMapping();
        $index = 0;

        foreach ($criteria->getSearch() as $condition) {
            $column = $mapping[$condition->field] ?? null;
            if ($column === null) {
                continue;
            }

            $this->applySearchCondition($queryBuilder, $alias, $column, $condition, $index);
            $index++;
        }
    }

    private function applySearchCondition(
        QueryBuilder $queryBuilder,
        string $alias,
        string $column,
        SearchCondition $condition,
        int $index,
    ): void {
        $parameter = 'search_' . $index;
        $qualified = $alias . '.' . $column;

        switch ($condition->operator) {
            case SearchOperatorEnum::EQUAL:
                $queryBuilder->andWhere("{$qualified} = :{$parameter}")->setParameter($parameter, $this->scalarValue($condition->value));
                break;
            case SearchOperatorEnum::NOT_EQUAL:
                $queryBuilder->andWhere("{$qualified} != :{$parameter}")->setParameter($parameter, $this->scalarValue($condition->value));
                break;
            case SearchOperatorEnum::LIKE:
                $queryBuilder->andWhere("{$qualified} LIKE :{$parameter}")->setParameter($parameter, '%' . $this->scalarValue($condition->value) . '%');
                break;
            case SearchOperatorEnum::NOT_LIKE:
                $queryBuilder->andWhere("{$qualified} NOT LIKE :{$parameter}")->setParameter($parameter, '%' . $this->scalarValue($condition->value) . '%');
                break;
            case SearchOperatorEnum::IN:
                $queryBuilder->andWhere("{$qualified} IN (:{$parameter})")
                    ->setParameter($parameter, $this->listValue($condition->value), ArrayParameterType::STRING);
                break;
            case SearchOperatorEnum::NOT_IN:
                $queryBuilder->andWhere("{$qualified} NOT IN (:{$parameter})")
                    ->setParameter($parameter, $this->listValue($condition->value), ArrayParameterType::STRING);
                break;
            default:
                // Comparison operators (gt, gte, lt, lte) are part of the canonical
                // enum but are never allowed by the listings built on this trait, so
                // they cannot reach this point. Fail loudly if a listing's
                // allowedOperators() and this switch ever drift apart, rather than
                // silently emitting an unfiltered query.
                throw new \LogicException(sprintf(
                    'Operator "%s" reached the repository but is not handled by this listing.',
                    $condition->operator->value,
                ));
        }
    }

    /**
     * Apply the requested sort as ORDER BY clauses. When the criteria carries no
     * sort, fall back to $fallbackColumn ASC so pagination stays deterministic
     * across pages (MySQL has no implicit row order). Pass null to skip the fallback.
     */
    private function applySort(
        QueryBuilder $queryBuilder,
        string $alias,
        SortableCriteria $criteria,
        ?string $fallbackColumn = null,
    ): void {
        $mapping = $criteria->getFieldMapping();
        $applied = false;

        foreach ($criteria->getSort() as $field => $direction) {
            $column = $mapping[$field] ?? null;
            if ($column === null) {
                continue;
            }

            $queryBuilder->addOrderBy($alias . '.' . $column, $direction === SortDirectionEnum::DESC ? 'DESC' : 'ASC');
            $applied = true;
        }

        if (! $applied && $fallbackColumn !== null) {
            $queryBuilder->addOrderBy($alias . '.' . $fallbackColumn, 'ASC');
        }
    }

    private function applyPagination(QueryBuilder $queryBuilder, Pagination $pagination): void
    {
        $queryBuilder->setFirstResult($pagination->getOffset())
            ->setMaxResults($pagination->itemsPerPage);
    }

    /**
     * Count the rows matching the current query, ignoring its sort and
     * pagination. Counted on a clone so the listing query keeps its
     * sort / pagination — SQL_CALC_FOUND_ROWS is deprecated in MySQL 8.x, so we
     * run a dedicated COUNT instead.
     *
     * @param string $countExpression the COUNT expression to select, e.g. 'COUNT(DISTINCT t.id)'
     *
     * @throws \Doctrine\DBAL\Exception
     */
    private function countMatching(QueryBuilder $queryBuilder, string $countExpression): int
    {
        $countQb = clone $queryBuilder;

        $count = $countQb
            ->resetOrderBy()
            ->select($countExpression)
            ->setFirstResult(0)
            ->setMaxResults(null)
            ->executeQuery()
            ->fetchOne();

        Assert::numeric($count);

        return (int) $count;
    }

    /**
     * Scalar operators (eq, neq, lk, nlk) carry a single string value, as guaranteed by
     * {@see SearchCondition}'s constructor.
     *
     * @param string|list<string> $value
     */
    private function scalarValue(string|array $value): string
    {
        Assert::string($value);

        return $value;
    }

    /**
     * List operators (in, nin) carry a non-empty list of string values, as guaranteed by
     * {@see SearchCondition}'s constructor.
     *
     * @param string|list<string> $value
     *
     * @return list<string>
     */
    private function listValue(string|array $value): array
    {
        Assert::isArray($value);

        return $value;
    }
}
