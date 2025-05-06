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

namespace Core\HostGroup\Application\UseCase\EnableDisableHostGroups;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Common\Domain\ResponseCodeEnum;
use Core\HostGroup\Application\Exceptions\HostGroupException;
use Core\HostGroup\Application\Repository\ReadHostGroupRepositoryInterface;
use Core\HostGroup\Application\Repository\WriteHostGroupRepositoryInterface;
use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use Core\MonitoringServer\Application\Repository\WriteMonitoringServerRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

final class EnableDisableHostGroups
{
    use LoggerTrait;

    public function __construct(
        private readonly ContactInterface $user,
        private readonly DataStorageEngineInterface $storageEngine,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ReadHostGroupRepositoryInterface $readRepository,
        private readonly WriteHostGroupRepositoryInterface $writeRepository,
        private readonly ReadMonitoringServerRepositoryInterface $readMonitoringServerRepository,
        private readonly WriteMonitoringServerRepositoryInterface $writeMonitoringServerRepository,
    ) {
    }

    public function __invoke(EnableDisableHostGroupsRequest $request): EnableDisableHostGroupsResponse
    {
        $results = [];
        foreach ($request->hostGroupIds as $hostGroupId) {
            $statusResponse = new EnableDisableHostGroupsStatusResponse();
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
                $this->writeRepository->enableDisableHostGroup($hostGroupId, $request->isEnable);
                $linkedHostIds = $this->readRepository->findLinkedHosts($hostGroupId);
                $this->notifyConfigurationChange($linkedHostIds);

                if ($this->storageEngine->isAlreadyinTransaction()) {
                    $this->storageEngine->commitTransaction();
                }

                $results[] = $statusResponse;
            } catch (\Exception $ex) {
                if ($this->storageEngine->isAlreadyinTransaction()) {
                    $this->storageEngine->rollbackTransaction();
                }
                $this->error(
                    "Error while enabling/disabling host groups : {$ex->getMessage()}",
                    [
                        'hostgroupIds' => $request->hostGroupIds,
                        'current_hostgroupId' => $hostGroupId,
                        'exception' => ['message' => $ex->getMessage(), 'trace' => $ex->getTraceAsString()],
                    ]
                );

                $statusResponse->status = ResponseCodeEnum::Error;
                $statusResponse->message = HostGroupException::errorWhileEnablingDisabling()->getMessage();

                $results[] = $statusResponse;
            }
        }

        return new EnableDisableHostGroupsResponse($results);
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
            ? $this->readRepository->existsOne($hostGroupId)
            : $this->readRepository->existsOneByAccessGroups(
                $hostGroupId,
                $this->readAccessGroupRepository->findByContact($this->user)
            );
    }

    /**
     * Notify configuration change to monitoring servers.
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
}
