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

namespace Core\HostGroup\Application\UseCase\UpdateHostGroup;

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Centreon\Domain\RequestParameters\RequestParameters;
use Core\Application\Common\UseCase\{
    ErrorResponse,
    InvalidArgumentResponse,
    NoContentResponse,
    NotFoundResponse,
    ResponseStatusInterface,
};
use Core\Common\Domain\SimpleEntity;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Domain\Common\GeoCoords;
use Core\Domain\Exception\InvalidGeoCoordException;
use Core\Host\Application\Exception\HostException;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\Host\Domain\Model\SmallHost;
use Core\HostGroup\Application\Exceptions\HostGroupException;
use Core\HostGroup\Application\Repository\{
    ReadHostGroupRepositoryInterface,
    WriteHostGroupRepositoryInterface,
};
use Core\HostGroup\Domain\Model\HostGroup;
use Core\MonitoringServer\Application\Repository\{
    ReadMonitoringServerRepositoryInterface,
    WriteMonitoringServerRepositoryInterface,
};
use Core\ResourceAccess\Application\Exception\RuleException;
use Core\ResourceAccess\Application\Repository\{
    ReadResourceAccessRepositoryInterface,
    WriteResourceAccessRepositoryInterface,
};
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\HostGroupFilterType;
use Core\Security\AccessGroup\Application\Repository\{
    ReadAccessGroupRepositoryInterface,
    WriteAccessGroupRepositoryInterface,
};
use Utility\Difference\BasicDifference;

final class UpdateHostGroup
{
    use LoggerTrait;

    /**
     * @param ContactInterface $user
     * @param UpdateHostGroupValidator $validator
     * @param DataStorageEngineInterface $storageEngine
     * @param boolean $isCloudPlatform
     * @param ReadHostGroupRepositoryInterface $readHostGroupRepository
     * @param ReadHostRepositoryInterface $readHostRepository
     * @param ReadAccessGroupRepositoryInterface $readAccessGroupRepository
     * @param ReadResourceAccessRepositoryInterface $readResourceAccessRepository
     * @param ReadMonitoringServerRepositoryInterface $readMonitoringServerRepository
     * @param ReadContactGroupRepositoryInterface $readContactGroupRepository
     * @param WriteHostGroupRepositoryInterface $writeHostGroupRepository
     * @param WriteResourceAccessRepositoryInterface $writeResourceAccessRepository
     * @param WriteMonitoringServerRepositoryInterface $writeMonitoringServerRepository
     * @param WriteAccessGroupRepositoryInterface $writeAccessGroupRepository
     */
    public function __construct(
        private readonly ContactInterface $user,
        private readonly UpdateHostGroupValidator $validator,
        private readonly DataStorageEngineInterface $storageEngine,
        private readonly bool $isCloudPlatform,
        private readonly ReadHostGroupRepositoryInterface $readHostGroupRepository,
        private readonly ReadHostRepositoryInterface $readHostRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ReadResourceAccessRepositoryInterface $readResourceAccessRepository,
        private readonly ReadMonitoringServerRepositoryInterface $readMonitoringServerRepository,
        private readonly ReadContactGroupRepositoryInterface $readContactGroupRepository,
        private readonly WriteHostGroupRepositoryInterface $writeHostGroupRepository,
        private readonly WriteResourceAccessRepositoryInterface $writeResourceAccessRepository,
        private readonly WriteMonitoringServerRepositoryInterface $writeMonitoringServerRepository,
        private readonly WriteAccessGroupRepositoryInterface $writeAccessGroupRepository
    ) {
    }

