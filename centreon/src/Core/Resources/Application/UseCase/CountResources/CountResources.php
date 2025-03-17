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

namespace Core\Resources\Application\UseCase\CountResources;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Resources\Application\Repository\ReadResourceRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

/**
 * Class
 *
 * @class CountResources
 * @package Core\Resources\Application\UseCase\CountResources
 */
final readonly class CountResources
{
    /**
     * CountResources constructor
     *
     * @param ReadResourceRepositoryInterface $readResourceRepository
     * @param ReadAccessGroupRepositoryInterface $accessGroupRepository
     */
    public function __construct(
        private ReadResourceRepositoryInterface $readResourceRepository,
        private ReadAccessGroupRepositoryInterface $accessGroupRepository
    ) {}

    /**
     * @param CountResourcesRequest $request
     * @param CountResourcesPresenterInterface $presenter
     *
     * @return void
     */
    public function __invoke(
        CountResourcesRequest $request,
        CountResourcesPresenterInterface $presenter
    ): void {
        $response = new CountResourcesResponse();

        if ($request->contact->isAdmin()) {
            try {
                $countResources = $this->readResourceRepository->countResources($request->resourceFilter,);
            } catch (RepositoryException $exception) {
                $presenter->presentResponse(
                    new ErrorResponse(
                        message: 'An error occurred while counting resources with admin rights',
                        context: [
                            'use_case' => 'CountResources',
                            'user_is_admin' => $request->contact->isAdmin(),
                            'contact_id' => $request->contact->getId(),
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
                    $this->accessGroupRepository->findByContact($request->contact)
                );
            } catch (RepositoryException $exception) {
                $presenter->presentResponse(
                    new ErrorResponse(
                        message: 'An error occurred while finding access groups for the contact',
                        context: [
                            'use_case' => 'CountResources',
                            'contact_id' => $request->contact->getId()
                        ],
                        exception: $exception
                    )
                );

                return;
            }

            try {
                $countResources = $this->readResourceRepository->countResourcesByAccessGroupIds(
                    $request->resourceFilter,
                    $accessGroupIds
                );
            } catch (RepositoryException $exception) {
                $presenter->presentResponse(
                    new ErrorResponse(
                        message: 'An error occurred while counting resources by access group IDs',
                        context: [
                            'use_case' => 'CountResources',
                            'user_is_admin' => $request->contact->isAdmin(),
                            'contact_id' => $request->contact->getId(),
                            'resources_filter' => $request->resourceFilter,
                        ],
                        exception: $exception
                    )
                );

                return;
            }
        }

        $response->setTotalResources($countResources);

        $presenter->presentResponse($response);
    }
}
