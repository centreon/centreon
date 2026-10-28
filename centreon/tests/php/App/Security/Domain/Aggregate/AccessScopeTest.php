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

namespace Tests\App\Security\Domain\Aggregate;

use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\Security\Domain\Aggregate\AccessScope;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;

final class AccessScopeTest extends TestCase
{
    public function testAnUnrestrictedScopeAllowsAnyId(): void
    {
        $scope = AccessScope::unrestricted(HostGroupId::class);
        $ids = new Collection([new HostGroupId(1), new HostGroupId(2)], HostGroupId::class);

        self::assertTrue($scope->isUnrestricted());
        self::assertTrue($scope->allows(new HostGroupId(42)));
        self::assertSame($ids, $scope->filter($ids));
    }

    public function testARestrictedScopeOnlyAllowsItsIds(): void
    {
        $scope = AccessScope::restrictedTo(HostGroupId::class, [new HostGroupId(2), new HostGroupId(3), new HostGroupId(2)]);

        self::assertFalse($scope->isUnrestricted());
        self::assertTrue($scope->allows(new HostGroupId(2)));
        self::assertFalse($scope->allows(new HostGroupId(1)));
        self::assertSame([2, 3], $scope->allowedIdValues());
        self::assertEquals(
            [new HostGroupId(2)],
            $scope->filter(new Collection([new HostGroupId(1), new HostGroupId(2)], HostGroupId::class))->toArray(),
        );
    }

    public function testAScopeRestrictedToNothingAllowsNothing(): void
    {
        $scope = AccessScope::restrictedTo(HostGroupId::class, []);

        self::assertFalse($scope->isUnrestricted());
        self::assertFalse($scope->allows(new HostGroupId(1)));
        self::assertSame([], $scope->allowedIdValues());
    }

    public function testOnlyAScopeRestrictedToNothingAllowsNothing(): void
    {
        self::assertTrue(AccessScope::restrictedTo(HostGroupId::class, [])->allowsNothing());
        self::assertFalse(AccessScope::restrictedTo(HostGroupId::class, [new HostGroupId(1)])->allowsNothing());
        self::assertFalse(AccessScope::unrestricted(HostGroupId::class)->allowsNothing());
    }

    public function testTheMissingIdsAreTheUnknownAndTheOutOfScopeOnes(): void
    {
        $scope = AccessScope::restrictedTo(HostGroupId::class, [new HostGroupId(1), new HostGroupId(2)]);
        $requested = [new HostGroupId(1), new HostGroupId(2), new HostGroupId(3), new HostGroupId(2)];

        // 1 exists and is in scope; 2 is in scope but unknown; 3 exists but is out of scope.
        self::assertSame([2, 3], $scope->missing($requested, ['1', 3]));
        self::assertSame([2], AccessScope::unrestricted(HostGroupId::class)->missing($requested, [1, 3]));
    }

    public function testARestrictedScopeOnlyTakesIdsOfItsClass(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        AccessScope::restrictedTo(HostGroupId::class, [new HostCategoryId(1)]);
    }

    public function testAnUnrestrictedScopeHasNoListOfAllowedIds(): void
    {
        $this->expectException(\LogicException::class);

        AccessScope::unrestricted(HostGroupId::class)->allowedIdValues();
    }

    public function testAPartialScopeAnswersForTheIdsItWasLookedUpAmong(): void
    {
        $scope = AccessScope::partiallyRestrictedTo(HostGroupId::class, [new HostGroupId(1)]);

        self::assertFalse($scope->isUnrestricted());
        self::assertTrue($scope->allows(new HostGroupId(1)));
        self::assertFalse($scope->allows(new HostGroupId(2)));
        self::assertSame([2], $scope->missing([new HostGroupId(1), new HostGroupId(2)], [1, 2]));
        self::assertEquals(
            new Collection([new HostGroupId(1)], HostGroupId::class),
            $scope->filter(new Collection([new HostGroupId(1), new HostGroupId(2)], HostGroupId::class)),
        );
    }

    public function testAPartialScopeHasNoListOfAllowedIds(): void
    {
        $this->expectException(\LogicException::class);

        AccessScope::partiallyRestrictedTo(HostGroupId::class, [new HostGroupId(1)])->allowedIdValues();
    }

    public function testAPartialScopeCannotTellWhetherItAllowsNothing(): void
    {
        $this->expectException(\LogicException::class);

        AccessScope::partiallyRestrictedTo(HostGroupId::class, [])->allowsNothing();
    }
}
