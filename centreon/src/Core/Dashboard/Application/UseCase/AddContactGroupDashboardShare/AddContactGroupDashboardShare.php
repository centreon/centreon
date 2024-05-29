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

namespace Core\Dashboard\Application\UseCase\AddContactGroupDashboardShare;

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Contact\Domain\Model\ContactGroup;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Application\Repository\ReadDashboardShareRepositoryInterface;
use Core\Dashboard\Application\Repository\WriteDashboardShareRepositoryInterface;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

final class AddContactGroupDashboardShare
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadDashboardRepositoryInterface $readDashboardRepository,
        private readonly ReadDashboardShareRepositoryInterface $readDashboardShareRepository,
        private readonly WriteDashboardShareRepositoryInterface $writeDashboardShareRepository,
        private readonly ReadContactGroupRepositoryInterface $readContactGroupRepository,
        private readonly DashboardRights $rights,
        private readonly ContactInterface $contact,
        private readonly ReadAccessGroupRepositoryInterface $accessGroupRepository
    ) {
    }

    public function __invoke(
        int $dashboardId,
        AddContactGroupDashboardShareRequest $request,
        AddContactGroupDashboardSharePresenterInterface $presenter
    ): void {
        try {
            if ($this->rights->hasAdminRole()) {
                if ($dashboard = $this->readDashboardRepository->findOne($dashboardId)) {
                    $this->info(
                        'Add a contact group share for dashboard',
                        ['id' => $dashboardId, 'contact_id' => $request->id]
                    );
                    $response = $this->addContactGroupShareAsAdmin($dashboard, $request);
                } else {
                    $this->warning('Dashboard (%s) not found', ['id' => $dashboardId]);
                    $response = new NotFoundResponse('Dashboard');
                }
            } elseif ($this->rights->canAccess()) {
                if ($dashboard = $this->readDashboardRepository->findOneByContact($dashboardId, $this->contact)) {
                    $this->info(
                        'Add a contact group share for dashboard',
                        ['id' => $dashboardId, 'contact_id' => $request->id]
                    );
                    $response = $this->addContactGroupShareAsContact($dashboard, $request);
                } else {
                    $this->warning('Dashboard (%s) not found', ['id' => $dashboardId]);
                    $response = new NotFoundResponse('Dashboard');
                }
            } else {
                $this->error(
                    "User doesn't have sufficient rights to see dashboards",
                    ['user_id' => $this->contact->getId()]
                );
                $response = new ForbiddenResponse(DashboardException::accessNotAllowedForWriting());
            }

            $presenter->presentResponse($response);
        } catch (AssertionFailedException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->presentResponse(new InvalidArgumentResponse($ex));
        } catch (DashboardException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->presentResponse(new ErrorResponse($ex));
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->presentResponse(new ErrorResponse('Error while adding the dashboard share'));
        }
    }

    /**
     * @param Dashboard $dashboard
     * @param AddContactGroupDashboardShareRequest $request
     *
     * @throws \Throwable
     * @throws DashboardException
     *
     * @return AddContactGroupDashboardShareResponse
     */
    private function addContactGroupShareAsAdmin(
        Dashboard $dashboard,
        AddContactGroupDashboardShareRequest $request
    ): AddContactGroupDashboardShareResponse {
        $contactGroup = $this->getContactGroupById($request->id);

        $this->writeDashboardShareRepository->upsertShareWithContactGroup(
            $contactGroup->getId(),
            $dashboard->getId(),
            $request->role
        );

        return new AddContactGroupDashboardShareResponse(
            $contactGroup->getId(),
            $contactGroup->getName(),
            $request->role
        );
    }

    /**
     * @param Dashboard $dashboard
     * @param AddContactGroupDashboardShareRequest $request
     *
     * @throws \Throwable
     * @throws DashboardException
     *
     * @return AddContactGroupDashboardShareResponse|ResponseStatusInterface
     */
    private function addContactGroupShareAsContact(
        Dashboard $dashboard,
        AddContactGroupDashboardShareRequest $request
    ): AddContactGroupDashboardShareResponse|ResponseStatusInterface {
        $sharingRoles = $this->readDashboardShareRepository->getOneSharingRoles($this->contact, $dashboard);
        if (! $this->rights->canCreateShare($sharingRoles)) {
            return new ForbiddenResponse(
                DashboardException::dashboardAccessRightsNotAllowedForWriting($dashboard->getId())
            );
        }

        $contactGroup = $this->getContactGroupById($request->id);

        $accessGroupIds = array_map(
            static fn (AccessGroup $accessGroup): int => $accessGroup->getId(),
            $this->accessGroupRepository->findByContact($this->contact)
        );

        // Only share to contact groups that are in the same Access Groups that the current user
        if (! $this->readContactGroupRepository->existsInAccessGroups($contactGroup->getId(), $accessGroupIds)) {
            return new NotFoundResponse('Contact Group');
        }

        $this->writeDashboardShareRepository->upsertShareWithContactGroup(
            $contactGroup->getId(),
            $dashboard->getId(),
            $request->role
        );

        return new AddContactGroupDashboardShareResponse(
            $contactGroup->getId(),
            $contactGroup->getName(),
            $request->role
        );
    }

    /**
     * @param int $contactGroupId
     *
     * @throws DashboardException|\Throwable
     *
     * @return ContactGroup
     */
    private function getContactGroupById(int $contactGroupId): ContactGroup
    {
        if ($contactGroup = $this->readContactGroupRepository->find($contactGroupId)) {
            return $contactGroup;
        }

        $this->warning('Contact group (%s) not found', ['id' => $contactGroupId]);

        throw DashboardException::theContactGroupDoesNotExist($contactGroupId);
    }
}
