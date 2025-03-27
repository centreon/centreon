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
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
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
     * Allowed export formats
     *
     * @var array<string>
     */
    private const EXPORT_ALLOWED_FORMAT = ['csv'];

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

        if (($errorResponse = $this->validateRequest($request)) !== true) {
            $presenter->presentResponse($errorResponse);

            return;
        }

        if ($request->isAdmin) {
            try {
                $resources = $this->readResourceRepository->iterateResources(
                    filter: $request->resourceFilter,
                    maxResults: $request->allPages ? $request->maxResults : 0
                );
            } catch (RepositoryException $exception) {
                $presenter->presentResponse(
                    new ErrorResponse(
                        message: 'An error occurred while iterating resources with admin rights',
                        context: [
                            'use_case' => 'ExportResources',
                            'user_is_admin' => $request->isAdmin,
                            'contact_id' => $request->contactId,
                            'resources_filter' => $request->resourceFilter,
                        ],
                        exception: $exception
                    )
                );

                return;
            }
        } else {
            try {
                $accessGroups = $this->accessGroupRepository->findByContactId($request->contactId);
                $accessGroupIds = $accessGroups->getIds();
            } catch (RepositoryException $exception) {
                $presenter->presentResponse(
                    new ErrorResponse(
                        message: 'An error occurred while finding access groups for the contact',
                        context: [
                            'use_case' => 'ExportResources',
                            'contact_id' => $request->contactId,
                        ],
                        exception: $exception
                    )
                );

                return;
            }

            try {
                $resources = $this->readResourceRepository->iterateResourcesByAccessGroupIds(
                    filter: $request->resourceFilter,
                    accessGroupIds: $accessGroupIds,
                    maxResults: $request->allPages ? $request->maxResults : 0
                );
            } catch (RepositoryException $exception) {
                $presenter->presentResponse(
                    new ErrorResponse(
                        message: 'An error occurred while iterating resources by access group IDs',
                        context: [
                            'use_case' => 'ExportResources',
                            'user_is_admin' => $request->isAdmin,
                            'contact_id' => $request->contactId,
                            'resources_filter' => $request->resourceFilter,
                        ],
                        exception: $exception
                    )
                );

                return;
            }
        }

        $response->setExportedFormat($request->exportedFormat);
        $response->setFilteredColumns($request->columns);
        $response->setResources($resources);

        $presenter->presentResponse($response);
    }

    /**
     * @param ExportResourcesRequest $request
     *
     * @return ResponseStatusInterface|true
     */
    private function validateRequest(ExportResourcesRequest $request): ResponseStatusInterface|true
    {
        if (! $request->contactId > 0) {
            return new InvalidArgumentResponse('Invalid request, contact ID must be greater than 0');
        }

        if (! in_array($request->exportedFormat, self::EXPORT_ALLOWED_FORMAT, true)) {
            return new InvalidArgumentResponse(
                'Invalid request, format must be one of the following: ' . implode(', ', self::EXPORT_ALLOWED_FORMAT)
            );
        }

        if ($request->allPages && $request->maxResults === 0) {
            return new InvalidArgumentResponse(
                'Invalid request, max number of resources is required when exporting all pages'
            );
        }

        if ($request->allPages && $request->maxResults > 10000) {
            return new InvalidArgumentResponse(
                'Invalid request, max number of resources to export must be equal or less than 10000'
            );
        }

        return true;
    }
}
