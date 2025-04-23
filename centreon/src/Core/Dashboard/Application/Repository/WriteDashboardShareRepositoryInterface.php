<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\Dashboard\Application\Repository;

use Core\Dashboard\Domain\Model\Role\DashboardSharingRole;
use Core\Dashboard\Domain\Model\Role\TinyRole;

interface WriteDashboardShareRepositoryInterface
{
    /**
     * Create the share relation between a dashboard and a contact.
     *
     * @param int $contactId
     * @param int $dashboardId
     * @param DashboardSharingRole $role
     *
     * @throws \Throwable
     */
    public function upsertShareWithContact(int $contactId, int $dashboardId, DashboardSharingRole $role): void;

    /**
     * Create the share relation between a dashboard and a contact group.
     *
     * @param int $contactGroupId
     * @param int $dashboardId
     * @param DashboardSharingRole $role
     *
     * @throws \Throwable
     */
    public function upsertShareWithContactGroup(int $contactGroupId, int $dashboardId, DashboardSharingRole $role): void;

    /**
     * Delete the share relation between a dashboard and a contact.
     *
     * @param int $contactId
     * @param int $dashboardId
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function deleteContactShare(int $contactId, int $dashboardId): bool;

    /**
     * Delete the share relation between a dashboard and a contact group.
     *
     * @param int $dashboardId
     * @param int $contactGroupId
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function deleteContactGroupShare(int $contactGroupId, int $dashboardId): bool;

    /**
     * Update the share relation between a dashboard and a contact.
     *
     * @param int $contactId
     * @param int $dashboardId
     * @param DashboardSharingRole $role
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function updateContactShare(int $contactId, int $dashboardId, DashboardSharingRole $role): bool;

    /**
     * Update the share relation between a dashboard and a contact group.
     *
     * @param int $contactGroupId
     * @param int $dashboardId
     * @param DashboardSharingRole $role
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function updateContactGroupShare(int $contactGroupId, int $dashboardId, DashboardSharingRole $role): bool;

    /**
     * Delete all the contact and contactgroups shared on a dashboard.
     *
     * @param int $dashboardId
     *
     * @throws \Throwable
     */
    public function deleteDashboardShares(int $dashboardId): void;

    /**
     * Delete given contacts shared on a dashboard.
     *
     * @param int $dashboardId
     * @param int[] $contactIds
     *
     * @throws \Throwable
     */
    public function deleteDashboardSharesByContactIds(int $dashboardId, array $contactIds): void;

    /**
     * Delete given contact groups shared on a dashboard.
     *
     * @param int $dashboardId
     * @param int[] $contactGroupIds
     *
     * @throws \Throwable
     */
    public function deleteDashboardSharesByContactGroupIds(int $dashboardId, array $contactGroupIds): void;

    /**
     * Add multiple contact shares on a dashboard.
     *
     * @param int $dashboardId
     * @param TinyRole[] $contactGroupRoles
     *
     * @throws \Throwable
     */
    public function addDashboardContactGroupShares(int $dashboardId, array $contactGroupRoles): void;

    /**
     * Add multiple contact group shares on a dashboard.
     *
     * @param int $dashboardId
     * @param TinyRole[] $contactRoles
     *
     * @throws \Throwable
     */
    public function addDashboardContactShares(int $dashboardId, array $contactRoles): void;
}
