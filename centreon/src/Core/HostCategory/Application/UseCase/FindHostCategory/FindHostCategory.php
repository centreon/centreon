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

namespace Core\HostCategory\Application\UseCase\FindHostCategory;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\HostCategory\Application\Exception\HostCategoryException;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\HostCategory\Domain\Model\HostCategory;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

final class FindHostCategory
{
    use LoggerTrait;

    /**
     * @param ReadHostCategoryRepositoryInterface $readHostCategoryRepository
     * @param ReadAccessGroupRepositoryInterface $readAccessGroupRepositoryInterface
     * @param ContactInterface $user
     */
    public function __construct(
        private ReadHostCategoryRepositoryInterface $readHostCategoryRepository,
        private ReadAccessGroupRepositoryInterface $readAccessGroupRepositoryInterface,
        private ContactInterface $user
    ) {
    }

    /**
     * @param int $hostCategoryId
     * @param PresenterInterface $presenter
     */
    public function __invoke(int $hostCategoryId, PresenterInterface $presenter): void
    {
        try {
            if ($this->user->isAdmin()) {
                if (! $this->readHostCategoryRepository->exists($hostCategoryId)) {
                    $this->error('Host category not found', [
                        'hostcategory_id' => $hostCategoryId,
                    ]);
                    $presenter->setResponseStatus(new NotFoundResponse('Host category'));

                    return;
                }

                $this->retrieveObjectAndSetResponse($presenter, $hostCategoryId);
            } elseif (
                $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_CATEGORIES_READ)
                || $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_CATEGORIES_READ_WRITE)
            ) {
                $this->debug(
                    'User is not admin, use ACLs to retrieve a host category',
                    ['user_id' => $this->user->getId()]
                );
                $accessGroups = $this->readAccessGroupRepositoryInterface->findByContact($this->user);

                if (! $this->readHostCategoryRepository->existsByAccessGroups($hostCategoryId, $accessGroups)) {
                    $this->error('Host category not found', [
                        'hostcategory_id' => $hostCategoryId,
                        'accessgroups' => $accessGroups,
                    ]);
                    $presenter->setResponseStatus(new NotFoundResponse('Host category'));

                    return;
                }

                $this->retrieveObjectAndSetResponse($presenter, $hostCategoryId);
            } else {
                $this->error('User doesn\'t have sufficient rights to see a host category', [
                    'user_id' => $this->user->getId(),
                    'host_category_id' => $hostCategoryId,
                ]);
                $presenter->setResponseStatus(
                    new ForbiddenResponse(HostCategoryException::accessNotAllowed())
                );
            }
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(
                new ErrorResponse(HostCategoryException::findHostCategory($ex, $hostCategoryId))
            );
            $this->error($ex->getMessage());
        }
    }

    /**
     * @param HostCategory $hostCategory
     *
     * @return FindHostCategoryResponse
     */
    private function createResponse(
        HostCategory $hostCategory,
    ): FindHostCategoryResponse {
        $response = new FindHostCategoryResponse();

        $response->id = $hostCategory->getId();
        $response->name = $hostCategory->getName();
        $response->alias = $hostCategory->getAlias();
        $response->isActivated = $hostCategory->isActivated();
        $response->comment = $hostCategory->getComment();

        return $response;
    }

    /**
     * Retrieve host category and set response with object or error if retrieving fails.
     *
     * @param PresenterInterface $presenter
     * @param int $hostCategoryId
     */
    private function retrieveObjectAndSetResponse(PresenterInterface $presenter, int $hostCategoryId): void
    {
        $hostCategory = $this->readHostCategoryRepository->findById($hostCategoryId);
        if (! $hostCategory) {
            $presenter->setResponseStatus(
                new ErrorResponse(HostCategoryException::errorWhileRetrievingObject())
            );

            return;
        }
        $presenter->present($this->createResponse($hostCategory));
    }
}
