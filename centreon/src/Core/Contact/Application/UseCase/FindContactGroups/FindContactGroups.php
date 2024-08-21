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

namespace Core\Contact\Application\UseCase\FindContactGroups;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

class FindContactGroups
{
    use LoggerTrait;
    public const AUTHORIZED_ACL_GROUPS = ['customer_admin_acl'];

    /**
     * @param ReadContactGroupRepositoryInterface $repository
     * @param ContactInterface $user
     * @param ReadAccessGroupRepositoryInterface $accessGroupRepository
     * @param bool $isCloudPlatform
     */
    public function __construct(
        private readonly ReadAccessGroupRepositoryInterface $accessGroupRepository,
        private readonly ReadContactGroupRepositoryInterface $repository,
        private readonly ContactInterface $user,
        private readonly bool $isCloudPlatform
    ) {
    }

    /**
     * @param FindContactGroupsPresenterInterface $presenter
     */
    public function __invoke(FindContactGroupsPresenterInterface $presenter): void
    {
        try {
            $this->info('Find contact groups', ['user' => $this->user->getName()]);
            if ($this->isUserAdmin()) {
                $contactGroups = $this->repository->findAll();
                $presenter->present(new FindContactGroupsResponse($contactGroups));
            } elseif ($this->contactCanExecuteThisUseCase()) {
                $contactGroups = $this->repository->findAllByUserId($this->user->getId());
                $presenter->present(new FindContactGroupsResponse($contactGroups));
            } else {
                $this->error('User doesn\'t have sufficient right to see contact groups', [
                    'user_id' => $this->user->getId(),
                ]);
                $presenter->setResponseStatus(
                    new ForbiddenResponse('You are not allowed to access contact groups')
                );

                return;
            }
        } catch (\Throwable $ex) {
            $this->error(
                'An error occured in data storage while getting contact groups',
                ['trace' => $ex->getTraceAsString()]
            );
            $presenter->setResponseStatus(new ErrorResponse(
                'Impossible to get contact groups from data storage'
            ));

            return;
        }
    }

    /**
     * @return bool
     */
    private function isUserAdmin(): bool
    {
        if ($this->user->isAdmin()) {
            return true;
        }

        // this is only true on Cloud context
        $userAccessGroupNames = array_map(
            static fn (AccessGroup $accessGroup): string => $accessGroup->getName(),
            $this->accessGroupRepository->findByContact($this->user)
        );

        return ! (empty(array_intersect($userAccessGroupNames, self::AUTHORIZED_ACL_GROUPS)))
            && $this->isCloudPlatform === true;
    }

    /**
     * @return bool
     */
    private function contactCanExecuteThisUseCase(): bool
    {
        // The use case can be executed onPrem is user has access to the pages.
        // On Cloud context user does not have access to those pages so he can execute the use case in any case.
        if ($this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_USERS_CONTACT_GROUPS_READ)) {
            return true;
        }
        if ($this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_USERS_CONTACT_GROUPS_READ_WRITE)) {
            return true;
        }
        return $this->isCloudPlatform === true;
    }
}
