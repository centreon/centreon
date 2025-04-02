<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Core\HostGroup\Application\UseCase\DeleteHostGroups;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Common\Domain\ResponseCodeEnum;
use Core\HostGroup\Application\Exceptions\HostGroupException;
use Core\HostGroup\Application\Repository\ReadHostGroupRepositoryInterface;
use Core\HostGroup\Application\Repository\WriteHostGroupRepositoryInterface;
use Core\Notification\Application\Repository\ReadNotificationRepositoryInterface;
use Core\Notification\Application\Repository\WriteNotificationRepositoryInterface;
use Core\ResourceAccess\Application\Repository\ReadResourceAccessRepositoryInterface;
use Core\ResourceAccess\Application\Repository\WriteResourceAccessRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Service\Application\Repository\ReadServiceRepositoryInterface;
use Core\Service\Application\Repository\WriteServiceRepositoryInterface;
use Core\Service\Domain\Model\ServiceRelation;

final class DeleteHostGroups
{
    use LoggerTrait;

    /**
     * @param ContactInterface $user
     * @param WriteHostGroupRepositoryInterface $writeHostGroupRepository
     * @param ReadHostGroupRepositoryInterface $readHostGroupRepository
     * @param ReadAccessGroupRepositoryInterface $readAccessGroupRepository
     * @param ReadNotificationRepositoryInterface $readNotificationRepository
     * @param WriteNotificationRepositoryInterface $writeNotificationRepository
     * @param ReadServiceRepositoryInterface $readServiceRepository
     * @param WriteServiceRepositoryInterface $writeServiceRepository
     * @param ReadResourceAccessRepositoryInterface $readResourceAccessRepository
     * @param WriteResourceAccessRepositoryInterface $writeResourceAccessRepository
     * @param DataStorageEngineInterface $storageEngine
     * @param bool $isCloudPlatform
     */
    public function __construct(
        private readonly ContactInterface $user,
        private readonly WriteHostGroupRepositoryInterface $writeHostGroupRepository,
        private readonly ReadHostGroupRepositoryInterface $readHostGroupRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ReadNotificationRepositoryInterface $readNotificationRepository,
        private readonly WriteNotificationRepositoryInterface $writeNotificationRepository,
        private readonly ReadServiceRepositoryInterface $readServiceRepository,
        private readonly WriteServiceRepositoryInterface $writeServiceRepository,
        private readonly ReadResourceAccessRepositoryInterface $readResourceAccessRepository,
        private readonly WriteResourceAccessRepositoryInterface $writeResourceAccessRepository,
        private readonly DataStorageEngineInterface $storageEngine,
        private readonly bool $isCloudPlatform
    ) {
    }

    /**
     * @param DeleteHostGroupsRequest $request
     *
     * @return DeleteHostGroupsResponse
     */
    public function __invoke(DeleteHostGroupsRequest $request): DeleteHostGroupsResponse
    {
        $results = [];
        foreach ($request->hostGroupIds as $hostGroupId) {
            $statusResponse = new DeleteHostGroupsStatusResponse();
            $statusResponse->id = $hostGroupId;
            try {
                if (! $this->storageEngine->isAlreadyinTransaction()) {
                    $this->storageEngine->startTransaction();
                }
                if (! $this->hostGroupExists($hostGroupId)) {
                    $statusResponse->status = ResponseCodeEnum::NotFound;
                    $statusResponse->message = (new NotFoundResponse('Host Group'))->getMessage();
                    $results[] = $statusResponse;

                    continue;
                }
                $this->deleteNotificationHostGroupDependency($hostGroupId);
                $this->deleteServiceRelations($hostGroupId);
                if ($this->isCloudPlatform) {
                    $this->deleteFromDatasets($hostGroupId);
                }

                $this->writeHostGroupRepository->deleteHostGroup($hostGroupId);
                $this->storageEngine->commitTransaction();

                $results[] = $statusResponse;
            } catch (\Throwable $ex) {
                if ($this->storageEngine->isAlreadyinTransaction()) {
                    $this->storageEngine->rollbackTransaction();
                }
                $this->error(
                    "Error while deleting host groups : {$ex->getMessage()}",
                    [
                        'hostgroupIds' => $request->hostGroupIds,
                        'current_hostgroupId' => $hostGroupId,
                        'exception' => ['message' => $ex->getMessage(), 'trace' => $ex->getTraceAsString()],
                    ]
                );

                $statusResponse->status = ResponseCodeEnum::Error;
                $statusResponse->message = HostGroupException::errorWhileDeleting()->getMessage();

                $results[] = $statusResponse;
            }
        }

        return new DeleteHostGroupsResponse($results);
    }

    /**
     * Check that host group exists for the user regarding ACLs.
     *
     * @param int $hostGroupId
     *
     * @return bool
     */
    private function hostGroupExists(int $hostGroupId): bool
    {
        return $this->user->isAdmin()
            ? $this->readHostGroupRepository->existsOne($hostGroupId)
            : $this->readHostGroupRepository->existsOneByAccessGroups(
                $hostGroupId,
                $this->readAccessGroupRepository->findByContact($this->user)
            );
    }

    /**
     * If the host group was the last dependency of a notification, we need to delete the Dependency.
     *
     * @param int $hostGroupId
     *
     * @throws \Throwable
     */
    private function deleteNotificationHostGroupDependency(int $hostGroupId): void
    {
        $lastDependencyIds = $this->readNotificationRepository
            ->findLastNotificationDependencyIdsByHostGroup($hostGroupId);

        if (! empty($lastDependencyIds)) {
            $this->writeNotificationRepository->deleteDependencies($lastDependencyIds);
        }
    }

    /**
     * If the host group was the last host group of a service, we need to delete the service.
     *
     * @param int $hostGroupId
     *
     * @throws \Throwable
     */
    private function deleteServiceRelations(int $hostGroupId): void
    {
        $lastRelations = array_filter(
            $this->readServiceRepository->findServiceRelationsByHostGroupId($hostGroupId),
            fn (ServiceRelation $relation): bool => $relation->hasOnlyOneHostGroup()
        );
        if (! empty($lastRelations)) {
            $this->writeServiceRepository->deleteByIds(
                ...array_map(fn (ServiceRelation $relation) => $relation->getServiceId(), $lastRelations)
            );
        }
    }

    /**
     * Delete the host group from the Resource Access datasets.
     *
     * @param int $hostGroupId
     *
     * @throws \Throwable
     */
    private function deleteFromDatasets(int $hostGroupId): void
    {
        $datasetResourceIds = $this->readResourceAccessRepository
            ->findDatasetResourceIdsByHostGroupId($hostGroupId);

        foreach ($datasetResourceIds as $datasetFilterId => &$resourceIds) {
            $resourceIds = array_filter($resourceIds, fn($resourceId) => $resourceId !== $hostGroupId);
            if (empty($resourceIds)) {
                $this->writeResourceAccessRepository->deleteDatasetFilter($datasetFilterId);
            } else {
                $this->writeResourceAccessRepository->updateDatasetResources($datasetFilterId, $resourceIds);
            }
        }
    }
}
