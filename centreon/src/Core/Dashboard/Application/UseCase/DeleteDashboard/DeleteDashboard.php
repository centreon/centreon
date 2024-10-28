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

namespace Core\Dashboard\Application\UseCase\DeleteDashboard;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Application\Repository\ReadDashboardShareRepositoryInterface;
use Core\Dashboard\Application\Repository\WriteDashboardRepositoryInterface;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Media\Application\Repository\WriteMediaRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Throwable;

readonly final class DeleteDashboard
{
    use LoggerTrait;
    public const AUTHORIZED_ACL_GROUPS = ['customer_admin_acl'];

    public function __construct(
        private ReadDashboardRepositoryInterface $readDashboardRepository,
        private WriteDashboardRepositoryInterface $writeDashboardRepository,
        private ReadDashboardShareRepositoryInterface $readDashboardShareRepository,
        private DashboardRights $rights,
        private ContactInterface $contact,
        private ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private WriteMediaRepositoryInterface $mediaRepository,
        private bool $isCloudPlatform
    ) {
    }

    public function __invoke(int $dashboardId, DeleteDashboardPresenterInterface $presenter): void
    {
        try {
            if ($this->isUserAdmin()) {
                $presenter->presentResponse($this->deleteDashboardAsAdmin($dashboardId));
            } elseif ($this->rights->canCreate()) {
                $presenter->presentResponse($this->deleteDashboardAsContact($dashboardId));
            } else {
                $this->error(
                    "User doesn't have sufficient rights to see dashboards",
                    ['user_id' => $this->contact->getId()]
                );
                $presenter->presentResponse(
                    new ForbiddenResponse(DashboardException::accessNotAllowedForWriting())
                );
            }
        } catch (Throwable $ex) {
            $presenter->presentResponse(new ErrorResponse(DashboardException::errorWhileDeleting()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @param int $dashboardId
     *
     * @throws Throwable
     *
     * @return ResponseStatusInterface
     */
    private function deleteDashboardAsAdmin(int $dashboardId): ResponseStatusInterface
    {
        if ($this->readDashboardRepository->existsOne($dashboardId)) {
            if (($thumbail = $this->readDashboardRepository->findThumbnailByDashboardId($dashboardId)) !== null) {
                $this->mediaRepository->delete($thumbail);
            }

            $this->writeDashboardRepository->delete($dashboardId);

            return new NoContentResponse();
        }

        $this->warning('Dashboard (%s) not found', ['id' => $dashboardId]);

        return new NotFoundResponse('Dashboard');
    }

    /**
     * @param int $dashboardId
     *
     * @throws Throwable
     *
     * @return ResponseStatusInterface
     */
    private function deleteDashboardAsContact(int $dashboardId): ResponseStatusInterface
    {
        $dashboard = $this->readDashboardRepository->findOneByContact($dashboardId, $this->contact);
        if (null === $dashboard) {
            $this->warning('Dashboard (%s) not found', ['id' => $dashboardId]);

            return new NotFoundResponse('Dashboard');
        }

        $sharingRoles = $this->readDashboardShareRepository->getOneSharingRoles($this->contact, $dashboard);
        if (! $this->rights->canDelete($sharingRoles)) {
            return new ForbiddenResponse(DashboardException::dashboardAccessRightsNotAllowedForWriting($dashboardId));
        }

        if (($thumbail = $this->readDashboardRepository->findThumbnailByDashboardId($dashboardId)) !== null) {
            $this->mediaRepository->delete($thumbail);
        }

        $this->writeDashboardRepository->delete($dashboardId);

        return new NoContentResponse();
    }

    /**
     * @throws Throwable
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
