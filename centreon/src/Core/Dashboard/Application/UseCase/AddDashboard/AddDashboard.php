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
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Application\Repository\WriteDashboardShareRepositoryInterface;
use Core\Dashboard\Application\Repository\WriteDashboardRepositoryInterface;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Domain\Model\Role\DashboardSharingRole;

final class AddDashboard
{
    use LoggerTrait;

    private DashboardSharingRole $defaultSharingRoleOnCreate;

    public function __construct(
        private readonly ReadDashboardRepositoryInterface $readDashboardRepository,
        private readonly WriteDashboardRepositoryInterface $writeDashboardRepository,
        private readonly WriteDashboardShareRepositoryInterface $writeDashboardShareRepository,
        private readonly DataStorageEngineInterface $dataStorageEngine,
        private readonly DashboardRights $rights,
        private readonly ContactInterface $contact
    ) {
        $this->defaultSharingRoleOnCreate = DashboardSharingRole::Editor;
    }

    public function __invoke(
        AddDashboardRequest $request,
        AddDashboardPresenterInterface $presenter
    ): void {
        try {
            if ($this->rights->hasAdminRole()) {
                $this->info('Add dashboard', ['request' => $request]);
                $presenter->presentResponse($this->addDashboardAsAdmin($request));
            } elseif ($this->rights->canCreate()) {
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
     * @throws \Throwable
     * @throws DashboardException
     *
     * @return AddDashboardResponse
     */
    private function addDashboardAsAdmin(AddDashboardRequest $request): AddDashboardResponse
    {
        $newDashboardId = $this->addDashboard($request);

        $dashboard = $this->readDashboardRepository->findOne($newDashboardId)
            ?? throw DashboardException::errorWhileRetrievingJustCreated();

        return AddDashboardFactory::createResponse($dashboard, $this->contact, $this->defaultSharingRoleOnCreate);
    }

    /**
     * @param AddDashboardRequest $request
     *
     * @throws \Throwable
     * @throws DashboardException
     *
     * @return AddDashboardResponse
     */
    private function addDashboardAsContact(AddDashboardRequest $request): AddDashboardResponse
    {
        $newDashboardId = $this->addDashboard($request);

        $dashboard = $this->readDashboardRepository->findOneByContact($newDashboardId, $this->contact)
            ?? throw DashboardException::errorWhileRetrievingJustCreated();

        return AddDashboardFactory::createResponse($dashboard, $this->contact, $this->defaultSharingRoleOnCreate);
    }

    /**
     * @param AddDashboardRequest $request
     *
     * @throws \Throwable
     * @throws DashboardException
     *
     * @return int
     */
    private function addDashboard(AddDashboardRequest $request): int
    {
        $newDashboard = AddDashboardFactory::createNewDashboard($request, $this->contact);

        try {
            $this->dataStorageEngine->startTransaction();

            $newDashboardId = $this->writeDashboardRepository->add($newDashboard);

            $this->writeDashboardShareRepository->createShareWithContact(
                $this->contact->getId(),
                $newDashboardId,
                $this->defaultSharingRoleOnCreate
            );

            $this->dataStorageEngine->commitTransaction();
        } catch (\Throwable $ex) {
            $this->error("Rollback of 'Add Dashboard' transaction.");
            $this->dataStorageEngine->rollbackTransaction();

            throw $ex;
        }

        return $newDashboardId;
    }
}
