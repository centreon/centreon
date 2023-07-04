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
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Configuration\User\Repository\ReadUserRepositoryInterface;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\UseCase\FindDashboardContacts\Response\ContactsResponseDto;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Domain\Configuration\User\Model\User;

final class FindDashboardContacts
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadUserRepositoryInterface $readUserRepository,
        private readonly DashboardRights $rights,
        private readonly ContactInterface $contact
    )
    {
    }

    public function __invoke(FindDashboardContactsPresenterInterface $presenter): void
    {
        try {
            if ($this->rights->canAccess()) {
                $users = $this->readUserRepository->findAllUsers();
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
     * @param User[] $users
     */
    private function createResponse(array $users): FindDashboardContactsResponse
    {
        $response = new FindDashboardContactsResponse();

        foreach ($users as $user) {
            $response->contacts[] = new ContactsResponseDto(
                $user->getId(),
                $user->getName(),
            );
        }

        return $response;
    }
}
