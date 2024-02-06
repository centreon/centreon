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

namespace Core\Dashboard\Application\UseCase\ShareDashboard;

use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\WriteDashboardShareRepositoryInterface;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Domain\Model\Role\TinyRole;
use Core\Dashboard\Infrastructure\Model\DashboardSharingRoleConverter;

final class ShareDashboard
{
    use LoggerTrait;

    public function __construct(
        private readonly DashboardRights $rights,
        private readonly ShareDashboardValidator $validator,
        private readonly WriteDashboardShareRepositoryInterface $writeDashboardShareRepository,
    ) {
    }

    public function __invoke(ShareDashboardRequest $request, ShareDashboardPresenterInterface $presenter): void
    {
        try {
            if (! $this->rights->canCreate()) {
                $this->error("User doesn't have sufficient rights to add shares on dashboards");
                $presenter->presentResponse(
                    new ForbiddenResponse(DashboardException::accessNotAllowedForWriting())
                );

                return;
            }

            $this->validator->validateDashboard($request->dashboardId);
            $this->validator->validateContacts($request->contacts);
            $contactRoles = [];
            foreach ($request->contacts as $contact) {
                $contactRoles[] = new TinyRole(
                    $contact['id'],
                    DashboardSharingRoleConverter::fromString($contact['role'])
                );
            }
            $this->validator->validateContactGroups($request->contactGroups);
            $contactGroupRoles = [];
            foreach ($request->contactGroups as $contactGroup) {
                $contactGroupRoles[] = new TinyRole(
                    $contactGroup['id'],
                    DashboardSharingRoleConverter::fromString($contactGroup['role'])
                );
            }
            if ($this->rights->hasAdminRole()) {
                $this->updateDashboardSharesAsAdmin($request->dashboardId, $contactRoles, $contactGroupRoles);
                $presenter->presentResponse(new NoContentResponse());

                return;
            }

            $this->updateDashboardSharesAsNonAdmin($request->dashboardId, $contactRoles, $contactGroupRoles);
            $presenter->presentResponse(new NoContentResponse());
        } catch(DashboardException $ex) {
            return;
        } catch (\Throwable $ex) {
            $this->error(DashboardException::errorWhileUpdating()->getMessage());
        }
    }

    private function updateDashboardSharesAsAdmin(int $dashboardId, array $contactRoles, array $contactGroupRoles): void {
        $this->writeDashboardShareRepository->deleteDashboardShares($request->dashboardId);
        $this->writeDashboardShareRepository->addDashboardContactShares($request->dashboardId, $request->contacts);
        $this->writeDashboardShareRepository->addDashboardContactGroupShares(
            $request->dashboardId,
            $request->contactGroups
        );
    }

    private function updateDashboardSharesAsNonAdmin(int $dashboardId, array $contactRoles, array $contactGroupRoles): void {
        $this->writeDashboardShareRepository->deleteDashboardSharesByContactIds(
            $playlistId,
            $contactsInUserContactGroups
        );
        $this->writeDashboardShareRepository->deleteDashboardSharesByContactGroupIds(
            $playlistId,
            $userContactGroups
        );
        $this->writeDashboardShareRepository->addDashboardContactShares($request->dashboardId, $request->contacts);
        $this->writeDashboardShareRepository->addDashboardContactGroupShares(
            $request->dashboardId,
            $request->contactGroups
        );
    }
}