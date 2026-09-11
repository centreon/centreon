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

namespace Tests\App\Security\Infrastructure\Double;

use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Shared\Domain\Aggregate\AclScopedInterface;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Aggregate\AggregateRootId;
use App\Shared\Domain\Collection;

final class FakeResourceAccessRepository implements ResourceAccessRepository
{
    public bool $unrestrictedPollerAccess = true;

    /** @var ?Collection<HostGroupId> null means unrestricted, matching the real contract */
    public ?Collection $accessibleHostGroupIds = null;

    /** @var list<array{resource: AggregateRoot<AggregateRootId>&AclScopedInterface, accessGroupIds: list<int>}> */
    public array $grantedAccess = [];

    public bool $allResourcesFlaggedAsChanged = false;

    public function hasAccessToAllPollers(UserId $userId): bool
    {
        return $this->unrestrictedPollerAccess;
    }

    public function hasAccessToPoller(PollerId $pollerId, UserId $userId): bool
    {
        return $this->unrestrictedPollerAccess;
    }

    public function findAccessibleHostSeverityIds(UserId $userId): ?Collection
    {
        return null;
    }

    public function findAccessibleHostCategoryIds(UserId $userId): ?Collection
    {
        return null;
    }

    public function findAccessiblePollerIds(UserId $userId): ?Collection
    {
        return null;
    }

    public function findAccessibleHostGroupIds(UserId $userId): ?Collection
    {
        return $this->accessibleHostGroupIds;
    }

    public function grantResourceAccess(AggregateRoot&AclScopedInterface $resource, Collection $accessGroupIds): void
    {
        $this->grantedAccess[] = [
            'resource' => $resource,
            'accessGroupIds' => array_values(array_map(static fn ($id): int => $id->value, $accessGroupIds->toArray())),
        ];
    }

    public function flagAllResourcesAsChanged(): void
    {
        $this->allResourcesFlaggedAsChanged = true;
    }
}
