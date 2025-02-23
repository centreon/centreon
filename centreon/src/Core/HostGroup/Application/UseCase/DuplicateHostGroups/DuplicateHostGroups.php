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

namespace Core\HostGroup\Application\UseCase\DuplicateHostGroups;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Common\Domain\ResponseCodeEnum;
use Core\HostGroup\Application\Exceptions\HostGroupException;
use Core\HostGroup\Application\Repository\ReadHostGroupRepositoryInterface;
use Core\HostGroup\Application\Repository\WriteHostGroupRepositoryInterface;
use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use Core\MonitoringServer\Application\Repository\WriteMonitoringServerRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\WriteAccessGroupRepositoryInterface;

final class DuplicateHostGroups
{
    use LoggerTrait;

    public function __construct(
        private readonly ContactInterface $user,
        private readonly ReadHostGroupRepositoryInterface $readHostGroupRepository,
        private readonly WriteHostGroupRepositoryInterface $writeHostGroupRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly WriteAccessGroupRepositoryInterface $writeAccessGroupRepository,
        private readonly ReadMonitoringServerRepositoryInterface $readMonitoringServerRepository,
        private readonly WriteMonitoringServerRepositoryInterface $writeMonitoringServerRepository,
    ) {
    }

    /**
     * @param DuplicateHostGroupsRequest $request
     *
     * @return DuplicateHostGroupsResponse
     */
    public function __invoke(DuplicateHostGroupsRequest $request): DuplicateHostGroupsResponse
    {
        $results = [];
        foreach ($request->hostGroupIds as $hostGroupId) {
            $statusResponse = new DuplicateHostGroupsStatusResponse();
            $statusResponse->id = $hostGroupId;
            try {
                if (! $this->hostGroupExists($hostGroupId)) {
                    $statusResponse->status = ResponseCodeEnum::NotFound;
                    $statusResponse->message = (new NotFoundResponse('Host Group'))->getMessage();
                    $results[] = $statusResponse;
                    continue;
                }

                $hostGroupName = $this->readHostGroupRepository->findNames([$hostGroupId])->getName($hostGroupId);
                $duplicated = 0;
                $duplicateIndex = 1;

                while ($duplicated < $request->nbDuplicates) {
                    if ($this->hostGroupExistsByName($hostGroupName . '_' . $duplicateIndex)) {
                        $duplicateIndex++;
                        continue;
                    }
                    $newHostGroupId = $this->writeHostGroupRepository->duplicate($hostGroupId, $duplicateIndex);
                    // Handle ACL Resources for non admin users
                    if (! $this->user->isAdmin()) {
                        $this->duplicateContactAclResources($newHostGroupId);
                    }
                    // Duplicate Host Relationships
                    $linkedHostsIds = $this->readHostGroupRepository->findLinkedHosts($hostGroupId);
                    $this->duplicateHostsRelations($linkedHostsIds, $newHostGroupId);
                    // Signal configuration change
                    $this->signalConfigurationChange($linkedHostsIds);
                    // Duplicate HG ACLs
                    $this->duplicateHostGroupAcls($hostGroupId, $newHostGroupId);
                    // Update ACL Groups Flag
                    $this->updateAclGroupsFlag();
                    $duplicated++;
                }

                $results[] = $statusResponse;
            } catch (\Throwable $ex) {
                $this->error(
                    "Error while duplicating host groups : {$ex->getMessage()}",
                    [
                        'hostgroupIds' => $request->hostGroupIds,
                        'current_hostgroupId' => $hostGroupId,
                        'exception' => ['message' => $ex->getMessage(), 'trace' => $ex->getTraceAsString()],
                    ]
                );

                $statusResponse->status = ResponseCodeEnum::Error;
                $statusResponse->message = HostGroupException::errorWhileDuplicating()->getMessage();
                $results[] = $statusResponse;
            }
        }

        return new DuplicateHostGroupsResponse($results);
    }

    /**
     * Checks that host group exists for the user regarding ACLs
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
     * Checks whether a host group exists by name
     *
     * @param string $hostGroupName
     * @return bool
     */
    private function hostGroupExistsByName(string $hostGroupName): bool
    {
        return $this->user->isAdmin()
            ? $this->readHostGroupRepository->nameAlreadyExists($hostGroupName)
            : $this->readHostGroupRepository->nameAlreadyExistsByAccessGroups(
                $hostGroupName,
                $this->readAccessGroupRepository->findByContact($this->user)
            );
    }

    /**
     * Duplicate hosts relations.
     *
     * @param int $newHostGroupId
     * @param int[] $linkedHostsIds
     */
    private function duplicateHostsRelations(array $linkedHostsIds, int $newHostGroupId): void
    {
        foreach ($linkedHostsIds as $hostId) {
            $this->writeHostGroupRepository->linkToHost($hostId, [$newHostGroupId]);
        }
    }

    /**
     * Link HG to contact ACL.
     *
     * @param int $hostGroupId
     */
    private function duplicateContactAclResources(int $hostGroupId): void
    {
        $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
        $this->writeAccessGroupRepository->addLinksBetweenHostGroupAndAccessGroups($hostGroupId, $accessGroups);
    }

    /**
     * Duplicate HG ACL.
     *
     * @param int $hostGroupId
     * @param int $newHostGroupId
     */
    private function duplicateHostGroupAcls(int $hostGroupId, int $newHostGroupId): void
    {
        $aclResources = $this->readAccessGroupRepository->findAclResourcesByHostGroupId($hostGroupId);
        $this->writeAccessGroupRepository->addLinksBetweenHostGroupAndResourceIds($newHostGroupId, $aclResources);
    }

    /**
     * Update ACL groups flag.
     */
    private function updateAclGroupsFlag(): void
    {
        if (! $this->user->isAdmin()) {
            $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
            $this->writeAccessGroupRepository->updateAclGroupsFlag($accessGroups);
        } else {
            $this->writeAccessGroupRepository->updateAclResourcesFlag();
        }
    }

    /**
     * Signal configuration change.
     *
     * @param int[] $linkedHostsIds
     */
    private function signalConfigurationChange(array $linkedHostsIds): void
    {
        // Find monitoring servers associated with the linked hosts
        $serverIds = $this->readMonitoringServerRepository->findByHostsIds($linkedHostsIds);

        // Notify configuration changes
        $this->writeMonitoringServerRepository->notifyConfigurationChanges($serverIds);
    }
}