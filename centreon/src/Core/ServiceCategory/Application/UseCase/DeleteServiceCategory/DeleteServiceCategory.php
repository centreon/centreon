<?php

/*
* Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\ServiceCategory\Application\UseCase\DeleteServiceCategory;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\ServiceCategory\Application\Exception\ServiceCategoryException;
use Core\ServiceCategory\Application\Repository\ReadServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Application\Repository\WriteServiceCategoryRepositoryInterface;

final class DeleteServiceCategory
{
    use LoggerTrait;

    public function __construct(
        private readonly WriteServiceCategoryRepositoryInterface $writeServiceCategoryRepository,
        private readonly ReadServiceCategoryRepositoryInterface $readServiceCategoryRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepositoryInterface,
        private readonly ContactInterface $user
    ) {
    }

    /**
     * @param int $serviceCategoryId
     * @param PresenterInterface $presenter
     */
    public function __invoke(int $serviceCategoryId, PresenterInterface $presenter): void
    {
        try {
            if ($this->user->isAdmin()) {
                if ($this->readServiceCategoryRepository->exists($serviceCategoryId)) {
                    $this->writeServiceCategoryRepository->deleteById($serviceCategoryId);
                    $presenter->setResponseStatus(new NoContentResponse());
                } else {
                    $this->error('Service category not found', [
                        'servicecategory_id' => $serviceCategoryId,
                    ]);
                    $presenter->setResponseStatus(new NotFoundResponse('Service category'));
                }
            } elseif ($this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_SERVICES_CATEGORIES_READ_WRITE)) {
                $accessGroups = $this->readAccessGroupRepositoryInterface->findByContact($this->user);

                if ($this->readServiceCategoryRepository->existsByAccessGroups($serviceCategoryId, $accessGroups)) {
                    $this->writeServiceCategoryRepository->deleteById($serviceCategoryId);
                    $presenter->setResponseStatus(new NoContentResponse());
                } else {
                    $this->error('Service category not found', [
                        'servicecategory_id' => $serviceCategoryId,
                        'accessgroups' => $accessGroups,
                    ]);
                    $presenter->setResponseStatus(new NotFoundResponse('Service category'));
                }
            } else {
                $this->error('User doesn\'t have sufficient rights to see service category', [
                    'user_id' => $this->user->getId(),
                ]);
                $presenter->setResponseStatus(
                    new ForbiddenResponse(ServiceCategoryException::deleteNotAllowed())
                );
            }
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(
                new ErrorResponse(ServiceCategoryException::deleteServiceCategory($ex))
            );
            $this->error($ex->getMessage());
        }
    }
}
