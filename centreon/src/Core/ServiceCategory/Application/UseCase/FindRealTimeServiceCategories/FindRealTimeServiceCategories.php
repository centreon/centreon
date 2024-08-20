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

namespace Core\ServiceCategory\Application\UseCase\FindRealTimeServiceCategories;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\ServiceCategory\Application\Exception\ServiceCategoryException;
use Core\ServiceCategory\Application\Repository\ReadRealTimeServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Application\Repository\ReadServiceCategoryRepositoryInterface;
use Core\Tag\RealTime\Domain\Model\Tag;

final class FindRealTimeServiceCategories
{
    use LoggerTrait;

    public function __construct(
        private readonly ContactInterface $user,
        private readonly ReadRealTimeServiceCategoryRepositoryInterface $repository,
        private readonly ReadServiceCategoryRepositoryInterface $configurationRepository,
        private readonly RequestParametersInterface $requestParameters,
        private readonly ReadAccessGroupRepositoryInterface $accessGroupRepository
    ) {
    }

    /**
     * @param FindRealTimeServiceCategoriesPresenterInterface $presenter
     */
    public function __invoke(FindRealTimeServiceCategoriesPresenterInterface $presenter): void
    {
        $this->info('Find service categories', ['user_id' => $this->user->getId()]);

        try {
            $serviceCategories = $this->user->isAdmin()
                ? $this->findServiceCategoriesAsAdmin()
                : $this->findServiceCategoriesAsUser();

            $presenter->presentResponse($this->createResponse($serviceCategories));
        } catch (\Throwable $exception) {
            $presenter->presentResponse(
                new ErrorResponse(ServiceCategoryException::errorWhileRetrievingRealTimeServiceCategories($exception))
            );
            $this->error($exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
        }
    }

    /**
     * @param Tag[] $serviceCategories
     *
     * @return FindRealTimeServiceCategoriesResponse
     */
    private function createResponse(array $serviceCategories): FindRealTimeServiceCategoriesResponse
    {
        return new FindRealTimeServiceCategoriesResponse($serviceCategories);
    }

    /**
     * @return Tag[]
     */
    private function findServiceCategoriesAsUser(): array
    {
        $categories = [];

        $this->debug(
            'User is not admin, use ACLs to retrieve service categories',
            ['user' => $this->user->getName()]
        );

        $accessGroupIds = array_map(
            static fn (AccessGroup $accessGroup): int => $accessGroup->getId(),
            $this->accessGroupRepository->findByContact($this->user)
        );

        if ($accessGroupIds === []) {
            return $categories;
        }

        // If the current user has ACL filter on Service Categories it means that not all categories are visible so
        // we need to apply the ACL
        if ($this->configurationRepository->hasRestrictedAccessToServiceCategories($accessGroupIds)) {
            return $this->repository->findAllByAccessGroupIds(
                $this->requestParameters,
                $accessGroupIds,
            );
        }

        $this->debug(
            'No ACL filter found on service categories for user. Retrieving all service categories',
            ['user' => $this->user->getName()]
        );

        return $this->repository->findAll($this->requestParameters);
    }

    /**
     * @return Tag[]
     */
    private function findServiceCategoriesAsAdmin(): array
    {
        return $this->repository->findAll($this->requestParameters);
    }
}

