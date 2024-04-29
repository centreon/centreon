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

namespace Core\Dashboard\Application\UseCase\DeleteContactGroupDashboardShare;

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Application\Repository\ReadDashboardShareRepositoryInterface;
use Core\Dashboard\Application\Repository\WriteDashboardShareRepositoryInterface;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

final class DeleteContactGroupDashboardShare
{
    use LoggerTrait;
    public const AUTHORIZED_ACL_GROUPS = ['customer_admin_acl'];

    public function __construct(
        private readonly ReadDashboardShareRepositoryInterface $readDashboardShareRepository,
        private readonly ReadDashboardRepositoryInterface $readDashboardRepository,
        private readonly WriteDashboardShareRepositoryInterface $writeDashboardShareRepository,
        private readonly ReadContactGroupRepositoryInterface $readContactGroupRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly DashboardRights $rights,
        private readonly ContactInterface $contact,
        private readonly bool $isCloudPlatform
    ) {
    }

    public function __invoke(
        int $dashboardId,
        int $contactGroupId,
        DeleteContactGroupDashboardSharePresenterInterface $presenter
    ): void {
        try {
            if ($this->isUserAdmin()) {
                if ($dashboard = $this->readDashboardRepository->findOne($dashboardId)) {
                    $this->info(
                        'Delete a contact group share for dashboard',
                        ['id' => $dashboardId, 'contact_id' => $contactGroupId]
                    );
                    $response = $this->deleteContactGroupShareAsAdmin($dashboard, $contactGroupId);
                } else {
                    $this->warning('Dashboard (%s) not found', ['id' => $dashboardId]);
                    $response = new NotFoundResponse('Dashboard');
                }
            } elseif ($this->rights->canCreate()) {
                if ($dashboard = $this->readDashboardRepository->findOneByContact($dashboardId, $this->contact)) {
                    $this->info(
                        'Delete a contact contact group for dashboard',
                        ['id' => $dashboardId, 'contact_id' => $contactGroupId]
                    );
                    $response = $this->deleteContactGroupShareAsContact($dashboard, $contactGroupId);
                } else {
                    $this->warning('Dashboard (%s) not found', ['id' => $dashboardId]);
                    $response = new NotFoundResponse('Dashboard');
                }
            } else {
                $this->error(
                    "User doesn't have sufficient rights to see dashboards",
                    ['user_id' => $this->contact->getId()]
                );
                $response = new ForbiddenResponse(DashboardException::accessNotAllowedForWriting());
            }

            $presenter->presentResponse($response);
        } catch (AssertionFailedException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->presentResponse(new InvalidArgumentResponse($ex));
        } catch (DashboardException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->presentResponse(new ErrorResponse($ex));
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->presentResponse(new ErrorResponse('Error while deleting the dashboard share'));
        }
    }

    /**
     * @param Dashboard $dashboard
     * @param int $contactGroupId
     *
     * @throws \Throwable
     *
     * @return ResponseStatusInterface
     */
    private function deleteContactGroupShareAsAdmin(Dashboard $dashboard, int $contactGroupId): ResponseStatusInterface
    {
        $group = $this->readContactGroupRepository->find($contactGroupId);
        if (null === $group) {
            $this->warning('Contact group (%s) not found', ['id' => $contactGroupId]);

            return new NotFoundResponse('Contact');
        }

        if (! $this->writeDashboardShareRepository->deleteContactGroupShare($group->getId(), $dashboard->getId())) {
            return new NotFoundResponse('Dashboard share');
        }

        return new NoContentResponse();
    }

    /**
     * @param Dashboard $dashboard
     * @param int $contactGroupId
     *
     * @throws \Throwable
     *
     * @return ResponseStatusInterface
     */
    private function deleteContactGroupShareAsContact(Dashboard $dashboard, int $contactGroupId): ResponseStatusInterface
    {
        $group = $this->readContactGroupRepository->find($contactGroupId);
        if (null === $group) {
            $this->warning('Contact group (%s) not found', ['id' => $contactGroupId]);

            return new NotFoundResponse('Contact');
        }

        $sharingRoles = $this->readDashboardShareRepository->getOneSharingRoles($this->contact, $dashboard);
        if (! $this->rights->canDeleteShare($sharingRoles)) {
            return new ForbiddenResponse(
                DashboardException::dashboardAccessRightsNotAllowedForWriting($dashboard->getId())
            );
        }

        $accessGroups = $this->readAccessGroupRepository->findByContact($this->contact);
        $accessGroupIds = array_map(static fn (AccessGroup $accessGroup): int => $accessGroup->getId(), $accessGroups);

        if (! $this->readContactGroupRepository->existsInAccessGroups($group->getId(), $accessGroupIds)) {
            return new NotFoundResponse('Contact Group');
        }

        if (! $this->writeDashboardShareRepository->deleteContactGroupShare($group->getId(), $dashboard->getId())) {
            return new NotFoundResponse('Dashboard share');
        }

        return new NoContentResponse();
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
            $this->readAccessGroupRepository->findByContact($this->contact)
        );

        return ! (empty(array_intersect($userAccessGroupNames, self::AUTHORIZED_ACL_GROUPS)))
            && $this->isCloudPlatform;
    }
}