    /**
     * @param int $hostGroupId
     * @param UpdateHostGroupRequest $request
     * @param UpdateHostGroupPresenterInterface $presenter
     */
    public function __invoke(UpdateHostGroupRequest $request): ResponseStatusInterface
    {
        try {
            $existingHostGroup = $this->user->isAdmin()
                ? $this->readHostGroupRepository->findOne($request->id)
                : $this->readHostGroupRepository->findOneByAccessGroups(
                    $request->id,
                    $this->readAccessGroupRepository->findByContact($this->user)
                );

            if ($existingHostGroup === null) {
                return new NotFoundResponse('Host Group');
            }
            $this->validator->assertNameDoesNotAlreadyExists($existingHostGroup, $request->name);
            $this->validator->assertHostsExist($request->hosts);
            if ($this->isCloudPlatform) {
                $this->validator->assertResourceAccessRulesExist($request->resourceAccessRules);
            }

            if (! $this->storageEngine->isAlreadyInTransaction()) {
                $this->storageEngine->startTransaction();
            }

            $this->updateHostGroup($request, $existingHostGroup);
            $this->updateHosts($request);
            if($this->isCloudPlatform) {
                $this->updateResourceAccess($request);
            }

            $this->storageEngine->commitTransaction();

            return new NoContentResponse();
        } catch (HostGroupException|HostException|RuleException|AssertionFailedException|InvalidGeoCoordException $ex) {
            if ($this->storageEngine->isAlreadyInTransaction()) {
                $this->storageEngine->rollbackTransaction();
            }
            $this->error(
                "Error while updating host group : {$ex->getMessage()}",
                [
                    'hostGroupId' => $request->id,
                    'exception' => ['message' => $ex->getMessage(), 'trace' => $ex->getTraceAsString()],
                ]
            );

            return new InvalidArgumentResponse($ex);
        } catch (\Throwable $ex) {
            if ($this->storageEngine->isAlreadyInTransaction()) {
                $this->storageEngine->rollbackTransaction();
            }
            $this->error(
                "Error while updating host group : {$ex->getMessage()}",
                [
                    'hostGroupId' => $request->id,
                    'exception' => ['message' => $ex->getMessage(), 'trace' => $ex->getTraceAsString()],
                ]
            );

            return new ErrorResponse(HostGroupException::errorWhileUpdating());
        }
    }

    /**
     * Update the configuration options of the host group.
     *
     * @param UpdateHostGroupRequest $request
     * @param HostGroup $existingHostGroup
     *
     * @throws InvalidGeoCoordException|\Throwable
     */
    private function updateHostGroup(UpdateHostGroupRequest $request, HostGroup $existingHostGroup): void
    {
        $updatedHostGroup = new HostGroup(
            id: $request->id,
            name: $request->name,
            alias: $request->alias,
            comment: $request->comment,
            geoCoords: match ($request->geoCoords) {
                null, '' => null,
                default => GeoCoords::fromString($request->geoCoords),
            },
        );
        $this->writeHostGroupRepository->update($updatedHostGroup);
    }

    /**
     * Update the hosts linked to the host group.
     *
     * @param UpdateHostGroupRequest $request
     *
     * @throws \Throwable
     */
    private function updateHosts(UpdateHostGroupRequest $request): void
    {
        if ($this->user->isAdmin()) {
            $existingHosts = $this->readHostRepository->findByHostGroup($request->id);
            $hostsToRemove = array_map(fn (SimpleEntity $host): int => $host->getId(), $existingHosts);
        } else {
            $reachableHosts = $this->readHostRepository->findByRequestParametersAndAccessGroups(
                new RequestParameters(),
                $this->readAccessGroupRepository->findByContact($this->user)
            );

            $hostsToRemove = (new BasicDifference(
                array_map(fn (SmallHost $host) => $host->getId(), $reachableHosts),
                $request->hosts
            ))->getRemoved();
        }

        $this->writeHostGroupRepository->deleteHosts($request->id, $hostsToRemove);
        $this->writeHostGroupRepository->addHosts($request->id, $request->hosts);
        $this->notifyConfigurationChange($request->hosts);
    }

    /**
     * Update Resource Access Rules linked to the host group.
     *
     * @param UpdateHostGroupRequest $request
     *
     * @throws \Throwable
     */
    private function updateResourceAccess(UpdateHostGroupRequest $request): void
    {
        $rulesByHostgroup = $this->readResourceAccessRepository->existByTypeAndResourceId(
            HostGroupFilterType::TYPE_NAME,
            $request->id
        );

        $rulesDifference = new BasicDifference($rulesByHostgroup, $request->resourceAccessRules);
        $rulestoRemove = $rulesDifference->getRemoved();
        $rulestoAdd = $rulesDifference->getAdded();
        $this->unlinkHostGroupToRAM($rulestoRemove, $request->id);
        $this->linkHostGroupToRAM($rulestoAdd, $request->id);
    }

