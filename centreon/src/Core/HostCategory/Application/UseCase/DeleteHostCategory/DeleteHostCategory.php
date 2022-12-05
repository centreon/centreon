<?php

/*
* Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
*
* Licensed under the Apache License, Version 2.0 (the "License");
* you may not use this file except in compliance with the License.
* You may obtain a copy of the License at
*
* http://www.apache.org/licenses/LICENSE-2.0
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

namespace Core\HostCategory\Application\UseCase\DeleteHostCategory;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\HostCategory\Application\Repository\WriteHostCategoryRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

class DeleteHostCategory
{
    use LoggerTrait;

    public function __construct(
        private WriteHostCategoryRepositoryInterface $writeHostCategoryRepository,
        private ReadHostCategoryRepositoryInterface $readHostCategoryRepository,
        private ReadAccessGroupRepositoryInterface $readAccessGroupRepositoryInterface,
        private ContactInterface $user
    ) {
    }

    public function __invoke(int $hostCategoryId, PresenterInterface $presenter): void
    {
        try {
            if ($this->user->isAdmin()) {
                if (! $this->doesHostCategoryExist($hostCategoryId)) {
                    $this->error('Host category not found', [
                        'hostcategory_id' => $hostCategoryId,
                    ]);
                    $presenter->setResponseStatus(
                        new NotFoundResponse('Host category')
                    );

                    return;
                }
                $this->writeHostCategoryRepository->deleteById($hostCategoryId);
            } else {
                if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_CATEGORIES_READ_WRITE)) {
                    $this->error('User doesn\'t have sufficient right to see host category', [
                        'user_id' => $this->user->getId(),
                    ]);
                    $presenter->setResponseStatus(
                        new ForbiddenResponse('You are not allowed to delete host categories')
                    );

                    return;
                }

                $accessGroups = $this->readAccessGroupRepositoryInterface->findByContact($this->user);

                if (! $this->doesHostCategoryExist($hostCategoryId, $accessGroups)) {
                    $this->error('Host category not found', [
                        'hostcategory_id' => $hostCategoryId,
                        'accessgroups' => $accessGroups
                    ]);
                    $presenter->setResponseStatus(
                        new NotFoundResponse('Host category')
                    );

                    return;
                }

                $this->writeHostCategoryRepository->deleteById($hostCategoryId);
            }

            $presenter->setResponseStatus(new NoContentResponse());
        } catch (\Throwable $th) {
            $presenter->setResponseStatus(new ErrorResponse('Error while deleting host category'));
            // TODO : translate error messages
            $this->error($th->getMessage());
        }
    }

    /**
     * @param int $hostCategoryId
     * @param AccessGroup[]|null $accessGroups
     * @return bool
     */
    private function doesHostCategoryExist(int $hostCategoryId, ?array $accessGroups = null): bool
    {
        $hostCategory = $accessGroups === null
        ? $this->readHostCategoryRepository->findById($hostCategoryId)
        : $this->readHostCategoryRepository->findByIdAndAccessGroups($hostCategoryId, $accessGroups);

        return (bool) ($hostCategory ?? false);
    }
}
