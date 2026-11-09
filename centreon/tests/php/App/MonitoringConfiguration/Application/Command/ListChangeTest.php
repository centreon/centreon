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

namespace Tests\App\MonitoringConfiguration\Application\Command;

use App\MonitoringConfiguration\Application\Command\ListChange;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use PHPUnit\Framework\TestCase;

final class ListChangeTest extends TestCase
{
    public function testReplaceGivesTheListItDescribesInItsOrder(): void
    {
        self::assertSame([3, 1], ListChange::replace([3, 1])->applyTo([1, 2]));
    }

    public function testReplaceWithAnEmptyListClears(): void
    {
        self::assertSame([], ListChange::replace([])->applyTo([1, 2]));
    }

    public function testAddPutsTheNewValuesAfterTheCurrentOnes(): void
    {
        self::assertSame([1, 2, 5, 4], ListChange::add([5, 4])->applyTo([1, 2]));
    }

    public function testAddNeverRepeatsAValue(): void
    {
        self::assertSame([1, 2, 3], ListChange::add([2, 3, 3])->applyTo([1, 2]));
    }

    public function testRemoveDropsTheGivenValuesAndKeepsTheOrderOfTheRest(): void
    {
        self::assertSame([1, 3], ListChange::remove([2, 9])->applyTo([1, 2, 3]));
    }

    public function testReplaceRepeatedValuesAreKeptOnce(): void
    {
        self::assertSame([2, 1], ListChange::replace([2, 1, 2])->applyTo([]));
    }

    public function testValuesAreComparedThroughTheGivenIdentity(): void
    {
        $identity = static fn (HostGroupId $id): int => $id->value;

        $result = ListChange::add([new HostGroupId(2), new HostGroupId(3)])->applyTo([new HostGroupId(1), new HostGroupId(2)], $identity);

        self::assertSame([1, 2, 3], array_map($identity, $result));
    }

    public function testReplaceKeepsTheValuesTheRequesterCannotSee(): void
    {
        $isVisible = static fn (int $value): bool => $value < 10;

        self::assertSame([3, 20], ListChange::replace([3])->applyTo([1, 20], null, $isVisible));
    }

    public function testReplaceDoesNotRepeatAHiddenValueItAlsoGives(): void
    {
        $isVisible = static fn (int $value): bool => $value < 10;

        self::assertSame([20, 3], ListChange::replace([20, 3])->applyTo([1, 20], null, $isVisible));
    }

    public function testRemoveLeavesTheValuesTheRequesterCannotSee(): void
    {
        $isVisible = static fn (int $value): bool => $value < 10;

        self::assertSame([20], ListChange::remove([1, 20])->applyTo([1, 20], null, $isVisible));
    }
}
