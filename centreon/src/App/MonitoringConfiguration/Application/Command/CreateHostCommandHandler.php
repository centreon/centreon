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

namespace App\MonitoringConfiguration\Application\Command;

use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Event\HostCreated;
use App\MonitoringConfiguration\Domain\Exception\HostAlreadyExistsException;
use App\MonitoringConfiguration\Domain\Exception\HostGroupNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\PollerNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\HostGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\MonitoringConfiguration\Domain\Repository\PollerRepository;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Shared\Application\Command\AsCommandHandler;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Event\EventBus;

#[AsCommandHandler]
final readonly class CreateHostCommandHandler
{
    public function __construct(
        private HostRepository $repository,
        private PollerRepository $pollerRepository,
        private HostGroupRepository $hostGroupRepository,
        private ResourceAccessRepository $resourceAccessRepository,
        private EventBus $eventBus,
    ) {
    }

    public function __invoke(CreateHostCommand $command): Host
    {
        if ($this->repository->isNameUsedByHostOrTemplate($command->name)) {
            throw new HostAlreadyExistsException(['name' => $command->name->value]);
        }

        // Throws PollerNotFoundException if it doesn't exist at all.
        $this->pollerRepository->get($command->pollerId);

        // A restricted (non-admin) viewer referencing a poller outside their own ACL scope gets
        // the same not-found error as a truly nonexistent poller — legacy does not distinguish
        // "doesn't exist" from "exists but you can't see it", to avoid leaking existence.
        if (
            $command->viewerId instanceof UserId
            && ! $this->resourceAccessRepository->hasAccessToPoller($command->pollerId, $command->viewerId)
        ) {
            throw new PollerNotFoundException(['id' => $command->pollerId->value]);
        }

        $this->assertHostGroupsExist($command->hostGroupIds, $command->viewerId);

        $host = new Host(
            id: null,
            name: $command->name,
            alias: null,
            address: $command->address,
            activated: true,
            pollerId: $command->pollerId,
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: $command->hostGroupIds,
        );

        $this->repository->add($host);

        $this->eventBus->fire(new HostCreated($host, $command->creatorId));

        return $host;
    }

    /**
     * @param Collection<HostGroupId> $hostGroupIds
     */
    private function assertHostGroupsExist(Collection $hostGroupIds, ?UserId $viewerId): void
    {
        if (count($hostGroupIds) === 0) {
            return;
        }

        $foundIds = array_keys($this->hostGroupRepository->findNamesByIds($hostGroupIds)->toArray());
        $requestedIds = array_map(static fn (HostGroupId $id): int => $id->value, $hostGroupIds->toArray());
        $missingIds = array_diff($requestedIds, $foundIds);

        if ($viewerId instanceof UserId) {
            $accessibleIds = $this->resourceAccessRepository->findAccessibleHostGroupIds($viewerId);
            if ($accessibleIds instanceof Collection) {
                $accessibleIdValues = array_map(static fn (HostGroupId $id): int => $id->value, $accessibleIds->toArray());
                $missingIds = array_unique(array_merge($missingIds, array_diff($requestedIds, $accessibleIdValues)));
            }
        }

        if ($missingIds !== []) {
            throw new HostGroupNotFoundException(['host_group_ids' => array_values($missingIds)]);
        }
    }
}
