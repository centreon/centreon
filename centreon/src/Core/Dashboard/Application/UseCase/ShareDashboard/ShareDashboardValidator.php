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

namespace Core\Dashboard\Application\UseCase\ShareDashboard;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Contact\Application\Repository\ReadContactRepositoryInterface;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Application\Repository\ReadDashboardShareRepositoryInterface;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Domain\Model\Role\DashboardContactGroupRole;
use Core\Dashboard\Domain\Model\Role\DashboardContactRole;
use Core\Dashboard\Domain\Model\Role\DashboardGlobalRole;
use Core\Dashboard\Domain\Model\Role\DashboardSharingRole;
use Core\Dashboard\Domain\Model\Role\TinyRole;
use Core\Dashboard\Infrastructure\Model\DashboardGlobalRoleConverter;
use Core\Dashboard\Infrastructure\Model\DashboardSharingRoleConverter;
use Utility\Difference\BasicDifference;

class ShareDashboardValidator
{
    public function __construct(
        private readonly ContactInterface $user,
        private readonly DashboardRights $rights,
        private readonly ReadDashboardRepositoryInterface $readDashboardRepository,
        private readonly ReadDashboardShareRepositoryInterface $readDashboardShareRepository,
        private readonly ReadContactRepositoryInterface $contactRepository,
        private readonly ReadContactGroupRepositoryInterface $readContactGroupRepository,
    ) {
    }

    /**
     * @param int $dashboardId
     *
     * @throws DashboardException|\Throwable
     */
    public function validateDashboard(int $dashboardId): void
    {
        //Validate Dashboard Exists
        if ($this->readDashboardRepository->existsOne($dashboardId) === false) {
            throw DashboardException::theDashboardDoesNotExist($dashboardId);
        }

        // Validate Dashboard is shared as editor to the user.
        if (
            ! $this->rights->hasAdminRole()
            && ! $this->readDashboardShareRepository->existsAsEditor($dashboardId, $this->user)
        ) {
            throw DashboardException::dashboardAccessRightsNotAllowedForWriting($dashboardId);
        }

    }

    /**
     * Validate request contacts against the following rules:
     *  - The contacts should exist
     *  - The contacts should be unique in the request
     *  - The contacts should have Dashboard related ACLs
     *  - The contacts should have sufficient ACLs for their given roles
     *      (e.g a Viewer in ACLs can not be shared as Editor)
     *  - If the user executing the request is not an admin, the contacts should be member of his access groups
     *
     * @param array<array{id: int, role: string}> $contacts
     * @param int[] $contactIdsInUserAccessGroups
     *
     * @throws DashboardException|\Throwable
     */
    public function validateContacts(array $contacts, array $contactIdsInUserAccessGroups): void
    {
        $contactIds = array_map(static fn (array $contact): int => $contact['id'], $contacts);
        $this->validateContactsExist($contactIds);
        $this->validateContactsAreUnique($contactIds);

        $dashboardContactRoles = $this->readDashboardShareRepository->findContactsWithAccessRightByContactIds(
            $contactIds
        );
        $adminUsers = $this->contactRepository->findAdminsByIds($contactIds);
        foreach ($adminUsers as $adminUser) {
            $dashboardContactRoles[] = new DashboardContactRole(
                $adminUser->getId(),
                $adminUser->getName(),
                $adminUser->getEmail(),
                [DashboardGlobalRole::Administrator]
            );
        }
        $this->validateContactsHaveDashboardACLs($dashboardContactRoles, $contactIds);
        $contactsByIdAndRole = [];
        foreach ($contacts as $contact) {
            $contactsByIdAndRole[$contact['id']] = $contact['role'];
        }
        $this->validateContactsHaveSufficientRightForSharingRole($contactsByIdAndRole, $dashboardContactRoles);

        // If the current user is not admin, the shared contacts should be member of his access groups.
        if (! $this->rights->hasAdminRole()) {
            $this->validateContactsAreInTheSameAccessGroupThanCurrentUser($contactIds, $contactIdsInUserAccessGroups);
        }



    }

    /**
     * @param array<array{id: int, role: string}> $contactGroups
     *
     * @throws DashboardException|\Throwable
     */
    public function validateContactGroups(array $contactGroups, array $userContactGroupIds): void
    {
        //Validate contact groups exists
        $contactGroupIds = array_map(static fn (array $contactGroup): int => $contactGroup['id'], $contactGroups);
        $this->validateContactGroupsExist($contactGroupIds);
        $this->validateContactGroupsAreUnique($contactGroupIds);

        $dashboardContactGroupRoles = $this->readDashboardShareRepository
            ->findContactGroupsWithAccessRightByContactGroupIds($contactGroupIds);
        $this->validateContactGroupsHaveDashboardACLs($dashboardContactGroupRoles, $contactGroupIds);
        $this->validateContactGroupsHaveSufficientRightForSharingRole($contactGroups, $dashboardContactGroupRoles);
        if (! $this->rights->hasAdminRole()) {
            $this->validateContactGroupsAreInCurrentUserContactGroups($contactGroupIds, $$userContactGroupIds);
        }
    }

    /**
     * @param int[] $contactIds
     *
     * @throws DashboardException
     */
    private function validateContactsExist(array $contactIds): void
    {
        if (
            ! empty(
                ($nonexistentUsers = array_diff($contactIds, $this->contactRepository->exist($contactIds)))
            )
        ) {
            throw DashboardException::theContactsDoNotExist($nonexistentUsers);
        }
    }

    /**
     * @param int[] $contactIds
     *
     * @throws DashboardException
     */
    private function validateContactsAreUnique(array $contactIds): void
    {
        if (count(array_flip($contactIds)) < count($contactIds)) {
            throw DashboardException::contactForShareShouldBeUnique();
        }
    }

