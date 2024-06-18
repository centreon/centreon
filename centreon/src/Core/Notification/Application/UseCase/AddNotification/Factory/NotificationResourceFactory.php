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

namespace Core\Notification\Application\UseCase\AddNotification\Factory;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Notification\Application\Converter\NotificationServiceEventConverter;
use Core\Notification\Application\Exception\NotificationException;
use Core\Notification\Application\Repository\NotificationResourceRepositoryInterface;
use Core\Notification\Application\Repository\NotificationResourceRepositoryProviderInterface;
use Core\Notification\Domain\Model\ConfigurationResource;
use Core\Notification\Domain\Model\NotificationResource;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Utility\Difference\BasicDifference;

class NotificationResourceFactory
{
    use LoggerTrait;

    public function __construct(
        private readonly NotificationResourceRepositoryProviderInterface $notificationResourceRepositoryProvider,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ContactInterface $user
    ) {
    }

    /**
     * Create multiple NotificationResource.
     *
     * @param array<array{
     *  type: string,
     *  ids: int[],
     *  events: int,
     *  includeServiceEvents: int
     * }> $resources
     *
     * @throws \Assert\AssertionFailedException
     * @throws NotificationException
     * @throws \Throwable
     *
     * @return NotificationResource[]
     */
    public function createNotificationResources(array $resources): array
    {
        if (empty($resources)) {
            throw NotificationException::emptyArrayNotAllowed('resource');
        }

        $newResources = [];
        foreach ($resources as $resourceData) {
            $resourceIds = array_unique($resourceData['ids']);
            if (count($resourceIds) === 0) {
                continue;
            }

            $resourceRepository = $this->notificationResourceRepositoryProvider->getRepository($resourceData['type']);
            // If multiple resources with same type are defined, only the last one of each type is kept
            $newResources[$resourceRepository->resourceType()] = $this->createNotificationResource(
                $resourceRepository,
                $resourceData
            );
        }

        $totalResources = 0;
        foreach ($newResources as $newResource) {
            $totalResources += $newResource->getResourcesCount();
        }
        if ($totalResources <= 0) {
            throw NotificationException::emptyArrayNotAllowed('resource.ids');
        }

        return $newResources;
    }

    /**
     * Create a NotificationResource.
     *
     * @param NotificationResourceRepositoryInterface $resourceRepository
     * @param array{
     *     type: string,
     *     ids: int[],
     *     events: int,
     *     includeServiceEvents: int
     * } $resource
     *
     * @throws NotificationException
     * @throws \Throwable
     *
     * @return NotificationResource
     */
    private function createNotificationResource(
        NotificationResourceRepositoryInterface $resourceRepository,
        array $resource
    ): NotificationResource{
        $resourceIds = array_unique($resource['ids']);

        if ($this->user->isAdmin()) {
            // Assert IDs validity without ACLs
            $existingResources = $resourceRepository->exist($resourceIds);
        } else {
            // Assert IDs validity with ACLs
            $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
            $existingResources = $resourceRepository->existByAccessGroups($resourceIds, $accessGroups);
        }

        $difference = new BasicDifference($resourceIds, $existingResources);
        $missingResources = $difference->getRemoved();
        if ([] !== $missingResources) {
            $this->error(
                'Invalid ID(s) provided',
                ['propertyName' => 'resources', 'propertyValues' => array_values($missingResources)]
            );

            throw NotificationException::invalidId('resource.ids');
        }

        // If multiple resources with same type are defined, only the last one of each type is kept
        return new NotificationResource(
            $resourceRepository->resourceType(),
            $resourceRepository->eventEnum(),
            array_map((fn($resourceId) => new ConfigurationResource($resourceId, '')), $resourceIds),
            ($resourceRepository->eventEnumConverter())::fromBitFlags($resource['events']),
            $resource['includeServiceEvents']
                ? NotificationServiceEventConverter::fromBitFlags($resource['includeServiceEvents'])
                : []
        );
    }
}
