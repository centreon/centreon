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

use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\Security\Domain\Service\AccessScopeResolver;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;
use Tests\App\Security\Infrastructure\Double\FakeResourceAccessRepository;
use Tests\App\Security\Infrastructure\Double\TestActingUsers;

final class AccessScopeResolverTest extends TestCase
{
    private FakeResourceAccessRepository $resourceAccessRepository;

    private AccessScopeResolver $resolver;

    protected function setUp(): void
    {
        $this->resourceAccessRepository = new FakeResourceAccessRepository();
        $this->resourceAccessRepository->accessibleHostGroupIds = new Collection([new HostGroupId(2)], HostGroupId::class);

        $this->resolver = new AccessScopeResolver($this->resourceAccessRepository);
    }

    public function testAnUnrestrictedActorGetsAnUnrestrictedScopeWithoutAnyLookup(): void
    {
        $scope = $this->resolver->hostGroups(TestActingUsers::unrestricted());

        self::assertTrue($scope->isUnrestricted());
        self::assertSame(0, $this->resourceAccessRepository->accessListLoads);
    }

    public function testARestrictedActorGetsTheScopeOfTheirAccessGroups(): void
    {
        $scope = $this->resolver->hostGroups(TestActingUsers::restricted(7));

        self::assertSame([2], $scope->allowedIdValues());
        self::assertSame(1, $this->resourceAccessRepository->accessListLoads);
    }
}
