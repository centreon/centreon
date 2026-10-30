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

use App\MonitoringConfiguration\Domain\Aggregate\ServiceGroup\ServiceGroupId;
use App\MonitoringConfiguration\Domain\Repository\Criteria\ServiceGroupCriteria;
use App\Security\Domain\Aggregate\AccessScope;
use App\Security\Domain\Aggregate\ActingUser;
use PHPUnit\Framework\TestCase;
use Tests\App\Security\Infrastructure\Double\TestActingUsers;

final class ServiceGroupCriteriaTest extends TestCase
{
    // Pagination immutability is already covered generically by PaginableCriteriaTest, since
    // ServiceGroupCriteria only wires in PaginableCriteriaTrait without overriding its behavior.

    public function testWithNameRejectsAnEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ServiceGroupCriteria::unscoped()->withName('');
    }

    public function testWithNameAcceptsTheLiteralZero(): void
    {
        // stringNotEmpty(), not notEmpty(): notEmpty() relies on empty(), which treats "0" as empty.
        self::assertSame('0', ServiceGroupCriteria::unscoped()->withName('0')->getName());
    }

    public function testWithNameReturnsACopyKeepingTheActor(): void
    {
        $criteria = ServiceGroupCriteria::forActor(TestActingUsers::restricted(7));

        $withName = $criteria->withName('Web-Servers');

        self::assertNotSame($criteria, $withName);
        self::assertNull($criteria->getName());
        self::assertSame('Web-Servers', $withName->getName());
        self::assertSame([7], $withName->accessScope($this->scopeOf(...), ServiceGroupId::class)->allowedIdValues());
    }

    public function testForActorScopesToWhatTheActorMayAccess(): void
    {
        $scope = ServiceGroupCriteria::forActor(TestActingUsers::restricted(7))->accessScope($this->scopeOf(...), ServiceGroupId::class);

        self::assertSame([7], $scope->allowedIdValues());
    }

    public function testAnUnscopedCriteriaIsUnrestricted(): void
    {
        $scope = ServiceGroupCriteria::unscoped()->accessScope(
            static fn (ActingUser $actor): never => self::fail('An unscoped criteria must not resolve any scope.'),
            ServiceGroupId::class,
        );

        self::assertTrue($scope->isUnrestricted());
    }

    public function testACriteriaCannotBeBuiltWithoutChoosingItsScope(): void
    {
        // Fail closed by construction: only forActor() and unscoped() build a criteria.
        self::assertTrue((new \ReflectionMethod(ServiceGroupCriteria::class, '__construct'))->isPrivate());
    }

    /**
     * @return AccessScope<ServiceGroupId>
     */
    private function scopeOf(ActingUser $actor): AccessScope
    {
        return AccessScope::restrictedTo(ServiceGroupId::class, [new ServiceGroupId($actor->userId->value)]);
    }
}
