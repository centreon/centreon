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

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Application\Repository\WriteDashboardRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Throwable;

final class DeleteDashboard
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadDashboardRepositoryInterface $readDashboardRepository,
        private readonly WriteDashboardRepositoryInterface $writeDashboardRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ContactInterface $contact
    ) {
    }

    public function __invoke(int $dashboardId, DeleteDashboardPresenterInterface $presenter): void
    {
        try {
            if ($this->contact->isAdmin()) {
                $presenter->presentResponse($this->deleteDashboardAsAdmin($dashboardId));
            } elseif ($this->contactCanExecuteThisUseCase()) {
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
        $accessGroups = $this->readAccessGroupRepository->findByContact($this->contact);

        if ($this->readDashboardRepository->existsOneByAccessGroups($dashboardId, $accessGroups)) {
            $this->writeDashboardRepository->delete($dashboardId);

            return new NoContentResponse();
        }

        $this->warning('Dashboard (%s) not found', ['id' => $dashboardId]);

        return new NotFoundResponse('Dashboard');
    }

    private function contactCanExecuteThisUseCase(): bool
    {
        return $this->contact->hasTopologyRole(Contact::ROLE_HOME_DASHBOARD_WRITE);
    }
}
