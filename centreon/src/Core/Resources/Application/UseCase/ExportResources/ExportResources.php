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

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Resources\Application\Repository\ReadResourceRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

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
     * @param ContactInterface $contact
     */
    public function __construct(
        private ReadResourceRepositoryInterface $readResourceRepository,
        private ReadAccessGroupRepositoryInterface $accessGroupRepository,
        private ContactInterface $contact,
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

        if (! $this->validateRequest($request)) {
            $presenter->presentResponse(
                new InvalidArgumentResponse('Invalid request, maxResults must be equal or less than 10000')
            );

            return;
        }

        if ($this->contact->isAdmin()) {
            try {
                $resources = $this->readResourceRepository->iterateResourcesByMaxResults(
                    $request->resourceFilter,
                    $request->maxResults
                );
            } catch (RepositoryException $exception) {
                $presenter->presentResponse(
                    new ErrorResponse(
                        message: 'An error occurred while iterating resources with admin rights',
                        context: [
                            'use_case' => 'ExportResources',
                            'user_is_admin' => $this->contact->isAdmin(),
                            'contact_id' => $this->contact->getId(),
                            'resources_filter' => $request->resourceFilter,
                        ],
                        exception: $exception
                    )
                );

                return;
            }
        } else {
            try {
                $accessGroupIds = array_map(
                    static fn(AccessGroup $accessGroup) => $accessGroup->getId(),
                    $this->accessGroupRepository->findByContact($this->contact)
                );
            } catch (RepositoryException $exception) {
                $presenter->presentResponse(
                    new ErrorResponse(
                        message: 'An error occurred while finding access groups for the contact',
                        context: [
                            'use_case' => 'ExportResources',
                            'contact_id' => $this->contact->getId()
                        ],
                        exception: $exception
                    )
                );

                return;
            }

            try {
                $resources = $this->readResourceRepository->iterateResourcesByAccessGroupIdsAndMaxResults(
                    $request->resourceFilter,
                    $accessGroupIds,
                    $request->maxResults
                );
            } catch (RepositoryException $exception) {
                $presenter->presentResponse(
                    new ErrorResponse(
                        message: 'An error occurred while iterating resources by access group IDs',
                        context: [
                            'use_case' => 'ExportResources',
                            'user_is_admin' => $this->contact->isAdmin(),
                            'contact_id' => $this->contact->getId(),
                            'resources_filter' => $request->resourceFilter,
                        ],
                        exception: $exception
                    )
                );

                return;
            }
        }

        $response->setResources($resources);

        $presenter->presentResponse($response);
    }

    /**
     * @param ExportResourcesRequest $request
     *
     * @return bool
     */
    private function validateRequest(ExportResourcesRequest $request): bool
    {
        return $request->maxResults <= 10000;
    }
}