    /**
     * @param DashboardContactRole[] $dashboardContactRoles
     * @param int[] $contactIds
     *
     * @throws DashboardException
     */
    private function validateContactsHaveDashboardACLs(array $dashboardContactRoles, array $contactIds): void
    {
        $dashboardContactRoleIds = array_map(
            static fn (DashboardContactRole $dashboardContactRole) => $dashboardContactRole->getContactId()
            , $dashboardContactRoles
        );
        $contactIdsDifference = new BasicDifference($contactIds, $dashboardContactRoleIds);
        if ([] !== $contactIdsDifference->getRemoved()) {
            throw DashboardException::theContactsDoesNotHaveDashboardAccessRights($contactIdsDifference->getRemoved());
        }
    }

    /**
     * @param DashboardContactGroupRole[] $dashboardContactGroupRoles
     * @param int[] $contactGroupIds
     *
     * @throws DashboardException
     */
    private function validateContactGroupsHaveDashboardACLs(
        array $dashboardContactGroupRoles,
        array $contactGroupIds
    ): void {
        $dashboardContactGroupRoleIds = array_map(
            static fn (DashboardContactGroupRole $dashboardContactRole) => $dashboardContactRole->getContactGroupId()
            , $dashboardContactGroupRoles
        );
        $contactGroupIdsDifference = new BasicDifference($contactGroupIds, $dashboardContactGroupRoleIds);
        if ([] !== $contactGroupIdsDifference->getRemoved()) {
            throw DashboardException::theContactGroupsDoesNotHaveDashboardAccessRights(
                $contactGroupIdsDifference->getRemoved()
            );
        }
    }


    /**
     * @param array<int, string> $contactsByIdAndRole
     * @param DashboardContactRole[] $dashboardContactRoles
     *
     * @throws DashboardException
     */
    private function validateContactsHaveSufficientRightForSharingRole(
        array $contactsByIdAndRole,
        array $dashboardContactRoles
    ): void {
        foreach ($dashboardContactRoles as $dashboardContactRole) {
            if (
                DashboardSharingRoleConverter::fromString($contactsByIdAndRole[$dashboardContactRole->getContactId()])
                !== DashboardSharingRole::Viewer
                && $dashboardContactRole->getMostPermissiveRole() === DashboardGlobalRole::Viewer
            ) {
                throw DashboardException::notSufficientAccessRightForUser(
                    $dashboardContactRole->getContactId(),
                    $contactsByIdAndRole[$dashboardContactRole->getContactId()]
                );
            }
        }
    }

    /**
     * @param array<array{id:int, role:string}> $contactGroups
     * @param DashboardContactGroupRole[] $dashboardContactGroupRoles
     *
     * @throws DashboardException
     */
    private function validateContactGroupsHaveSufficientRightForSharingRole(
        array $contactGroups,
        array $dashboardContactGroupRoles
    ): void {
        $contactGroupByIdAndRole = [];
        foreach ($contactGroups as $contactGroup) {
            $contactGroupByIdAndRole[$contactGroup['id']] = $contactGroup['role'];
        }

        foreach ($dashboardContactGroupRoles as $dashboardContactGroupRole) {
            if (
                DashboardSharingRoleConverter::fromString(
                    $contactGroupByIdAndRole[$dashboardContactGroupRole->getContactGroupId()]
                )  !== DashboardSharingRole::Viewer
                && $dashboardContactGroupRole->getMostPermissiveRole() === DashboardGlobalRole::Viewer
            ) {
                throw DashboardException::notSufficientAccessRightForContactGroup(
                    $dashboardContactGroupRole->getContactGroupId(),
                    $contactGroupByIdAndRole[$dashboardContactGroupRole->getContactGroupId()]
                );
            }
        }
    }

    /**
     * @param int[] $contactIds
     *
     * @throws DashboardException
     */
    private function validateContactsAreInTheSameAccessGroupThanCurrentUser(array $requestContactIds, array $contactIdsInUserAccessGroups): void
    {
        $contactDifference = new BasicDifference($requestContactIds, $contactIdsInUserAccessGroups);
        if ([] !== $contactDifference->getRemoved()) {
            DashboardException::userAreNotInAccessGroups($contactDifference->getRemoved());
        }
    }

    /**
     * @param int[] $contactGroupIds
     * @param int[] $userContactGroupIds
     *
     * @throws DashboardException
     */
    private function validateContactGroupsAreInCurrentUserContactGroups(array $contactGroupIds, array $userContactGroupIds): void
    {
        $contactGroupIdsDifference = new BasicDifference($contactGroupIds, $userContactGroupIds);
        if ([] !== $contactGroupIdsDifference->getRemoved()) {
            throw DashboardException::contactGroupIsNotInUserContactGroups(
                $contactGroupIdsDifference->getRemoved()
            );
        }
    }

    /**
     * @param int[] $contactGroupIds
     *
     * @throws DashboardException|\Throwable
     */
    private function validateContactGroupsExist(array $contactGroupIds): void
    {
        if (
            ! empty(
                ($nonexistentContactGroups = array_diff(
                    $contactGroupIds,
                    $this->readContactGroupRepository->exist($contactGroupIds)
                ))
            )
        ) {
            throw DashboardException::theContactGroupsDoNotExist($nonexistentContactGroups);
        }
    }

    /**
     * @param int[] $contactGroupIds
     *
     * @throws DashboardException
     */
    private function validateContactGroupsAreUnique(array $contactGroupIds): void
    {
        if (count(array_flip($contactGroupIds)) < count($contactGroupIds)) {
            throw DashboardException::contactGroupForShareShouldBeUnique();
        }
    }
}