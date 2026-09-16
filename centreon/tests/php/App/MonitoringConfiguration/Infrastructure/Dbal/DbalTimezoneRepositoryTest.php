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

use App\MonitoringConfiguration\Domain\Aggregate\Timezone\Timezone;
use App\MonitoringConfiguration\Domain\Repository\Criteria\TimezoneCriteria;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalTimezoneRepository;
use App\MonitoringConfiguration\Infrastructure\Dbal\TimezoneTransformer;
use App\Shared\Domain\Repository\Paginator;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DbalTimezoneRepositoryTest extends KernelTestCase
{
    private Connection $connection;

    private DbalTimezoneRepository $repository;

    private string $tag;

    protected function setUp(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        // The repository has no ApiPlatform consumer yet (that lands with the Provider), so the
        // container would prune it; construct it directly from the always-public DBAL connection.
        $this->repository = new DbalTimezoneRepository($this->connection, new TimezoneTransformer());

        // unique per test run so assertions are isolated from the pre-seeded IANA timezone rows
        $this->tag = Uuid::v4()->toRfc4122();
    }

    public function testFindAllReturnsAllTimezones(): void
    {
        $name = "Test/{$this->tag}";
        $this->insertTimezone($name);

        $names = $this->names($this->repository->findAll());

        self::assertContains($name, $names);
    }

    public function testFindAllFiltersByNameUsingLike(): void
    {
        $this->insertTimezone("Match/{$this->tag}");
        $this->insertTimezone("Other/{$this->tag}");

        $names = $this->names($this->repository->findAll((new TimezoneCriteria())->withName("Match/{$this->tag}")));

        self::assertSame(["Match/{$this->tag}"], $names);
    }

    public function testFindAllPaginatesAndReturnsATotalAcrossAllPages(): void
    {
        $this->insertTimezone("Pg/{$this->tag}-A");
        $this->insertTimezone("Pg/{$this->tag}-B");
        $this->insertTimezone("Pg/{$this->tag}-C");

        // scope to our own rows via the name filter so pre-seeded timezones cannot skew the total.
        // page 2 @ 1 item/page proves the OFFSET arithmetic (a page-1 request cannot).
        $result = $this->repository->findAll(
            (new TimezoneCriteria())->withName("Pg/{$this->tag}-")->withPagination(2, 1)
        );

        self::assertInstanceOf(Paginator::class, $result);
        self::assertSame(3, $result->getTotalItems());
        // rows are ordered by timezone_name (A, B, C), so page 2 is the second row
        self::assertSame(["Pg/{$this->tag}-B"], $this->names($result));
    }

    /**
     * @param \IteratorAggregate<int, Timezone>&\Countable $result
     *
     * @return list<string>
     */
    private function names(\IteratorAggregate&\Countable $result): array
    {
        return array_values(array_map(
            static fn (Timezone $timezone): string => $timezone->name->value,
            iterator_to_array($result)
        ));
    }

    private function insertTimezone(string $name): int
    {
        $this->connection->insert('timezone', [
            'timezone_name' => $name,
            'timezone_offset' => '+00:00',
            'timezone_dst_offset' => '+00:00',
            'timezone_description' => null,
        ]);

        return (int) $this->connection->lastInsertId();
    }
}
