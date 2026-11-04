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

use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalHostTemplateRepository;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalModuleRepository;
use App\MonitoringConfiguration\Infrastructure\Dbal\HostTemplateTransformer;
use App\Security\Domain\Service\AccessScopeResolver;
use App\Security\Infrastructure\Dbal\DbalAccessGroupRepository;
use App\Security\Infrastructure\Dbal\DbalResourceAccessRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Tests\App\MonitoringConfiguration\Infrastructure\InsertsHostTemplateInheritance;
use Webmozart\Assert\Assert;

/**
 * findDescendantIds(): the inheritance shapes, the loops already in the data, and its cost on deep
 * and wide trees.
 */
final class DbalHostTemplateRepositoryDescendantsTest extends KernelTestCase
{
    use InsertsHostTemplateInheritance;

    // about 200 times the cost of the query (under 5 ms), so a loaded runner cannot flake
    private const DEEP_CHAIN_MAX_SECONDS = 1.0;
    private const WIDE_TREE_MAX_SECONDS = 1.0;

    private Connection $connection;

    private DbalHostTemplateRepository $repository;

    private string $tag;

    protected function setUp(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        /** @var Connection $realTimeConnection */
        $realTimeConnection = self::getContainer()->get('doctrine.dbal.realtime_connection');

        $this->repository = new DbalHostTemplateRepository(
            $this->connection,
            new HostTemplateTransformer(),
            new AccessScopeResolver(new DbalResourceAccessRepository($this->connection, $realTimeConnection, new DbalAccessGroupRepository($this->connection))),
            new DbalModuleRepository($this->connection),
        );

        $this->tag = Uuid::v4()->toBase58();
    }

    public function testItReturnsTheChildrenAndTheirDescendantsButNotTheTemplateItself(): void
    {
        [$root, $child, $grandchild, $otherChild] = $this->templates(4);
        $this->link([$child, $root], [$grandchild, $child], [$otherChild, $root]);

        self::assertSame($this->sorted($child, $grandchild, $otherChild), $this->descendantsOf($root));
    }

    public function testItReturnsADescendantReachedThroughTwoPathsOnce(): void
    {
        [$root, $left, $right, $diamond, $belowDiamond] = $this->templates(5);
        $this->link([$left, $root], [$right, $root], [$diamond, $left], [$diamond, $right], [$belowDiamond, $diamond]);

        self::assertSame($this->sorted($left, $right, $diamond, $belowDiamond), $this->descendantsOf($root));
    }

    public function testItLeavesOutTheAncestorsAndTheUnrelatedTemplates(): void
    {
        [$grandparent, $parent, $template, $child, $sibling, $unrelated] = $this->templates(6);
        $this->link([$parent, $grandparent], [$template, $parent], [$child, $template], [$sibling, $parent]);

        self::assertSame([$child], $this->descendantsOf($template));
        self::assertNotContains($unrelated, $this->descendantsOf($grandparent));
    }

    public function testItWalksThroughAnInactiveTemplate(): void
    {
        [$root] = $this->templates(1);
        $inactive = $this->insertTemplate("inactive-{$this->tag}", active: false);
        [$belowInactive] = $this->templates(1, prefix: 'below');
        $this->link([$inactive, $root], [$belowInactive, $inactive]);

        self::assertSame($this->sorted($inactive, $belowInactive), $this->descendantsOf($root));
    }

    public function testItLeavesOutTheHostsInheritingTheTemplate(): void
    {
        [$root, $child] = $this->templates(2);
        $this->connection->insert('host', ['host_name' => "host-{$this->tag}", 'host_register' => '1', 'host_activate' => '1']);
        $hostId = (int) $this->connection->lastInsertId();
        $this->link([$child, $root], [$hostId, $root], [$hostId, $child]);

        self::assertSame([$child], $this->descendantsOf($root));
    }

    public function testItReturnsNothingForALeafOrAnUnknownTemplate(): void
    {
        [$leaf] = $this->templates(1);

        self::assertSame([], $this->descendantsOf($leaf));
        self::assertSame([], $this->descendantsOf(\PHP_INT_MAX));
    }

