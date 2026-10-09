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

use App\MonitoringConfiguration\Application\Service\AdditiveInheritanceModeApplier;
use App\MonitoringConfiguration\Application\Service\HostMacroSecretsSynchronizer;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\CheckOptions;
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
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\MonitoringConfiguration\Domain\Repository\HostCategoryRepository;
use App\MonitoringConfiguration\Domain\Repository\HostGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\MonitoringConfiguration\Domain\Repository\HostSeverityRepository;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\PollerRepository;
use App\MonitoringConfiguration\Domain\Repository\TimezoneRepository;
use App\MonitoringConfiguration\Domain\Service\HostMacroChangesResolver;
use App\MonitoringConfiguration\Domain\Service\InheritedHostMacrosResolver;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Shared\Application\Command\AsCommandHandler;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Event\EventBus;
use App\Shared\Domain\VaultInterface;

#[AsCommandHandler]
final readonly class CreateHostCommandHandler
{
    /** Must match legacy: both address the same vault entries. */
    public const HOST_VAULT_PATH = 'monitoring/hosts';
    public const HOST_SNMP_COMMUNITY_KEY = '_HOSTSNMPCOMMUNITY';

    public function __construct(
        private HostRepository $repository,
        private PollerRepository $pollerRepository,
        private HostGroupRepository $hostGroupRepository,
        private HostTemplateRepository $hostTemplateRepository,
        private HostCategoryRepository $hostCategoryRepository,
        private HostSeverityRepository $hostSeverityRepository,
        private TimezoneRepository $timezoneRepository,
        private CommandRepository $commandRepository,
        private InheritedHostMacrosResolver $inheritedHostMacrosResolver,
        private HostMacroChangesResolver $hostMacroChangesResolver,
        private HostMacroSecretsSynchronizer $hostMacroSecretsSynchronizer,
        private AdditiveInheritanceModeApplier $additiveInheritanceModeApplier,
        private ResourceAccessRepository $resourceAccessRepository,
        private VaultInterface $vault,
        private EventBus $eventBus,
    ) {
    }

    public function __invoke(CreateHostCommand $command): Host
    {
        // References are authorized before the name is looked up: otherwise a restricted viewer
        // could tell a duplicate name (409) apart from an inaccessible poller/host-group (404)
        // for a request they aren't even authorized to make, and enumerate host/template names
        // that way.

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

        $this->assertTemplatesExist($command->templateIds);

        $this->assertCategoriesExist($command->categoryIds, $command->viewerId);

        $this->assertSeverityExists($command->severityId, $command->viewerId);

        $this->assertTimezoneExists($command->timezoneId);

        $this->assertRelatedHostsExist($command->parentHostIds, $command->childHostIds, $command->viewerId);

        $this->assertRelationsAreNotCircular($command->parentHostIds, $command->childHostIds);

        // Authoritative existence guard for the referenced check command, like the poller check
        // above: existence and the "must be a check command" rule are validated at the API boundary
        // (CheckCommandTypeValidator, →422), but this getById() stays as the last word so a command
        // deleted between validation and execution surfaces as a 404 rather than a broken write.
        if ($command->checkCommandId instanceof CommandId) {
            $this->commandRepository->getById($command->checkCommandId);
        }

        if ($this->repository->isNameUsedByHostOrTemplate($command->name)) {
            throw new HostAlreadyExistsException(['name' => $command->name->value]);
        }

        // Every validation runs before the first vault write, so a rejected request never leaves an
        // orphan secret behind. Inherited macros are resolved from the requested templates and the
        // check command together, so a submitted macro that merely duplicates an inherited one is
        // dropped.
        $checkOptions = new CheckOptions($command->checkCommandId, $command->checkCommandArgs);
        $inherited = $this->inheritedHostMacrosResolver->resolve($command->templateIds, $command->checkCommandId);
        $macros = $this->hostMacroChangesResolver->resolve(array_values($command->macroChanges->toArray()), [], $inherited);

        // Vault the SNMP community first so its entry's UUID can be reused for the password macros:
        // legacy keeps all of a host's secrets (SNMP community + password macros) under a single vault
        // entry. When there is no community (or the vault is off) the UUID is null and the macros mint
        // their own shared entry instead.
        $snmpCommunity = $this->vaultOrPlaintext($command->snmpCommunity);
        $vaultUuid = $snmpCommunity instanceof SnmpCommunity ? $this->extractVaultUuid($snmpCommunity->value) : null;

        // Moves any password macro's plaintext into the vault, leaving a `secret::` reference in its
        // place. A host being created owns no macro yet, so there is nothing previous to purge.
        $checkOptions = $checkOptions->with(
            macros: $this->hostMacroSecretsSynchronizer->synchronize($macros, [], $vaultUuid),
        );

        $host = new Host(
            id: null,
            name: $command->name,
            alias: $command->alias,
            address: $command->address,
            activated: true,
            pollerId: $command->pollerId,
            hostGroupIds: $command->hostGroupIds,
            dataProcessing: $command->dataProcessing,
            templateIds: $command->templateIds,
            categoryIds: $command->categoryIds,
            parentHostIds: $command->parentHostIds,
            childHostIds: $command->childHostIds,
            snmpVersion: $command->snmpVersion,
            snmpCommunity: $snmpCommunity,
            timezoneId: $command->timezoneId,
            severityId: $command->severityId,
            extendedInformations: $command->extendedInformations,
            schedulingOptions: $command->schedulingOptions,
            checkOptions: $checkOptions,
            notifications: $this->additiveInheritanceModeApplier->apply($command->notifications),
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

    /**
     * Vaults the SNMP community into the host's entry when a vault is enabled; the caller reuses that
     * entry's UUID (see {@see extractVaultUuid()}) for the host's password macros rather than minting
     * a second secret.
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
            self::HOST_VAULT_PATH,
            self::HOST_SNMP_COMMUNITY_KEY,
            $snmpCommunity,
        ));
    }

    /**
     * Extracts the entry UUID from a `secret::vault::<path>/<uuid>::<key>` reference (the segment
     * between the last '/' and '::'), mirroring legacy VaultTrait::getUuidFromPath. Returns null for a
     * plaintext value (vault disabled), so the password macros mint their own shared entry instead.
     */
    private function extractVaultUuid(string $value): ?string
    {
        if (! $this->vault->isVaultPath($value)) {
            return null;
        }

        return preg_match('/^(.*)\/(.*)::(.*)$/', $value, $matches) === 1 ? $matches[2] : null;
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
            throw new HostCategoryNotFoundException($missingIds);
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
            throw new HostSeverityNotFoundException($severityId->value);
        }
    }

    private function assertTimezoneExists(?TimezoneId $timezoneId): void
    {
        if ($timezoneId instanceof TimezoneId && ! $this->timezoneRepository->findNameById($timezoneId) instanceof TimezoneName) {
            throw new TimezoneNotFoundException($timezoneId->value);
        }
    }

    /**
     * Never ACL-scoped: legacy has no access-group variant for templates, and neither do we.
     *
     * @param Collection<HostTemplateId> $templateIds
     */
    private function assertTemplatesExist(Collection $templateIds): void
    {
        $missingIds = $this->missingIds(
            $templateIds,
            fn (Collection $ids): array => array_keys($this->hostTemplateRepository->findNamesByIds($ids)->toArray()),
        );

        if ($missingIds !== []) {
            throw new HostTemplateNotFoundException($missingIds);
        }
    }

    /**
     * ACL-scoped like host groups: a restricted viewer may only link the hosts within their scope,
     * as the legacy form only offers those (CentreonHost::getObjectForSelect2()). Unknown and
     * inaccessible ids are not told apart, so a restricted viewer cannot probe for hosts they
     * cannot see. Host templates resolve to nothing here, so one cannot be smuggled in.
     *
     * @param Collection<HostId> $parentHostIds
     * @param Collection<HostId> $childHostIds
     */
    private function assertRelatedHostsExist(Collection $parentHostIds, Collection $childHostIds, ?UserId $viewerId): void
    {
        $accessibleHostIds = $viewerId instanceof UserId && count($parentHostIds) + count($childHostIds) > 0
            ? $this->resourceAccessRepository->findAccessibleHostIds($viewerId)
            : null;

        $this->assertHostsExist($parentHostIds, 'parentHostIds', $accessibleHostIds);
        $this->assertHostsExist($childHostIds, 'childHostIds', $accessibleHostIds);
    }

    /**
     * @param Collection<HostId> $hostIds
     * @param Collection<HostId>|null $accessibleHostIds null when the viewer is unrestricted
     */
    private function assertHostsExist(Collection $hostIds, string $criterion, ?Collection $accessibleHostIds): void
    {
        $missingIds = $this->missingIds(
            $hostIds,
            fn (Collection $ids): array => array_keys($this->repository->findNamesByIds($ids)->toArray()),
            $accessibleHostIds,
        );

        if ($missingIds !== []) {
            throw new HostNotFoundException($missingIds, $criterion);
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
            throw new CircularHostRelationException(array_values($offendingIds));
        }
    }
}
