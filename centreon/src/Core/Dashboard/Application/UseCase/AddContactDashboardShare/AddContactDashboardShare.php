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

namespace Core\Dashboard\Application\UseCase\AddContactDashboardShare;

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Contact\Interfaces\ContactRepositoryInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Application\Repository\ReadDashboardShareRepositoryInterface;
use Core\Dashboard\Application\Repository\WriteDashboardShareRepositoryInterface;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Domain\Model\Share\DashboardSharingRoles;

final class AddContactDashboardShare
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadDashboardRepositoryInterface $readDashboardRepository,
        private readonly ReadDashboardShareRepositoryInterface $readDashboardShareRepository,
        private readonly WriteDashboardShareRepositoryInterface $writeDashboardShareRepository,
        private readonly ContactRepositoryInterface $contactRepository,
        private readonly DashboardRights $rights,
        private readonly ContactInterface $contact
    )
    {
    }

    public function __invoke(
        int $dashboardId,
        AddContactDashboardShareRequest $request,
        AddContactDashboardSharePresenterInterface $presenter
    ): void
    {
        try {
            if ($this->rights->hasAdminRole()) {
                if ($dashboard = $this->readDashboardRepository->findOne($dashboardId)) {
                    $this->info('Add a contact share for dashboard', ['id' => $dashboardId, 'contact_id' => $request->id]);
                    $response = $this->addContactShareAsAdmin($dashboard, $request);
                } else {
                    $this->warning('Dashboard (%s) not found', ['id' => $dashboardId]);
                    $response = new NotFoundResponse('Dashboard');
                }
            } elseif ($this->rights->canAccess()) {
                if ($dashboard = $this->readDashboardRepository->findOneByContact($dashboardId, $this->contact)) {
                    $this->info('Add a contact share for dashboard', ['id' => $dashboardId, 'contact_id' => $request->id]);
                    $response = $this->addContactShareAsContact($dashboard, $request);
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
            $presenter->presentResponse(new ErrorResponse('Error while adding the share of dashboard'));
        }
    }

    /**
     * @param Dashboard $dashboard
     * @param AddContactDashboardShareRequest $request
     *
     * @throws DashboardException
     * @throws \Throwable
     *
     * @return AddContactDashboardShareResponse
     */
    private function addContactShareAsAdmin(
        Dashboard $dashboard,
        AddContactDashboardShareRequest $request
    ): AddContactDashboardShareResponse
    {
        $contact = $this->getContactById($request->id);

        $this->writeDashboardShareRepository->upsertShareWithContact(
            $contact->getId(),
            $dashboard->getId(),
            $request->role
        );

        // We retrieve it from repository
        $sharingRoles = $this->readDashboardShareRepository->getOneSharingRoles($contact, $dashboard);

        return $this->createResponse($sharingRoles);
    }

    /**
     * @param Dashboard $dashboard
     * @param AddContactDashboardShareRequest $request
     *
     * @throws DashboardException
     * @throws \Throwable
     *
     * @return AddContactDashboardShareResponse|ResponseStatusInterface
     */
    private function addContactShareAsContact(
        Dashboard $dashboard,
        AddContactDashboardShareRequest $request
    ): AddContactDashboardShareResponse|ResponseStatusInterface
    {
        $sharingRoles = $this->readDashboardShareRepository->getOneSharingRoles($this->contact, $dashboard);
        if (! $this->rights->canCreateShare($sharingRoles)) {
            return new ForbiddenResponse(
                DashboardException::dashboardAccessRightsNotAllowedForWriting($dashboard->getId())
            );
        }

        $contact = $this->getContactById($request->id);

        $this->writeDashboardShareRepository->upsertShareWithContact(
            $contact->getId(),
            $dashboard->getId(),
            $request->role
        );

        // We retrieve it from repository
        $sharingRoles = $this->readDashboardShareRepository->getOneSharingRoles($contact, $dashboard);

        return $this->createResponse($sharingRoles);
    }

    /**
     * @param DashboardSharingRoles $sharingRoles
     *
     * @throws DashboardException
     *
     * @return AddContactDashboardShareResponse
     */
    private function createResponse(DashboardSharingRoles $sharingRoles): AddContactDashboardShareResponse
    {
        if ($contact = $sharingRoles->getContactShare()) {
            $dto = new AddContactDashboardShareResponse();
            $dto->id = $contact->getContactId();
            $dto->name = $contact->getContactName();
            $dto->email = $contact->getContactEmail();
            $dto->role = $contact->getRole();

            return $dto;
        }

        throw DashboardException::errorWhileRetrievingJustCreatedShare();
    }

    /**
     * @param int $contactId
     *
     * @throws DashboardException
     *
     * @return ContactInterface
     */
    private function getContactById(int $contactId): ContactInterface
    {
        if ($contact = $this->contactRepository->findById($contactId)) {
            return $contact;
        }

        $this->warning('Contact (%s) not found', ['id' => $contactId]);

        throw DashboardException::theContactDoesNotExist($contactId);
    }
}
