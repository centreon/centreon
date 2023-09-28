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
            // Save the search and sort provided to be restored later on
            $searchProvided = $this->requestParameters->getSearchAsString();
            $sortProvided = $this->requestParameters->getSort();

            // create specific filter for parent search
            $parentFilter = (new ResourceFilter())->setTypes([ResourceFilter::TYPE_HOST]);

            // Creating a new sort to search children as we want a specific order priority
            $servicesSort = ['parent_id' => 'DESC', ...$sortProvided];

            $this->requestParameters->setSort(json_encode($servicesSort));

            if ($this->contact->isAdmin()) {
                $children = $this->findResourcesAsAdmin($filter);
                $parentFilter->setHostIds($this->extractParentIdsFromResources($children));
                // unset search provided in order to find parents linked to the resources found and restore sort
                $this->unsetInitialSearchParameter();
                $this->requestParameters->setSort(json_encode($sortProvided));
                $parents = $this->findResourcesAsAdmin($parentFilter);
            } else {
                $children = $this->findResourcesAsUser($filter);
                $parentFilter->setHostIds($this->extractParentIdsFromResources($children));
                // unset search provided in order to find parents linked to the resources found and restore sort
                $this->unsetInitialSearchParameter();
                $this->requestParameters->setSort(json_encode($sortProvided));
                $parents = $this->findResourcesAsUser($parentFilter);
           }

            // Restore search and total from initial request (for accurate meta in presenter).
            $this->requestParameters->setSearch($searchProvided);
            $this->requestParameters->setTotal((int) count($children->resources));

            $presenter->presentResponse(
                FindResourcesByParentFactory::createResponse($parents->resources, $children->resources)
            );
        } catch (\Throwable $ex) {
            $presenter->presentResponse(new ErrorResponse(ResourceException::errorWhileSearching()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    private function unsetInitialSearchParameter(): void
    {
        $this->requestParameters->unsetSearch();
    }

    /**
     * @param FindResourcesResponse $response
     *
     * @return int[]
     */
    private function extractParentIdsFromResources(FindResourcesResponse $response): array
    {
        return array_map(
            static fn (ResourceResponseDto $resource) => (int) $resource->parent?->id,
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
