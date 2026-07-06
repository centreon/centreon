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

namespace Tests\App\Shared\Infrastructure\Dbal;

use App\Shared\Domain\Repository\Pagination;
use App\Shared\Domain\Repository\SearchCondition;
use App\Shared\Domain\Repository\SearchOperatorEnum;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Query\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Tests\App\Shared\Domain\Repository\Double\FakeCriteria;
use Tests\App\Shared\Infrastructure\Dbal\Double\CriteriaApplierProbe;

/**
 * Exercises the shared operator-to-SQL translation and the sort / pagination /
 * count boilerplate against a real in-memory SQLite connection, so the generated
 * SQL is asserted both as text ({@see QueryBuilder::getSQL()}) and by execution.
 */
final class DbalCriteriaApplierTraitTest extends TestCase
{
    private Connection $connection;

    private CriteriaApplierProbe $applier;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement('CREATE TABLE ba (ba_id INTEGER PRIMARY KEY, ba_name TEXT NOT NULL)');
        $this->connection->executeStatement(
            "INSERT INTO ba (ba_id, ba_name) VALUES (1, 'Alpha'), (2, 'Beta'), (3, 'Gamma'), (4, 'Delta'), (5, 'Epsilon')"
        );

        $this->applier = new CriteriaApplierProbe();
    }

    public function testEqualOperatorBuildsExactMatch(): void
    {
        $queryBuilder = $this->baseQuery();
        $this->applier->searchCondition($queryBuilder, 't', 'ba_name', new SearchCondition('name', SearchOperatorEnum::EQUAL, 'Alpha'), 0);

        self::assertStringContainsString('t.ba_name = :search_0', $queryBuilder->getSQL());
        self::assertSame(['search_0' => 'Alpha'], $queryBuilder->getParameters());
    }

    public function testNotEqualOperatorBuildsInequality(): void
    {
        $queryBuilder = $this->baseQuery();
        $this->applier->searchCondition($queryBuilder, 't', 'ba_name', new SearchCondition('name', SearchOperatorEnum::NOT_EQUAL, 'Alpha'), 0);

        self::assertStringContainsString('t.ba_name != :search_0', $queryBuilder->getSQL());
    }

    public function testNotEqualOperatorExcludesMatchingValue(): void
    {
        $queryBuilder = $this->baseQuery();
        $this->applier->searchCondition($queryBuilder, 't', 'ba_name', new SearchCondition('name', SearchOperatorEnum::NOT_EQUAL, 'Alpha'), 0);

        // Every seeded row except "Alpha".
        self::assertSame(4, $this->applier->count($queryBuilder, 'COUNT(DISTINCT t.ba_id)'));
    }

    public function testLikeOperatorWrapsValueWithWildcards(): void
    {
        $queryBuilder = $this->baseQuery();
        $this->applier->searchCondition($queryBuilder, 't', 'ba_name', new SearchCondition('name', SearchOperatorEnum::LIKE, 'lph'), 0);

        self::assertStringContainsString('t.ba_name LIKE :search_0', $queryBuilder->getSQL());
        self::assertSame(['search_0' => '%lph%'], $queryBuilder->getParameters());
    }

    public function testNotLikeOperatorWrapsValueWithWildcards(): void
    {
        $queryBuilder = $this->baseQuery();
        $this->applier->searchCondition($queryBuilder, 't', 'ba_name', new SearchCondition('name', SearchOperatorEnum::NOT_LIKE, 'lph'), 0);

        self::assertStringContainsString('t.ba_name NOT LIKE :search_0', $queryBuilder->getSQL());
        self::assertSame(['search_0' => '%lph%'], $queryBuilder->getParameters());
    }

    public function testInOperatorBuildsListClause(): void
    {
        $queryBuilder = $this->baseQuery();
        $this->applier->searchCondition($queryBuilder, 't', 'ba_id', new SearchCondition('id', SearchOperatorEnum::IN, ['1', '2']), 0);

        self::assertStringContainsString('t.ba_id IN (:search_0)', $queryBuilder->getSQL());
        self::assertSame(['search_0' => ['1', '2']], $queryBuilder->getParameters());
    }

    public function testNotInOperatorBuildsListClause(): void
    {
        $queryBuilder = $this->baseQuery();
        $this->applier->searchCondition($queryBuilder, 't', 'ba_id', new SearchCondition('id', SearchOperatorEnum::NOT_IN, ['1', '2']), 0);

        self::assertStringContainsString('t.ba_id NOT IN (:search_0)', $queryBuilder->getSQL());
    }

    public function testInOperatorMatchesListedValues(): void
    {
        $criteria = (new FakeCriteria())->withSearch('name', SearchOperatorEnum::IN, ['Alpha', 'Beta']);

        $queryBuilder = $this->baseQuery();
        $this->applier->search($queryBuilder, 't', $criteria);

        // Only the two listed names match; verifies the bound-list expansion executes.
        self::assertSame(2, $this->applier->count($queryBuilder, 'COUNT(DISTINCT t.ba_id)'));
    }

    public function testNotInOperatorExcludesListedValues(): void
    {
        $criteria = (new FakeCriteria())->withSearch('name', SearchOperatorEnum::NOT_IN, ['Alpha', 'Beta']);

        $queryBuilder = $this->baseQuery();
        $this->applier->search($queryBuilder, 't', $criteria);

        // The five seeded rows minus the two excluded names.
        self::assertSame(3, $this->applier->count($queryBuilder, 'COUNT(DISTINCT t.ba_id)'));
    }

    public function testIndexDisambiguatesSuccessiveConditions(): void
    {
        $queryBuilder = $this->baseQuery();
        $this->applier->searchCondition($queryBuilder, 't', 'ba_name', new SearchCondition('name', SearchOperatorEnum::EQUAL, 'Alpha'), 0);
        $this->applier->searchCondition($queryBuilder, 't', 'ba_name', new SearchCondition('name', SearchOperatorEnum::LIKE, 'Be'), 1);

        self::assertSame(['search_0' => 'Alpha', 'search_1' => '%Be%'], $queryBuilder->getParameters());
    }

    public function testComparisonOperatorFailsLoudly(): void
    {
        $this->expectException(\LogicException::class);

        $this->applier->searchCondition(
            $this->baseQuery(),
            't',
            'ba_id',
            new SearchCondition('id', SearchOperatorEnum::GREATER_THAN, '10'),
            0,
        );
    }

    public function testSearchMapsApiFieldsToColumnsAndSkipsUnmappedOnes(): void
    {
        $criteria = (new FakeCriteria())
            ->withSearch('name', SearchOperatorEnum::EQUAL, 'Alpha')
            ->withSearch('unmapped', SearchOperatorEnum::EQUAL, 'ignored');

        $queryBuilder = $this->baseQuery();
        $this->applier->search($queryBuilder, 't', $criteria);

        // 'name' maps to 'ba_name'; 'unmapped' has no column and is skipped.
        self::assertStringContainsString('t.ba_name = :search_0', $queryBuilder->getSQL());
        self::assertSame(['search_0' => 'Alpha'], $queryBuilder->getParameters());
    }

    public function testSearchWithoutConditionsLeavesQueryUnfiltered(): void
    {
        $queryBuilder = $this->baseQuery();
        $this->applier->search($queryBuilder, 't', new FakeCriteria());

        self::assertStringNotContainsString('WHERE', $queryBuilder->getSQL());
        // All five seeded rows remain reachable.
        self::assertSame(5, $this->applier->count($queryBuilder, 'COUNT(DISTINCT t.ba_id)'));
    }

    public function testSearchAppliesEachMappedConditionWithItsOwnParameter(): void
    {
        $criteria = (new FakeCriteria())
            ->withSearch('name', SearchOperatorEnum::EQUAL, 'Alpha')
            ->withSearch('name', SearchOperatorEnum::LIKE, 'et');

        $queryBuilder = $this->baseQuery();
        $this->applier->search($queryBuilder, 't', $criteria);

        $sql = $queryBuilder->getSQL();
        // Both mapped conditions land, AND-combined, each with a distinct parameter index.
        self::assertStringContainsString('t.ba_name = :search_0', $sql);
        self::assertStringContainsString('t.ba_name LIKE :search_1', $sql);
        self::assertSame(['search_0' => 'Alpha', 'search_1' => '%et%'], $queryBuilder->getParameters());
    }

    public function testSortAppliesRequestedDirections(): void
    {
        $criteria = (new FakeCriteria())->withSort('name', 'DESC');

        $queryBuilder = $this->baseQuery();
        $this->applier->sort($queryBuilder, 't', $criteria, 'ba_id');

        self::assertStringContainsString('ORDER BY t.ba_name DESC', $queryBuilder->getSQL());
    }

    public function testSortAppliesExplicitAscendingDirection(): void
    {
        $criteria = (new FakeCriteria())->withSort('name', 'ASC');

        $queryBuilder = $this->baseQuery();
        $this->applier->sort($queryBuilder, 't', $criteria, 'ba_id');

        self::assertStringContainsString('ORDER BY t.ba_name ASC', $queryBuilder->getSQL());
    }

    public function testSortAppliesMultipleFieldsInRequestedOrder(): void
    {
        $criteria = (new FakeCriteria())
            ->withSort('name', 'ASC')
            ->withSort('id', 'DESC');

        $queryBuilder = $this->baseQuery();
        $this->applier->sort($queryBuilder, 't', $criteria, 'ba_id');

        self::assertStringContainsString('ORDER BY t.ba_name ASC, t.ba_id DESC', $queryBuilder->getSQL());
    }

    public function testSortFallsBackWhenNoSortRequested(): void
    {
        $queryBuilder = $this->baseQuery();
        $this->applier->sort($queryBuilder, 't', new FakeCriteria(), 'ba_id');

        self::assertStringContainsString('ORDER BY t.ba_id ASC', $queryBuilder->getSQL());
    }

    public function testSortWithoutFallbackLeavesQueryUnordered(): void
    {
        $queryBuilder = $this->baseQuery();
        $this->applier->sort($queryBuilder, 't', new FakeCriteria(), null);

        self::assertStringNotContainsString('ORDER BY', $queryBuilder->getSQL());
    }

    public function testPaginationSetsLimitAndOffset(): void
    {
        $queryBuilder = $this->baseQuery();
        $this->applier->paginate($queryBuilder, new Pagination(page: 3, itemsPerPage: 10));

        self::assertSame(20, $queryBuilder->getFirstResult());
        self::assertSame(10, $queryBuilder->getMaxResults());
    }

    public function testCountMatchingIgnoresSortAndPagination(): void
    {
        $criteria = (new FakeCriteria())->withSort('name', 'ASC');

        $queryBuilder = $this->baseQuery();
        $this->applier->sort($queryBuilder, 't', $criteria, 'ba_id');
        $this->applier->paginate($queryBuilder, new Pagination(page: 1, itemsPerPage: 2));

        // The five seeded rows are all counted, regardless of the LIMIT 2.
        self::assertSame(5, $this->applier->count($queryBuilder, 'COUNT(DISTINCT t.ba_id)'));
    }

    public function testCountMatchingReflectsSearchFilter(): void
    {
        $criteria = (new FakeCriteria())->withSearch('name', SearchOperatorEnum::LIKE, 'lph');

        $queryBuilder = $this->baseQuery();
        $this->applier->search($queryBuilder, 't', $criteria);

        // Only "Alpha" contains the substring "lph".
        self::assertSame(1, $this->applier->count($queryBuilder, 'COUNT(DISTINCT t.ba_id)'));
    }

    public function testCountMatchingDeduplicatesJoinedRows(): void
    {
        // A one-to-many join inflates the row set (five joined rows for two parents);
        // COUNT(DISTINCT t.ba_id) must still report the distinct parent count, which is
        // the reason countMatching takes an explicit count expression.
        $this->connection->executeStatement('CREATE TABLE ba_tag (ba_id INTEGER, tag TEXT)');
        $this->connection->executeStatement(
            "INSERT INTO ba_tag (ba_id, tag) VALUES (1, 'x'), (1, 'y'), (1, 'z'), (2, 'x'), (2, 'y')"
        );

        $queryBuilder = $this->baseQuery()->innerJoin('t', 'ba_tag', 'g', 't.ba_id = g.ba_id');

        self::assertSame(2, $this->applier->count($queryBuilder, 'COUNT(DISTINCT t.ba_id)'));
    }

    public function testCountMatchingRejectsNonNumericResult(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/numeric/i');

        // A non-aggregate text expression yields a non-numeric value; the guard must
        // reject it rather than silently cast it to 0.
        $this->applier->count($this->baseQuery(), 't.ba_name');
    }

    private function baseQuery(): QueryBuilder
    {
        return $this->connection->createQueryBuilder()
            ->select('t.ba_id', 't.ba_name')
            ->from('ba', 't');
    }
}
