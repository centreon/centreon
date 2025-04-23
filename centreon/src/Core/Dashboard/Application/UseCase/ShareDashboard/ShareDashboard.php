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

namespace Core\Dashboard\Application\UseCase\ShareDashboard;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
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

/** @package Core\Dashboard\Application\UseCase\ShareDashboard */
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

    public function __invoke(ShareDashboardRequest $request): ResponseStatusInterface
    {
        try {
            $isUserAdmin = $this->isUserAdmin();
            $contactIdsRelationsToDelete = [];

            $this->validator->validateDashboard(
                dashboardId: $request->dashboardId,
                isAdmin: $isUserAdmin,
            );

            if ($isUserAdmin) {
                $this->isCloudPlatform
                    ? $this->validator->validateContactsForCloud(isAdmin: true, contacts: $request->contacts)
                    : $this->validator->validateContactsForOnPremise(isAdmin: true, contacts: $request->contacts);

                $this->isCloudPlatform
                    ? $this->validator->validateContactGroupsForCloud(isAdmin: true, contactGroups: $request->contactGroups)
                    : $this->validator->validateContactGroupsForOnPremise(isAdmin: true, contactGroups: $request->contactGroups);
            } else {
                $currentUserContactGroupIds = $this->findCurrentUserContactGroupIds();

                if ($this->isCloudPlatform) {
                    $contactIdsInCurrentUserContactGroups = $this->readContactRepository->findContactIdsByContactGroups(
                        $currentUserContactGroupIds
                    );

                    $this->validator->validateContactsForCloud(
                        isAdmin: false,
                        contacts: $request->contacts,
                        contactIdsInUserContactGroups: $contactIdsInCurrentUserContactGroups,
                    );

                    $this->validator->validateContactGroupsForCloud(
                        isAdmin: false,
                        contactGroups: $request->contactGroups,
                        userContactGroupIds: $currentUserContactGroupIds,
                    );

                    $contactIdsRelationsToDelete = $contactIdsInCurrentUserContactGroups;
                } else {
                    $contactIdsInUserAccessGroups = $this->readContactRepository->findContactIdsByAccessGroups(
                        $this->findCurrentUserAccessGroupIds()
                    );

                    $this->validator->validateContactsForOnPremise(
                        isAdmin: false,
                        contacts: $request->contacts,
                        contactIdsInUserAccessGroups: $contactIdsInUserAccessGroups,
                    );
                    $this->validator->validateContactGroupsForOnPremise(
                        isAdmin: false,
                        contactGroups: $request->contactGroups,
                        userContactGroupIds: $currentUserContactGroupIds,
                    );

                    $contactIdsRelationsToDelete = $contactIdsInUserAccessGroups;
                }
            }

            $contactRoles = $this->createRolesFromRequest($request->contacts);
            $contactGroupRoles = $this->createRolesFromRequest($request->contactGroups);

            $isUserAdmin
                ? $this->updateDashboardSharesAsAdmin(
                    $request->dashboardId,
                    $contactRoles,
                    $contactGroupRoles
                )
                : $this->updateDashboardSharesAsNonAdmin(
                    $request->dashboardId,
                    $contactRoles,
                    $contactGroupRoles,
                    $currentUserContactGroupIds,
                    $contactIdsRelationsToDelete
                );

            return new NoContentResponse();
        } catch (DashboardException $ex) {
            $this->error(
                "Error while updating dashboard shares : {$ex->getMessage()}",
                [
                    'dashboard_id' => $request->dashboardId,
                    'contact_id' => $this->user->getId(),
                    'contacts' => $request->contacts,
                    'contact_groups' => $request->contactGroups,
                    'exception' => [
                        'message' => $ex->getMessage(),
                        'trace' => (string) $ex,
                    ],
                ]
            );

            return match ($ex->getCode()) {
                DashboardException::CODE_NOT_FOUND => new NotFoundResponse($ex),
                DashboardException::CODE_FORBIDDEN => new ForbiddenResponse($ex),
                default => new InvalidArgumentResponse($ex->getMessage()),
            };
        } catch (\Throwable $ex) {
            $this->error(
                "Error while updating dashboard shares : {$ex->getMessage()}",
                [
                    'dashboard_id' => $request->dashboardId,
                    'contact_id' => $this->user->getId(),
                    'contacts' => $request->contacts,
                    'contact_groups' => $request->contactGroups,
                    'exception' => [
                        'message' => $ex->getMessage(),
                        'trace' => (string) $ex,
                    ],
                ]
            );

            return new ErrorResponse(DashboardException::errorWhileUpdatingDashboardShare()->getMessage());
        }
    }

    /**
     * @param array<array{id: int, role: string}> $data
     * @return TinyRole[]
     */
    private function createRolesFromRequest(array $data): array
    {
        return array_map(
            static fn (array $item): TinyRole => new TinyRole(
                $item['id'],
                DashboardSharingRoleConverter::fromString($item['role'])
            ),
            $data
        );
    }

    /**
     * @throws \Throwable
     * @return int[]
     */
    private function findCurrentUserContactGroupIds(): array
    {
        $contactGroups = $this->readContactGroupRepository->findAllByUserId($this->user->getId());

        return array_map(
            static fn (ContactGroup $contactGroup): int => $contactGroup->getId(),
            $contactGroups
        );
    }

    /**
     * @throws \Throwable
     * @return int[]
     */
    private function findCurrentUserAccessGroupIds(): array
    {
        $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);

        return array_map(
            static fn (AccessGroup $accessGroup): int => $accessGroup->getId(),
            $accessGroups
        );
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
            $this->error(
                "Error during update dashboard shares transaction, rolling back: {$ex->getMessage()}",
                [
                    'dashboard_id' => $dashboardId,
                    'exception' => [
                        'message' => $ex->getMessage(),
                        'trace' => (string) $ex,
                    ],
                ]
            );
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
            $this->error(
                "Error during update dashboard shares transaction, rolling back: {$ex->getMessage()}",
                [
                    'dashboard_id' => $dashboardId,
                    'exception' => [
                        'message' => $ex->getMessage(),
                        'trace' => (string) $ex,
                    ],
                ]
            );
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