    /**
     * Link Host groups to Datasets's Resource Access Rules,
     * only if the dataset Hostgroup has no parent
     *
     * @param int[] $resourceAccessRuleIds
     * @param int $hostGroupId
     *
     * @throws \Throwable
     */
    private function linkHostGroupToRAM(array $resourceAccessRuleIds, int $hostGroupId): void
    {
        $datasetFilterRelations = $this->readResourceAccessRepository->findLastLevelDatasetFilterByRuleIdsAndType(
            $resourceAccessRuleIds,
            HostGroupFilterType::TYPE_NAME
        );
        foreach ($datasetFilterRelations as $datasetFilterRelation) {
            /**
             * Empty $resourceIds are dataset with "All Host Groups" Configured
             * So we don't want to update it,
             * as we will loss the "All Host Groups" notion.
             */
            if (! empty($datasetFilterRelation->getResourceIds())) {
                $resourceIds = $datasetFilterRelation->getResourceIds();
                $resourceIds[] = $hostGroupId;
                $this->writeResourceAccessRepository->updateDatasetResources(
                    $datasetFilterRelation->getDatasetFilterId(),
                    $resourceIds
                );
                $this->linkHostGroupToResourcesACL($hostGroupId, $datasetFilterRelation->getResourceAccessGroupId());
            }
        }
    }

    /**
     * Unlink Host groups to Datasets's Resource Access Rules.
     * Remove dataset filters if he is empty after removing the hostgroup.
     *
     * @param int[] $resourceAccessRuleIds
     * @param int $hostGroupId
     *
     * @throws \Throwable
     */
    private function unlinkHostGroupToRAM(array $resourceAccessRuleIds, int $hostGroupId): void
    {
        $datasetFilterRelations = $this->readResourceAccessRepository->findLastLevelDatasetFilterByRuleIdsAndType(
            $resourceAccessRuleIds,
            HostGroupFilterType::TYPE_NAME
        );
        foreach ($datasetFilterRelations as $datasetFilterRelation) {
            /**
             * Empty $resourceIds are dataset with "All Host Groups" Configured
             * So we don't want to delete the dataset, and we don't want to update it either,
             * as we will loss the "All Host Groups" notion.
             */
            if (! empty($datasetFilterRelation->getResourceIds())) {
                $resourceIdToUpdates = array_filter(
                    $datasetFilterRelation->getResourceIds(),
                    function ($resourceId) use ($hostGroupId) {
                        return $resourceId !== $hostGroupId;
                    }
                );
                if (empty($resourceIdToUpdates)) {
                    $this->writeResourceAccessRepository->deleteDatasetFilter($datasetFilterRelation->getDatasetFilterId());
                } else {
                    $this->writeResourceAccessRepository->updateDatasetResources($datasetFilterRelation->getDatasetFilterId(), $resourceIdToUpdates);
                }
                $this->unlinkHostGroupToResourcesACL($hostGroupId);
            }
        }
    }

    /**
     * Signal Monitoring Server Configuration change.
     *
     * @param int[] $linkedHostsIds
     */
    private function notifyConfigurationChange(array $linkedHostsIds): void
    {
        // Find monitoring servers associated with the linked hosts
        $serverIds = $this->readMonitoringServerRepository->findByHostsIds($linkedHostsIds);

        // Notify configuration changes
        $this->writeMonitoringServerRepository->notifyConfigurationChanges($serverIds);
    }

    /**
     * Unlink Host Groups to user Resource Access Groups
     *
     * @param int $hostGroupId
     *
     * @throws \Throwable
     */
    private function unlinkHostGroupToResourcesACL(int $hostGroupId): void
    {
        if (! $this->user->isAdmin()) {
            $this->writeAccessGroupRepository->removeLinksBetweenHostGroupAndAccessGroups(
                $hostGroupId,
                $this->readAccessGroupRepository->findByContact($this->user)
            );
        }
    }

    /**
     * Link Host Groups to user Resource Access Groups
     *
     * @param int $hostGroupId
     *
     * @throws \Throwable
     */
    private function linkHostGroupToResourcesACL(int $hostGroupId, int $datasetId): void
    {
        if (! $this->user->isAdmin()) {
            $this->writeAccessGroupRepository->addLinksBetweenHostGroupAndResourceAccessGroup(
                $hostGroupId,
                $datasetId
            );
        }
    }
}
