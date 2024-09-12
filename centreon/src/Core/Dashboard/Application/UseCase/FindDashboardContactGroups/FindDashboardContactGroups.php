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

namespace Core\Dashboard\Application\UseCase\FindDashboardContactGroups;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardShareRepositoryInterface;
use Core\Dashboard\Application\UseCase\FindDashboardContactGroups\Response\ContactGroupsResponseDto;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Domain\Model\Role\DashboardContactGroupRole;

final class FindDashboardContactGroups
{
    use LoggerTrait;

    public function __construct(
        private readonly RequestParametersInterface $requestParameters,
        private readonly DashboardRights $rights,
        private readonly ContactInterface $contact,
        private readonly ReadDashboardShareRepositoryInterface $readDashboardShareRepository,
    ) {
    }

    public function __invoke(FindDashboardContactGroupsPresenterInterface $presenter): void
    {
        try {
            if ($this->rights->canAccess()) {
                $this->info('Find dashboard contact groups', ['request' => $this->requestParameters->toArray()]);
                $users = $this->rights->hasAdminRole()
                    ? $this->findContactGroupsAsAdmin()
                    : $this->findContactGroupsAsContact();

                $presenter->presentResponse($this->createResponse($users));
            } else {
                $this->error(
                    "User doesn't have sufficient rights to see dashboards",
                    ['user_id' => $this->contact->getId()]
                );
                $presenter->presentResponse(new ForbiddenResponse(DashboardException::accessNotAllowed()));
            }
        } catch (\Throwable $ex) {
            $presenter->presentResponse(new ErrorResponse(DashboardException::errorWhileRetrieving()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @throws \Throwable
     *
     * @return DashboardContactGroupRole[]
     */
    private function findContactGroupsAsAdmin(): array
    {
        return $this->readDashboardShareRepository->findContactGroupsWithAccessRightByRequestParameters(
            $this->requestParameters
        );
    }

    /**
     * @throws \Throwable
     *
     * @return DashboardContactGroupRole[]
     */
    private function findContactGroupsAsContact(): array
    {
        return $this->readDashboardShareRepository->findContactGroupsWithAccessRightByUserAndRequestParameters(
            $this->requestParameters,
            $this->contact->getId()
        );
    }

    /**
     * @param DashboardContactGroupRole[] $groups
     */
    private function createResponse(array $groups): FindDashboardContactGroupsResponse
    {
        $response = new FindDashboardContactGroupsResponse();

        foreach ($groups as $group) {
            $response->contactGroups[] = new ContactGroupsResponseDto(
                $group->getContactGroupId(),
                $group->getContactGroupName(),
                $group->getMostPermissiverole()
            );
        }

        return $response;
    }
}
