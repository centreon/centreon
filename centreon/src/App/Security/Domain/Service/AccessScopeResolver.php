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

namespace App\Security\Domain\Service;

use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaDirectoryId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\Security\Domain\Aggregate\AccessScope;
use App\Security\Domain\Aggregate\ActingUser;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Shared\Domain\Collection;

/**
 * Resolves what an acting user may access: a user escaping resource scoping (a super admin or a Cloud
 * admin) gets an unrestricted scope without any lookup, anyone else the scope of their Access Groups.
 */
final readonly class AccessScopeResolver
{
    public function __construct(
        private ResourceAccessRepository $resourceAccessRepository,
    ) {
    }

    /**
     * @return AccessScope<HostCategoryId>
     */
    public function hostCategories(ActingUser $actor): AccessScope
    {
        return $actor->hasUnrestrictedResourceAccess()
            ? AccessScope::unrestricted(HostCategoryId::class)
            : $this->resourceAccessRepository->findHostCategoryAccessScope($actor->userId);
    }

    /**
     * @return AccessScope<HostSeverityId>
     */
    public function hostSeverities(ActingUser $actor): AccessScope
    {
        return $actor->hasUnrestrictedResourceAccess()
            ? AccessScope::unrestricted(HostSeverityId::class)
            : $this->resourceAccessRepository->findHostSeverityAccessScope($actor->userId);
    }

    /**
     * @return AccessScope<PollerId>
     */
    public function pollers(ActingUser $actor): AccessScope
    {
        return $actor->hasUnrestrictedResourceAccess()
            ? AccessScope::unrestricted(PollerId::class)
            : $this->resourceAccessRepository->findPollerAccessScope($actor->userId);
    }

    /**
     * @return AccessScope<HostGroupId>
     */
    public function hostGroups(ActingUser $actor): AccessScope
    {
        return $actor->hasUnrestrictedResourceAccess()
            ? AccessScope::unrestricted(HostGroupId::class)
            : $this->resourceAccessRepository->findHostGroupAccessScope($actor->userId);
    }

    /**
     * @return AccessScope<MediaDirectoryId>
     */
    public function imageFolders(ActingUser $actor): AccessScope
    {
        return $actor->hasUnrestrictedResourceAccess()
            ? AccessScope::unrestricted(MediaDirectoryId::class)
            : $this->resourceAccessRepository->findImageFolderAccessScope($actor->userId);
    }

    /**
     * @return AccessScope<NotificationContactId>
     */
    public function contacts(ActingUser $actor): AccessScope
    {
        return $actor->hasUnrestrictedResourceAccess()
            ? AccessScope::unrestricted(NotificationContactId::class)
            : $this->resourceAccessRepository->findContactAccessScope($actor->userId);
    }

    /**
     * @return AccessScope<ContactGroupId>
     */
    public function contactGroups(ActingUser $actor): AccessScope
    {
        return $actor->hasUnrestrictedResourceAccess()
            ? AccessScope::unrestricted(ContactGroupId::class)
            : $this->resourceAccessRepository->findContactGroupAccessScope($actor->userId);
    }

    /**
     * @param ?Collection<HostId> $among when given, only these hosts are looked up: the scope is then partial, and only answers for them
     *
     * @return AccessScope<HostId>
     */
    public function hosts(ActingUser $actor, ?Collection $among = null): AccessScope
    {
        return $actor->hasUnrestrictedResourceAccess()
            ? AccessScope::unrestricted(HostId::class)
            : $this->resourceAccessRepository->findHostAccessScope($actor->userId, $among);
    }
}
