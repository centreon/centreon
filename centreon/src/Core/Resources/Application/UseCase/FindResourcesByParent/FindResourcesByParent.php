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
use Centreon\Domain\Monitoring\Resource as ResourceEntity;
use Centreon\Domain\Monitoring\ResourceFilter;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Resources\Application\Exception\ResourceException;
use Core\Resources\Application\Repository\ReadResourceRepositoryInterface;
use Core\Resources\Application\UseCase\FindResources\FindResourcesFactory;
use Core\Resources\Application\UseCase\FindResources\FindResourcesResponse;
use Core\Resources\Infrastructure\Repository\ExtraDataProviders\ExtraDataProviderInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

final class FindResourcesByParent
{
    use LoggerTrait;

    private int $pageProvided = 1;

    private string $searchProvided = '';

    /** @var array<mixed> */
    private array $sortProvided = [];

    /**
     * @param ReadResourceRepositoryInterface $repository
     * @param ContactInterface $contact
     * @param RequestParametersInterface $requestParameters
     * @param ReadAccessGroupRepositoryInterface $accessGroupRepository
     * @param \Traversable<ExtraDataProviderInterface> $extraDataProviders
     */
    public function __construct(
        private readonly ReadResourceRepositoryInterface $repository,
        private readonly ContactInterface $contact,
        private readonly RequestParametersInterface $requestParameters,
        private readonly ReadAccessGroupRepositoryInterface $accessGroupRepository,
        private readonly \Traversable $extraDataProviders
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
            $this->searchProvided = $this->requestParameters->getSearchAsString();
            $this->sortProvided = $this->requestParameters->getSort();
            $this->pageProvided = $this->requestParameters->getPage();

            // create specific filter for parent search
            $parentFilter = (new ResourceFilter())->setTypes([ResourceFilter::TYPE_HOST]);

            // Creating a new sort to search children as we want a specific order priority
            $servicesSort = ['parent_id' => 'ASC', ...$this->sortProvided];

            $parents = new FindResourcesResponse([]);

            $this->requestParameters->setSort(json_encode($servicesSort) ?: '');

            $resources = [];
            $parentResources = [];

            if ($this->contact->isAdmin()) {
                $resources = $this->findResourcesAsAdmin($filter);
                // Save total children found
                $totalChildrenFound = $this->requestParameters->getTotal();

                if ($resources !== []) {
                    // prepare special ResourceFilter for parent search
                    $parentFilter->setHostIds($this->extractParentIdsFromResources($resources));

                    // unset search provided in order to find parents linked to the resources found and restore sort
                    $this->prepareRequestParametersForParentSearch();
                    $parentResources = $this->findParentResources($parentFilter);
                }
            } else {
                $resources = $this->findResourcesAsUser($filter);

                // Save total children found
                $totalChildrenFound = $this->requestParameters->getTotal();

                if ($resources !== []) {
                    // prepare special ResourceFilter for parent search
                    $parentFilter->setHostIds($this->extractParentIdsFromResources($resources));

                    // unset search provided in order to find parents linked to the resources found and restore sort
                    $this->prepareRequestParametersForParentSearch();
                    $parentResources = $this->findParentResources($parentFilter);
                }
            }

            // Only get extra data for services
            $extraData = [];
            foreach (iterator_to_array($this->extraDataProviders) as $provider) {
                $extraData[$provider->getExtraDataSourceName()] = $provider->getExtraDataForResources($filter, $resources);
            }

            // Set total to the number of children found
            $this->requestParameters->setTotal($totalChildrenFound);

            // Restore search and sort from initial request (for accurate meta in presenter).
            $this->restoreProvidedSearchParameters();

            $children = FindResourcesFactory::createResponse($resources);
            $parents = FindResourcesFactory::createResponse($parentResources);

            $presenter->presentResponse(
                FindResourcesByParentFactory::createResponse($parents->resources, $children->resources, $extraData)
            );
        } catch (\Throwable $ex) {
            $presenter->presentResponse(new ErrorResponse(ResourceException::errorWhileSearching()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    private function prepareRequestParametersForParentSearch(): void
    {
        $this->requestParameters->unsetSearch();
        $this->requestParameters->setSort(json_encode($this->sortProvided) ?: '');
        $this->requestParameters->setPage(1);
    }

    private function restoreProvidedSearchParameters(): void
    {
        $this->requestParameters->setPage($this->pageProvided);
        $this->requestParameters->setSearch($this->searchProvided);
    }

    /**
     * @param ResourceEntity[] $resources
     *
     * @return int[]
     */
    private function extractParentIdsFromResources(array $resources): array
    {
        $hostIds = array_map(
            static fn (ResourceEntity $resource) => (int) $resource->getParent()?->getId(),
            $resources
        );

        return array_unique($hostIds);
    }

    /**
     * @param ResourceFilter $filter
     *
     * @return ResourceEntity[]
     */
    private function findResourcesAsAdmin(ResourceFilter $filter): array
    {
        return $this->repository->findResources($filter);
    }

    /**
     * @param ResourceFilter $filter
     *
     * @return ResourceEntity[]
     */
    private function findParentResources(ResourceFilter $filter): array
    {
        return $this->repository->findParentResourcesById($filter);
    }

    /**
     * @param ResourceFilter $filter
     *
     * @throws \Throwable
     *
     * @return ResourceEntity[]
     */
    private function findResourcesAsUser(ResourceFilter $filter): array
    {
        $accessGroupIds = array_map(
            static fn (AccessGroup $accessGroup) => $accessGroup->getId(),
            $this->accessGroupRepository->findByContact($this->contact)
        );

        return $this->repository->findResourcesByAccessGroupIds($filter, $accessGroupIds);
    }
}
