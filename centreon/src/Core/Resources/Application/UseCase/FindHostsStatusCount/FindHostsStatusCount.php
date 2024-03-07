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

namespace Core\Resources\Application\UseCase\FindHostsStatusCount;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Monitoring\Resource;
use Centreon\Domain\Monitoring\ResourceFilter;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Resources\Application\Repository\ReadResourceRepositoryInterface;
use Core\Resources\Domain\Model\ResourcesStatusCount;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

final class FindHostsStatusCount
{
    public function __construct(
        private readonly ContactInterface $user,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ReadResourceRepositoryInterface $readResourceRepository,
        private readonly RequestParametersInterface $requestParameters,
    ) {

    }
    public function __invoke(FindHostsStatusCountPresenterInterface $presenter, ResourceFilter $filter): void
    {
        try {
            if ($this->hasEmptyFilter($filter)) {
                $resourcesStatusCount = $this->findAllHostsStatus();
            } else {
                $resourcesStatusCount = $this->findHostsStatusWithFilter($filter);
            }

            $presenter->presentResponse($this->createResponse($resourcesStatusCount));
        }catch (\Throwable $ex) {
            $presenter->presentResponse(new ErrorResponse($ex));
        }
    }

    private function findAllHostsStatus(): ResourcesStatusCount
    {
        if ($this->user->isAdmin()) {
            return $this->readResourceRepository->findResourcesStatusCount(Resource::TYPE_HOST);
        } else {
            $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
            $accessGroupIds = array_map(static fn (AccessGroup $accessGroup): int => $accessGroup->getId(), $accessGroups);

            return $this->readResourceRepository->findResourcesStatusCountByAccessGroupIds(
                Resource::TYPE_HOST,
                $accessGroupIds
            );
        }
    }

    private function findHostsStatusWithFilter(ResourceFilter $filter): ResourcesStatusCount
    {
        if ($this->user->isAdmin()) {
            $resources = $this->readResourceRepository->findResources($filter);
        } else {
            $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
            $accessGroupIds = array_map(static fn (AccessGroup $accessGroup): int => $accessGroup->getId(), $accessGroups);

            $resources =$this->readResourceRepository->findResourcesByAccessGroupIds($filter, $accessGroupIds);
        }
            $hostIds = array_map(static fn (Resource $resource): int => $resource->getParent()?->getId(), $resources);
            $resourcesStatusCount = $this->readResourceRepository->findResourcesStatusCountByHostIds(Resource::TYPE_HOST, $hostIds);
    }


    private function findHostsStatusCountAsAdmin(ResourceFilter $filter): ResourcesStatusCount
    {
        return $this->readResourceRepository->findResourcesStatusCountWithFilter(Resource::TYPE_HOST, $filter);
    }

    private function findHostsStatusCountAsNonAdmin(ResourceFilter $filter): ResourcesStatusCount
    {
        $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
        $accessGroupIds = array_map(static fn (AccessGroup $accessGroup): int => $accessGroup->getId(), $accessGroups);

        return $this->readResourceRepository->findResourcesStatusCountByAccessGroupIdsWithFilter(
            Resource::TYPE_HOST,
            $accessGroupIds,
            $filter
        );

    }

    private function createResponse(ResourcesStatusCount $resourcesStatusCount): FindHostsStatusCountResponse
    {
        $response = new FindHostsStatusCountResponse();
        $hostsStatusCount = $resourcesStatusCount->getHostsStatusCount();
        $response->downStatus = [
            'total' => $hostsStatusCount->getDownStatusCount()->getTotal(),
        ];
        $response->unreachableStatus = [
            'total' => $hostsStatusCount->getUnreachableStatusCount()->getTotal(),
        ];
        $response->upStatus = [
            'total' => $hostsStatusCount->getUpStatusCount()->getTotal(),
        ];
        $response->pendingStatus = [
            'total' => $hostsStatusCount->getPendingStatusCount()->getTotal(),
        ];

        $response->total = $hostsStatusCount->getTotal();

        return $response;
    }



    private function hasEmptyFilter(ResourceFilter $filter): bool
    {
        return empty($filter->getHostgroupNames())
            && empty($filter->getHostCategoryNames())
            && empty($filter->getServicegroupNames())
            && empty($filter->getServiceCategoryNames())
            && empty($this->requestParameters->getSearch());
    }
}