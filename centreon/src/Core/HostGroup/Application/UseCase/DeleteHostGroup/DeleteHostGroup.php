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

namespace Core\HostGroup\Application\UseCase\DeleteHostGroup;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\HostGroup\Application\Exceptions\HostGroupException;
use Core\HostGroup\Application\Repository\ReadHostGroupRepositoryInterface;
use Core\HostGroup\Application\Repository\WriteHostGroupRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Throwable;

final class DeleteHostGroup
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadHostGroupRepositoryInterface $readHostGroupRepository,
        private readonly WriteHostGroupRepositoryInterface $writeHostGroupRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ContactInterface $contact
    ) {
    }

    public function __invoke(int $hostGroupId, PresenterInterface $presenter): void
    {
        try {
            if ($this->contact->isAdmin()) {
                $presenter->setResponseStatus($this->deleteHostGroupAsAdmin($hostGroupId));
            } elseif ($this->contactCanExecuteThisUseCase()) {
                $presenter->setResponseStatus($this->deleteHostGroupAsContact($hostGroupId));
            } else {
                $this->error(
                    "User doesn't have sufficient right to see host groups",
                    ['user_id' => $this->contact->getId()]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(HostGroupException::accessNotAllowed()->getMessage())
                );
            }
        } catch (Throwable $ex) {
            $presenter->setResponseStatus(new ErrorResponse(HostGroupException::errorWhileDeleting()->getMessage()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @param int $hostGroupId
     *
     * @throws Throwable
     *
     * @return ResponseStatusInterface
     */
    private function deleteHostGroupAsAdmin(int $hostGroupId): ResponseStatusInterface
    {
        if ($this->readHostGroupRepository->existsOne($hostGroupId)) {
            $this->writeHostGroupRepository->deleteHostGroup($hostGroupId);

            return new NoContentResponse();
        }

        $this->warning('Host group (%s) not found', ['id' => $hostGroupId]);

        return new NotFoundResponse('Host group');
    }

    /**
     * @param int $hostGroupId
     *
     * @throws Throwable
     *
     * @return ResponseStatusInterface
     */
    private function deleteHostGroupAsContact(int $hostGroupId): ResponseStatusInterface
    {
        $accessGroups = $this->readAccessGroupRepository->findByContact($this->contact);

        if ($this->readHostGroupRepository->existsOneByAccessGroups($hostGroupId, $accessGroups)) {
            $this->writeHostGroupRepository->deleteHostGroup($hostGroupId);

            return new NoContentResponse();
        }

        $this->warning('Host group (%s) not found', ['id' => $hostGroupId]);

        return new NotFoundResponse('Host group');
    }

    /**
     * @return bool
     */
    private function contactCanExecuteThisUseCase(): bool
    {
        return $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_HOST_GROUPS_READ_WRITE);
    }
}
