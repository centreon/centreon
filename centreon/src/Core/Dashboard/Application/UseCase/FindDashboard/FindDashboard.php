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

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

final class FindDashboard
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadDashboardRepositoryInterface $readDashboardRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ContactInterface $contact
    ) {
    }

    /**
     * @param int $dashboardId
     * @param FindDashboardPresenterInterface $presenter
     */
    public function __invoke(int $dashboardId, FindDashboardPresenterInterface $presenter): void
    {
        try {
            if ($this->contact->isAdmin()) {
                $response = $this->findDashboardAsAdmin($dashboardId);
            } elseif ($this->contactCanExecuteThisUseCase()) {
                $response = $this->findDashboardAsContact($dashboardId);
            } else {
                $response = new ForbiddenResponse(DashboardException::accessNotAllowed());
            }

            if ($response instanceof FindDashboardResponse) {
                $this->info('Find dashboard', ['id' => $dashboardId]);
                $presenter->presentResponse($response);
            } elseif ($response instanceof NotFoundResponse) {
                $this->warning('Dashboard (%s) not found', ['id' => $dashboardId]);
                $presenter->presentResponse($response);
            } else {
                $this->error(
                    "User doesn't have sufficient rights to see the dashboard",
                    ['user_id' => $this->contact->getId()]
                );
                $presenter->presentResponse($response);
            }
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

        return $this->createResponse($dashboard);
    }

    /**
     * @param int $dashboardId
     *
     * @throws \Throwable
     *
     * @return FindDashboardResponse|NotFoundResponse
     */
    private function findDashboardAsContact(int $dashboardId): FindDashboardResponse|NotFoundResponse
    {
        $accessGroups = $this->readAccessGroupRepository->findByContact($this->contact);
        $dashboard = $this->readDashboardRepository->findOneByAccessGroups($dashboardId, $accessGroups);

        if (null === $dashboard) {
            return new NotFoundResponse('Dashboard');
        }

        return $this->createResponse($dashboard);
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
     * @param Dashboard $dashboard
     *
     * @return FindDashboardResponse
     */
    private function createResponse(Dashboard $dashboard): FindDashboardResponse
    {
        $response = new FindDashboardResponse();

        $response->id = $dashboard->getId();
        $response->name = $dashboard->getName();
        $response->description = $dashboard->getDescription();
        $response->createdAt = $dashboard->getCreatedAt();
        $response->updatedAt = $dashboard->getUpdatedAt();

        return $response;
    }
}
