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

namespace Core\Dashboard\Application\UseCase\PartialUpdateContactDashboardShare;

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Contact\Interfaces\ContactRepositoryInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Common\Application\Type\NoValue;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Application\Repository\ReadDashboardShareRepositoryInterface;
use Core\Dashboard\Application\Repository\WriteDashboardShareRepositoryInterface;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Dashboard\Domain\Model\DashboardRights;

final class PartialUpdateContactDashboardShare
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadDashboardRepositoryInterface $readDashboardRepository,
        private readonly ReadDashboardShareRepositoryInterface $readDashboardShareRepository,
        private readonly WriteDashboardShareRepositoryInterface $writeDashboardShareRepository,
        private readonly ContactRepositoryInterface $contactRepository,
        private readonly DashboardRights $rights,
        private readonly ContactInterface $contact
    ) {
    }

    public function __invoke(
        int $dashboardId,
        int $contactId,
        PartialUpdateContactDashboardShareRequest $request,
        PartialUpdateContactDashboardSharePresenterInterface $presenter
    ): void {
        try {
            if ($this->rights->hasAdminRole()) {
                $dashboard = $this->readDashboardRepository->findOne($dashboardId);
                $contact = $this->contactRepository->findById($contactId);

                if (null === $dashboard) {
                    $this->warning('Dashboard (%s) not found', ['id' => $dashboardId]);
                    $response = new NotFoundResponse('Dashboard');
                } elseif (null === $contact) {
                    $this->warning('Contact group (%s) not found', ['id' => $contactId]);
                    $response = new NotFoundResponse('Contact');
                } else {
                    $this->info('Update a contact share for dashboard', ['id' => $dashboardId, 'contact_id' => $contactId]);
                    $response = $this->updateContactShareAsAdmin($dashboard, $contact, $request);
                }
            } elseif ($this->rights->canAccess()) {
                $dashboard = $this->readDashboardRepository->findOneByContact($dashboardId, $this->contact);
                $contact = $this->contactRepository->findById($contactId);

                if (null === $dashboard) {
                    $this->warning('Dashboard (%s) not found', ['id' => $dashboardId]);
                    $response = new NotFoundResponse('Dashboard');
                } elseif (null === $contact) {
                    $this->warning('Contact group (%s) not found', ['id' => $contactId]);
                    $response = new NotFoundResponse('Contact');
                }else {
                    $this->info('Update a contact share for dashboard', ['id' => $dashboardId, 'contact_id' => $contactId]);
                    $response = $this->updateContactShareAsContact($dashboard, $contact, $request);
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
            $presenter->presentResponse(new ErrorResponse('Error while updating the dashboard share'));
        }
    }

    /**
     * @param Dashboard $dashboard
     * @param Contact $contact
     * @param PartialUpdateContactDashboardShareRequest $request
     *
     * @throws \Throwable
     *
     * @return ResponseStatusInterface
     */
    private function updateContactShareAsAdmin(
        Dashboard $dashboard,
        Contact $contact,
        PartialUpdateContactDashboardShareRequest $request
    ): ResponseStatusInterface {
        if (! ($request->role instanceof NoValue)) {
            $updated = $this->writeDashboardShareRepository->updateContactShare($contact->getId(), $dashboard->getId(), $request->role);

            return $updated
                ? new NoContentResponse()
                : new NotFoundResponse('Dashboard share');
        }

        return new NoContentResponse();
    }

    /**
     * @param Dashboard $dashboard
     * @param Contact $contact
     * @param PartialUpdateContactDashboardShareRequest $request
     *
     * @throws \Throwable
     *
     * @return ResponseStatusInterface
     */
    private function updateContactShareAsContact(
        Dashboard $dashboard,
        Contact $contact,
        PartialUpdateContactDashboardShareRequest $request
    ): ResponseStatusInterface {
        $sharingRoles = $this->readDashboardShareRepository->getOneSharingRoles($this->contact, $dashboard);
        if (! $this->rights->canUpdateShare($sharingRoles)) {
            return new ForbiddenResponse(
                DashboardException::dashboardAccessRightsNotAllowedForWriting($dashboard->getId())
            );
        }

        if (! ($request->role instanceof NoValue)) {
            $updated = $this->writeDashboardShareRepository->updateContactShare($contact->getId(), $dashboard->getId(), $request->role);

            return $updated
                ? new NoContentResponse()
                : new NotFoundResponse('Dashboard share');
        }

        return new NoContentResponse();
    }
}
