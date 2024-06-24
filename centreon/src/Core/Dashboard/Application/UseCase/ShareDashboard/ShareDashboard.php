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
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Contact\Application\Repository\ReadContactRepositoryInterface;
use Core\Contact\Domain\Model\ContactGroup;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\WriteDashboardShareRepositoryInterface;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Domain\Model\Role\TinyRole;
use Core\Dashboard\Infrastructure\Model\DashboardSharingRoleConverter;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

final class ShareDashboard
{
    use LoggerTrait;
    public const AUTHORIZED_ACL_GROUPS = ['customer_admin_acl'];

    public function __construct(
        private readonly DashboardRights $rights,
        private readonly ShareDashboardValidator $validator,
        private readonly WriteDashboardShareRepositoryInterface $writeDashboardShareRepository,
        private readonly ReadContactRepositoryInterface $readContactRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ReadContactGroupRepositoryInterface $readContactGroupRepository,
        private readonly ContactInterface $user,
        private readonly DataStorageEngineInterface $dataStorageEngine,
        private readonly bool $isCloudPlatform
    ) {
    }

    public function __invoke(ShareDashboardRequest $request, ShareDashboardPresenterInterface $presenter): void
    {
        try {
            $isUserAdmin = $this->isUserAdmin();
            if ($isUserAdmin) {
                $this->validator->validateDashboard($request->dashboardId);
                $this->validator->validateContacts($request->contacts);
                $this->validator->validateContactGroups($request->contactGroups);
            } elseif ($this->rights->canCreate()) {
                $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
                $accessGroupIds = array_map(
                    static fn (AccessGroup $accessGroup): int => $accessGroup->getId(),
                    $accessGroups
                );
                $contactIdsInUserAccessGroups = $this->readContactRepository->findContactIdsByAccessGroups(
                    $accessGroupIds
                );

                $userContactGroups = $this->readContactGroupRepository->findAllByUserId($this->user->getId());
                $userContactGroupIds = array_map(
                    static fn (ContactGroup $contactGroup): int => $contactGroup->getId(),
                    $userContactGroups
                );

                $this->validator->validateDashboard(
                    dashboardId: $request->dashboardId,
                    isAdmin: false
                );
                $this->validator->validateContacts(
                    contacts: $request->contacts,
                    contactIdsInUserAccessGroups: $contactIdsInUserAccessGroups,
                    isAdmin: false
                );
                $this->validator->validateContactGroups(
                    contactGroups: $request->contactGroups,
                    userContactGroupIds: $userContactGroupIds,
                    isAdmin: false
                );
            } else {
                $this->error("User doesn't have sufficient rights to add shares on dashboards");
                $presenter->presentResponse(
                    new ForbiddenResponse(DashboardException::accessNotAllowedForWriting())
                );

                return;
            }

            $contactRoles = [];
            foreach ($request->contacts as $contact) {
                $contactRoles[] = new TinyRole(
                    $contact['id'],
                    DashboardSharingRoleConverter::fromString($contact['role'])
                );
            }

            $contactGroupRoles = [];
            foreach ($request->contactGroups as $contactGroup) {
                $contactGroupRoles[] = new TinyRole(
                    $contactGroup['id'],
                    DashboardSharingRoleConverter::fromString($contactGroup['role'])
                );
            }

            if ($isUserAdmin) {
                $this->updateDashboardSharesAsAdmin($request->dashboardId, $contactRoles, $contactGroupRoles);
            } else {
                $this->updateDashboardSharesAsNonAdmin(
                    $request->dashboardId,
                    $contactRoles,
                    $contactGroupRoles,
                    $userContactGroupIds,
                    $contactIdsInUserAccessGroups
                );
            }

            $presenter->presentResponse(new NoContentResponse());
        } catch (DashboardException $ex) {
            $this->error($ex->getMessage(), ['trace' => (string) $ex]);
            $presenter->presentResponse(
                match ($ex->getCode()) {
                    DashboardException::CODE_NOT_FOUND => new NotFoundResponse($ex),
                    DashboardException::CODE_FORBIDDEN => new ForbiddenResponse($ex),
                    default => new InvalidArgumentResponse($ex->getMessage()),
                }
            );
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => (string) $ex]);
            $presenter->presentResponse(
                new ErrorResponse(DashboardException::errorWhileUpdating()->getMessage())
            );
        }
    }

    /**
     * @param int $dashboardId
     * @param TinyRole[] $contactRoles
     * @param TinyRole[] $contactGroupRoles
     *
     * @throws \Throwable
     */
    private function updateDashboardSharesAsAdmin(int $dashboardId, array $contactRoles, array $contactGroupRoles): void {
        try {
            $this->dataStorageEngine->startTransaction();
            $this->writeDashboardShareRepository->deleteDashboardShares($dashboardId);
            $this->writeDashboardShareRepository->addDashboardContactShares($dashboardId, $contactRoles);
            $this->writeDashboardShareRepository->addDashboardContactGroupShares(
                $dashboardId,
                $contactGroupRoles
            );
            $this->dataStorageEngine->commitTransaction();
        } catch (\Throwable $ex) {
            $this->error('Error during transaction, rollback', ['trace' => (string) $ex]);
            $this->dataStorageEngine->rollbackTransaction();

            throw $ex;
        }
    }

    /**
     * @param int $dashboardId
     * @param TinyRole[] $contactRoles
     * @param TinyRole[] $contactGroupRoles
     * @param int[] $userContactGroupIds
     * @param int[] $contactIdsInUserAccessGroups
     *
     * @throws \Throwable
     */
    private function updateDashboardSharesAsNonAdmin(
        int $dashboardId,
        array $contactRoles,
        array $contactGroupRoles,
        array $userContactGroupIds,
        array $contactIdsInUserAccessGroups
    ): void {
        try {
            $this->dataStorageEngine->startTransaction();
            $this->writeDashboardShareRepository->deleteDashboardSharesByContactIds(
                $dashboardId,
                $contactIdsInUserAccessGroups
            );
            $this->writeDashboardShareRepository->deleteDashboardSharesByContactGroupIds(
                $dashboardId,
                $userContactGroupIds
            );
            $this->writeDashboardShareRepository->addDashboardContactShares($dashboardId, $contactRoles);
            $this->writeDashboardShareRepository->addDashboardContactGroupShares(
                $dashboardId,
                $contactGroupRoles
            );
            $this->dataStorageEngine->commitTransaction();
        } catch (\Throwable $ex) {
            $this->error('Error during transaction, rollback', ['trace' => (string) $ex]);
            $this->dataStorageEngine->rollbackTransaction();

            throw $ex;
        }
    }

    /**
     * @throws \Throwable
     *
     * @return bool
     */
    private function isUserAdmin(): bool
    {
        if ($this->rights->hasAdminRole()) {
            return true;
        }

        $userAccessGroupNames = array_map(
            static fn (AccessGroup $accessGroup): string => $accessGroup->getName(),
            $this->readAccessGroupRepository->findByContact($this->user)
        );

        return ! (empty(array_intersect($userAccessGroupNames, self::AUTHORIZED_ACL_GROUPS)))
            && $this->isCloudPlatform;
    }
}