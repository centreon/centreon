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

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

final class FindDashboards
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadDashboardRepositoryInterface $readDashboardRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly RequestParametersInterface $requestParameters,
        private readonly ContactInterface $contact
    ) {
    }

    public function __invoke(FindDashboardsPresenterInterface $presenter): void
    {
        try {
            if ($this->contact->isAdmin()) {
                $this->info('Find dashboards', ['request' => $this->requestParameters->toArray()]);
                $presenter->presentResponse($this->findDashboardAsAdmin());
            } elseif ($this->contactCanExecuteThisUseCase()) {
                $this->info('Find dashboards', ['request' => $this->requestParameters->toArray()]);
                $presenter->presentResponse($this->findDashboardAsContact());
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

        return $this->createResponse($dashboards);
    }

    /**
     * @throws \Throwable
     *
     * @return FindDashboardsResponse
     */
    private function findDashboardAsContact(): FindDashboardsResponse
    {
        $accessGroups = $this->readAccessGroupRepository->findByContact($this->contact);
        $dashboards = $this->readDashboardRepository->findByRequestParameterAndAccessGroups(
            $accessGroups,
            $this->requestParameters,
        );

        return $this->createResponse($dashboards);
    }

    /**
     * @return bool
     */
    private function contactCanExecuteThisUseCase(): bool
    {
        return $this->contact->hasTopologyRole(Contact::ROLE_HOME_DASHBOARD_READ)
            || $this->contact->hasTopologyRole(Contact::ROLE_HOME_DASHBOARD_WRITE);
    }

    /**
     * @param list<Dashboard> $dashboards
     *
     * @return FindDashboardsResponse
     */
    private function createResponse(array $dashboards): FindDashboardsResponse
    {
        $response = new FindDashboardsResponse();

        foreach ($dashboards as $dashboard) {
            $response->dashboards[] = [
                'id' => $dashboard->getId(),
                'name' => $dashboard->getName(),
                'description' => $dashboard->getDescription(),
                'createdAt' => $dashboard->getCreatedAt(),
                'updatedAt' => $dashboard->getUpdatedAt(),
            ];
        }

        return $response;
    }
}
