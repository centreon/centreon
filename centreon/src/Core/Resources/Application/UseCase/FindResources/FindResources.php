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

namespace Core\Resources\Application\UseCase\FindResources;

use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Monitoring\ResourceFilter;
use Core\Resources\Application\Exception\ResourceException;
use Core\Resources\Application\Repository\ReadResourceRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

final class FindResources
{
    use LoggerTrait;

    /**
     * @param ReadResourceRepositoryInterface $repository
     * @param ContactInterface $contact
     */
    public function __construct(
        private readonly ReadResourceRepositoryInterface $repository,
        private readonly ContactInterface $contact,
        private readonly ReadAccessGroupRepositoryInterface $accessGroupRepository
    ) {
    }

    public function __invoke(FindResourcesPresenterInterface $presenter): void
    {
        $resourceFilter = new ResourceFilter();
        try {
            if ($this->contact->isAdmin()) {
                $presenter->presentResponse($this->findResourcesAsAdmin($resourceFilter));
            } else {
                $presenter->presentResponse($this->findResourcesAsUser($resourceFilter));
            }
        } catch (\Throwable $ex) {
            $presenter->presentResponse(new ErrorResponse(ResourceException::errorWhileSearching()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @return FindResourcesResponse
     */
    private function findResourcesAsAdmin(ResourceFilter $filter): FindResourcesResponse
    {
        $resources = $this->repository->findResources($filter);
        return new FindResourcesResponse();
    }

    /**
     * @return FindResourcesResponse
     */
    private function findResourcesAsUser(ResourceFilter $filter): FindResourcesResponse
    {
        $accessGroupIds = array_map(
            fn (AccessGroup $accessGroup) => $accessGroup->getId(),
            $this->accessGroupRepository->findByContact($this->contact)
        );
        $resources = $this->repository->findResourcesByAccessGroupIds($filter, $accessGroupIds);
        return new FindResourcesResponse();
    }

    private function createResponse(): FindResourcesResponse
    {
    }
}
