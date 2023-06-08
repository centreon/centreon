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
use Core\Dashboard\Application\Repository\ReadDashboardRelationRepositoryInterface;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Domain\Model\DashboardSharingRole;

final class FindDashboards
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadDashboardRepositoryInterface $readDashboardRepository,
        private readonly ReadDashboardRelationRepositoryInterface $readDashboardRelationRepository,
        private readonly RequestParametersInterface $requestParameters,
        private readonly ReadContactRepositoryInterface $readContactRepository,
        private readonly DashboardRights $rights,
        private readonly ContactInterface $contact
    ) {
    }

    public function __invoke(FindDashboardsPresenterInterface $presenter): void
    {
        try {
            if ($this->rights->hasAdminRole()) {
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
            $this->readDashboardRelationRepository->getMultipleSharingRoles($this->contact, ...$dashboards),
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
        $contactIds = $this->extractAllContactIdsFromDashboards($dashboards);

        return FindDashboardsFactory::createResponse(
            $dashboards,
            $this->readContactRepository->findNamesByIds(...$contactIds),
            $this->readDashboardRelationRepository->getMultipleSharingRoles($this->contact, ...$dashboards),
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
}
