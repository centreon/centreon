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
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Notifications;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SnmpCommunity;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityName;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Option\OptionName;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneName;
use App\MonitoringConfiguration\Domain\Event\HostDisabled;
use App\MonitoringConfiguration\Domain\Event\HostEnabled;
use App\MonitoringConfiguration\Domain\Event\HostServicesDeploymentRequested;
use App\MonitoringConfiguration\Domain\Event\HostUpdated;
use App\MonitoringConfiguration\Domain\Event\HostVaultPurgeRequested;
use App\MonitoringConfiguration\Domain\Exception\CircularHostRelationException;
use App\MonitoringConfiguration\Domain\Exception\HostAlreadyExistsException;
use App\MonitoringConfiguration\Domain\Exception\HostCategoryNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostGroupNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostSeverityNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\OptionDoesNotExistException;
use App\MonitoringConfiguration\Domain\Exception\PollerNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\TimezoneNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\MonitoringConfiguration\Domain\Repository\HostCategoryRepository;
use App\MonitoringConfiguration\Domain\Repository\HostGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\MonitoringConfiguration\Domain\Repository\HostSeverityRepository;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\InheritedHostMacroRepository;
use App\MonitoringConfiguration\Domain\Repository\OptionRepository;
use App\MonitoringConfiguration\Domain\Repository\PollerRepository;
use App\MonitoringConfiguration\Domain\Repository\TimezoneRepository;
use App\MonitoringConfiguration\Domain\Service\HostMacroInheritanceResolver;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Shared\Application\Command\AsCommandHandler;
use App\Shared\Application\Vault\VaultCredentialWriter;
use App\Shared\Domain\Aggregate\AggregateRootId;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Event\EventBus;
use App\Shared\Domain\Vault\VaultCredentials;
use App\Shared\Domain\Vault\VaultKeyEnum;
use App\Shared\Domain\Vault\VaultPathEnum;
use App\Shared\Domain\VaultInterface;

/**
 * Full replace (PUT) of a single host. Deliberately duplicates {@see CreateHostCommandHandler}'s
 * reference-validation and vault/macro/inheritance logic rather than sharing it, so the create path
 * is never coupled to a change made for the update path. The update-specific parts are: loading the
 * target (404 when absent or out of the viewer's ACL scope), excluding the host itself from the name
 * and circular-inheritance checks, reusing (or deleting) the host's existing vault entry, and firing
 * {@see HostUpdated} carrying the previous poller so both it and the new one are flagged.
 */
