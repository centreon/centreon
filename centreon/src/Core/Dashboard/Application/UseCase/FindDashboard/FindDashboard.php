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

namespace Core\Dashboard\Application\UseCase\FindDashboard;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Contact\Application\Repository\ReadContactRepositoryInterface;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardPanelRepositoryInterface;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Application\Repository\ReadDashboardShareRepositoryInterface;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Domain\Model\Role\DashboardSharingRole;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

final class FindDashboard
{
    use LoggerTrait;
    public const AUTHORIZED_ACL_GROUPS = ['customer_admin_acl'];

    public function __construct(
        private readonly ReadDashboardRepositoryInterface $readDashboardRepository,
        private readonly ReadDashboardPanelRepositoryInterface $readDashboardPanelRepository,
        private readonly ReadDashboardShareRepositoryInterface $readDashboardShareRepository,
        private readonly ReadContactRepositoryInterface $readContactRepository,
        private readonly DashboardRights $rights,
        private readonly ContactInterface $contact,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly bool $isCloudPlatform
    ) {
    }

    /**
     * @param int $dashboardId
     * @param FindDashboardPresenterInterface $presenter
     */
    public function __invoke(int $dashboardId, FindDashboardPresenterInterface $presenter): void
    {
        try {
            if ($this->isUserAdmin()) {
                $response = $this->findDashboardAsAdmin($dashboardId);
            } elseif ($this->rights->canAccess()) {
                $response = $this->findDashboardAsViewer($dashboardId);
            } else {
                $response = new ForbiddenResponse(DashboardException::accessNotAllowed());
            }

            if ($response instanceof FindDashboardResponse) {
                $this->info('Find dashboard', ['id' => $dashboardId]);
            } elseif ($response instanceof NotFoundResponse) {
                $this->warning('Dashboard (%s) not found', ['id' => $dashboardId]);
            } else {
                $this->error(
                    "User doesn't have sufficient rights to see the dashboard",
                    ['user_id' => $this->contact->getId()]
                );
            }

            $presenter->presentResponse($response);
        } catch (\Throwable $ex) {
            $presenter->presentResponse(new ErrorResponse(DashboardException::errorWhileRetrieving()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @param int $dashboardId
     *
     * @throws \Throwable
     *
     * @return FindDashboardResponse|NotFoundResponse
     */
    private function findDashboardAsAdmin(int $dashboardId): FindDashboardResponse|NotFoundResponse
    {
        $dashboard = $this->readDashboardRepository->findOne($dashboardId);

        if (null === $dashboard) {
            return new NotFoundResponse('Dashboard');
        }

        $dashboard->setThumbnail(
            $this->readDashboardRepository->findThumbnailByDashboardId($dashboard->getId()),
        );

        return $this->createResponse($dashboard, DashboardSharingRole::Editor);
    }

    /**
     * @param int $dashboardId
     *
     * @throws \Throwable
     *
     * @return FindDashboardResponse|NotFoundResponse
     */
    private function findDashboardAsViewer(int $dashboardId): FindDashboardResponse|NotFoundResponse
    {
        $dashboard = $this->readDashboardRepository->findOneByContact($dashboardId, $this->contact);

        if (null === $dashboard) {
            return new NotFoundResponse('Dashboard');
        }

        $dashboard->setThumbnail(
            $this->readDashboardRepository->findThumbnailByDashboardId($dashboard->getId()),
        );

        return $this->createResponseAsNonAdmin($dashboard, DashboardSharingRole::Viewer);
    }

    /**
     * @param Dashboard $dashboard
     * @param DashboardSharingRole $defaultRole
     *
     * @throws \Throwable
     *
     * @return FindDashboardResponse
     */
    private function createResponse(Dashboard $dashboard, DashboardSharingRole $defaultRole): FindDashboardResponse
    {
        $contactIds = $this->extractAllContactIdsFromDashboard($dashboard);

        return FindDashboardFactory::createResponse(
            $dashboard,
            $this->readContactRepository->findNamesByIds(...$contactIds),
            $this->readDashboardPanelRepository->findPanelsByDashboardId($dashboard->getId()),
            $this->readDashboardShareRepository->getOneSharingRoles($this->contact, $dashboard),
            $this->readDashboardShareRepository->findDashboardsContactShares($dashboard),
            $this->readDashboardShareRepository->findDashboardsContactGroupShares($dashboard),
            $defaultRole
        );
    }

    /**
     * @param Dashboard $dashboard
     * @param DashboardSharingRole $defaultRole
     *
     * @throws \Throwable
     *
     * @return FindDashboardResponse
     */
    private function createResponseAsNonAdmin(Dashboard $dashboard, DashboardSharingRole $defaultRole): FindDashboardResponse
    {
        $editorIds = $this->extractAllContactIdsFromDashboard($dashboard);

        $userAccessGroups = $this->readAccessGroupRepository->findByContact($this->contact);
        $accessGroupsIds = array_map(
            static fn(AccessGroup $accessGroup): int => $accessGroup->getId(),
            $userAccessGroups
        );

        $userInCurrentUserAccessGroups = $this->readContactRepository->findContactIdsByAccessGroups($accessGroupsIds);

        return FindDashboardFactory::createResponse(
            $dashboard,
            $this->readContactRepository->findNamesByIds(...$editorIds),
            $this->readDashboardPanelRepository->findPanelsByDashboardId($dashboard->getId()),
            $this->readDashboardShareRepository->getOneSharingRoles($this->contact, $dashboard),
            $this->readDashboardShareRepository->findDashboardsContactSharesByContactIds(
                $userInCurrentUserAccessGroups,
                $dashboard
            ),
            $this->readDashboardShareRepository->findDashboardsContactGroupSharesByContact(
                $this->contact,
                $dashboard
            ),
            $defaultRole
        );
    }

    /**
     * @param Dashboard $dashboard
     *
     * @return int[]
     */
    private function extractAllContactIdsFromDashboard(Dashboard $dashboard): array
    {
        $contactIds = [];
        if ($id = $dashboard->getCreatedBy()) {
            $contactIds[] = $id;
        }
        if ($id = $dashboard->getUpdatedBy()) {
            $contactIds[] = $id;
        }

        return $contactIds;
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
