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
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Contact\Application\Repository\ReadContactRepositoryInterface;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Application\Repository\ReadDashboardShareRepositoryInterface;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Domain\Model\Role\DashboardSharingRole;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

final class FindDashboards
{
    use LoggerTrait;
    public const AUTHORIZED_ACL_GROUPS = ['customer_admin_acl'];

    public function __construct(
        private readonly ReadDashboardRepositoryInterface $readDashboardRepository,
        private readonly ReadDashboardShareRepositoryInterface $readDashboardShareRepository,
        private readonly RequestParametersInterface $requestParameters,
        private readonly ReadContactRepositoryInterface $readContactRepository,
        private readonly DashboardRights $rights,
        private readonly ContactInterface $contact,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly bool $isCloudPlatform
    ) {
    }

    public function __invoke(FindDashboardsPresenterInterface $presenter): void
    {
        try {
            if ($this->isUserAdmin()) {
                $this->info('Find dashboards', ['request' => $this->requestParameters->toArray()]);
                $presenter->presentResponse($this->findDashboardAsAdmin());
            } elseif ($this->rights->canAccess()) {
                $this->info('Find dashboards', ['request' => $this->requestParameters->toArray()]);
                $presenter->presentResponse($this->findDashboardAsViewer());
            } else {
                $this->error(
                    "User doesn't have sufficient rights to see dashboards",
                    ['user_id' => $this->contact->getId()]
                );
                $presenter->presentResponse(new ForbiddenResponse(DashboardException::accessNotAllowed()));
            }
        } catch (\Throwable $ex) {
            $presenter->presentResponse(new ErrorResponse(DashboardException::errorWhileSearching()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @throws \Throwable
     *
     * @return FindDashboardsResponse
     */
    private function findDashboardAsAdmin(): FindDashboardsResponse
    {
        $dashboards = $this->readDashboardRepository->findByRequestParameter($this->requestParameters);
        $contactIds = $this->extractAllContactIdsFromDashboards($dashboards);

        return FindDashboardsFactory::createResponse(
            $dashboards,
            $this->readContactRepository->findNamesByIds(...$contactIds),
            $this->readDashboardShareRepository->getMultipleSharingRoles($this->contact, ...$dashboards),
            $this->readDashboardShareRepository->findDashboardsContactShares(...$dashboards),
            $this->readDashboardShareRepository->findDashboardsContactGroupShares(...$dashboards),
            DashboardSharingRole::Editor
        );
    }

    /**
     * @throws \Throwable
     *
     * @return FindDashboardsResponse
     */
    private function findDashboardAsViewer(): FindDashboardsResponse
    {
        $dashboards = $this->readDashboardRepository->findByRequestParameterAndContact(
            $this->requestParameters,
            $this->contact,
        );
        $editorIds = $this->extractAllContactIdsFromDashboards($dashboards);

        $userAccessGroups = $this->readAccessGroupRepository->findByContact($this->contact);
        $accessGroupsIds = array_map(
            static fn(AccessGroup $accessGroup): int => $accessGroup->getId(),
            $userAccessGroups
        );

        $userInCurrentUserAccessGroups = $this->readContactRepository->findContactIdsByAccessGroups($accessGroupsIds);

        return FindDashboardsFactory::createResponse(
            $dashboards,
            $this->readContactRepository->findNamesByIds(...$editorIds),
            $this->readDashboardShareRepository->getMultipleSharingRoles($this->contact, ...$dashboards),
            $this->readDashboardShareRepository->findDashboardsContactSharesByContactIds(
                $userInCurrentUserAccessGroups,
                ...$dashboards
            ),
            $this->readDashboardShareRepository->findDashboardsContactGroupSharesByContact($this->contact, ...$dashboards),
            DashboardSharingRole::Viewer
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
