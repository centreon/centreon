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

namespace Tests\App\MonitoringConfiguration\Domain\Service;

use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Exception\CircularHostTemplateInheritanceException;
use App\MonitoringConfiguration\Domain\Service\HostTemplateInheritance;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HostTemplateInheritanceTest extends TestCase
{
    /**
     * @param list<int> $parentIds
     * @param array<int, list<int>> $edges
     * @param list<int> $offendingIds
     */
    #[DataProvider('provideLoops')]
    public function testALoopIsRefusedWithItsOffendingParents(array $parentIds, array $edges, array $offendingIds): void
    {
        try {
            new HostTemplateInheritance()->assertNoLoop(new HostTemplateId(1), $this->ids($parentIds), $edges);
            self::fail('The loop must be refused.');
        } catch (CircularHostTemplateInheritanceException $exception) {
            self::assertSame(['templateIds' => $offendingIds], $exception->criteria);
        }
    }

    /**
     * @return iterable<string, array{list<int>, array<int, list<int>>, list<int>}>
     */
    public static function provideLoops(): iterable
    {
        yield 'itself' => [[1], [], [1]];

        yield 'a child' => [[2], [2 => [1]], [2]];

        yield 'a grandchild among other parents' => [[5, 3, 6], [2 => [1], 3 => [2]], [3]];

        yield 'a loop already stored through the template, its back edge kept' => [[2], [1 => [2], 2 => [1]], [2]];
    }

    /**
     * @param list<int> $parentIds
     * @param array<int, list<int>> $edges
     */
    #[DataProvider('provideAcceptedParents')]
    public function testParentsOutsideTheDescendantsAreAccepted(array $parentIds, array $edges): void
    {
        new HostTemplateInheritance()->assertNoLoop(new HostTemplateId(1), $this->ids($parentIds), $edges);

        $this->addToAssertionCount(1);
    }

    /**
     * @return iterable<string, array{list<int>, array<int, list<int>>}>
     */
    public static function provideAcceptedParents(): iterable
    {
        yield 'no parent' => [[], [2 => [1]]];

        yield 'a sibling' => [[3], [2 => [1], 3 => [4]]];

        yield 'a loop stored elsewhere' => [[2], [3 => [4], 4 => [3], 5 => [1]]];

        yield 'a loop stored through the template, its back edge dropped' => [[3], [1 => [2], 2 => [1]]];
    }

    public function testA2000LevelDescendantChainIsWalkedToItsLeaf(): void
    {
        $edges = [];
        for ($id = 2; $id <= 2001; ++$id) {
            $edges[$id] = [$id - 1];
        }

        $this->expectException(CircularHostTemplateInheritanceException::class);

        new HostTemplateInheritance()->assertNoLoop(new HostTemplateId(1), $this->ids([2001]), $edges);
    }

    public function testA5000ChildTemplateTakesAnUnrelatedParent(): void
    {
        $edges = [];
        for ($id = 2; $id <= 5001; ++$id) {
            $edges[$id] = [1];
        }

        new HostTemplateInheritance()->assertNoLoop(new HostTemplateId(1), $this->ids([9999]), $edges);

        $this->addToAssertionCount(1);
    }

    /**
     * @param list<int> $values
     *
     * @return Collection<HostTemplateId>
     */
    private function ids(array $values): Collection
    {
        return new Collection(array_map(static fn (int $value): HostTemplateId => new HostTemplateId($value), $values), HostTemplateId::class);
    }
}
