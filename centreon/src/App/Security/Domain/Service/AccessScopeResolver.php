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
use App\Security\Domain\Aggregate\UserId;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Shared\Domain\Aggregate\AggregateRootId;
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
        return $this->resolve($actor, HostCategoryId::class, $this->resourceAccessRepository->findHostCategoryAccessScope(...));
    }

    /**
     * @return AccessScope<HostSeverityId>
     */
    public function hostSeverities(ActingUser $actor): AccessScope
    {
        return $this->resolve($actor, HostSeverityId::class, $this->resourceAccessRepository->findHostSeverityAccessScope(...));
    }

    /**
     * @return AccessScope<PollerId>
     */
    public function pollers(ActingUser $actor): AccessScope
    {
        return $this->resolve($actor, PollerId::class, $this->resourceAccessRepository->findPollerAccessScope(...));
    }

    /**
     * @return AccessScope<HostGroupId>
     */
    public function hostGroups(ActingUser $actor): AccessScope
    {
        return $this->resolve($actor, HostGroupId::class, $this->resourceAccessRepository->findHostGroupAccessScope(...));
    }

    /**
     * @return AccessScope<MediaDirectoryId>
     */
    public function imageFolders(ActingUser $actor): AccessScope
    {
        return $this->resolve($actor, MediaDirectoryId::class, $this->resourceAccessRepository->findImageFolderAccessScope(...));
    }

    /**
     * @return AccessScope<NotificationContactId>
     */
    public function contacts(ActingUser $actor): AccessScope
    {
        return $this->resolve($actor, NotificationContactId::class, $this->resourceAccessRepository->findContactAccessScope(...));
    }

    /**
     * @return AccessScope<ContactGroupId>
     */
    public function contactGroups(ActingUser $actor): AccessScope
    {
        return $this->resolve($actor, ContactGroupId::class, $this->resourceAccessRepository->findContactGroupAccessScope(...));
    }

    /**
     * @param ?Collection<HostId> $among when given, only these hosts are looked up: the scope is then partial, and only answers for them
     *
     * @return AccessScope<HostId>
     */
    public function hosts(ActingUser $actor, ?Collection $among = null): AccessScope
    {
        return $this->resolve(
            $actor,
            HostId::class,
            fn (UserId $userId): AccessScope => $this->resourceAccessRepository->findHostAccessScope($userId, $among),
        );
    }

    /**
     * @template T of AggregateRootId
     *
     * @param class-string<T> $idClass
     * @param \Closure(UserId): AccessScope<T> $find
     *
     * @return AccessScope<T>
     */
    private function resolve(ActingUser $actor, string $idClass, \Closure $find): AccessScope
    {
        return $actor->hasUnrestrictedResourceAccess() ? AccessScope::unrestricted($idClass) : $find($actor->userId);
    }
}
