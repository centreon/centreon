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
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SnmpCommunity;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityName;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneName;
use App\MonitoringConfiguration\Domain\Event\HostCreated;
use App\MonitoringConfiguration\Domain\Event\HostServicesDeploymentRequested;
use App\MonitoringConfiguration\Domain\Exception\CircularHostRelationException;
use App\MonitoringConfiguration\Domain\Exception\HostAlreadyExistsException;
use App\MonitoringConfiguration\Domain\Exception\HostCategoryNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostGroupNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostSeverityNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\PollerNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\TimezoneNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\HostCategoryRepository;
use App\MonitoringConfiguration\Domain\Repository\HostGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\MonitoringConfiguration\Domain\Repository\HostSeverityRepository;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\PollerRepository;
use App\MonitoringConfiguration\Domain\Repository\TimezoneRepository;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Shared\Application\Command\AsCommandHandler;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Event\EventBus;
use App\Shared\Domain\VaultInterface;

#[AsCommandHandler]
final readonly class CreateHostCommandHandler
{
    public function __construct(
        private HostRepository $repository,
        private PollerRepository $pollerRepository,
        private HostGroupRepository $hostGroupRepository,
        private HostTemplateRepository $hostTemplateRepository,
        private HostCategoryRepository $hostCategoryRepository,
        private HostSeverityRepository $hostSeverityRepository,
        private TimezoneRepository $timezoneRepository,
        private ResourceAccessRepository $resourceAccessRepository,
        private VaultInterface $vault,
        private EventBus $eventBus,
    ) {
    }

    public function __invoke(CreateHostCommand $command): Host
    {
        // References first, so a restricted viewer cannot tell a duplicate name apart from an
        // inaccessible reference. UniqueHostName already reports the duplicate at validation time,
        // which makes this the fallback ordering rather than the gate.
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
        $this->assertTemplatesExist($command->templateIds);
        $this->assertCategoriesExist($command->categoryIds, $command->viewerId);
        $this->assertSeverityExists($command->severityId, $command->viewerId);
        $this->assertTimezoneExists($command->timezoneId);
        $this->assertRelatedHostsExist($command->parentHostIds, $command->childHostIds);
        $this->assertRelationsAreNotCircular($command->parentHostIds, $command->childHostIds);

        if ($this->repository->isNameUsedByHostOrTemplate($command->name)) {
            throw new HostAlreadyExistsException(['name' => $command->name->value]);
        }

        $host = new Host(
            id: null,
            name: $command->name,
            alias: $command->alias,
            address: $command->address,
            activated: true,
            pollerId: $command->pollerId,
            templateIds: $command->templateIds,
            hostGroupIds: $command->hostGroupIds,
            categoryIds: $command->categoryIds,
            parentHostIds: $command->parentHostIds,
            childHostIds: $command->childHostIds,
            snmpVersion: $command->snmpVersion,
            snmpCommunity: $this->vaultOrPlaintext($command->snmpCommunity),
            timezoneId: $command->timezoneId,
            severityId: $command->severityId,
            extendedInformations: $command->extendedInformations,
            schedulingOptions: $command->schedulingOptions,
        );

        $this->repository->add($host);

        $this->eventBus->fire(new HostCreated($host, $command->creatorId));

        if ($command->deployServicesFromTemplates && count($command->templateIds) > 0) {
            $this->eventBus->fire(new HostServicesDeploymentRequested(
                $host->id(),
                new UserId($command->creatorId),
            ));
        }

        return $host;
    }

    /**
     * Legacy reuses this entry's UUID for the host's password macros: whoever migrates them must
     * thread it through rather than mint a second secret.
     */
    private function vaultOrPlaintext(?string $snmpCommunity): ?SnmpCommunity
    {
        if ($snmpCommunity === null) {
            return null;
        }

        // Built from what the column will hold, so its length bound measures the right value:
        // the `secret::` reference with a vault, the plaintext without one.
        if (! $this->vault->isEnabled()) {
            return new SnmpCommunity($snmpCommunity);
        }

        return new SnmpCommunity($this->vault->write(
            VaultInterface::HOST_VAULT_PATH,
            VaultInterface::HOST_SNMP_COMMUNITY_KEY,
            $snmpCommunity,
        ));
    }

    /**
     * @param Collection<HostGroupId> $hostGroupIds
     */
    private function assertHostGroupsExist(Collection $hostGroupIds, ?UserId $viewerId): void
    {
        $missingIds = $this->missingIds(
            $hostGroupIds,
            fn (Collection $ids): array => array_keys($this->hostGroupRepository->findNamesByIds($ids)->toArray()),
            $viewerId instanceof UserId ? $this->resourceAccessRepository->findAccessibleHostGroupIds($viewerId) : null,
        );

        if ($missingIds !== []) {
            throw new HostGroupNotFoundException(['hostGroupIds' => $missingIds]);
        }
    }

    /**
     * Unknown and inaccessible ids are not told apart, so a restricted viewer cannot probe for
     * resources they cannot see.
     *
     * @template T of \App\Shared\Domain\Aggregate\AggregateRootId
     *
     * @param Collection<T> $requested
     * @param callable(Collection<T>): list<int> $resolve
     * @param Collection<T>|null $accessible
     *
     * @return list<int>
     */
    private function missingIds(Collection $requested, callable $resolve, ?Collection $accessible = null): array
    {
        if (count($requested) === 0) {
            return [];
        }

        $requestedIds = array_map(static fn (object $id): int => $id->value, $requested->toArray());
        $missingIds = array_diff($requestedIds, $resolve($requested));

        if ($accessible instanceof Collection) {
            $accessibleIds = array_map(static fn (object $id): int => $id->value, $accessible->toArray());
            $missingIds = array_merge($missingIds, array_diff($requestedIds, $accessibleIds));
        }

        return array_values(array_unique($missingIds));
    }

    /**
     * @param Collection<HostTemplateId> $templateIds
     */
    private function assertTemplatesExist(Collection $templateIds): void
    {
        $missingIds = $this->missingIds(
            $templateIds,
            fn (Collection $ids): array => array_keys($this->hostTemplateRepository->findNamesByIds($ids)->toArray()),
        );

        if ($missingIds !== []) {
            throw HostTemplateNotFoundException::forIds($missingIds);
        }
    }

    /**
     * @param Collection<HostCategoryId> $categoryIds
     */
    private function assertCategoriesExist(Collection $categoryIds, ?UserId $viewerId): void
    {
        $missingIds = $this->missingIds(
            $categoryIds,
            fn (Collection $ids): array => array_keys($this->hostCategoryRepository->findNamesByIds($ids)->toArray()),
            $viewerId instanceof UserId ? $this->resourceAccessRepository->findAccessibleHostCategoryIds($viewerId) : null,
        );

        if ($missingIds !== []) {
            throw HostCategoryNotFoundException::forIds($missingIds);
        }
    }

    private function assertSeverityExists(?HostSeverityId $severityId, ?UserId $viewerId): void
    {
        if (! $severityId instanceof HostSeverityId) {
            return;
        }

        $missingIds = $this->missingIds(
            new Collection([$severityId], HostSeverityId::class),
            fn (Collection $ids): array => $this->hostSeverityRepository->findNameById($severityId) instanceof HostSeverityName
                ? [$severityId->value]
                : [],
            $viewerId instanceof UserId ? $this->resourceAccessRepository->findAccessibleHostSeverityIds($viewerId) : null,
        );

        if ($missingIds !== []) {
            throw HostSeverityNotFoundException::forId($severityId->value);
        }
    }

    private function assertTimezoneExists(?TimezoneId $timezoneId): void
    {
        if ($timezoneId instanceof TimezoneId && ! $this->timezoneRepository->findNameById($timezoneId) instanceof TimezoneName) {
            throw TimezoneNotFoundException::forId($timezoneId->value);
        }
    }

    /**
     * Not ACL-scoped: the legacy API never exposed these two fields, and its form writes back
     * whatever is posted (DB-Func.php's updateHostHostParent()), so there is nothing stricter to
     * be ISO with.
     *
     * @param Collection<HostId> $parentHostIds
     * @param Collection<HostId> $childHostIds
     */
    private function assertRelatedHostsExist(Collection $parentHostIds, Collection $childHostIds): void
    {
        $this->assertHostsExist($parentHostIds, 'parentHostIds');
        $this->assertHostsExist($childHostIds, 'childHostIds');
    }

    /**
     * @param Collection<HostId> $hostIds
     */
    private function assertHostsExist(Collection $hostIds, string $criterion): void
    {
        $missingIds = $this->missingIds(
            $hostIds,
            fn (Collection $ids): array => array_keys($this->repository->findNamesByIds($ids)->toArray()),
        );

        if ($missingIds !== []) {
            throw HostNotFoundException::forIds($missingIds, $criterion);
        }
    }

    /**
     * A loop is closed exactly when a requested child is an ancestor-or-self of a requested
     * parent. `findAncestorIds()` includes the inputs, so the same-host-on-both-sides case falls
     * out of the same traversal.
     *
     * @param Collection<HostId> $parentHostIds
     * @param Collection<HostId> $childHostIds
     */
    private function assertRelationsAreNotCircular(Collection $parentHostIds, Collection $childHostIds): void
    {
        if (count($parentHostIds) === 0 || count($childHostIds) === 0) {
            return;
        }

        $ancestorIds = array_map(
            static fn (HostId $id): int => $id->value,
            $this->repository->findAncestorIds($parentHostIds)->toArray(),
        );
        $childIds = array_map(static fn (HostId $id): int => $id->value, $childHostIds->toArray());

        $offendingIds = array_intersect($childIds, $ancestorIds);

        if ($offendingIds !== []) {
            throw CircularHostRelationException::forIds(array_values($offendingIds));
        }
    }
}
