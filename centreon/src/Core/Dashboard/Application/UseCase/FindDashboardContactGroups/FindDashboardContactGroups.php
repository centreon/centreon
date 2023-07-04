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
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Contact\Domain\Model\ContactGroup;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\UseCase\FindDashboardContactGroups\Response\ContactGroupsResponseDto;
use Core\Dashboard\Domain\Model\DashboardRights;

final class FindDashboardContactGroups
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadContactGroupRepositoryInterface $readContactGroupRepository,
        private readonly DashboardRights $rights,
        private readonly ContactInterface $contact
    ) {
    }

    public function __invoke(FindDashboardContactGroupsPresenterInterface $presenter): void
    {
        try {
            if ($this->rights->canAccess()) {
                $users = $this->contact->isAdmin()
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
     * @return array<ContactGroup>
     */
    private function findContactGroupsAsAdmin(): array
    {
        return $this->readContactGroupRepository->findAll();
    }

    /**
     * @throws \Throwable
     *
     * @return array<ContactGroup>
     */
    private function findContactGroupsAsContact(): array
    {
        return $this->readContactGroupRepository->findAllByUserId($this->contact->getId());
    }

    /**
     * @param ContactGroup[] $groups
     */
    private function createResponse(array $groups): FindDashboardContactGroupsResponse
    {
        $response = new FindDashboardContactGroupsResponse();

        foreach ($groups as $group) {
            $response->contactGroups[] = new ContactGroupsResponseDto(
                $group->getId(),
                $group->getName(),
            );
        }

        return $response;
    }
}
