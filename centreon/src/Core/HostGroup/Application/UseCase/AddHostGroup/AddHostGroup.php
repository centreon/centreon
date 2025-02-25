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

namespace Core\HostGroup\Application\UseCase\AddHostGroup;

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\{
    ErrorResponse,
    InvalidArgumentResponse,
    ResponseStatusInterface
};
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Domain\Common\GeoCoords;
use Core\Host\Application\Exception\HostException;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\HostGroup\Application\Exceptions\HostGroupException;
use Core\HostGroup\Application\Repository\{
    ReadHostGroupRepositoryInterface,
    WriteHostGroupRepositoryInterface
};
use Core\HostGroup\Domain\Model\HostGroupRelation;
use Core\HostGroup\Domain\Model\NewHostGroup;
use Core\ResourceAccess\Application\Exception\RuleException;
use Core\ResourceAccess\Application\Repository\{
    ReadResourceAccessRepositoryInterface,
    WriteResourceAccessRepositoryInterface
};
use Core\ResourceAccess\Domain\Model\DatasetFilter\Providers\HostGroupFilterType;
use Core\Security\AccessGroup\Application\Repository\{
    ReadAccessGroupRepositoryInterface,
    WriteAccessGroupRepositoryInterface
};

final class AddHostGroup
{
    use LoggerTrait;

    public function __construct(
        private readonly ContactInterface $user,
        private readonly AddHostGroupValidator $validator,
        private readonly DataStorageEngineInterface $storageEngine,
        private readonly bool $isCloudPlatform,
        private readonly ReadHostGroupRepositoryInterface $readHostGroupRepository,
        private readonly ReadResourceAccessRepositoryInterface $readResourceAccessRepository,
        private readonly ReadHostRepositoryInterface $readHostRepository,
        private readonly ReadContactGroupRepositoryInterface $readContactGroupRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly WriteHostGroupRepositoryInterface $writeHostGroupRepository,
        private readonly WriteResourceAccessRepositoryInterface $writeResourceAccessRepository,
        private readonly WriteAccessGroupRepositoryInterface $writeAccessGroupRepository,
    ) {
    }

    /**
     * @param AddHostGroupRequest $request
     */
    public function __invoke(AddHostGroupRequest $request): AddHostGroupResponse|ResponseStatusInterface
    {
        try {
            $this->validator->assertNameDoesNotAlreadyExists($request->name);
            $this->validator->assertHostsExist($request->hosts);
            if ($this->isCloudPlatform) {
                $this->validator->assertResourceAccessRulesExist($request->resourceAccessRules);
            }

            $hostGroup = new NewHostGroup(
                name: $request->name,
                alias: $request->alias,
                comment: $request->comment,
                geoCoords: match ($request->geoCoords) {
                    null, '' => null,
                    default => GeoCoords::fromString($request->geoCoords),
                },
            );

            if (! $this->storageEngine->isAlreadyinTransaction()) {
                $this->storageEngine->startTransaction();
            }

            $newHostGroupId = $this->writeHostGroupRepository->add($hostGroup);

            $this->writeHostGroupRepository->addHosts($newHostGroupId, $request->hosts);
            $this->linkHostGroupToRessourceAccess($request->resourceAccessRules, $newHostGroupId);
            $this->storageEngine->commitTransaction();

            $newHostGroup = $this->readHostGroupRepository->findOne($newHostGroupId);
            if ($newHostGroup === null) {
                throw HostGroupException::errorWhileRetrievingJustCreated();
            }
            $linkedHosts = $this->readHostRepository->findByHostGroup($newHostGroupId);
            if ($this->isCloudPlatform) {
                $linkedResourceAccessRules = array_map(
                    fn (int $ruleId) => $this->readResourceAccessRepository->findById($ruleId),
                    $request->resourceAccessRules
                );
            }

            return new AddHostGroupResponse(
                new HostGroupRelation($newHostGroup, $linkedHosts, $linkedResourceAccessRules ?? [])
            );
        } catch (HostGroupException|HostException|RuleException|AssertionFailedException $ex) {
            $this->error(
                "Error while adding host groups : {$ex->getMessage()}",
                [
                    'exception' => ['message' => $ex->getMessage(), 'trace' => $ex->getTraceAsString()],
                ]
            );


            return new InvalidArgumentResponse($ex);
        } catch (\Throwable $ex) {
            $this->error(
                "Error while adding host groups : {$ex->getMessage()}",
                [
                    'exception' => ['message' => $ex->getMessage(), 'trace' => $ex->getTraceAsString()],
                ]
            );

            return new ErrorResponse(HostGroupException::errorWhileAdding());
        }
    }

    /**
     * Link Host Groups to Ressource Access
     *      For On Prem: Host Groups are linked to Ressource Access Groups
     *      For Cloud: Host Groups are added to Datasets's Resource Access Rules,
     *          only if the dataset Hostgroup has no parent
     *
     * @param array $resourceAccessRuleIds
     * @param int $hostGroupId
     *
     * @throws \Throwable
     */
    private function linkHostGroupToRessourceAccess(array $resourceAccessRuleIds, int $hostGroupId): void
    {
        if ($this->isCloudPlatform) {
            $this->linkHostGroupToRAM($resourceAccessRuleIds, $hostGroupId);
        } else {
            $this->linkHostGroupToResourcesACL($hostGroupId);
        }
    }

    /**
     * Link Host groups to Datasets's Resource Access Rules, only if the dataset Hostgroup has no parent
     *
     * @param int[] $resourceAccessRuleIds
     * @param int $hostGroupId
     *
     * @throws \Throwable
     */
    private function linkHostGroupToRAM(array $resourceAccessRuleIds, int $hostGroupId): void
    {
        $datasetFilters = $this->readResourceAccessRepository->findLastLevelDatasetFilterByRuleIdsAndType(
            $resourceAccessRuleIds,
            HostGroupFilterType::TYPE_NAME
        );

        foreach ($datasetFilters as $datasetId => $resourceIds) {
            $resourceIds[] = $hostGroupId;
            $this->writeResourceAccessRepository->updateDatasetResources($datasetId, $resourceIds);
        }

    }

    /**
     * Link Host Groups to user Resource Access Groups
     *
     * @param int $hostGroupId
     *
     * @throws \Throwable
     */
    private function linkHostGroupToResourcesACL(int $hostGroupId): void
    {
        if (! $this->user->isAdmin()) {
            $this->writeAccessGroupRepository->addLinksBetweenHostGroupAndAccessGroups(
                $hostGroupId,
                $this->readAccessGroupRepository->findByContact($this->contact)
            );
        }
    }
}
