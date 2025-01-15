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

namespace Core\Dashboard\Application\UseCase\FindDashboards;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Contact\Application\Repository\ReadContactRepositoryInterface;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Application\Repository\ReadDashboardShareRepositoryInterface;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Domain\Model\Role\DashboardSharingRole;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\UserProfile\Application\Repository\ReadUserProfileRepositoryInterface;
use Throwable;

/** @package Core\Dashboard\Application\UseCase\FindDashboards */
final class FindDashboards
{
    use LoggerTrait;
    public const AUTHORIZED_ACL_GROUPS = ['customer_admin_acl'];

    /** @var int[] */
    private array $usersFavoriteDashboards = [];

    public function __construct(
        private readonly ReadDashboardRepositoryInterface $readDashboardRepository,
        private readonly ReadDashboardShareRepositoryInterface $readDashboardShareRepository,
        private readonly RequestParametersInterface $requestParameters,
        private readonly ReadContactRepositoryInterface $readContactRepository,
        private readonly DashboardRights $rights,
        private readonly ContactInterface $contact,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ReadUserProfileRepositoryInterface $userProfileReader,
        private readonly bool $isCloudPlatform
    ) {
    }

    public function __invoke(FindDashboardsPresenterInterface $presenter): void
    {
        try {
            $profile = $this->userProfileReader->findByContact($this->contact);
            $this->usersFavoriteDashboards = $profile !== null ? $profile->getFavoriteDashboards() : [];

            $presenter->presentResponse(
                $this->isUserAdmin() ? $this->findDashboardAsAdmin() : $this->findDashboardAsViewer(),
            );
            $this->info('Find dashboards', ['request' => $this->requestParameters->toArray()]);
        } catch (Throwable $ex) {
            $this->error(
                "Error while searching dashboards : {$ex->getMessage()}",
                [
                    'contact_id' => $this->contact->getId(),
                    'favorite_dashboards' => $this->usersFavoriteDashboards,
                    'request_parameters' => $this->requestParameters->toArray(),
                    'exception' => ['message' => $ex->getMessage(), 'trace' => $ex->getTraceAsString()],
                ]
            );
            $presenter->presentResponse(new ErrorResponse(DashboardException::errorWhileSearching()));
        }
    }

    /**
     * @throws Throwable
     *
     * @return FindDashboardsResponse
     */
    private function findDashboardAsAdmin(): FindDashboardsResponse
    {
        $dashboards = $this->readDashboardRepository->findByRequestParameter($this->requestParameters);

        $dashboardIds = array_map(
            static fn (Dashboard $dashboard): int => $dashboard->getId(),
            $dashboards
        );

        $thumbnails = $this->readDashboardRepository->findThumbnailsByDashboardIds($dashboardIds);
        $contactIds = $this->extractAllContactIdsFromDashboards($dashboards);

        return FindDashboardsFactory::createResponse(
            dashboards: $dashboards,
            contactNames: $this->readContactRepository->findNamesByIds(...$contactIds),
            sharingRolesList: $this->readDashboardShareRepository->getMultipleSharingRoles($this->contact, ...$dashboards),
            contactShares: $this->readDashboardShareRepository->findDashboardsContactShares(...$dashboards),
            contactGroupShares: $this->readDashboardShareRepository->findDashboardsContactGroupShares(...$dashboards),
            defaultRole: DashboardSharingRole::Editor,
            thumbnails: $thumbnails,
            favoriteDashboards: $this->usersFavoriteDashboards
        );
    }

    /**
     * @throws Throwable
     *
     * @return FindDashboardsResponse
     */
    private function findDashboardAsViewer(): FindDashboardsResponse
    {
        $dashboards = $this->readDashboardRepository->findByRequestParameterAndContact(
            $this->requestParameters,
            $this->contact,
        );

        $dashboardIds = array_map(
            static fn (Dashboard $dashboard): int => $dashboard->getId(),
            $dashboards
        );

        $thumbnails = $this->readDashboardRepository->findThumbnailsByDashboardIds($dashboardIds);

        $editorIds = $this->extractAllContactIdsFromDashboards($dashboards);

        $userAccessGroups = $this->readAccessGroupRepository->findByContact($this->contact);
        $accessGroupsIds = array_map(
            static fn(AccessGroup $accessGroup): int => $accessGroup->getId(),
            $userAccessGroups
        );

        $userInCurrentUserAccessGroups = $this->readContactRepository->findContactIdsByAccessGroups($accessGroupsIds);

        return FindDashboardsFactory::createResponse(
            dashboards: $dashboards,
            contactNames: $this->readContactRepository->findNamesByIds(...$editorIds),
            sharingRolesList: $this->readDashboardShareRepository->getMultipleSharingRoles($this->contact, ...$dashboards),
            contactShares: $this->readDashboardShareRepository->findDashboardsContactSharesByContactIds(
                $userInCurrentUserAccessGroups,
                ...$dashboards
            ),
            contactGroupShares: $this->readDashboardShareRepository->findDashboardsContactGroupSharesByContact($this->contact, ...$dashboards),
            defaultRole: DashboardSharingRole::Viewer,
            thumbnails: $thumbnails,
            favoriteDashboards: $this->usersFavoriteDashboards
        );
    }

    /**
     * @param list<Dashboard> $dashboards
     *
     * @return int[]
     */
    private function extractAllContactIdsFromDashboards(array $dashboards): array
    {
        $contactIds = [];
        foreach ($dashboards as $dashboard) {
            if ($id = $dashboard->getCreatedBy()) {
                $contactIds[] = $id;
            }
            if ($id = $dashboard->getUpdatedBy()) {
                $contactIds[] = $id;
            }
        }

        return $contactIds;
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
