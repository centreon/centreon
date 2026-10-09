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
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\CheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Notifications;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SnmpCommunity;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityName;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneName;
use App\MonitoringConfiguration\Domain\Event\HostDisabled;
use App\MonitoringConfiguration\Domain\Event\HostEnabled;
use App\MonitoringConfiguration\Domain\Event\HostServicesDeploymentRequested;
use App\MonitoringConfiguration\Domain\Event\HostTemplateServicesCleanupRequested;
use App\MonitoringConfiguration\Domain\Event\HostUpdated;
use App\MonitoringConfiguration\Domain\Event\HostVaultPurgeRequested;
use App\MonitoringConfiguration\Domain\Exception\CircularHostRelationException;
use App\MonitoringConfiguration\Domain\Exception\HostAlreadyExistsException;
use App\MonitoringConfiguration\Domain\Exception\HostCategoryNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostGroupNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostSeverityNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\PollerNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\TimezoneNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\VaultWriteFailedException;
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
use App\Shared\Application\Vault\VaultCredentialWriter;
use App\Shared\Domain\Aggregate\AggregateRootId;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Event\EventBus;
use App\Shared\Domain\NoValue;
use App\Shared\Domain\Vault\VaultCredentials;
use App\Shared\Domain\Vault\VaultKeyEnum;
use App\Shared\Domain\Vault\VaultPathEnum;
use App\Shared\Domain\VaultInterface;
use Psr\Log\LoggerInterface;

/**
 * Full replace (PUT) of a single host. Deliberately duplicates {@see CreateHostCommandHandler}'s
 * reference validation rather than sharing it, so the create path is never coupled to a change made
 * for the update path; macros go through the same domain services. The update-specific parts are:
 * loading the target (404 when absent or out of the viewer's ACL scope), excluding the host itself
 * from the name and circular-inheritance checks, resolving the submitted macros against the ones the
 * host already owns, reusing (or releasing) the host's existing vault entry, and firing
 * {@see HostUpdated} carrying the previous poller so both it and the new one are flagged.
 *
 * The new host is built from the stored one through {@see Host::with()}, and compared with it through
 * {@see Host::hasSameConfigurationAs()} and {@see Host::hasSameRelationsAs()}: an update that changes
 * nothing writes nothing and fires nothing.
 */
