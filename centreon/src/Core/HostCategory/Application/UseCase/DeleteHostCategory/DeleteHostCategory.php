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
use Core\HostCategory\Application\Exception\HostCategoryException;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\HostCategory\Application\Repository\WriteHostCategoryRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

final class DeleteHostCategory
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
                if ($this->readHostCategoryRepository->exists($hostCategoryId)) {
                    $this->writeHostCategoryRepository->deleteById($hostCategoryId);
                    $presenter->setResponseStatus(new NoContentResponse());
                } else {
                    $this->error('Host category not found', [
                        'hostcategory_id' => $hostCategoryId,
                    ]);
                    $presenter->setResponseStatus(new NotFoundResponse('Host category'));
                }
            } elseif ($this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_CATEGORIES_READ_WRITE)) {
                $accessGroups = $this->readAccessGroupRepositoryInterface->findByContact($this->user);

                if ($this->readHostCategoryRepository->existsByAccessGroups($hostCategoryId, $accessGroups)) {
                    $this->writeHostCategoryRepository->deleteById($hostCategoryId);
                    $presenter->setResponseStatus(new NoContentResponse());
                } else {
                    $this->error('Host category not found', [
                        'hostcategory_id' => $hostCategoryId,
                        'accessgroups' => $accessGroups
                    ]);
                    $presenter->setResponseStatus(new NotFoundResponse('Host category'));
                }
            } else {
                $this->error('User doesn\'t have sufficient rights to see host category', [
                    'user_id' => $this->user->getId(),
                ]);
                $presenter->setResponseStatus(
                    new ForbiddenResponse(HostCategoryException::deleteNotAllowed()->getMessage())
                );
            }
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(
                new ErrorResponse(HostCategoryException::deleteHostCategory($ex)->getMessage())
            );
            $this->error($ex->getMessage());
        }
    }
}
