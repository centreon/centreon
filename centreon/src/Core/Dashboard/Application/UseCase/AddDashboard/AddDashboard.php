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

namespace Core\Dashboard\Application\UseCase\AddDashboard;

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Application\Repository\WriteDashboardRepositoryInterface;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Dashboard\Domain\Model\NewDashboard;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

final class AddDashboard
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadDashboardRepositoryInterface $readDashboardRepository,
        private readonly WriteDashboardRepositoryInterface $writeDashboardRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ContactInterface $contact
    ) {
    }

    public function __invoke(
        AddDashboardRequest $request,
        AddDashboardPresenterInterface $presenter
    ): void {
        try {
            if ($this->contact->isAdmin()) {
                $this->info('Add dashboard', ['request' => $request]);
                $presenter->presentResponse($this->addDashboardAsAdmin($request));
            } elseif ($this->contactCanPerformWriteOperations()) {
                $this->info('Add dashboard', ['request' => $request]);
                $presenter->presentResponse($this->addDashboardAsContact($request));
            } else {
                $this->error(
                    "User doesn't have sufficient rights to add dashboards",
                    ['user_id' => $this->contact->getId()]
                );
                $presenter->presentResponse(
                    new ForbiddenResponse(DashboardException::accessNotAllowedForWriting())
                );
            }
        } catch (AssertionFailedException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->presentResponse(new InvalidArgumentResponse($ex));
        } catch (DashboardException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->presentResponse(new ErrorResponse($ex));
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->presentResponse(new ErrorResponse(DashboardException::errorWhileAdding()));
        }
    }

    /**
     * @param AddDashboardRequest $request
     *
     * @throws DashboardException
     * @throws \Throwable
     *
     * @return AddDashboardResponse
     */
    private function addDashboardAsAdmin(AddDashboardRequest $request): AddDashboardResponse
    {
        $newDashboard = $this->createNewDashboard($request);
        $newDashboardId = $this->writeDashboardRepository->add($newDashboard);
        $dashboard = $this->readDashboardRepository->findOne($newDashboardId)
            ?? throw DashboardException::errorWhileRetrievingJustCreated();

        return $this->createResponse($dashboard);
    }

    /**
     * @param AddDashboardRequest $request
     *
     * @throws DashboardException
     * @throws \Throwable
     *
     * @return AddDashboardResponse
     */
    private function addDashboardAsContact(AddDashboardRequest $request): AddDashboardResponse
    {
        $accessGroups = $this->readAccessGroupRepository->findByContact($this->contact);
        $newDashboard = $this->createNewDashboard($request);

        $newDashboardId = $this->writeDashboardRepository->add($newDashboard);

        // Retrieve the Dashboard for the response.
        $dashboard = $this->readDashboardRepository->findOneByAccessGroups($newDashboardId, $accessGroups)
            ?? throw DashboardException::errorWhileRetrievingJustCreated();

        return $this->createResponse($dashboard);
    }

    /**
     * @return bool
     */
    private function contactCanPerformWriteOperations(): bool
    {
        return $this->contact->hasTopologyRole(Contact::ROLE_HOME_DASHBOARD_WRITE);
    }

    /**
     * @param AddDashboardRequest $request
     *
     * @throws AssertionFailedException
     *
     * @return NewDashboard
     */
    private function createNewDashboard(AddDashboardRequest $request): NewDashboard
    {
        $dashboard = new NewDashboard($request->name);
        $dashboard->setDescription($request->description);

        return $dashboard;
    }

    /**
     * @param Dashboard $dashboard
     *
     * @return AddDashboardResponse
     */
    private function createResponse(Dashboard $dashboard): AddDashboardResponse
    {
        $response = new AddDashboardResponse();

        $response->id = $dashboard->getId();
        $response->name = $dashboard->getName();
        $response->description = $dashboard->getDescription();
        $response->createdAt = $dashboard->getCreatedAt();
        $response->updatedAt = $dashboard->getUpdatedAt();

        return $response;
    }
}
