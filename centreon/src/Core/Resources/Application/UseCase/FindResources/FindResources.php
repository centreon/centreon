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

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Monitoring\Resource as ResourceEntity;
use Centreon\Domain\Monitoring\ResourceFilter;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Resources\Application\Exception\ResourceException;
use Core\Resources\Application\Repository\ReadResourceRepositoryInterface;
use Core\Resources\Infrastructure\Repository\ExtraDataProviders\ExtraDataProviderInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

final class FindResources
{
    use LoggerTrait;

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
     * @param FindResourcesPresenterInterface $presenter
     * @param ResourceFilter $filter
     */
    public function __invoke(
        FindResourcesPresenterInterface $presenter,
        ResourceFilter $filter
    ): void {
        try {
            $resources = [];
            if ($this->contact->isAdmin()) {
                $resources = $this->findResourcesAsAdmin($filter);
                $this->info('Find resources', ['request' => $this->requestParameters->toArray()]);
            } else {
                $resources = $this->findResourcesAsUser($filter);
                $this->info('Find resources', ['request' => $this->requestParameters->toArray()]);
            }

            $extraData = [];
            foreach (iterator_to_array($this->extraDataProviders) as $provider) {
                $extraData[$provider->getExtraDataSourceName()] = $provider->getExtraDataForResources($filter, $resources);
            }

            $presenter->presentResponse(FindResourcesFactory::createResponse($resources, $extraData));
        } catch (\Throwable $ex) {
            $presenter->presentResponse(new ErrorResponse(ResourceException::errorWhileSearching()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
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
     * @throws \Throwable
     *
     * @return ResourceEntity[]
     */
    private function findResourcesAsUser(ResourceFilter $filter): array
    {
        $accessGroupIds = array_map(
            static fn(AccessGroup $accessGroup) => $accessGroup->getId(),
            $this->accessGroupRepository->findByContact($this->contact)
        );

        return $this->repository->findResourcesByAccessGroupIds($filter, $accessGroupIds);
    }
}
