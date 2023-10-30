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

namespace Core\ServiceGroup\Application\UseCase\DeleteServiceGroup;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\ServiceGroup\Application\Exception\ServiceGroupException;
use Core\ServiceGroup\Application\Repository\ReadServiceGroupRepositoryInterface;
use Core\ServiceGroup\Application\Repository\WriteServiceGroupRepositoryInterface;
use Throwable;

final class DeleteServiceGroup
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadServiceGroupRepositoryInterface $readServiceGroupRepository,
        private readonly WriteServiceGroupRepositoryInterface $writeServiceGroupRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ContactInterface $contact
    ) {
    }

    public function __invoke(int $serviceGroupId, PresenterInterface $presenter): void
    {
        try {
            if ($this->contact->isAdmin()) {
                $presenter->setResponseStatus($this->deleteServiceGroupAsAdmin($serviceGroupId));
            } elseif ($this->contactCanExecuteThisUseCase()) {
                $presenter->setResponseStatus($this->deleteServiceGroupAsContact($serviceGroupId));
            } else {
                $this->error(
                    "User doesn't have sufficient rights to see service groups",
                    ['user_id' => $this->contact->getId()]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(ServiceGroupException::accessNotAllowedForWriting())
                );
            }
        } catch (Throwable $ex) {
            $presenter->setResponseStatus(new ErrorResponse(ServiceGroupException::errorWhileDeleting()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @param int $serviceGroupId
     *
     * @throws Throwable
     *
     * @return ResponseStatusInterface
     */
    private function deleteServiceGroupAsAdmin(int $serviceGroupId): ResponseStatusInterface
    {
        if ($this->readServiceGroupRepository->existsOne($serviceGroupId)) {
            $this->writeServiceGroupRepository->deleteServiceGroup($serviceGroupId);

            return new NoContentResponse();
        }

        $this->warning('Service group (%s) not found', ['id' => $serviceGroupId]);

        return new NotFoundResponse('Service group');
    }

    /**
     * @param int $serviceGroupId
     *
     * @throws Throwable
     *
     * @return ResponseStatusInterface
     */
    private function deleteServiceGroupAsContact(int $serviceGroupId): ResponseStatusInterface
    {
        $accessGroups = $this->readAccessGroupRepository->findByContact($this->contact);

        if ($this->readServiceGroupRepository->existsOneByAccessGroups($serviceGroupId, $accessGroups)) {
            $this->writeServiceGroupRepository->deleteServiceGroup($serviceGroupId);

            return new NoContentResponse();
        }

        $this->warning('Service group (%s) not found', ['id' => $serviceGroupId]);

        return new NotFoundResponse('Service group');
    }

    /**
     * @return bool
     */
    private function contactCanExecuteThisUseCase(): bool
    {
        return $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_SERVICES_SERVICE_GROUPS_READ_WRITE);
    }
}