#[AsCommandHandler]
final readonly class UpdateHostCommandHandler
{
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
        private VaultCredentialWriter $vaultCredentialWriter,
        private EventBus $eventBus,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * The secrets are written to the vault before the host is saved, so that an unreachable vault
     * saves nothing (HTTP 502). The vault is outside the transaction: when the update fails after a
     * successful vault write, the database rolls back but the vault keeps the new values, which the
     * stored references may now resolve to (changed secret) or miss (cleared key). This divergence is
     * logged as a warning rather than left silent. A failure of the commit itself, after this handler
     * returns, is not covered.
     */
    public function __invoke(UpdateHostCommand $command): Host
    {
        $vaultWritten = false;

        try {
            return $this->update($command, $vaultWritten);
        } catch (\Throwable $exception) {
            if ($vaultWritten) {
                $this->logger->warning(
                    'A host update failed after writing to its vault entry: the vault may now diverge from the host stored in the database.',
                    ['host_id' => $command->id->value, 'exception' => $exception],
                );
            }

            throw $exception;
        }
    }

    /**
     * @param bool $vaultWritten set to true once a vault write has succeeded
     */
    private function update(UpdateHostCommand $command, bool &$vaultWritten): Host
    {
        // Viewer-scoped: findOne returns null for a host outside the viewer's ACL scope, the same as a
        // nonexistent one, so a restricted viewer can never tell them apart.
        $existingHost = $this->repository->findOne($command->id, $command->viewerId);
        if (! $existingHost instanceof Host) {
            throw new HostNotFoundException([$command->id->value], 'id');
        }

        // References are authorized before the name is looked up, for the same anti-enumeration reason
        // as the create path.

        // Throws PollerNotFoundException if it doesn't exist at all.
        $this->pollerRepository->get($command->pollerId);

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

        $accessibleHostIds = $command->viewerId instanceof UserId
            ? $this->resourceAccessRepository->findAccessibleHostIds($command->viewerId)
            : null;

        $this->assertRelatedHostsExist($command->parentHostIds, $command->childHostIds, $accessibleHostIds);

        // Parent and child hosts are ACL-scoped on read like host groups (the legacy host form hides
        // the out-of-scope ones), so they are preserved the same way as the relations below. The
        // circularity check runs on the final lists, since a preserved relation is kept in the graph.
        $parentHostIds = $this->preserveInaccessible($command->parentHostIds, $existingHost->parentHostIds, $accessibleHostIds, HostId::class);
        $childHostIds = $this->preserveInaccessible($command->childHostIds, $existingHost->childHostIds, $accessibleHostIds, HostId::class);

        $this->assertRelationsAreNotCircular($command->id, $parentHostIds, $childHostIds);

        $checkCommand = $command->checkOptions->checkCommandId instanceof CommandId
            ? $this->commandRepository->getById($command->checkOptions->checkCommandId)
            : null;

        // Excludes the host itself: keeping its own name is not a conflict.
        if ($this->repository->isNameUsedByHostOrTemplate($command->name, $command->id)) {
            throw new HostAlreadyExistsException(['name' => $command->name->value]);
        }

        // Legacy keeps every secret of a host (SNMP community + password macros) under one vault entry.
        // On an update that entry may already exist, so its UUID is reused rather than minting a second.
        $existingVaultUuid = $this->vault->isEnabled() ? $existingHost->getVaultUuid($this->vault) : null;

        $snmpCommunity = $this->resolveSnmpCommunity($command->snmpCommunity, $existingHost, $existingVaultUuid, $vaultWritten);
        $vaultUuid = $snmpCommunity instanceof SnmpCommunity
            ? $this->extractVaultUuid($snmpCommunity->value)
            : $existingVaultUuid;

        [$checkOptions, $passwordRewritten] = $this->prepareCheckOptions($command, $existingHost, $vaultUuid, $vaultWritten);

        // The update replaces every relation wholesale (DbalHostRepository::update). Host groups,
        // categories and severity are ACL-scoped (asserted above), so a restricted viewer never sees
        // — and so can never resend — the ones outside their scope. Preserve those from the existing
        // host instead of letting the wholesale replace drop them, even on a GET -> PUT round trip,
        // mirroring legacy (Core PartialUpdateHost, "silently preserved"). Admins and unrestricted
        // dimensions have nothing out of scope, so the submitted set is used unchanged.
        $hostGroupIds = $this->preserveInaccessible(
            $command->hostGroupIds,
            $existingHost->hostGroupIds,
            $command->viewerId instanceof UserId ? $this->resourceAccessRepository->findAccessibleHostGroupIds($command->viewerId) : null,
            HostGroupId::class,
        );
        $categoryIds = $this->preserveInaccessible(
            $command->categoryIds,
            $existingHost->categoryIds,
            $command->viewerId instanceof UserId ? $this->resourceAccessRepository->findAccessibleHostCategoryIds($command->viewerId) : null,
            HostCategoryId::class,
        );
        $severityId = $this->preserveInaccessibleSeverity(
            $command->severityId,
            $existingHost->severityId,
            $command->viewerId instanceof UserId ? $this->resourceAccessRepository->findAccessibleHostSeverityIds($command->viewerId) : null,
        );

        $host = $existingHost->with(
            name: $command->name,
            address: $command->address,
            pollerId: $command->pollerId,
            alias: $command->alias,
            snmpVersion: $command->snmpVersion,
            snmpCommunity: $snmpCommunity,
            timezoneId: $command->timezoneId,
            severityId: $severityId,
            extendedInformations: $command->extendedInformations,
            schedulingOptions: $command->schedulingOptions,
            // A Centreon Monitoring Agent pushes its results: freshness is forced, like the legacy form
            // and the Core do, so a silent agent is still detected.
            dataProcessing: $checkCommand?->isCentreonMonitoringAgent() === true
                ? $command->dataProcessing->withCentreonMonitoringAgentFreshness()
                : $command->dataProcessing,
            checkOptions: $checkOptions,
            // Absent on Cloud, where notifications follow another model: the stored block is kept.
            notifications: $command->notifications instanceof Notifications
                ? $this->additiveInheritanceModeApplier->apply(
                    $this->preserveInaccessibleContacts($command->notifications, $existingHost->notifications, $command->viewerId),
                )
                : new NoValue(),
            activated: $command->activated,
            templateIds: $command->templateIds,
            hostGroupIds: $hostGroupIds,
            categoryIds: $categoryIds,
            parentHostIds: $parentHostIds,
            childHostIds: $childHostIds,
        );

        // A vault reference only depends on the entry and the key, never on the value: a secret
        // rewritten under the host's entry leaves the host with the very same reference, so it is
        // tracked apart, to still save the host and fire its event.
        $snmpCommunityRewritten = $this->isRewrittenInVault($command->snmpCommunity);

        $activationFlipped = $existingHost->activated !== $host->activated;
        $loggedChange = $snmpCommunityRewritten || $this->hasLoggedChange($existingHost, $host);
        // Macros and contacts are compared by hasSameConfigurationAs() but never logged by legacy, so
        // they count among the unlogged changes, with the relations.
        $unloggedChange = $passwordRewritten
            || ! $host->hasSameConfigurationAs($existingHost)
            || ! $host->hasSameRelationsAs($existingHost);

        if (! $activationFlipped && ! $loggedChange && ! $unloggedChange) {
            return $host;
        }

        $this->repository->update($host);
        $this->repository->replaceRelationsAndMacros($host);

        // Activity log, ISO with legacy (Core\Host\...\DbWriteHostActionLogRepository::update): the
        // activation flip and the rest of the change are logged independently, so an update writes 0,
        // 1 or 2 lines: an enable/disable line when the activation flipped, a "change" line when a
        // logged property changed. The side-effect handlers (ACL reload, poller flag) catch these
        // events via the AggregateUpdated supertype, so they run on any change.
        if ($activationFlipped) {
            $this->eventBus->fire(
                $host->activated
                    ? new HostEnabled($host, $command->updatedBy)
                    : new HostDisabled($host, $command->updatedBy),
            );
        }

        // Carries the previous poller so both it and the new one are flagged when it changed (the
        // poller is a logged field, so a poller change always lands on the loggable branch below).
        $previousPollerId = $existingHost->pollerId->value === $host->pollerId->value ? null : $existingHost->pollerId;

        if ($loggedChange) {
            $this->eventBus->fire(new HostUpdated($host, $command->updatedBy, $previousPollerId));
        } elseif ($unloggedChange && ! $activationFlipped) {
            // Only properties legacy never logs changed (host groups, templates, categories, parent/
            // child hosts, macros or notification contacts). The side effects (engine flag, ACL reload)
            // must still run: fire a non-loggable event. (When activation also flipped, the
            // enable/disable event above already runs them.)
            $this->eventBus->fire(new HostUpdated($host, $command->updatedBy, $previousPollerId, loggable: false));
        }

        // Deferred (post-commit) purge of the vault entry this update left without any secret: the
        // pre-update host still references it, so the handler resolves the right uuid from it, and a
        // failed/rolled-back update never deletes a live secret. Best effort: the save succeeded, and
        // an orphan entry is harmless.
        if ($this->vault->isEnabled() && $host->releasesVaultEntryOf($existingHost, $this->vault)) {
            $this->eventBus->fire(new HostVaultPurgeRequested($existingHost, bestEffort: true));
        }

        // Like the PATCH, removing a template removes the services it brought to the host. Fired
        // before the deployment, so the services of the remaining templates are deployed afterwards.
        $templateIdValues = static fn (Host $host): array => array_map(
            static fn (HostTemplateId $id): int => $id->value,
            $host->templateIds->toArray(),
        );
        if (array_diff($templateIdValues($existingHost), $templateIdValues($host)) !== []) {
            $this->eventBus->fire(new HostTemplateServicesCleanupRequested(
                $host->id(),
                $existingHost->templateIds,
                $host->templateIds,
                new UserId($command->updatedBy),
            ));
        }

        if ($command->deployServicesFromTemplates && count($command->templateIds) > 0) {
            $this->eventBus->fire(new HostServicesDeploymentRequested(
                $host->id(),
                new UserId($command->updatedBy),
            ));
        }

        return $host;
    }

    /**
     * Whether a property legacy records as a "change" line differs: everything
     * {@see Host::hasSameConfigurationAs()} compares but the macros and the notification contacts,
     * which legacy leaves out of that diff. Both are taken from $before on the compared copy.
     */
    private function hasLoggedChange(Host $before, Host $after): bool
    {
        $beforeNotifications = $before->notifications;
        $afterNotifications = $after->notifications;

        $comparable = $after->with(
            checkOptions: $after->checkOptions->with(macros: $before->checkOptions->macros),
            notifications: $afterNotifications instanceof Notifications
                ? new Notifications(
                    enabled: $afterNotifications->enabled,
                    contactIds: $beforeNotifications->contactIds ?? new Collection([], NotificationContactId::class),
                    contactGroupIds: $beforeNotifications->contactGroupIds ?? new Collection([], ContactGroupId::class),
                    options: $afterNotifications->options,
                    interval: $afterNotifications->interval,
                    periodId: $afterNotifications->periodId,
                    firstDelay: $afterNotifications->firstDelay,
                    recoveryDelay: $afterNotifications->recoveryDelay,
                    contactAdditiveInheritance: $afterNotifications->contactAdditiveInheritance,
                    contactGroupAdditiveInheritance: $afterNotifications->contactGroupAdditiveInheritance,
                )
                : null,
        );

        return ! $comparable->hasSameConfigurationAs($before);
    }

    /**
     * Resolves the submitted macros by id + parent against the macros the host owns and those it
     * inherits (from its templates or its check command): a direct macro is changed in place, a
     * changed inherited macro becomes a new direct macro of the host (the inherited one is left
     * untouched), and one that merely repeats an inherited macro is dropped. Then moves every
     * password macro under the host's own vault entry, leaving a `secret::` reference in its place,
     * and removes the keys no macro references any more, before the host is persisted.
     *
     * @param ?string $vaultUuid the host's vault entry (its SNMP community's, or the existing one), or
     *                           null when there is none, or the vault is off
     * @param bool $vaultWritten see writeToVault()
     *
     * @return array{CheckOptions, bool} the check options, and whether a password was written to the vault
     */
    private function prepareCheckOptions(UpdateHostCommand $command, Host $existingHost, ?string $vaultUuid, bool &$vaultWritten): array
    {
        $current = $existingHost->checkOptions->macros;
        $inherited = $this->inheritedHostMacrosResolver->resolve($command->templateIds, $command->checkOptions->checkCommandId);
        $resolved = $this->hostMacroChangesResolver->resolve($command->macroChanges, $current, $inherited);
        $synchronized = false;
        $macros = $this->writeToVault(
            $existingHost->id(),
            fn (): array => $this->hostMacroSecretsSynchronizer->synchronize($resolved, $current, $vaultUuid),
            $synchronized,
        );
        // The synchronizer leaves the vault untouched when no password macro is involved.
        $vaultWritten = $vaultWritten || (
            $synchronized
            && $this->vault->isEnabled()
            && array_any([...$resolved, ...$current], static fn (HostMacro $macro): bool => $macro->isPassword)
        );

        // The synchronizer only swaps the value of the macros it wrote to the vault.
        $passwordRewritten = array_any(
            $macros,
            static fn (HostMacro $macro, int $index): bool => $macro->value !== $resolved[$index]->value,
        );

        return [
            new CheckOptions($command->checkOptions->checkCommandId, $command->checkOptions->args, $macros),
            $passwordRewritten,
        ];
    }

    /**
     * Whether the submitted SNMP community is written to the vault (see resolveSnmpCommunity()).
     */
    private function isRewrittenInVault(?string $community): bool
    {
        return $community !== null && $this->vault->isEnabled() && ! $this->vault->isVaultPath($community);
    }

    /**
     * The SNMP community to store, written under the host's own vault entry (the one shared with its
     * password macros) when a vault is enabled:
     * - null: the community is emptied, and its key removed from that entry;
     * - a `secret::` reference: never trusted from the caller, the host keeps the community it has;
     * - anything else: the new community, stored as is without a vault.
     *
     * @param bool $vaultWritten see writeToVault()
     */
    private function resolveSnmpCommunity(?string $community, Host $existingHost, ?string $existingVaultUuid, bool &$vaultWritten): ?SnmpCommunity
    {
        if (! $this->vault->isEnabled()) {
            return $community === null ? null : new SnmpCommunity($community);
        }

        if ($community === null) {
            $stored = $existingHost->snmpCommunity?->value;
            if ($existingVaultUuid !== null && $stored !== null && $this->vault->isVaultPath($stored)) {
                $this->writeToVault(
                    $existingHost->id(),
                    fn (): array => $this->vaultCredentialWriter->persist(
                        VaultPathEnum::MonitoringHosts,
                        VaultCredentials::empty()->clear(VaultKeyEnum::HostSnmpCommunity),
                        $existingVaultUuid,
                    ),
                    $vaultWritten,
                );
            }

            return null;
        }

        if ($this->vault->isVaultPath($community)) {
            return $existingHost->snmpCommunity;
        }

        $stored = $this->writeToVault(
            $existingHost->id(),
            fn (): array => $this->vaultCredentialWriter->persist(
                VaultPathEnum::MonitoringHosts,
                VaultCredentials::empty()->set(VaultKeyEnum::HostSnmpCommunity, $community),
                $existingVaultUuid,
            ),
            $vaultWritten,
        );

        return new SnmpCommunity($stored[VaultKeyEnum::HostSnmpCommunity->value]);
    }

    /**
     * Runs a vault write made before the host is saved: a failure there means the host itself was not
     * persisted, reported as an upstream failure (HTTP 502) rather than an internal error.
     *
     * @template T
     *
     * @param callable(): T $write
     * @param bool $vaultWritten set to true once the write succeeds, so a later failure of the update
     *                           can report that the vault may diverge from the database
     *
     * @return T
     */
    private function writeToVault(HostId $hostId, callable $write, bool &$vaultWritten): mixed
    {
        try {
            $result = $write();
        } catch (\Throwable $exception) {
            throw VaultWriteFailedException::forHost($hostId, $exception);
        }

        $vaultWritten = true;

        return $result;
    }

    /**
     * Extracts the entry UUID from a `secret::vault::<path>/<uuid>::<key>` reference, or null for a
     * plaintext value (vault disabled).
     */
    private function extractVaultUuid(string $value): ?string
    {
        if (! $this->vault->isVaultPath($value)) {
            return null;
        }

        return preg_match('/^(.*)\/(.*)::(.*)$/', $value, $matches) === 1 ? $matches[2] : null;
    }

    /**
     * Scopes a full-replace association list to what the viewer may change. Ids outside the viewer's
     * ACL scope are invisible to them, so they can never be resent; preserving them from the existing
     * host keeps the wholesale relation replace from silently dropping them (legacy
     * PartialUpdateHost). A null $accessible means the dimension is unrestricted for this viewer
     * (nothing is out of scope), so the submitted list is used as is.
     *
     * @template T of AggregateRootId
     *
     * @param Collection<T> $submitted
     * @param Collection<T> $existing
     * @param Collection<T>|null $accessible
     * @param class-string<T> $className
     *
     * @return Collection<T>
     */
    private function preserveInaccessible(
        Collection $submitted,
        Collection $existing,
        ?Collection $accessible,
        string $className,
    ): Collection {
        if (! $accessible instanceof Collection) {
            return $submitted;
        }

        $accessibleValues = array_map(static fn (AggregateRootId $id): int => $id->value, $accessible->toArray());
        $submittedValues = array_map(static fn (AggregateRootId $id): int => $id->value, $submitted->toArray());

        $preserved = array_filter(
            $existing->toArray(),
            static fn (AggregateRootId $id): bool => ! in_array($id->value, $accessibleValues, true)
                && ! in_array($id->value, $submittedValues, true),
        );

        return new Collection([...$submitted->toArray(), ...array_values($preserved)], $className);
    }

    /**
     * {@see self::preserveInaccessible()} applied to the notification contacts and contact groups,
     * which the legacy host form hides too when out of the viewer's scope.
     */
    private function preserveInaccessibleContacts(
        Notifications $submitted,
        ?Notifications $existing,
        ?UserId $viewerId,
    ): Notifications {
        if (! $existing instanceof Notifications || ! $viewerId instanceof UserId) {
            return $submitted;
        }

        return $submitted->withContacts(
            $this->preserveInaccessible(
                $submitted->contactIds,
                $existing->contactIds,
                $this->resourceAccessRepository->findAccessibleContactIds($viewerId),
                NotificationContactId::class,
            ),
            $this->preserveInaccessible(
                $submitted->contactGroupIds,
                $existing->contactGroupIds,
                $this->resourceAccessRepository->findAccessibleContactGroupIds($viewerId),
                ContactGroupId::class,
            ),
        );
    }

    /**
     * Single-valued counterpart of {@see self::preserveInaccessible()} for the host severity: the
     * submitted severity wins when present; otherwise an existing severity the viewer cannot access
     * is preserved (a restricted viewer who sends none never meant to clear a severity they cannot
     * even see).
     *
     * @param Collection<HostSeverityId>|null $accessible
     */
    private function preserveInaccessibleSeverity(
        ?HostSeverityId $submitted,
        ?HostSeverityId $existing,
        ?Collection $accessible,
    ): ?HostSeverityId {
        if ($submitted instanceof HostSeverityId || ! $existing instanceof HostSeverityId || ! $accessible instanceof Collection) {
            return $submitted;
        }

        $accessibleValues = array_map(static fn (HostSeverityId $id): int => $id->value, $accessible->toArray());

        return in_array($existing->value, $accessibleValues, true) ? null : $existing;
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
     * @param Collection<HostId>|null $accessibleHostIds null when the viewer is unrestricted
     */
    private function assertRelatedHostsExist(Collection $parentHostIds, Collection $childHostIds, ?Collection $accessibleHostIds): void
    {
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
     * A loop is closed when a requested child can still reach a requested parent through the rest of
     * the graph. The edited host's own parent/child edges are about to be replaced, so they are
     * excluded from the ancestor traversal: otherwise a soon-to-be-deleted edge would raise a phantom
     * cycle. `findAncestorIds()` includes the inputs, so the same-host-on-both-sides case falls out too.
     *
     * @param Collection<HostId> $parentHostIds
     * @param Collection<HostId> $childHostIds
     */
    private function assertRelationsAreNotCircular(HostId $hostId, Collection $parentHostIds, Collection $childHostIds): void
    {
        // Catch the immediate self-reference here, before the early return below and before the Host
        // constructor's own guard would throw a raw InvalidArgumentException (→ 500): a host listed
        // among its own parents or children is a circular relation and must surface as a 422 through
        // the same path as the full-tree check.
        foreach ([...$parentHostIds->toArray(), ...$childHostIds->toArray()] as $relatedId) {
            if ($relatedId->value === $hostId->value) {
                throw new CircularHostRelationException([$hostId->value]);
            }
        }

        if (count($parentHostIds) === 0 || count($childHostIds) === 0) {
            return;
        }

        $ancestorIds = array_map(
            static fn (HostId $id): int => $id->value,
            $this->repository->findAncestorIds($parentHostIds, $hostId)->toArray(),
        );
        $childIds = array_map(static fn (HostId $id): int => $id->value, $childHostIds->toArray());

        $offendingIds = array_intersect($childIds, $ancestorIds);

        if ($offendingIds !== []) {
            throw new CircularHostRelationException(array_values($offendingIds));
        }
    }
}