#[AsCommandHandler]
final readonly class UpdateHostCommandHandler
{
    private const INHERITANCE_MODE_OPTION = 'inheritance_mode';
    private const ADDITIVE_INHERITANCE_MODE = 1;

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
        private OptionRepository $optionRepository,
        private ResourceAccessRepository $resourceAccessRepository,
        private VaultInterface $vault,
        private VaultCredentialWriter $vaultCredentialWriter,
        private EventBus $eventBus,
    ) {
    }

    public function __invoke(UpdateHostCommand $command): Host
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

        $this->assertRelatedHostsExist($command->parentHostIds, $command->childHostIds);

        $this->assertRelationsAreNotCircular($command->id, $command->parentHostIds, $command->childHostIds);

        if ($command->checkOptions->checkCommandId instanceof CommandId) {
            $this->commandRepository->getById($command->checkOptions->checkCommandId);
        }

        // Excludes the host itself: keeping its own name is not a conflict.
        if ($this->repository->isNameUsedByHostOrTemplate($command->name, $command->id)) {
            throw new HostAlreadyExistsException(['name' => $command->name->value]);
        }

        // Legacy keeps every secret of a host (SNMP community + password macros) under one vault entry.
        // On an update that entry may already exist, so its UUID is reused rather than minting a second.
        $existingVaultUuid = $this->vault->isEnabled() ? $existingHost->getVaultUuid($this->vault) : null;

        $snmpCommunity = $this->vaultOrPlaintext($command->snmpCommunity, $existingVaultUuid);
        $vaultUuid = $snmpCommunity instanceof SnmpCommunity
            ? $this->extractVaultUuid($snmpCommunity->value)
            : $existingVaultUuid;

        $checkOptions = $this->prepareCheckOptions($command->checkOptions, $command->templateIds, $vaultUuid);

        // Whether this update leaves the host's existing vault entry orphaned (no secret survives):
        // the purge itself is deferred to after the commit (see below), never done here, so a
        // rollback can never destroy a still-referenced secret.
        $orphansVaultEntry = $this->updateOrphansVaultEntry($existingVaultUuid, $snmpCommunity, $checkOptions);

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

        $host = new Host(
            id: $command->id,
            name: $command->name,
            alias: $command->alias,
            address: $command->address,
            activated: $command->activated,
            pollerId: $command->pollerId,
            hostGroupIds: $hostGroupIds,
            dataProcessing: $command->dataProcessing,
            templateIds: $command->templateIds,
            categoryIds: $categoryIds,
            parentHostIds: $command->parentHostIds,
            childHostIds: $command->childHostIds,
            snmpVersion: $command->snmpVersion,
            snmpCommunity: $snmpCommunity,
            timezoneId: $command->timezoneId,
            severityId: $severityId,
            extendedInformations: $command->extendedInformations,
            schedulingOptions: $command->schedulingOptions,
            checkOptions: $checkOptions,
            notifications: $this->applyInheritanceMode($command->notifications),
        );

        $this->repository->update($host);

        // Activity log, ISO with legacy (Core\Host\...\DbWriteHostActionLogRepository::update): the
        // activation flip and the rest of the change are logged independently, so an update writes 0,
        // 1 or 2 lines — an enable/disable line when only the activation flipped, a "change" line when
        // any other logged property differs, both when both happened, and nothing on a true no-op.
        // The side-effect handlers (ACL reload, poller flag) catch these events via the AggregateUpdated
        // supertype, so they run whenever something actually changed and are skipped on a no-op.
        $activationFlipped = $existingHost->activated !== $host->activated;
        $loggedChange = $this->loggedConfigChanged($existingHost, $host);

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
            // Writes a "change" line and, via the AggregateUpdated supertype, runs the side effects.
            $this->eventBus->fire(new HostUpdated($host, $command->updatedBy, $previousPollerId));
        } elseif (! $activationFlipped && $this->nonLoggedConfigChanged($existingHost, $host)) {
            // Only properties legacy never logs changed (host groups, templates, categories, parent/
            // child hosts, macros or notification contacts). Logging and the side effects (engine flag,
            // ACL reload) are independent effects of the update, not a chain reaction: fire a
            // non-loggable event so the engine still regenerates and the ACL still reloads, with no log
            // line. (When activation also flipped, the enable/disable event above already runs them.)
            $this->eventBus->fire(new HostUpdated($host, $command->updatedBy, $previousPollerId, loggable: false));
        }

        // Deferred (post-commit) purge of the now-orphaned vault entry: the pre-update host still
        // references it, so the handler resolves the right uuid from it, and a failed/rolled-back
        // update never deletes a live secret.
        if ($orphansVaultEntry) {
            $this->eventBus->fire(new HostVaultPurgeRequested($existingHost));
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
     * Whether any host property that the activity log records (other than the activation flag) differs
     * between the stored host and the updated one. This mirrors exactly the field set legacy diffs to
     * decide whether to write a "change" line (its NewHost carries only scalar properties): the host's
     * own scalars and its scheduling/data-processing/extended/notification scalars, but NOT its
     * relations (templates, groups, categories, parents, children), its macros, or its contact and
     * contact-group lists — none of which legacy includes in that diff.
     */
    private function loggedConfigChanged(Host $before, Host $after): bool
    {
        return $this->loggedScalars($before) !== $this->loggedScalars($after);
    }

    /**
     * The host properties whose change legacy records as a "change" line, flattened to comparable
     * scalars (strings, ints, bools, enums and scalar lists). A strict comparison of two such maps is
     * enough to decide whether anything other than the activation flag differs. Nullable composites
     * (extended informations, notifications) contribute null for every one of their keys when absent,
     * so "present vs absent" reads as a change.
     *
     * @return array<string, mixed>
     */
    private function loggedScalars(Host $host): array
    {
        $extended = $host->extendedInformations;
        $scheduling = $host->schedulingOptions;
        $dataProcessing = $host->dataProcessing;
        $notifications = $host->notifications;

        return [
            'name' => $host->name->value,
            'alias' => $host->alias?->value,
            'address' => $host->address->value,
            'poller' => $host->pollerId->value,
            'snmpVersion' => $host->snmpVersion,
            'snmpCommunity' => $host->snmpCommunity?->value,
            'timezone' => $host->timezoneId?->value,
            'severity' => $host->severityId?->value,
            'noteUrl' => $extended?->noteUrl,
            'note' => $extended?->note,
            'actionUrl' => $extended?->actionUrl,
            'iconId' => $extended?->iconId?->value,
            'altIcon' => $extended?->altIcon,
            'comment' => $extended?->comment,
            'geoCoordinates' => (string) $extended?->geoCoordinates,
            'checkTimeperiod' => $scheduling->checkTimeperiodId?->value,
            'maxCheckAttempts' => $scheduling->maxCheckAttempts,
            'normalCheckInterval' => $scheduling->normalCheckInterval,
            'retryCheckInterval' => $scheduling->retryCheckInterval,
            'activeChecks' => $scheduling->activeCheckEnabled,
            'passiveChecks' => $scheduling->passiveCheckEnabled,
            'checkFreshness' => $dataProcessing->checkFreshness,
            'flapDetection' => $dataProcessing->flapDetectionEnabled,
            'eventHandler' => $dataProcessing->eventHandlerEnabled,
            'acknowledgmentTimeout' => $dataProcessing->acknowledgmentTimeout,
            'freshnessThreshold' => $dataProcessing->freshnessThreshold,
            'lowFlapThreshold' => $dataProcessing->lowFlapThreshold,
            'highFlapThreshold' => $dataProcessing->highFlapThreshold,
            'eventHandlerCommand' => $dataProcessing->eventHandlerCommandId?->value,
            'eventHandlerArgs' => $dataProcessing->eventHandlerArgs,
            // CheckOptions also carries macros (not logged), so only its logged parts contribute.
            'checkCommand' => $host->checkOptions->checkCommandId?->value,
            'checkCommandArgs' => $host->checkOptions->args,
            // Notifications also carries contact/contact-group lists (not logged): only scalars here.
            'notificationsEnabled' => $notifications?->enabled,
            'notificationOptions' => $notifications?->options,
            'notificationInterval' => $notifications?->interval,
            'notificationPeriod' => $notifications?->periodId?->value,
            'firstNotificationDelay' => $notifications?->firstDelay,
            'recoveryNotificationDelay' => $notifications?->recoveryDelay,
            'contactAdditiveInheritance' => $notifications?->contactAdditiveInheritance,
            'contactGroupAdditiveInheritance' => $notifications?->contactGroupAdditiveInheritance,
        ];
    }

    /**
     * Whether any persisted property legacy never logs changed: host groups, templates, categories,
     * parent/child hosts, macros or notification contacts. Combined with {@see self::loggedConfigChanged()}
     * (the logged scalars), this tells whether the host changed at all — so the side effects (engine
     * flag, ACL reload) run on any real change while a no-op update still triggers nothing. Erring
     * towards a change is safe (the side effects are idempotent); missing one would leave the engine or
     * the ACL stale.
     */
    private function nonLoggedConfigChanged(Host $before, Host $after): bool
    {
        if (! $this->sameIdSet($before->hostGroupIds, $after->hostGroupIds)) {
            return true;
        }
        if (! $this->sameIdSet($before->templateIds, $after->templateIds)) {
            return true;
        }
        if (! $this->sameIdSet($before->categoryIds, $after->categoryIds)) {
            return true;
        }
        if (! $this->sameIdSet($before->parentHostIds, $after->parentHostIds)) {
            return true;
        }
        if (! $this->sameIdSet($before->childHostIds, $after->childHostIds)) {
            return true;
        }
        if ($this->macroSignature($before) !== $this->macroSignature($after)) {
            return true;
        }
        return $this->contactSignature($before) !== $this->contactSignature($after);
    }

    /**
     * @template T of AggregateRootId
     *
     * @param Collection<T> $before
     * @param Collection<T> $after
     */
    private function sameIdSet(Collection $before, Collection $after): bool
    {
        $beforeValues = array_map(static fn (AggregateRootId $id): int => $id->value, $before->toArray());
        $afterValues = array_map(static fn (AggregateRootId $id): int => $id->value, $after->toArray());
        sort($beforeValues);
        sort($afterValues);

        return $beforeValues === $afterValues;
    }

    /**
     * @return list<string> one entry per macro, carrying every persisted part so any change is seen
     */
    private function macroSignature(Host $host): array
    {
        return array_map(
            static fn (HostMacro $macro): string => implode('|', [
                $macro->name->value,
                $macro->value,
                $macro->isPassword ? '1' : '0',
                $macro->description ?? '',
            ]),
            $host->checkOptions->macros,
        );
    }

    /**
     * @return array{contacts: list<int>, contactGroups: list<int>}
     */
    private function contactSignature(Host $host): array
    {
        $notifications = $host->notifications;
        if (! $notifications instanceof Notifications) {
            return ['contacts' => [], 'contactGroups' => []];
        }

        $contacts = array_map(static fn (NotificationContactId $id): int => $id->value, $notifications->contactIds->toArray());
        $contactGroups = array_map(static fn (ContactGroupId $id): int => $id->value, $notifications->contactGroupIds->toArray());
        sort($contacts);
        sort($contactGroups);

        return ['contacts' => $contacts, 'contactGroups' => $contactGroups];
    }

    /**
     * Whether the update leaves the host's existing vault entry with no secret at all — neither an
     * SNMP community nor a password macro reference — so it should be purged. Only meaningful when a
     * vault is enabled and the host already had an entry. The purge itself is deferred to after the
     * commit (via {@see HostVaultPurgeRequested}), so this is a pure predicate with no side effect.
     */
    private function updateOrphansVaultEntry(?string $existingVaultUuid, ?SnmpCommunity $snmpCommunity, CheckOptions $checkOptions): bool
    {
        if ($existingVaultUuid === null || ! $this->vault->isEnabled()) {
            return false;
        }

        $hasSnmpSecret = $snmpCommunity instanceof SnmpCommunity && $this->vault->isVaultPath($snmpCommunity->value);
        $hasPasswordMacro = array_any(
            $checkOptions->macros,
            fn (HostMacro $macro): bool => $macro->isPassword && $this->vault->isVaultPath($macro->value),
        );

        return ! $hasSnmpSecret && ! $hasPasswordMacro;
    }

    /**
     * @param Collection<HostTemplateId> $templateIds
     * @param ?string $vaultUuid the vault entry to reuse for the password macros (the SNMP community's,
     *                           or the host's existing one), or null when there is none
     */
    private function prepareCheckOptions(CheckOptions $checkOptions, Collection $templateIds, ?string $vaultUuid): CheckOptions
    {
        $inherited = $this->inheritedHostMacroRepository->findInheritedMacros(
            $templateIds,
            $checkOptions->checkCommandId,
        )->toArray();
        $macros = $this->hostMacroInheritanceResolver->keepOverridesOnly($checkOptions->macros, $inherited);
        $macros = $this->vaultizePasswordMacros($macros, $vaultUuid);

        return new CheckOptions($checkOptions->checkCommandId, $checkOptions->args, $macros);
    }

    /**
     * @param list<HostMacro> $macros
     * @param ?string $vaultUuid the entry to reuse, or null to mint a fresh one
     *
     * @return list<HostMacro>
     */
    private function vaultizePasswordMacros(array $macros, ?string $vaultUuid): array
    {
        $hasPasswordMacro = array_any($macros, static fn (HostMacro $macro): bool => $macro->isPassword);
        if (! $hasPasswordMacro) {
            return $macros;
        }

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

        $vaultedValues = $this->vaultCredentialWriter->write(VaultPathEnum::MonitoringHosts, $credentials, $vaultUuid);

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
     * The additive-inheritance flags only mean something when the platform's `inheritance_mode`
     * option enables them; otherwise legacy silently drops whatever the client asked for.
     */
    private function applyInheritanceMode(?Notifications $notifications): ?Notifications
    {
        if (! $notifications instanceof Notifications) {
            return null;
        }

        if (! $notifications->contactAdditiveInheritance && ! $notifications->contactGroupAdditiveInheritance) {
            return $notifications;
        }

        if ($this->isAdditiveInheritanceEnabled()) {
            return $notifications;
        }

        return $notifications->withoutAdditiveInheritance();
    }

    private function isAdditiveInheritanceEnabled(): bool
    {
        try {
            $option = $this->optionRepository->getByName(new OptionName(self::INHERITANCE_MODE_OPTION));
        } catch (OptionDoesNotExistException) {
            return false;
        }

        return (int) $option->value->value === self::ADDITIVE_INHERITANCE_MODE;
    }

    /**
     * Vaults the SNMP community into the host's entry when a vault is enabled, reusing the given entry
     * UUID (the host's existing one on an update) rather than minting a second.
     */
    private function vaultOrPlaintext(?string $snmpCommunity, ?string $vaultUuid): ?SnmpCommunity
    {
        if ($snmpCommunity === null) {
            return null;
        }

        if (! $this->vault->isEnabled()) {
            return new SnmpCommunity($snmpCommunity);
        }

        return new SnmpCommunity($this->vault->write(
            VaultPathEnum::MonitoringHosts->value,
            VaultKeyEnum::HostSnmpCommunity->value,
            $snmpCommunity,
            $vaultUuid,
        ));
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