    /**
     * A loop is refused on save, but older data may hold one: the walk must still end.
     */
    public function testItTerminatesOnALoopBetweenTwoTemplates(): void
    {
        [$first, $second, $belowSecond] = $this->templates(3);
        $this->link([$first, $second], [$second, $first], [$belowSecond, $second]);

        self::assertSame($this->sorted($first, $second, $belowSecond), $this->descendantsOf($first));
    }

    public function testItTerminatesOnATemplateInheritingFromItself(): void
    {
        [$template, $child] = $this->templates(2);
        $this->link([$template, $template], [$child, $template]);

        self::assertSame($this->sorted($template, $child), $this->descendantsOf($template));
    }

    public function testItResolvesA200LevelChainInOneQuery(): void
    {
        $chain = $this->templates(201);
        $links = [];
        for ($level = 1; $level < 201; ++$level) {
            $links[] = [$chain[$level], $chain[$level - 1]];
        }
        $this->link(...$links);

        [$queries, $seconds, $descendants] = $this->measure($chain[0]);

        self::assertSame(1, $queries);
        self::assertLessThan(self::DEEP_CHAIN_MAX_SECONDS, $seconds);
        self::assertSame(array_slice($chain, 1), $descendants);
        // from the middle: the lower half only, the ancestors are not descendants
        self::assertSame(array_slice($chain, 101), $this->descendantsOf($chain[100]));
    }

    public function testItResolvesA2000TemplateTreeInOneQuery(): void
    {
        // a root, 40 children, 49 grandchildren each: 2000 descendants
        [$root] = $this->templates(1, prefix: 'root');
        $children = $this->templates(40, prefix: 'child');
        $grandchildren = $this->templates(40 * 49, prefix: 'grandchild');
        $links = array_map(static fn (int $child): array => [$child, $root], $children);
        foreach ($grandchildren as $index => $grandchild) {
            $links[] = [$grandchild, $children[intdiv($index, 49)]];
        }
        $this->link(...$links);

        [$queries, $seconds, $descendants] = $this->measure($root);

        self::assertSame(1, $queries);
        self::assertLessThan(self::WIDE_TREE_MAX_SECONDS, $seconds);
        self::assertSame($this->sorted(...$children, ...$grandchildren), $descendants);
    }

    /**
     * @return list<int>
     */
    private function descendantsOf(int $templateId): array
    {
        return array_values(array_map(
            static fn (HostTemplateId $id): int => $id->value,
            $this->repository->findDescendantIds(new HostTemplateId($templateId))->toArray(),
        ));
    }

    /**
     * Statements the call sends to the server (the session's "Questions" counter, net of the
     * counter's own reads), its wall-clock time, and its result.
     *
     * @return array{int, float, list<int>}
     */
    private function measure(int $templateId): array
    {
        $before = $this->sessionQuestions();
        $overhead = $this->sessionQuestions() - $before;

        $before = $this->sessionQuestions();
        $start = hrtime(true);
        $descendants = $this->descendantsOf($templateId);
        $seconds = (hrtime(true) - $start) / 1e9;
        $queries = $this->sessionQuestions() - $before - $overhead;

        return [$queries, $seconds, $descendants];
    }

    private function sessionQuestions(): int
    {
        $row = $this->connection->fetchAssociative("SHOW SESSION STATUS LIKE 'Questions'");
        Assert::isArray($row);
        Assert::numeric($row['Value']);

        return (int) $row['Value'];
    }

    /**
     * @return list<int>
     */
    private function sorted(int ...$ids): array
    {
        sort($ids);

        return $ids;
    }

    /**
     * @return list<int>
     */
    private function templates(int $count, string $prefix = 'tpl'): array
    {
        return $this->insertTemplates($this->connection, $count, "{$prefix}-{$this->tag}");
    }

    private function insertTemplate(string $name, bool $active): int
    {
        $this->connection->insert('host', ['host_name' => $name, 'host_register' => '0', 'host_activate' => $active ? '1' : '0', 'host_locked' => 0]);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * @param array{int, int} ...$links [child, parent] pairs
     */
    private function link(array ...$links): void
    {
        $this->linkTemplates($this->connection, ...$links);
    }
}
