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

namespace Tests\App\MonitoringConfiguration\Domain\Repository\Criteria;

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Repository\Criteria\HostChoiceCriteria;
use App\Security\Domain\Aggregate\AccessScope;
use App\Security\Domain\Aggregate\ActingUser;
use PHPUnit\Framework\TestCase;
use Tests\App\Security\Infrastructure\Double\TestActingUsers;

final class HostChoiceCriteriaTest extends TestCase
{
    // Pagination immutability is already covered generically by PaginableCriteriaTest, since
    // HostChoiceCriteria only wires in PaginableCriteriaTrait without overriding its behavior.

    public function testWithNameRejectsAnEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        HostChoiceCriteria::unscoped()->withName('');
    }

    public function testWithNameAcceptsTheLiteralZero(): void
    {
        // stringNotEmpty(), not notEmpty(): notEmpty() relies on empty(), which treats "0" as empty.
        self::assertSame('0', HostChoiceCriteria::unscoped()->withName('0')->getName());
    }

    public function testWithNameReturnsACopyKeepingTheActor(): void
    {
        $criteria = HostChoiceCriteria::forActor(TestActingUsers::restricted(7));

        $withName = $criteria->withName('Central-Server');

        self::assertNotSame($criteria, $withName);
        self::assertNull($criteria->getName());
        self::assertSame('Central-Server', $withName->getName());
        self::assertSame([7], $withName->accessScope($this->scopeOf(...), HostId::class)->allowedIdValues());
    }

    public function testForActorScopesToWhatTheActorMayAccess(): void
    {
        $scope = HostChoiceCriteria::forActor(TestActingUsers::restricted(7))->accessScope($this->scopeOf(...), HostId::class);

        self::assertSame([7], $scope->allowedIdValues());
    }

    public function testAnUnscopedCriteriaIsUnrestricted(): void
    {
        $scope = HostChoiceCriteria::unscoped()->accessScope(
            static fn (ActingUser $actor): never => self::fail('An unscoped criteria must not resolve any scope.'),
            HostId::class,
        );

        self::assertTrue($scope->isUnrestricted());
    }

    public function testACriteriaCannotBeBuiltWithoutChoosingItsScope(): void
    {
        // Fail closed by construction: only forActor() and unscoped() build a criteria.
        self::assertTrue((new \ReflectionMethod(HostChoiceCriteria::class, '__construct'))->isPrivate());
    }

    /**
     * @return AccessScope<HostId>
     */
    private function scopeOf(ActingUser $actor): AccessScope
    {
        return AccessScope::restrictedTo(HostId::class, [new HostId($actor->userId->value)]);
    }
}
