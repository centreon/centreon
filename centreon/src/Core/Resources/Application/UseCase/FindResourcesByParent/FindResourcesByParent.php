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

namespace Core\Resources\Application\UseCase\FindResourcesByParent;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Monitoring\ResourceFilter;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Resources\Application\Exception\ResourceException;
use Core\Resources\Application\Repository\ReadResourceRepositoryInterface;
use Core\Resources\Application\UseCase\FindResources\FindResourcesFactory;
use Core\Resources\Application\UseCase\FindResources\FindResourcesResponse;
use Core\Resources\Application\UseCase\FindResources\Response\ResourceResponseDto;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

final class FindResourcesByParent
{
    use LoggerTrait;

    /**
     * @param ReadResourceRepositoryInterface $repository
     * @param ContactInterface $contact
     * @param RequestParametersInterface $requestParameters
     * @param ReadAccessGroupRepositoryInterface $accessGroupRepository
     */
    public function __construct(
        private readonly ReadResourceRepositoryInterface $repository,
        private readonly ContactInterface $contact,
        private readonly RequestParametersInterface $requestParameters,
        private readonly ReadAccessGroupRepositoryInterface $accessGroupRepository
    ) {
    }

    /**
     * @param FindResourcesByParentPresenterInterface $presenter
     * @param ResourceFilter $filter
     */
    public function __invoke(
        FindResourcesByParentPresenterInterface $presenter,
        ResourceFilter $filter
    ): void {
        try {
            $searchProvided = $this->requestParameters->getSearchAsString();
            $parentFilter = (new ResourceFilter())->setTypes([ResourceFilter::TYPE_HOST]);

            if ($this->contact->isAdmin()) {
                $childrenResponse = $this->findResourcesAsAdmin($filter);
                $parentFilter->setHostIds($this->extractParentIdsFromResources($childrenResponse));
                $this->requestParameters->unsetSearch();
                $parentResponse = $this->findResourcesAsAdmin($parentFilter);
            } else {
                $childrenResponse = $this->findResourcesAsUser($filter);
                $parentFilter->setHostIds($this->extractParentIdsFromResources($childrenResponse));
                $this->requestParameters->unsetSearch();
                $parentResponse = $this->findResourcesAsUser($parentFilter);
           }

            // Keep search and total from initial request
            $this->requestParameters->setSearch($searchProvided);
            $this->requestParameters->setTotal((int) count($childrenResponse->resources));

            $response = new FindResourcesResponse();
            $response->resources = [...$childrenResponse->resources, ...$parentResponse->resources];
            $presenter->presentResponse($response);
        } catch (\Throwable $ex) {
            $presenter->presentResponse(new ErrorResponse(ResourceException::errorWhileSearching()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @param FindResourcesResponse $response
     *
     * @return int[]
     */
    private function extractParentIdsFromResources(FindResourcesResponse $response): array
    {
        return array_map(
            static fn (ResourceResponseDto $resource) => $resource->parent?->id,
            $response->resources
        );
    }

    /**
     * @param ResourceFilter $filter
     *
     * @return FindResourcesResponse
     */
    private function findResourcesAsAdmin(ResourceFilter $filter): FindResourcesResponse
    {
        return FindResourcesFactory::createResponse(
            $this->repository->findResources($filter)
        );
    }

    /**
     * @param ResourceFilter $filter
     *
     * @throws \Throwable
     *
     * @return FindResourcesResponse
     */
    private function findResourcesAsUser(ResourceFilter $filter): FindResourcesResponse
    {
        $accessGroupIds = array_map(
            static fn (AccessGroup $accessGroup) => $accessGroup->getId(),
            $this->accessGroupRepository->findByContact($this->contact)
        );

        return FindResourcesFactory::createResponse(
            $this->repository->findResourcesByAccessGroupIds($filter, $accessGroupIds)
        );
    }
}
