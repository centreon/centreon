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

use App\MonitoringConfiguration\Domain\Aggregate\GraphTemplate\GraphTemplate;
use App\MonitoringConfiguration\Domain\Repository\Criteria\GraphTemplateCriteria;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalGraphTemplateRepository;
use App\MonitoringConfiguration\Infrastructure\Dbal\GraphTemplateTransformer;
use App\Shared\Domain\Repository\Paginator;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DbalGraphTemplateRepositoryTest extends KernelTestCase
{
    private Connection $connection;

    private DbalGraphTemplateRepository $repository;

    private string $tag;

    protected function setUp(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        // constructed directly from the always-public DBAL connection, as the sibling Dbal tests do
        $this->repository = new DbalGraphTemplateRepository($this->connection, new GraphTemplateTransformer());

        // unique per test run so assertions are isolated from any pre-seeded graph templates
        $this->tag = Uuid::v4()->toRfc4122();
    }

    public function testItSkipsRowsWithoutAName(): void
    {
        $validId = $this->insertGraphTemplate("graph-{$this->tag}");
        $nullNameId = $this->insertGraphTemplate(null);
        $emptyNameId = $this->insertGraphTemplate('');

        // no name filter: a LIKE filter would exclude NULL names by itself and hide a missing guard
        $ids = $this->ids($this->repository->findAll());

        self::assertContains($validId, $ids);
        self::assertNotContains($nullNameId, $ids, 'A graph template with a NULL name is skipped.');
        self::assertNotContains($emptyNameId, $ids, 'A graph template with an empty name is skipped.');
    }

    public function testItOrdersById(): void
    {
        // inserted in reverse alphabetical order: an order by name would list them the other way round
        $this->insertGraphTemplate("b-{$this->tag}");
        $this->insertGraphTemplate("a-{$this->tag}");

        $names = $this->names($this->repository->findAll((new GraphTemplateCriteria())->withName($this->tag)));

        self::assertSame(["b-{$this->tag}", "a-{$this->tag}"], $names);
    }

    public function testItFiltersByNameLike(): void
    {
        $this->insertGraphTemplate("prefix-match-{$this->tag}-suffix");
        $this->insertGraphTemplate("other-{$this->tag}");

        $names = $this->names($this->repository->findAll((new GraphTemplateCriteria())->withName("match-{$this->tag}")));

        self::assertSame(["prefix-match-{$this->tag}-suffix"], $names);
    }

    public function testItPaginatesAndCountsAcrossPages(): void
    {
        $this->insertGraphTemplate("pg-{$this->tag}-A");
        $this->insertGraphTemplate("pg-{$this->tag}-B");
        $this->insertGraphTemplate("pg-{$this->tag}-C");

        // page 2 @ 1 item/page proves the OFFSET arithmetic (a page-1 request cannot)
        $result = $this->repository->findAll(
            (new GraphTemplateCriteria())->withName("pg-{$this->tag}-")->withPagination(2, 1)
        );

        self::assertInstanceOf(Paginator::class, $result);
        self::assertSame(3, $result->getTotalItems());
        self::assertSame(["pg-{$this->tag}-B"], $this->names($result));
    }

    /**
     * @param \IteratorAggregate<int, GraphTemplate>&\Countable $result
     *
     * @return list<string>
     */
    private function names(\IteratorAggregate&\Countable $result): array
    {
        return array_values(array_map(
            static fn (GraphTemplate $graphTemplate): string => $graphTemplate->name->value,
            iterator_to_array($result)
        ));
    }

    /**
     * @param \IteratorAggregate<int, GraphTemplate>&\Countable $result
     *
     * @return list<int>
     */
    private function ids(\IteratorAggregate&\Countable $result): array
    {
        return array_values(array_map(
            static fn (GraphTemplate $graphTemplate): int => $graphTemplate->id()->value,
            iterator_to_array($result)
        ));
    }

    private function insertGraphTemplate(?string $name): int
    {
        $this->connection->insert('giv_graphs_template', ['name' => $name]);

        return (int) $this->connection->lastInsertId();
    }
}
