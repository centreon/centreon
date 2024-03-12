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

namespace Core\Resources\Application\UseCase\FindServicesStatusCount;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Monitoring\Resource;
use Centreon\Domain\Monitoring\ResourceFilter;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Resources\Application\Exception\ResourceException;
use Core\Resources\Application\Repository\ReadResourceRepositoryInterface;
use Core\Resources\Domain\Model\ResourcesStatusCount;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

final class FindServicesStatusCount
{
    use LoggerTrait;

    /**
     * @param ContactInterface $user
     * @param ReadAccessGroupRepositoryInterface $readAccessGroupRepository
     * @param ReadResourceRepositoryInterface $readResourceRepository
     */
    public function __construct(
        private readonly ContactInterface $user,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ReadResourceRepositoryInterface $readResourceRepository,
    ) {
    }

    /**
     * @param FindServicesStatusCountPresenterInterface $presenter
     * @param ResourceFilter $filter
     */
    public function __invoke(FindServicesStatusCountPresenterInterface $presenter, ResourceFilter $filter): void
    {
        try {
            $resourcesStatusCount = $this->findResourcesStatus($filter);
            $presenter->presentResponse($this->createResponse($resourcesStatusCount));
        } catch (\Throwable $ex) {
            $this->error(
                ResourceException::errorWhileFindingServicesStatusCount()->getMessage(),
                ['trace' => (string) $ex]
            );
            $presenter->presentResponse(
                new ErrorResponse(ResourceException::errorWhileFindingServicesStatusCount()->getMessage())
            );
        }
    }

    /**
     * @param ResourceFilter $filter
     *
     * @throws \Throwable
     *
     * @return ResourcesStatusCount
     */
    private function findResourcesStatus(ResourceFilter $filter): ResourcesStatusCount
    {
        if ($this->user->isAdmin()) {
            return $this->readResourceRepository->findResourcesStatusCount(Resource::TYPE_SERVICE, $filter);
        }

        $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
        $accessGroupIds = array_map(static fn (AccessGroup $accessGroup): int => $accessGroup->getId(), $accessGroups);

        return $this->readResourceRepository->findResourcesStatusCountByAccessGroupIds(
            Resource::TYPE_SERVICE,
            $accessGroupIds,
            $filter
        );
    }

    /**
     * @param ResourcesStatusCount $resourcesStatusCount
     *
     * @return FindServicesStatusCountResponse
     */
    private function createResponse(ResourcesStatusCount $resourcesStatusCount): FindServicesStatusCountResponse
    {
        $response = new FindServicesStatusCountResponse();
        $servicesStatusCount = $resourcesStatusCount->getServicesStatusCount();
        if ($servicesStatusCount !== null) {
            $response->criticalStatus = [
                'total' => $servicesStatusCount->getCriticalStatusCount()->getTotal(),
            ];
            $response->warningStatus = [
                'total' => $servicesStatusCount->getWarningStatusCount()->getTotal(),
            ];
            $response->unknownStatus = [
                'total' => $servicesStatusCount->getUnknownStatusCount()->getTotal(),
            ];
            $response->okStatus = [
                'total' => $servicesStatusCount->getOkStatusCount()->getTotal(),
            ];
            $response->pendingStatus = [
                'total' => $servicesStatusCount->getPendingStatusCount()->getTotal(),
            ];

            $response->total = $servicesStatusCount->getTotal();
        }

        return $response;
    }
}
