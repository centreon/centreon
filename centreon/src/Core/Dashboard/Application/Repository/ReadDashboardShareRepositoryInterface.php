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

namespace Core\Dashboard\Application\Repository;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Repository\RepositoryException;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Contact\Domain\Model\ContactGroup;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Dashboard\Domain\Model\Role\DashboardContactGroupRole;
use Core\Dashboard\Domain\Model\Role\DashboardContactRole;
use Core\Dashboard\Domain\Model\Share\DashboardContactGroupShare;
use Core\Dashboard\Domain\Model\Share\DashboardContactShare;
use Core\Dashboard\Domain\Model\Share\DashboardSharingRoles;

/** @package Core\Dashboard\Application\Repository */
interface ReadDashboardShareRepositoryInterface
{
    /**
     * Find all contact shares of one dashboard.
     *
     * @param RequestParametersInterface $requestParameters
     * @param Dashboard $dashboard
     *
     * @throws \Throwable
     *
     * @return DashboardContactShare[]
     */
    public function findDashboardContactSharesByRequestParameter(
        Dashboard $dashboard,
        RequestParametersInterface $requestParameters
    ): array;

    /**
     * Find all contact group shares of one dashboard.
     *
     * @param RequestParametersInterface $requestParameters
     * @param Dashboard $dashboard
     *
     * @throws \Throwable
     *
     * @return DashboardContactGroupShare[]
     */
    public function findDashboardContactGroupSharesByRequestParameter(
        Dashboard $dashboard,
        RequestParametersInterface $requestParameters
    ): array;

    /**
     * Retrieve the sharing roles of a dashboard.
     *
     * @param Dashboard $dashboard
     * @param ContactInterface $contact
     *
     * @throws \Throwable
     */
    public function getOneSharingRoles(ContactInterface $contact, Dashboard $dashboard): DashboardSharingRoles;

    /**
     * Retrieve the sharing roles of several dashboards.
     *
     * @param Dashboard ...$dashboards
     * @param ContactInterface $contact
     *
     * @throws \Throwable
     *
     * @return array<int, DashboardSharingRoles>
     */
    public function getMultipleSharingRoles(ContactInterface $contact, Dashboard ...$dashboards): array;

    /**
     * Retrieve all the contacts shares for several dashboards.
     *
     * @param Dashboard ...$dashboards
     *
     * @throws \Throwable
     *
     * @return array<int, array<DashboardContactShare>>
     */
    public function findDashboardsContactShares(Dashboard ...$dashboards): array;

    /**
     * Retrieve all the contacts shares for several dashboards based on contact IDs.
     *
     * @param int[] $contactIds
     * @param Dashboard ...$dashboards
     *
     * @throws \Throwable
     *
     * @return array<int, array<DashboardContactShare>>
     */
    public function findDashboardsContactSharesByContactIds(array $contactIds, Dashboard ...$dashboards): array;

    /**
     * Retrieve all the contact groups shares for several dashboards.
     *
     * @param Dashboard ...$dashboards
     *
     * @throws \Throwable
     *
     * @return array<int, array<DashboardContactGroupShare>>
     */
    public function findDashboardsContactGroupShares(Dashboard ...$dashboards): array;

    /**
     * Retrieve the contact groups shares for several dashboards member of contact contactgroups.
     *
     * @param ContactInterface $contact
     * @param Dashboard ...$dashboards
     *
     * @throws \Throwable
     *
     * @return array<int, array<DashboardContactGroupShare>>
     */
    public function findDashboardsContactGroupSharesByContact(ContactInterface $contact, Dashboard ...$dashboards): array;

    /**
     * Find users with Topology ACLs on dashboards.
     * For cloud case all users with Dashboard ACLS (cloud admins included).
     * For on-premise case all users with Dashboard ACLS withouth admins (as admins does not have ACLs).
     *
     * @param RequestParametersInterface $requestParameters
     *
     * @throws \Throwable|\UnexpectedValueException
     *
     * @return DashboardContactRole[]
     */
    public function findContactsWithAccessRightByRequestParameters(
        RequestParametersInterface $requestParameters,
    ): array;

    /**
     * Find users with Topology ACLs on dashboards based on given contact IDs.
     *
     * @param int[] $contactIds
     *
     * @throws \Throwable|\UnexpectedValueException
     *
     * @return DashboardContactRole[]
     */
    public function findContactsWithAccessRightByContactIds(array $contactIds): array;

    /**
     * Find users with Topology ACLs on dashboards by current user ACLs.
     *
     * @param RequestParametersInterface $requestParameters
     * @param int[] $aclGroupIds
     *
     * @throws \Throwable|\UnexpectedValueException
     *
     * @return DashboardContactRole[]
     */
    public function findContactsWithAccessRightByACLGroupsAndRequestParameters(
        RequestParametersInterface $requestParameters,
        array $aclGroupIds
    ): array;

    /**
     * @param ContactGroup[] $contactGroups
     * @param RequestParametersInterface $requestParameters
     *
     * @throws RepositoryException
     * @return DashboardContactRole[]
     */
    public function findContactsWithAccessRightsByContactGroupsAndRequestParameters(
        array $contactGroups,
        RequestParametersInterface $requestParameters
    ): array;

    /**
     * Find contact groups with Topology ACLs on dashboards.
     *
     * @param RequestParametersInterface $requestParameters
     *
     * @throws \Throwable|\UnexpectedValueException
     *
     * @return DashboardContactGroupRole[]
     */
    public function findContactGroupsWithAccessRightByRequestParameters(
        RequestParametersInterface $requestParameters
    ): array;

    /**
     * Find all contactgroups by requestParameters (all contactgroups will have the Viewer Role by default - cloud case).
     *
     * @param RequestParametersInterface $requestParameters
     *
     * @throws RepositoryException
     *
     * @return DashboardContactGroupRole[]
     */
    public function findContactGroupsByRequestParameters(
        RequestParametersInterface $requestParameters
    ): array;

    /**
     * Find contact groups with Topology ACLs on dashboards based on given contact group IDs.
     *
     * @param int[] $contactGroupIds
     *
     * @throws \Throwable|\UnexpectedValueException
     *
     * @return DashboardContactGroupRole[]
     */
    public function findContactGroupsWithAccessRightByContactGroupIds(array $contactGroupIds): array;

    /**
     * Find contact groups with Topology ACLs on dashboards by current user ACLs.
     *
     * @param RequestParametersInterface $requestParameters
     * @param int $contactId
     *
     * @throws \Throwable|\UnexpectedValueException
     *
     * @return DashboardContactGroupRole[]
     */
    public function findContactGroupsWithAccessRightByUserAndRequestParameters(
        RequestParametersInterface $requestParameters,
        int $contactId
    ): array;

    /**
     * Find contact groups for current user.
     *
     * @param RequestParametersInterface $requestParameters
     * @param int $contactId
     *
     * @throws RepositoryException
     *
     * @return DashboardContactGroupRole[]
     */
    public function findContactGroupsByUserAndRequestParameters(
        RequestParametersInterface $requestParameters,
        int $contactId
    ): array;

    /**
     * Check if a user is editor on a dashboard.
     *
     * @param int $dashboardId
     * @param ContactInterface $contact
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function existsAsEditor(int $dashboardId, ContactInterface $contact): bool;
}
