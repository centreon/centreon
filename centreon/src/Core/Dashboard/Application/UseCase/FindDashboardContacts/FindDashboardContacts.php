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

namespace Core\Dashboard\Application\UseCase\FindDashboardContacts;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Configuration\User\Repository\ReadUserRepositoryInterface;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Application\Repository\ReadDashboardShareRepositoryInterface;
use Core\Dashboard\Application\UseCase\FindDashboardContacts\Response\ContactsResponseDto;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Domain\Model\Role\DashboardContactRole;
use Core\Dashboard\Infrastructure\Repository\DbReadDashboardShareRepository;
use Core\Domain\Configuration\User\Model\User;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

final class FindDashboardContacts
{
    use LoggerTrait;

    /**
     * @param ReadUserRepositoryInterface $readUserRepository
     * @param RequestParametersInterface $requestParameters
     * @param DashboardRights $rights
     * @param ContactInterface $contact
     * @param ReadDashboardShareRepositoryInterface $readDashboardShareRepository
     * @param ReadAccessGroupRepositoryInterface $readAccessGroupRepository
     */
    public function __construct(
        private readonly ReadUserRepositoryInterface $readUserRepository,
        private readonly RequestParametersInterface $requestParameters,
        private readonly DashboardRights $rights,
        private readonly ContactInterface $contact,
        private readonly ReadDashboardShareRepositoryInterface $readDashboardShareRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository
    ) {
    }

    /**
     * @param FindDashboardContactsPresenterInterface $presenter
     */
    public function __invoke(FindDashboardContactsPresenterInterface $presenter): void
    {
        try {
            if ($this->rights->canAccess()) {
                $this->info('Find dashboard contacts', ['request' => $this->requestParameters->toArray()]);
                $this->contact->isAdmin()
                    ? $users = $this->readDashboardShareRepository->findContactsWithAccessRightByRequestParameters(
                        $this->requestParameters
                    )
                    : $users = $this->findContactsAsNonAdmin();
                $presenter->presentResponse($this->createResponse($users));
            } else {
                $this->error(
                    "User doesn't have sufficient rights to see dashboards",
                    ['user_id' => $this->contact->getId()]
                );
                $presenter->presentResponse(new ForbiddenResponse(DashboardException::accessNotAllowed()));
            }
        } catch (\UnexpectedValueException $ex) {
            $presenter->presentResponse(new ErrorResponse($ex->getMessage()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            }
        catch (\Throwable $ex) {
            $presenter->presentResponse(new ErrorResponse(DashboardException::errorWhileRetrieving()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @param DashboardContactRole[] $users
     */
    private function createResponse(array $users): FindDashboardContactsResponse
    {
        $response = new FindDashboardContactsResponse();

        foreach ($users as $user) {
            $response->contacts[] = new ContactsResponseDto(
                $user->getContactId(),
                $user->getContactName(),
                $user->getContactEmail(),
                $user->getMostPermissiveRole()
            );
        }

        return $response;
    }

    /**
     * Find contacts with their Dashboards roles.
     *
     * @return DashboardContactRole[]
     *
     * @throws \Throwable
     */
    private function findContactsAsNonAdmin(): array
    {
        $accessGroups = $this->readAccessGroupRepository->findByContact($this->contact);
        $accessGroupIds = array_map(static fn (AccessGroup $accessGroup): int => $accessGroup->getId(), $accessGroups);

        return $this->readDashboardShareRepository->findContactsWithAccessRightByACLGroupsAndRequestParameters(
            $this->requestParameters,
            $accessGroupIds
        );
    }
}
