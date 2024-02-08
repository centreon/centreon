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
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NoContentResponse;
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

    public function __construct(
        private readonly DashboardRights $rights,
        private readonly ShareDashboardValidator $validator,
        private readonly WriteDashboardShareRepositoryInterface $writeDashboardShareRepository,
        private readonly ReadContactRepositoryInterface $readContactRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ReadContactGroupRepositoryInterface $readContactGroupRepository,
        private readonly ContactInterface $user,
        private readonly DataStorageEngineInterface $dataStorageEngine
    ) {
    }

    public function __invoke(ShareDashboardRequest $request, ShareDashboardPresenterInterface $presenter): void
    {
        try {
            if (! $this->rights->canCreate()) {
                $this->error("User doesn't have sufficient rights to add shares on dashboards");
                $presenter->presentResponse(
                    new ForbiddenResponse(DashboardException::accessNotAllowedForWriting())
                );

                return;
            }

            $this->validator->validateDashboard($request->dashboardId);
            if (! $this->rights->hasAdminRole()) {
                $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
                $accessGroupIds = array_map(
                    static fn (AccessGroup $accessGroup): int => $accessGroup->getId(),
                    $accessGroups
                );
                $contactIdsInUserAccessGroups = $this->readContactRepository->findContactIdsByAccessGroups(
                    $accessGroupIds
                );

                $userContactGroups = $this->readContactGroupRepository->findAllByUserId($this->user->getId());
                $userContactGroupIds = array_map(static fn (ContactGroup $contactGroup): int => $contactGroup->getId(), $userContactGroups);
            }
            $this->validator->validateContacts(
                $request->contacts,
                $contactIdsInUserAccessGroups ?? []
            );
            $contactRoles = [];
            foreach ($request->contacts as $contact) {
                $contactRoles[] = new TinyRole(
                    $contact['id'],
                    DashboardSharingRoleConverter::fromString($contact['role'])
                );
            }
            $this->validator->validateContactGroups($request->contactGroups, $userContactGroupIds ?? []);
            $contactGroupRoles = [];
            foreach ($request->contactGroups as $contactGroup) {
                $contactGroupRoles[] = new TinyRole(
                    $contactGroup['id'],
                    DashboardSharingRoleConverter::fromString($contactGroup['role'])
                );
            }
            if ($this->rights->hasAdminRole()) {
                $this->updateDashboardSharesAsAdmin($request->dashboardId, $contactRoles, $contactGroupRoles);
                $presenter->presentResponse(new NoContentResponse());

                return;
            }

            $this->updateDashboardSharesAsNonAdmin(
                $request->dashboardId,
                $contactRoles,
                $contactGroupRoles,
                $userContactGroupIds,
                $contactIdsInUserAccessGroups
            );
            $presenter->presentResponse(new NoContentResponse());
        } catch(DashboardException $ex) {
            $this->error($ex->getMessage(), ['trace' => (string) $ex]);
            $presenter->presentResponse(
                new InvalidArgumentResponse($ex->getMessage())
            );
        } catch (\Throwable $ex) {
            $this->error(DashboardException::errorWhileUpdating()->getMessage());
            $presenter->presentResponse(
                new InvalidArgumentResponse($ex->getMessage())
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
}