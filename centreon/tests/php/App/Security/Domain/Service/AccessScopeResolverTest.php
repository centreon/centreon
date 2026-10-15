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

namespace Tests\App\Security\Domain\Service;

use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaDirectoryId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\Security\Domain\Aggregate\AccessScope;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Security\Domain\Service\AccessScopeResolver;
use App\Shared\Domain\Aggregate\AggregateRootId;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\App\Security\Infrastructure\Double\TestActingUsers;

final class AccessScopeResolverTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, class-string<AggregateRootId>}>
     */
    public static function provideScopes(): iterable
    {
        yield 'host categories' => ['hostCategories', 'findHostCategoryAccessScope', HostCategoryId::class];

        yield 'host severities' => ['hostSeverities', 'findHostSeverityAccessScope', HostSeverityId::class];

        yield 'pollers' => ['pollers', 'findPollerAccessScope', PollerId::class];

        yield 'host groups' => ['hostGroups', 'findHostGroupAccessScope', HostGroupId::class];

        yield 'image folders' => ['imageFolders', 'findImageFolderAccessScope', MediaDirectoryId::class];

        yield 'contacts' => ['contacts', 'findContactAccessScope', NotificationContactId::class];

        yield 'contact groups' => ['contactGroups', 'findContactGroupAccessScope', ContactGroupId::class];

        yield 'hosts' => ['hosts', 'findHostAccessScope', HostId::class];
    }

    /**
     * @param class-string<AggregateRootId> $idClass
     */
    #[DataProvider('provideScopes')]
    public function testAnUnrestrictedActorGetsAnUnrestrictedScopeWithoutAnyLookup(string $method, string $lookup, string $idClass): void
    {
        $repository = $this->createMock(ResourceAccessRepository::class);
        $repository->expects(self::never())->method($lookup);

        $scope = new AccessScopeResolver($repository)->{$method}(TestActingUsers::unrestricted());
        self::assertInstanceOf(AccessScope::class, $scope);

        self::assertTrue($scope->isUnrestricted());
        self::assertTrue($scope->allows(new $idClass(42)));
    }

    /**
     * @param class-string<AggregateRootId> $idClass
     */
    #[DataProvider('provideScopes')]
    public function testARestrictedActorGetsTheScopeOfTheirAccessGroups(string $method, string $lookup, string $idClass): void
    {
        $expected = AccessScope::restrictedTo($idClass, [new $idClass(2)]);
        $repository = $this->createMock(ResourceAccessRepository::class);
        $repository->expects(self::once())->method($lookup)->with(new UserId(7))->willReturn($expected);

        self::assertSame($expected, new AccessScopeResolver($repository)->{$method}(TestActingUsers::restricted(7)));
    }

    public function testTheHostLookupIsNarrowedToTheGivenHosts(): void
    {
        $among = new Collection([new HostId(2), new HostId(3)], HostId::class);
        $expected = AccessScope::restrictedTo(HostId::class, [new HostId(2)]);
        $repository = $this->createMock(ResourceAccessRepository::class);
        $repository->expects(self::once())->method('findHostAccessScope')->with(new UserId(7), $among)->willReturn($expected);

        self::assertSame($expected, new AccessScopeResolver($repository)->hosts(TestActingUsers::restricted(7), $among));
    }
}
