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

namespace App\Security\Domain\Repository;

use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaDirectoryId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceGroup\ServiceGroupId;
use App\Security\Domain\Aggregate\AccessGroupId;
use App\Security\Domain\Aggregate\AccessScope;
use App\Security\Domain\Aggregate\UserId;
use App\Shared\Domain\Aggregate\AclScopedInterface;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Aggregate\AggregateRootId;
use App\Shared\Domain\Collection;

/**
 * The resource access rules of a user, from their Access Groups. The `find*AccessScope()` lookups ignore
 * whether the user escapes resource scoping (a super admin or a Cloud admin): ask
 * {@see \App\Security\Domain\Service\AccessScopeResolver} what an acting user may access.
 */
interface ResourceAccessRepository
{
    public function hasAccessToAllPollers(UserId $userId): bool;

    /**
     * Seeds the `centreon_acl` real-time cache directly for the given Access Groups, so a
     * non-admin creator immediately sees their own newly created resource instead of waiting
     * for the `centAcl` cron to recompute it. Never call this for an admin creator — admins
     * bypass ACL scoping entirely in the read path and need no such row.
     *
     * @param AggregateRoot<AggregateRootId>&AclScopedInterface $resource
     * @param Collection<AccessGroupId> $accessGroupIds
     */
    public function grantResourceAccess(AggregateRoot&AclScopedInterface $resource, Collection $accessGroupIds): void;

    /**
     * Flags every Access Group's resource scoping as changed, so the `centAcl` cron fully
     * recomputes `centreon_acl` for all of them on its next run. Used when the creator is an
     * admin: the new resource may now be visible via any group's resource-scoping rule, not
     * just the creator's own.
     */
    public function flagAllResourcesAsChanged(): void;

    /**
     * Copies a host's ACL scope onto a freshly duplicated host, so the copy is visible to exactly the
     * same Access Groups as its source without waiting for the `centAcl` cron:
     *  - the configuration relations `acl_resources_host_relations` and `acl_resources_hostex_relations`
     *    (mirroring legacy centreonACL::duplicateHostAcl), and
     *  - the real-time `centreon_acl` rows scoping the host per group.
     *
     * Only host-level rows are copied here: the copy has no services yet (they are duplicated after the
     * commit). The service-level `centreon_acl` rows are written later by
     * {@see self::duplicateHostServiceAccess()}, once the copy's services exist.
     */
    public function duplicateHostAccess(HostId $sourceHostId, HostId $newHostId): void;

    /**
     * Completes {@see self::duplicateHostAccess()} once the copy's services have been duplicated: for
     * every service now linked to the copy and every Access Group that already sees the source host,
     * writes the real-time `centreon_acl` service row, so a non-admin sees the copy's services without
     * waiting for the `centAcl` cron (mirrors legacy updateACL('DUP') over the duplicated services).
     */
    public function duplicateHostServiceAccess(HostId $sourceHostId, HostId $newHostId): void;

    public function hasAccessToPoller(PollerId $pollerId, UserId $userId): bool;

    /**
     * The levelless host categories the user may access, mirroring the legacy host-category ACL
     * (DbReadHostCategoryRepository::findAllByAccessGroupIds guarded by hasRestrictedAccessToHostCategories):
     *  - unrestricted, when their accessible ACL resources carry no host-category relation;
     *  - restricted to nothing, when they have no accessible ACL resource or are granted only severities;
     *  - restricted to the granted categories otherwise.
     *
     * @return AccessScope<HostCategoryId>
     */
    public function findHostCategoryAccessScope(UserId $userId): AccessScope;

    /**
     * The host severities (host categories carrying a level) the user may access, mirroring the legacy
     * host-template ACL (DbReadHostTemplateRepository::findByRequestParametersAndAccessGroups):
     *  - unrestricted, when their accessible ACL resources carry no host-category relation;
     *  - restricted to nothing, when they have no accessible ACL resource or are granted no severity;
     *  - restricted to the granted severities otherwise.
     *
     * @return AccessScope<HostSeverityId>
     */
    public function findHostSeverityAccessScope(UserId $userId): AccessScope;

    /**
     * @return AccessScope<PollerId>
     */
    public function findPollerAccessScope(UserId $userId): AccessScope;

    /**
     * @return AccessScope<HostGroupId>
     */
    public function findHostGroupAccessScope(UserId $userId): AccessScope;

    /**
     * Service groups granted by the user's active ACL resources (disabled service groups included).
     *
     * @return Collection<ServiceGroupId>|null null means no restriction applies (an accessible ACL
     *                                         resource grants all service groups); an empty
     *                                         Collection means the user can access none
     */
    public function findAccessibleServiceGroupIds(UserId $userId): ?Collection;

    /**
     * @return AccessScope<MediaDirectoryId>
     */
    public function findImageFolderAccessScope(UserId $userId): AccessScope;

    /**
     * Contacts are not ACL *resources*: they carry no `acl_resources_*` relation and no "all
     * contacts" flag. A user sees the contacts attached to one of their Access Groups, directly or
     * through a contact group, so the scope is always restricted.
     *
     * @return AccessScope<NotificationContactId>
     */
    public function findContactAccessScope(UserId $userId): AccessScope;

    /**
     * Same Access-Group-membership model as {@see self::findContactAccessScope()}, plus the contact
     * groups the user is a member of.
     *
     * @return AccessScope<ContactGroupId>
     */
    public function findContactGroupAccessScope(UserId $userId): AccessScope;

    /**
     * Hosts follow the real-time ACL cache (`centreon_acl`), which already resolves every
     * resource-scoping rule per Access Group, so the scope is always restricted.
     *
     * @param ?Collection<HostId> $among when given, only these hosts are looked up, instead of every host the user can see:
     *                                   the scope is then partial, and only answers for them
     *
     * @return AccessScope<HostId>
     */
    public function findHostAccessScope(UserId $userId, ?Collection $among = null): AccessScope;
}
