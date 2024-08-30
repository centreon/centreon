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

namespace Core\HostCategory\Application\UseCase\FindHostCategories;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\HostCategory\Application\Exception\HostCategoryException;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\HostCategory\Domain\Model\HostCategory;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

final class FindHostCategories
{
    use LoggerTrait;

    /**
     * @param ReadHostCategoryRepositoryInterface $readHostCategoryRepository
     * @param ReadAccessGroupRepositoryInterface $readAccessGroupRepository
     * @param RequestParametersInterface $requestParameters
     * @param ContactInterface $user
     */
    public function __construct(
        private readonly ReadHostCategoryRepositoryInterface $readHostCategoryRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly RequestParametersInterface $requestParameters,
        private readonly ContactInterface $user
    ) {
    }

    /**
     * @param PresenterInterface $presenter
     */
    public function __invoke(PresenterInterface $presenter): void
    {
        try {
            if (! $this->isAuthorized()) {
                $this->error(
                    'User doesn\'t have sufficient rights to see host categories',
                    ['user_id' => $this->user->getId()]
                );

                $presenter->setResponseStatus(
                    new ForbiddenResponse(HostCategoryException::accessNotAllowed()->getMessage())
                );

                return;
            }

            $this->info('Finding host categories');
            $presenter->present(
                $this->createResponse($this->user->isAdmin() ? $this->findAllAsAdmin() : $this->findAllAsUser())
            );
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(
                new ErrorResponse(HostCategoryException::findHostCategories($ex)->getMessage())
            );
            $this->error($ex->getMessage());
        }
    }

    /**
     * @throws \Throwable
     *
     * @return HostCategory[]
     */
    private function findAllAsAdmin(): array
    {
        $this->debug('Retrieve all categories as an admin user');

        return $this->readHostCategoryRepository->findAll($this->requestParameters);
    }

    /**
     * @throws \Throwable
     *
     * @return HostCategory[]
     */
    private function findAllAsUser(): array
    {
        $categories = [];

        $this->debug(
            'User is not admin, use ACLs to retrieve host categories',
            ['user' => $this->user->getName()]
        );

        $accessGroupIds = array_map(
            fn (AccessGroup $accessGroup) => $accessGroup->getId(),
            $this->readAccessGroupRepository->findByContact($this->user)
        );

        if ($accessGroupIds === []) {
            return $categories;
        }

        // If the current user has ACL filter on Host Categories it means that not all categories are visible so
        // we need to apply the ACL
        if ($this->readHostCategoryRepository->hasRestrictedAccessToHostCategories($accessGroupIds)) {
            $categories = $this->readHostCategoryRepository->findAllByAccessGroupIds(
                $accessGroupIds,
                $this->requestParameters
            );
        } else {
            $this->debug(
                'No ACL filter found on host categories for user. Retrieving all host categories',
                ['user' => $this->user->getName()]
            );

            $categories = $this->readHostCategoryRepository->findAll($this->requestParameters);

        }

        return $categories;
    }

    /**
     * Check if current user is authorized to perform the action.
     * Only admin users and users under ACL with access to the host categories configuration page are authorized.
     *
     * @return bool
     */
    private function isAuthorized(): bool
    {
        return $this->user->isAdmin()
            || (
                $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_CATEGORIES_READ)
                || $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_CATEGORIES_READ_WRITE)
            );
    }

    /**
     * @param HostCategory[] $hostCategories
     *
     * @return FindHostCategoriesResponse
     */
    private function createResponse(
        array $hostCategories,
    ): FindHostCategoriesResponse {
        $response = new FindHostCategoriesResponse();

        foreach ($hostCategories as $hostCategory) {
            $response->hostCategories[] = [
                'id' => $hostCategory->getId(),
                'name' => $hostCategory->getName(),
                'alias' => $hostCategory->getAlias(),
                'is_activated' => $hostCategory->isActivated(),
                'comment' => $hostCategory->getComment(),
            ];
        }

        return $response;
    }
}
