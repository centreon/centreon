<?php

/*
* Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
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

namespace Core\HostCategory\Application\UseCase\CreateHostCategory;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\HostCategory\Application\Repository\WriteHostCategoryRepositoryInterface;
use Core\HostCategory\Application\UseCase\CreateHostCategory\CreateHostCategoryRequest;
use Core\HostCategory\Domain\Model\HostCategory;
use Core\HostCategory\Domain\Model\NewHostCategory;

final class CreateHostCategory
{
    use LoggerTrait;

    public function __construct(
        private WriteHostCategoryRepositoryInterface $writeHostCategoryRepository,
        private ReadHostCategoryRepositoryInterface $readHostCategoryRepository,
        private ContactInterface $user
    ) {
    }

    public function __invoke(CreateHostCategoryRequest $request, PresenterInterface $presenter): void
    {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_CATEGORIES_READ_WRITE)) {
                $this->error('User doesn\'t have sufficient right to see host categories', [
                    'user_id' => $this->user->getId(),
                ]);
                $presenter->setResponseStatus(
                    new ForbiddenResponse('You are not allowed to create host categories')
                );
            } elseif ($this->readHostCategoryRepository->existsByName(trim($request->name))) {
                $this->error('Host category name already exists', [
                    'hostcategory_name' => trim($request->name),
                ]);
                $presenter->setResponseStatus(
                    new InvalidArgumentResponse('Host category name already exists')
                );
            } else {
                $newHostCategory = new NewHostCategory(trim($request->name), trim($request->alias));
                $newHostCategory->setComment($request->comment ? trim($request->comment) : null);

                $hostCategoryId = $this->writeHostCategoryRepository->create($newHostCategory);
                $hostCategory = $this->readHostCategoryRepository->findById($hostCategoryId);

                $presenter->present(
                    // new CreatedResponse($hostCategoryId, $this->createResponse($hostCategory))
                    $this->createResponse($hostCategory)
                );
            }
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(new ErrorResponse('Error while creating host category'));
            $this->error($ex->getMessage());
        }
    }

    private function createResponse(HostCategory $hostCategory): CreateHostCategoryResponse
    {
        $response = new CreateHostCategoryResponse();
        $response->hostCategory = [
                'id' => $hostCategory->getId(),
                'name' => $hostCategory->getName(),
                'alias' => $hostCategory->getAlias(),
                'is_activated' => $hostCategory->isActivated(),
                'comment' => $hostCategory->getComment(),
            ];
        return $response;
    }
}
