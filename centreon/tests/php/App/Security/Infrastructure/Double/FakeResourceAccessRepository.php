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
use App\Security\Domain\Aggregate\AccessScope;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Shared\Domain\Aggregate\AclScopedInterface;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Aggregate\AggregateRootId;
use App\Shared\Domain\Collection;

final class FakeResourceAccessRepository implements ResourceAccessRepository
{
    /** @var AccessScope<PollerId> */
    public AccessScope $pollerScope;

    /** @var array<string, int> how many times each `find*AccessScope()` lookup ran */
    public array $lookups = [];

    /** @var AccessScope<HostGroupId> */
    public AccessScope $hostGroupScope;

    /** @var AccessScope<MediaDirectoryId> */
    public AccessScope $imageFolderScope;

    /** @var AccessScope<HostCategoryId> */
    public AccessScope $hostCategoryScope;

    /** @var AccessScope<HostSeverityId> */
    public AccessScope $hostSeverityScope;

    /** @var Collection<NotificationContactId> always restricted, as contacts carry no "all contacts" ACL flag */
    public Collection $accessibleContactIds;

    /** @var Collection<ContactGroupId> always restricted, as contact groups carry no "all contact groups" ACL flag */
    public Collection $accessibleContactGroupIds;

    /** @var Collection<HostId> always restricted, as hosts follow the real-time ACL cache */
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
        $this->pollerScope = AccessScope::unrestricted(PollerId::class);
        $this->hostGroupScope = AccessScope::unrestricted(HostGroupId::class);
        $this->imageFolderScope = AccessScope::unrestricted(MediaDirectoryId::class);
        $this->hostCategoryScope = AccessScope::unrestricted(HostCategoryId::class);
        $this->hostSeverityScope = AccessScope::unrestricted(HostSeverityId::class);
        $this->accessibleContactIds = new Collection([], NotificationContactId::class);
        $this->accessibleContactGroupIds = new Collection([], ContactGroupId::class);
        $this->accessibleHostIds = new Collection([], HostId::class);
    }

    public function hasAccessToAllPollers(UserId $userId): bool
    {
        return $this->pollerScope->isUnrestricted();
    }

    public function hasAccessToPoller(PollerId $pollerId, UserId $userId): bool
    {
        return $this->pollerScope->allows($pollerId);
    }

    public function findHostSeverityAccessScope(UserId $userId): AccessScope
    {
        $this->lookups[__FUNCTION__] = ($this->lookups[__FUNCTION__] ?? 0) + 1;

        return $this->hostSeverityScope;
    }

    public function findHostCategoryAccessScope(UserId $userId): AccessScope
    {
        $this->lookups[__FUNCTION__] = ($this->lookups[__FUNCTION__] ?? 0) + 1;

        return $this->hostCategoryScope;
    }

    public function findPollerAccessScope(UserId $userId): AccessScope
    {
        $this->lookups[__FUNCTION__] = ($this->lookups[__FUNCTION__] ?? 0) + 1;

        return $this->pollerScope;
    }

    public function findHostGroupAccessScope(UserId $userId): AccessScope
    {
        $this->lookups[__FUNCTION__] = ($this->lookups[__FUNCTION__] ?? 0) + 1;

        return $this->hostGroupScope;
    }

    public function findContactAccessScope(UserId $userId): AccessScope
    {
        $this->lookups[__FUNCTION__] = ($this->lookups[__FUNCTION__] ?? 0) + 1;

        return AccessScope::restrictedTo(NotificationContactId::class, $this->accessibleContactIds);
    }

    public function findContactGroupAccessScope(UserId $userId): AccessScope
    {
        $this->lookups[__FUNCTION__] = ($this->lookups[__FUNCTION__] ?? 0) + 1;

        return AccessScope::restrictedTo(ContactGroupId::class, $this->accessibleContactGroupIds);
    }

    public function findHostAccessScope(UserId $userId, ?Collection $among = null): AccessScope
    {
        $this->lookups[__FUNCTION__] = ($this->lookups[__FUNCTION__] ?? 0) + 1;

        if (! $among instanceof Collection) {
            return AccessScope::restrictedTo(HostId::class, $this->accessibleHostIds);
        }

        $amongIds = array_map(static fn (HostId $id): int => $id->value, $among->toArray());

        return AccessScope::partiallyRestrictedTo(HostId::class, array_filter(
            $this->accessibleHostIds->toArray(),
            static fn (HostId $id): bool => in_array($id->value, $amongIds, true),
        ));
    }

    public function findImageFolderAccessScope(UserId $userId): AccessScope
    {
        $this->lookups[__FUNCTION__] = ($this->lookups[__FUNCTION__] ?? 0) + 1;

        return $this->imageFolderScope;
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
