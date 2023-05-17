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

namespace Core\Dashboard\Application\UseCase\UpdateDashboard;

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Application\Repository\WriteDashboardRepositoryInterface;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

final class UpdateDashboard
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadDashboardRepositoryInterface $readDashboardRepository,
        private readonly WriteDashboardRepositoryInterface $writeDashboardRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ContactInterface $contact
    ) {
    }

    /**
     * @param int $dashboardId
     * @param UpdateDashboardRequest $request
     * @param UpdateDashboardPresenterInterface $presenter
     */
    public function __invoke(
        int $dashboardId,
        UpdateDashboardRequest $request,
        UpdateDashboardPresenterInterface $presenter
    ): void {
        try {
            if ($this->contact->isAdmin()) {
                $response = $this->updateDashboardAsAdmin($dashboardId, $request);
            } elseif ($this->contactCanPerformWriteOperations()) {
                $response = $this->updateDashboardAsContact($dashboardId, $request);
            } else {
                $response = new ForbiddenResponse(DashboardException::accessNotAllowedForWriting());
            }

            if ($response instanceof NoContentResponse) {
                $presenter->presentResponse($response);
                $this->info('Update dashboard', ['request' => $request]);
            } elseif ($response instanceof NotFoundResponse) {
                $presenter->presentResponse($response);
                $this->warning('Dashboard (%s) not found', ['id' => $dashboardId]);
            } else {
                $presenter->presentResponse($response);
                $this->error(
                    "User doesn't have sufficient rights to update dashboards",
                    ['user_id' => $this->contact->getId()]
                );
            }
        } catch (AssertionFailedException $ex) {
            $presenter->presentResponse(new InvalidArgumentResponse($ex));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (DashboardException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->presentResponse(new ErrorResponse($ex));
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->presentResponse(new ErrorResponse(DashboardException::errorWhileUpdating()));
        }
    }

    /**
     * @param UpdateDashboardRequest $request
     * @param int $dashboardId
     *
     * @throws DashboardException
     * @throws \Throwable
     *
     * @return NoContentResponse|NotFoundResponse
     */
    private function updateDashboardAsAdmin(
        int $dashboardId,
        UpdateDashboardRequest $request
    ): NoContentResponse|NotFoundResponse {
        $dashboard = $this->readDashboardRepository->findOne($dashboardId);
        if (null === $dashboard) {
            return new NotFoundResponse('Dashboard');
        }

        $this->updateDashboardAndSave($dashboard, $request);

        return new NoContentResponse();
    }

    /**
     * @param UpdateDashboardRequest $request
     * @param int $dashboardId
     *
     * @throws DashboardException
     * @throws \Throwable
     *
     * @return NoContentResponse|NotFoundResponse
     */
    private function updateDashboardAsContact(
        int $dashboardId,
        UpdateDashboardRequest $request
    ): NoContentResponse|NotFoundResponse {
        $accessGroups = $this->readAccessGroupRepository->findByContact($this->contact);
        $dashboard = $this->readDashboardRepository->findOneByAccessGroups($dashboardId, $accessGroups);
        if (null === $dashboard) {
            return new NotFoundResponse('Dashboard');
        }

        $this->updateDashboardAndSave($dashboard, $request);

        return new NoContentResponse();
    }

    private function contactCanPerformWriteOperations(): bool
    {
        return $this->contact->hasTopologyRole(Contact::ROLE_HOME_DASHBOARD_WRITE);
    }

    /**
     * @param Dashboard $dashboard
     * @param UpdateDashboardRequest $request
     *
     * @throws AssertionFailedException
     */
    private function updateDashboardAndSave(Dashboard $dashboard, UpdateDashboardRequest $request): void
    {
        $updatedDashboard = new Dashboard(
            id: $dashboard->getId(),
            name: $request->name,
            description: $request->description,
            createdAt: $dashboard->getCreatedAt(),
            updatedAt: new \DateTimeImmutable(),
        );

        $this->writeDashboardRepository->update($updatedDashboard);
    }
}
