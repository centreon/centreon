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

use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Repository\Criteria\ContactGroupCriteria;
use App\Security\Domain\Aggregate\AccessScope;
use App\Security\Domain\Aggregate\ActingUser;
use PHPUnit\Framework\TestCase;
use Tests\App\Security\Infrastructure\Double\TestActingUsers;

final class ContactGroupCriteriaTest extends TestCase
{
    public function testWithNameLeavesTheOriginalUnchanged(): void
    {
        $criteria = new ContactGroupCriteria();

        $new = $criteria->withName('Supervisors');

        self::assertSame([], $criteria->getNames());
        self::assertSame(['Supervisors'], $new->getNames());
    }

    public function testWithPaginationLeavesTheOriginalUnchanged(): void
    {
        $criteria = new ContactGroupCriteria();

        $new = $criteria->withPagination(2, 10);

        self::assertNull($criteria->getPagination());
        $pagination = $new->getPagination();
        self::assertNotNull($pagination);
        self::assertSame(2, $pagination->page);
        self::assertSame(10, $pagination->itemsPerPage);
    }

    public function testWithActorScopesToWhatTheActorMayAccess(): void
    {
        $scope = (new ContactGroupCriteria())->withActor(TestActingUsers::restricted(7))->accessScope($this->scopeOf(...), ContactGroupId::class);

        self::assertSame([7], $scope->allowedIdValues());
    }

    public function testACriteriaNeitherScopedNorOptedOutFailsClosed(): void
    {
        $criteria = new ContactGroupCriteria();
        $criteria->withActor(TestActingUsers::restricted(7));

        $this->expectException(\LogicException::class);

        $criteria->accessScope($this->scopeOf(...), ContactGroupId::class);
    }

    public function testACriteriaWithoutAccessScopeIsUnrestricted(): void
    {
        $scope = (new ContactGroupCriteria())->withoutAccessScope()->accessScope(
            static fn (ActingUser $actor): never => self::fail('An opted-out criteria must not resolve any scope.'),
            ContactGroupId::class,
        );

        self::assertTrue($scope->isUnrestricted());
    }

    public function testWithActorScopesAnOptedOutCriteriaAgain(): void
    {
        $scope = (new ContactGroupCriteria())->withoutAccessScope()->withActor(TestActingUsers::restricted(7))->accessScope($this->scopeOf(...), ContactGroupId::class);

        self::assertFalse($scope->isUnrestricted());
    }

    public function testWithNameDeduplicates(): void
    {
        $criteria = (new ContactGroupCriteria())
            ->withName('Supervisors')
            ->withName('Supervisors');

        self::assertSame(['Supervisors'], $criteria->getNames());
    }

    public function testWithNameAcceptsAZeroName(): void
    {
        // "0" is a legitimate name: the guard must be stringNotEmpty, not notEmpty (which uses empty()).
        self::assertSame(['0'], (new ContactGroupCriteria())->withName('0')->getNames());
    }

    public function testWithNameRejectsAnEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ContactGroupCriteria())->withName('');
    }

    public function testWithPaginationRejectsANonPositivePage(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ContactGroupCriteria())->withPagination(0, 10);
    }

    public function testWithPaginationRejectsANonPositiveItemsPerPage(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ContactGroupCriteria())->withPagination(1, 0);
    }

    /**
     * @return AccessScope<ContactGroupId>
     */
    private function scopeOf(ActingUser $actor): AccessScope
    {
        return AccessScope::restrictedTo(ContactGroupId::class, [new ContactGroupId($actor->userId->value)]);
    }
}
