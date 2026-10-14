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

use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaDirectoryId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\Security\Domain\Aggregate\ActingUser;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Shared\Domain\Aggregate\AclScopedInterface;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Aggregate\AggregateRootId;
use App\Shared\Domain\Collection;

final class FakeResourceAccessRepository implements ResourceAccessRepository
{
    public bool $unrestrictedPollerAccess = true;

    /** How many times an access list has been loaded, for the host groups, the categories, the contacts and the contact groups. */
    public int $accessListLoads = 0;

    /** @var ?Collection<HostGroupId> null means unrestricted, matching the real contract */
    public ?Collection $accessibleHostGroupIds = null;

    /** @var ?Collection<MediaDirectoryId> null means unrestricted, matching the real contract */
    public ?Collection $accessibleImageFolderIds = null;

    /** @var ?Collection<HostCategoryId> null means unrestricted, matching the real contract */
    public ?Collection $accessibleHostCategoryIds = null;

    /** @var ?Collection<HostSeverityId> null means unrestricted, matching the real contract */
    public ?Collection $accessibleHostSeverityIds = null;

    /** @var Collection<NotificationContactId> unlike the nullable ones, contacts have no "unrestricted" case */
    public Collection $accessibleContactIds;

    /** @var Collection<ContactGroupId> unlike the nullable ones, contact groups have no "unrestricted" case */
    public Collection $accessibleContactGroupIds;

    /** @var Collection<HostId> unlike the nullable ones, hosts have no "unrestricted" case */
    public Collection $accessibleHostIds;

    /** @var list<array{resource: AggregateRoot<AggregateRootId>&AclScopedInterface, accessGroupIds: list<int>}> */
    public array $grantedAccess = [];

    public bool $allResourcesFlaggedAsChanged = false;

    /** @var list<array{sourceHostId: int, newHostId: int}> */
    public array $duplicatedHostAccess = [];

    /** @var list<array{sourceHostId: int, newHostId: int}> */
    public array $duplicatedHostServiceAccess = [];

    public bool $duplicateHostAccessThrows = false;

    public function __construct()
    {
        $this->accessibleContactIds = new Collection([], NotificationContactId::class);
        $this->accessibleContactGroupIds = new Collection([], ContactGroupId::class);
        $this->accessibleHostIds = new Collection([], HostId::class);
    }

    public function hasAccessToAllPollers(UserId $userId): bool
    {
        return $this->unrestrictedPollerAccess;
    }

    public function hasAccessToPoller(PollerId $pollerId, UserId $userId): bool
    {
        return $this->unrestrictedPollerAccess;
    }

    public function findAccessibleHostSeverityIds(ActingUser $actor): ?Collection
    {
        if ($actor->hasUnrestrictedResourceAccess()) {
            return null;
        }

        return $this->accessibleHostSeverityIds;
    }

    public function findAccessibleHostCategoryIds(ActingUser $actor): ?Collection
    {
        if ($actor->hasUnrestrictedResourceAccess()) {
            return null;
        }

        ++$this->accessListLoads;

        return $this->accessibleHostCategoryIds;
    }

    public function findAccessiblePollerIds(ActingUser $actor): ?Collection
    {
        if ($actor->hasUnrestrictedResourceAccess()) {
            return null;
        }

        return null;
    }

    public function findAccessibleHostGroupIds(ActingUser $actor): ?Collection
    {
        if ($actor->hasUnrestrictedResourceAccess()) {
            return null;
        }

        ++$this->accessListLoads;

        return $this->accessibleHostGroupIds;
    }

    public function findAccessibleContactIds(ActingUser $actor): ?Collection
    {
        if ($actor->hasUnrestrictedResourceAccess()) {
            return null;
        }

        ++$this->accessListLoads;

        return $this->accessibleContactIds;
    }

    public function findAccessibleContactGroupIds(ActingUser $actor): ?Collection
    {
        if ($actor->hasUnrestrictedResourceAccess()) {
            return null;
        }

        ++$this->accessListLoads;

        return $this->accessibleContactGroupIds;
    }

    public function findAccessibleHostIds(ActingUser $actor, ?Collection $among = null): ?Collection
    {
        if ($actor->hasUnrestrictedResourceAccess()) {
            return null;
        }

        if (! $among instanceof Collection) {
            return $this->accessibleHostIds;
        }

        $amongIds = array_map(static fn (HostId $id): int => $id->value, $among->toArray());

        return new Collection(
            array_values(array_filter(
                $this->accessibleHostIds->toArray(),
                static fn (HostId $id): bool => in_array($id->value, $amongIds, true),
            )),
            HostId::class,
        );
    }

    public function findAccessibleImageFolderIds(ActingUser $actor): ?Collection
    {
        if ($actor->hasUnrestrictedResourceAccess()) {
            return null;
        }

        return $this->accessibleImageFolderIds;
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

    public function duplicateHostAccess(HostId $sourceHostId, HostId $newHostId): void
    {
        if ($this->duplicateHostAccessThrows) {
            throw new \RuntimeException('duplicateHostAccess failed');
        }

        $this->duplicatedHostAccess[] = [
            'sourceHostId' => $sourceHostId->value,
            'newHostId' => $newHostId->value,
        ];
    }

    public function duplicateHostServiceAccess(HostId $sourceHostId, HostId $newHostId): void
    {
        $this->duplicatedHostServiceAccess[] = [
            'sourceHostId' => $sourceHostId->value,
            'newHostId' => $newHostId->value,
        ];
    }
}
