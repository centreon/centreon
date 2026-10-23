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

use App\MonitoringConfiguration\Domain\Aggregate\Trap\Trap;
use App\MonitoringConfiguration\Domain\Repository\Criteria\TrapCriteria;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalTrapRepository;
use App\MonitoringConfiguration\Infrastructure\Dbal\TrapTransformer;
use App\Shared\Domain\Repository\Paginator;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DbalTrapRepositoryTest extends KernelTestCase
{
    private Connection $connection;

    private DbalTrapRepository $repository;

    private string $tag;

    protected function setUp(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        // constructed directly from the always-public DBAL connection, as the sibling Dbal tests do
        $this->repository = new DbalTrapRepository($this->connection, new TrapTransformer());

        // unique per test run so assertions are isolated from any pre-seeded traps
        $this->tag = Uuid::v4()->toRfc4122();
    }

    public function testItMapsTheTrapWithItsVendor(): void
    {
        $vendorId = $this->insertVendor("vendor-{$this->tag}");
        $trapId = $this->insertTrap("trap-{$this->tag}", $vendorId);

        $traps = iterator_to_array($this->repository->findAll((new TrapCriteria())->withName($this->tag)));

        self::assertCount(1, $traps);
        $trap = array_values($traps)[0];
        self::assertSame($trapId, $trap->id()->value);
        self::assertSame("trap-{$this->tag}", $trap->name->value);
        self::assertSame($vendorId, $trap->vendor->id->value);
        self::assertSame("vendor-{$this->tag}", $trap->vendor->name->value);
    }

    public function testItExcludesTrapsWithoutVendor(): void
    {
        $vendorId = $this->insertVendor("vendor-{$this->tag}");
        $this->insertTrap("with-vendor-{$this->tag}", $vendorId);
        $this->insertTrap("without-vendor-{$this->tag}", null);

        $names = $this->names($this->repository->findAll((new TrapCriteria())->withName($this->tag)));

        self::assertSame(["with-vendor-{$this->tag}"], $names);
    }

    public function testItSkipsRowsWithoutAName(): void
    {
        $vendorId = $this->insertVendor("vendor-{$this->tag}");
        $validId = $this->insertTrap("trap-{$this->tag}", $vendorId);
        $nullNameId = $this->insertTrap(null, $vendorId);
        $emptyNameId = $this->insertTrap('', $vendorId);
        $nullVendorNameId = $this->insertTrap("trap-null-vendor-{$this->tag}", $this->insertVendor(null));
        $emptyVendorNameId = $this->insertTrap("trap-empty-vendor-{$this->tag}", $this->insertVendor(''));

        // no name filter: a LIKE filter would exclude NULL names by itself and hide a missing guard
        $ids = $this->ids($this->repository->findAll());

        self::assertContains($validId, $ids);
        self::assertNotContains($nullNameId, $ids, 'A trap with a NULL name is skipped.');
        self::assertNotContains($emptyNameId, $ids, 'A trap with an empty name is skipped.');
        self::assertNotContains($nullVendorNameId, $ids, 'A trap whose vendor has a NULL name is skipped.');
        self::assertNotContains($emptyVendorNameId, $ids, 'A trap whose vendor has an empty name is skipped.');
    }

    public function testItOrdersById(): void
    {
        $vendorId = $this->insertVendor("vendor-{$this->tag}");
        // inserted in reverse alphabetical order: an order by name would list them the other way round
        $this->insertTrap("b-{$this->tag}", $vendorId);
        $this->insertTrap("a-{$this->tag}", $vendorId);

        $names = $this->names($this->repository->findAll((new TrapCriteria())->withName($this->tag)));

        self::assertSame(["b-{$this->tag}", "a-{$this->tag}"], $names);
    }

    public function testItFiltersByNameLike(): void
    {
        $vendorId = $this->insertVendor("vendor-{$this->tag}");
        $this->insertTrap("prefix-match-{$this->tag}-suffix", $vendorId);
        $this->insertTrap("other-{$this->tag}", $vendorId);

        $names = $this->names($this->repository->findAll((new TrapCriteria())->withName("match-{$this->tag}")));

        self::assertSame(["prefix-match-{$this->tag}-suffix"], $names);
    }

    public function testTheNameFilterIgnoresTheVendor(): void
    {
        $matchingVendorId = $this->insertVendor("vendor-match-{$this->tag}");
        $this->insertTrap("trap-{$this->tag}", $matchingVendorId);
        $this->insertTrap("trap-match-{$this->tag}", $this->insertVendor("vendor-{$this->tag}"));

        $names = $this->names($this->repository->findAll((new TrapCriteria())->withName("match-{$this->tag}")));

        self::assertSame(["trap-match-{$this->tag}"], $names, 'Only the trap name is searched, not the vendor name.');
    }

    public function testItPaginatesAndCountsAcrossPages(): void
    {
        $vendorId = $this->insertVendor("vendor-{$this->tag}");
        $this->insertTrap("pg-{$this->tag}-A", $vendorId);
        $this->insertTrap("pg-{$this->tag}-B", $vendorId);
        $this->insertTrap("pg-{$this->tag}-C", $vendorId);

        // page 2 @ 1 item/page proves the OFFSET arithmetic (a page-1 request cannot)
        $result = $this->repository->findAll(
            (new TrapCriteria())->withName("pg-{$this->tag}-")->withPagination(2, 1)
        );

        self::assertInstanceOf(Paginator::class, $result);
        self::assertSame(3, $result->getTotalItems());
        self::assertSame(["pg-{$this->tag}-B"], $this->names($result));
    }

    /**
     * @param \IteratorAggregate<int, Trap>&\Countable $result
     *
     * @return list<string>
     */
    private function names(\IteratorAggregate&\Countable $result): array
    {
        return array_values(array_map(
            static fn (Trap $trap): string => $trap->name->value,
            iterator_to_array($result)
        ));
    }

    /**
     * @param \IteratorAggregate<int, Trap>&\Countable $result
     *
     * @return list<int>
     */
    private function ids(\IteratorAggregate&\Countable $result): array
    {
        return array_values(array_map(
            static fn (Trap $trap): int => $trap->id()->value,
            iterator_to_array($result)
        ));
    }

    private function insertVendor(?string $name): int
    {
        $this->connection->insert('traps_vendor', ['name' => $name, 'alias' => $name]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertTrap(?string $name, ?int $vendorId): int
    {
        // traps has a UNIQUE (traps_name, traps_oid): a unique oid keeps the fixtures independent
        $this->connection->insert('traps', [
            'traps_name' => $name,
            'traps_oid' => '.1.3.6.1.4.1.' . random_int(1, PHP_INT_MAX),
            'manufacturer_id' => $vendorId,
        ]);

        return (int) $this->connection->lastInsertId();
    }
}
