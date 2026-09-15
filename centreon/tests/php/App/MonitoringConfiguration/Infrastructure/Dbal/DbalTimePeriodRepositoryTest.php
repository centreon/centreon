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

namespace Tests\App\MonitoringConfiguration\Infrastructure\Dbal;

use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriod;
use App\MonitoringConfiguration\Domain\Repository\Criteria\TimePeriodCriteria;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalTimePeriodRepository;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalTimePeriodTransformer;
use App\Shared\Domain\Repository\Paginator;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DbalTimePeriodRepositoryTest extends KernelTestCase
{
    private Connection $connection;

    private DbalTimePeriodRepository $repository;

    private string $tag;

    protected function setUp(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        // The repository is a private service; construct it directly from the always-public
        // DBAL connection rather than relying on the container exposing it.
        $this->repository = new DbalTimePeriodRepository($this->connection, new DbalTimePeriodTransformer());

        // unique per test run so assertions are isolated from the pre-seeded time periods
        $this->tag = Uuid::v4()->toRfc4122();
    }

    public function testFindAllReturnsIdAndNameOfEveryTimePeriod(): void
    {
        $name = "tp-{$this->tag}";
        $id = $this->insertTimePeriod($name);

        $timePeriods = iterator_to_array($this->repository->findAll());

        $match = array_values(array_filter(
            $timePeriods,
            static fn (TimePeriod $timePeriod): bool => $timePeriod->id()->value === $id
        ));

        self::assertCount(1, $match);
        self::assertSame($name, $match[0]->name->value);
    }

    public function testFindAllFiltersByNameUsingLike(): void
    {
        $this->insertTimePeriod("match-{$this->tag}");
        $this->insertTimePeriod("other-{$this->tag}");

        $names = $this->names($this->repository->findAll(
            (new TimePeriodCriteria())->withName("match-{$this->tag}", TimePeriodCriteria::OPERATOR_LIKE)
        ));

        self::assertSame(["match-{$this->tag}"], $names);
    }

    public function testFindAllCombinesSeveralLikeNamesWithOr(): void
    {
        $this->insertTimePeriod("first-{$this->tag}");
        $this->insertTimePeriod("second-{$this->tag}");
        $this->insertTimePeriod("third-{$this->tag}");

        // "name[lk][]=a&name[lk][]=b" stacks two values under the same operator: they must widen
        // the result set (OR), not narrow it to nothing (AND).
        $names = $this->names($this->repository->findAll(
            (new TimePeriodCriteria())
                ->withName("first-{$this->tag}", TimePeriodCriteria::OPERATOR_LIKE)
                ->withName("second-{$this->tag}", TimePeriodCriteria::OPERATOR_LIKE)
        ));

        self::assertSame(["first-{$this->tag}", "second-{$this->tag}"], $names);
    }

    public function testFindAllPaginatesAndReturnsATotalAcrossAllPages(): void
    {
        $this->insertTimePeriod("pg-{$this->tag}-A");
        $this->insertTimePeriod("pg-{$this->tag}-B");
        $this->insertTimePeriod("pg-{$this->tag}-C");

        // scope to our own rows via the name filter so pre-seeded time periods cannot skew the
        // total. page 2 @ 1 item/page proves the OFFSET arithmetic (a page-1 request cannot).
        $result = $this->repository->findAll(
            (new TimePeriodCriteria())
                ->withName("pg-{$this->tag}-", TimePeriodCriteria::OPERATOR_LIKE)
                ->withPagination(2, 1)
        );

        self::assertInstanceOf(Paginator::class, $result);
        self::assertSame(3, $result->getTotalItems());
        // rows are ordered by tp_id (insertion order A, B, C), so page 2 is the second row
        self::assertSame(["pg-{$this->tag}-B"], $this->names($result));
    }

    public function testFindAllTrimsANameStoredWithSurroundingWhitespace(): void
    {
        $name = "pad-{$this->tag}";
        $this->insertTimePeriod("  {$name}  ");

        $names = $this->names($this->repository->findAll(
            (new TimePeriodCriteria())->withName($name, TimePeriodCriteria::OPERATOR_LIKE)
        ));

        // legacy TimePeriod::setName() trims, so the padded row must surface trimmed
        self::assertSame([$name], $names);
    }

    /**
     * @param \IteratorAggregate<int, TimePeriod>&\Countable $result
     *
     * @return list<string>
     */
    private function names(\IteratorAggregate&\Countable $result): array
    {
        return array_values(array_map(
            static fn (TimePeriod $timePeriod): string => $timePeriod->name->value,
            iterator_to_array($result)
        ));
    }

    private function insertTimePeriod(string $name): int
    {
        $this->connection->insert('timeperiod', ['tp_name' => $name, 'tp_alias' => $name]);

        return (int) $this->connection->lastInsertId();
    }
}
