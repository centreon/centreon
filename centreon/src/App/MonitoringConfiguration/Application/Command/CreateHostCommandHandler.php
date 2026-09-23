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

use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\CheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
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
use App\MonitoringConfiguration\Domain\Repository\InheritedHostMacroRepository;
use App\MonitoringConfiguration\Domain\Repository\PollerRepository;
use App\MonitoringConfiguration\Domain\Repository\TimezoneRepository;
use App\MonitoringConfiguration\Domain\Service\HostMacroInheritanceResolver;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Shared\Application\Command\AsCommandHandler;
use App\Shared\Application\Vault\VaultCredentialWriter;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Event\EventBus;
use App\Shared\Domain\Vault\VaultCredentials;
use App\Shared\Domain\Vault\VaultPathEnum;
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
        private InheritedHostMacroRepository $inheritedHostMacroRepository,
        private HostMacroInheritanceResolver $hostMacroInheritanceResolver,
        private ResourceAccessRepository $resourceAccessRepository,
        private VaultInterface $vault,
        private VaultCredentialWriter $vaultCredentialWriter,
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

        $this->assertRelatedHostsExist($command->parentHostIds, $command->childHostIds);

        $this->assertRelationsAreNotCircular($command->parentHostIds, $command->childHostIds);

        // Authoritative existence guard for the referenced check command, like the poller check
        // above: existence and the "must be a check command" rule are validated at the API boundary
        // (CheckCommandTypeValidator, →422), but this getById() stays as the last word so a command
        // deleted between validation and execution surfaces as a 404 rather than a broken write.
        if ($command->checkOptions->checkCommandId instanceof CommandId) {
            $this->commandRepository->getById($command->checkOptions->checkCommandId);
        }

        if ($this->repository->isNameUsedByHostOrTemplate($command->name)) {
            throw new HostAlreadyExistsException(['name' => $command->name->value]);
        }

        // Template-based macro inheritance is deferred: the host itself stores the requested templates
        // (below), but the inherited macro set is resolved from the check command alone for now, so an
        // empty template collection is passed here. The resolver already handles the template chain for
        // when it is wired in.
        $macroTemplateIds = new Collection([], HostTemplateId::class);

        $checkOptions = $this->prepareCheckOptions($command->checkOptions, $macroTemplateIds);

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
            snmpCommunity: $this->vaultOrPlaintext($command->snmpCommunity),
            timezoneId: $command->timezoneId,
            severityId: $command->severityId,
            extendedInformations: $command->extendedInformations,
            schedulingOptions: $command->schedulingOptions,
            checkOptions: $checkOptions,
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
     * Drops macros the host merely inherits (from its templates or its check command) and moves any
     * password macro's plaintext into the vault, leaving a `secret::` reference in its place, before
     * the host is persisted.
     *
     * @param Collection<HostTemplateId> $templateIds
     */
    private function prepareCheckOptions(CheckOptions $checkOptions, Collection $templateIds): CheckOptions
    {
        $inherited = $this->inheritedHostMacroRepository->findInheritedMacros(
            $templateIds,
            $checkOptions->checkCommandId,
        );
        $macros = $this->hostMacroInheritanceResolver->keepOverridesOnly($checkOptions->macros, $inherited);
        $macros = $this->vaultizePasswordMacros($macros);

        return new CheckOptions($checkOptions->checkCommandId, $checkOptions->args, $macros);
    }

    /**
     * @param list<HostMacro> $macros
     *
     * @return list<HostMacro>
     */
    private function vaultizePasswordMacros(array $macros): array
    {
        if (! $this->vault->isEnabled()) {
            return $macros;
        }

        $credentials = VaultCredentials::fromArray([]);
        foreach ($macros as $macro) {
            if ($macro->isPassword) {
                // Vault key convention matches legacy: `_HOST<NAME>`, without the `$...$` wrapper.
                $credentials = $credentials->with('_HOST' . $macro->name->value, $macro->value);
            }
        }

        $vaultedValues = $this->vaultCredentialWriter->write(VaultPathEnum::MonitoringHosts, $credentials);

        return array_map(
            static function (HostMacro $macro) use ($vaultedValues): HostMacro {
                $key = '_HOST' . $macro->name->value;
                if ($macro->isPassword && isset($vaultedValues[$key])) {
                    return new HostMacro($macro->name, $vaultedValues[$key], true, $macro->description);
                }

                return $macro;
            },
            $macros,
        );
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
            self::HOST_VAULT_PATH,
            self::HOST_SNMP_COMMUNITY_KEY,
            $snmpCommunity,
        ));
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
     * Not ACL-scoped: the legacy API never exposed these two fields, and its form writes back
     * whatever is posted (DB-Func.php's updateHostHostParent()), so there is nothing stricter to
     * be ISO with. Host templates resolve to nothing here, so one cannot be smuggled in.
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
