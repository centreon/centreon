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

use App\MonitoringConfiguration\Domain\Repository\Criteria\ServiceCriteria;
use App\Security\Domain\Aggregate\AccessGroupId;
use App\Security\Domain\Aggregate\AccessScope;
use App\Security\Domain\Aggregate\ActingUser;
use App\Shared\Domain\Repository\SortDirectionEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\App\Security\Infrastructure\Double\TestActingUsers;

/**
 * Pagination comes from the shared PaginableCriteriaTrait and the sort directions from SortableCriteriaTrait,
 * both already pinned by the other criteria tests: only what this criteria adds is tested here.
 */
final class ServiceCriteriaTest extends TestCase
{
    public function testNoFilterIsSetByDefault(): void
    {
        $criteria = ServiceCriteria::unscoped();

        self::assertNull($criteria->getHostName());
        self::assertNull($criteria->getName());
        self::assertNull($criteria->getTemplateId());
        self::assertNull($criteria->getActivated());
        self::assertNull($criteria->getHostActivated());
        self::assertSame([], $criteria->getSort());
    }

    public function testFiltersAreSetOnACopyAndLeaveTheOriginalUnchanged(): void
    {
        $criteria = ServiceCriteria::unscoped();

        $new = $criteria
            ->withHostName('central')
            ->withName('Ping')
            ->withTemplateId(12)
            ->withActivated(false)
            ->withHostActivated(true);

        self::assertNull($criteria->getHostName());
        self::assertNull($criteria->getName());
        self::assertNull($criteria->getTemplateId());
        self::assertNull($criteria->getActivated());
        self::assertNull($criteria->getHostActivated());

        self::assertSame('central', $new->getHostName());
        self::assertSame('Ping', $new->getName());
        self::assertSame(12, $new->getTemplateId());
        self::assertFalse($new->getActivated());
        self::assertTrue($new->getHostActivated());
    }

    public function testNameFiltersAcceptAZeroName(): void
    {
        // "0" is a legitimate name: the guard must be stringNotEmpty, not notEmpty (which uses empty()).
        $criteria = ServiceCriteria::unscoped()->withHostName('0')->withName('0');

        self::assertSame('0', $criteria->getHostName());
        self::assertSame('0', $criteria->getName());
    }

    public function testTheHostNameFilterRejectsAnEmptyValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ServiceCriteria::unscoped()->withHostName('');
    }

    public function testTheNameFilterRejectsAnEmptyValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ServiceCriteria::unscoped()->withName('');
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function provideNonPositiveIds(): iterable
    {
        yield 'zero' => [0];

        yield 'negative' => [-1];
    }

    #[DataProvider('provideNonPositiveIds')]
    public function testTheTemplateFilterRejectsANonPositiveId(int $templateId): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ServiceCriteria::unscoped()->withTemplateId($templateId);
    }

    public function testOnlyTheHostNameAndTheServiceNameCanBeSortedOn(): void
    {
        self::assertSame(
            ['host_name' => 'h.host_name', 'name' => 's.service_description'],
            ServiceCriteria::unscoped()->getFieldMapping(),
        );
    }

    public function testForActorScopesToWhatTheActorMayAccess(): void
    {
        $scope = ServiceCriteria::forActor(TestActingUsers::restricted(7))->accessScope($this->scopeOf(...), AccessGroupId::class);

        self::assertSame([7], $scope->allowedIdValues());
    }

    public function testWithersKeepTheActor(): void
    {
        $criteria = ServiceCriteria::forActor(TestActingUsers::restricted(7))
            ->withHostName('central')
            ->withName('Ping')
            ->withTemplateId(12)
            ->withActivated(true)
            ->withHostActivated(true)
            ->withSort('name', SortDirectionEnum::DESC);

        self::assertSame([7], $criteria->accessScope($this->scopeOf(...), AccessGroupId::class)->allowedIdValues());
    }

    public function testAnUnscopedCriteriaIsUnrestricted(): void
    {
        $scope = ServiceCriteria::unscoped()->accessScope(
            static fn (ActingUser $actor): never => self::fail('An unscoped criteria must not resolve any scope.'),
            AccessGroupId::class,
        );

        self::assertTrue($scope->isUnrestricted());
    }

    public function testACriteriaCannotBeBuiltWithoutChoosingItsScope(): void
    {
        // Fail closed by construction: only forActor() and unscoped() build a criteria.
        self::assertTrue((new \ReflectionMethod(ServiceCriteria::class, '__construct'))->isPrivate());
    }

    /**
     * @return AccessScope<AccessGroupId>
     */
    private function scopeOf(ActingUser $actor): AccessScope
    {
        return AccessScope::restrictedTo(AccessGroupId::class, [new AccessGroupId($actor->userId->value)]);
    }
}
