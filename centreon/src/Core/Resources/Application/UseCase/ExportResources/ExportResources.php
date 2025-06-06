<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Core\Resources\Application\UseCase\ExportResources;

use Core\Application\Common\UseCase\ErrorResponse;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Resources\Application\Repository\ReadResourceRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

/**
 * Class
 *
 * @class ExportResources
 * @package Core\Resources\Application\UseCase\ExportResources
 */
final readonly class ExportResources
{
    /**
     * ExportResources constructor
     *
     * @param ReadResourceRepositoryInterface $readResourceRepository
     * @param ReadAccessGroupRepositoryInterface $accessGroupRepository
     */
    public function __construct(
        private ReadResourceRepositoryInterface $readResourceRepository,
        private ReadAccessGroupRepositoryInterface $accessGroupRepository,
    ) {}

    /**
     * @param ExportResourcesRequest $request
     * @param ExportResourcesPresenterInterface $presenter
     *
     * @return void
     */
    public function __invoke(
        ExportResourcesRequest $request,
        ExportResourcesPresenterInterface $presenter
    ): void {
        $response = new ExportResourcesResponse();

        try {
            if ($request->isAdmin) {
                $resources = $this->readResourceRepository->iterateResources(
                    filter: $request->resourceFilter,
                    maxResults: $request->allPages ? $request->maxResults : 0
                );
            } else {
                $accessGroups = $this->accessGroupRepository->findByContactId($request->contactId);
                $accessGroupIds = $accessGroups->getIds();

                $resources = $this->readResourceRepository->iterateResourcesByAccessGroupIds(
                    filter: $request->resourceFilter,
                    accessGroupIds: $accessGroupIds,
                    maxResults: $request->allPages ? $request->maxResults : 0
                );
            }

            $response->setExportedFormat($request->exportedFormat);
            $response->setFilteredColumns($request->columns);
            $response->setResources($resources);

            $presenter->presentResponse($response);
        } catch (RepositoryException $exception) {
            $presenter->presentResponse(
                new ErrorResponse(
                    message: 'An error occurred while iterating resources',
                    context: [
                        'use_case' => 'ExportResources',
                        'user_is_admin' => $request->isAdmin,
                        'contact_id' => $request->contactId,
                        'resources_filter' => $request->resourceFilter,
                    ],
                    exception: $exception
                )
            );
        }
    }
}
