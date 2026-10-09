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
use App\MonitoringConfiguration\Application\Service\HostReferencesChecker;
use App\MonitoringConfiguration\Application\Service\HostRelationsUpdater;
use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\DataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Notifications;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SnmpCommunity;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
use App\MonitoringConfiguration\Domain\Event\HostDisabled;
use App\MonitoringConfiguration\Domain\Event\HostEnabled;
use App\MonitoringConfiguration\Domain\Event\HostMassChanged;
use App\MonitoringConfiguration\Domain\Event\HostVaultPurgeRequested;
use App\MonitoringConfiguration\Domain\Exception\CheckArgumentsRequireACommandException;
use App\MonitoringConfiguration\Domain\Exception\HostAlreadyExistsException;
use App\MonitoringConfiguration\Domain\Exception\HostNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\VaultWriteFailedException;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\Shared\Application\Command\AsCommandHandler;
use App\Shared\Application\Vault\VaultCredentialWriter;
use App\Shared\Domain\Event\EventBus;
use App\Shared\Domain\NoValue;
use App\Shared\Domain\Vault\VaultCredentials;
use App\Shared\Domain\Vault\VaultKeyEnum;
use App\Shared\Domain\Vault\VaultPathEnum;
use App\Shared\Domain\VaultInterface;

#[AsCommandHandler]
final readonly class PatchHostCommandHandler
{
    public function __construct(
        private HostRepository $repository,
        private CommandRepository $commandRepository,
        private HostReferencesChecker $references,
        private HostRelationsUpdater $relations,
        private VaultInterface $vault,
        private VaultCredentialWriter $vaultCredentialWriter,
        private AdditiveInheritanceModeApplier $additiveInheritanceModeApplier,
        private EventBus $eventBus,
    ) {
    }

    public function __invoke(PatchHostCommand $command): Host
    {
        $host = $this->getHost($command);
        // The references come before the name: a restricted viewer must not be able to tell a duplicate
        // name from a resource they cannot access.
        $this->assertReferencesExist($command);
        $withRelations = $this->relations->applyTo($host, $command);
        $this->assertNameIsAvailable($command, $host);
        $this->assertArgumentsHaveACheckCommand($command, $host);

        // The vault write comes last, once every validation and every invariant of the new host has
        // passed: a rejected update must never leave an orphan secret behind. It replaces the current
        // community in place, so if saving the host then fails the previous value is lost: the same
        // trade-off as the Core partial update, the vault being outside the database transaction.
        $secretToVault = $this->communityToVault($command);
        $updatedHost = $this->withSnmpCommunity($host, $this->applyChanges($withRelations, $command), $command, $secretToVault);
        $this->saveAndNotify($host, $updatedHost, $command->updatedBy, secretRewritten: $secretToVault !== null);

        return $updatedHost;
    }

    private function getHost(PatchHostCommand $command): Host
    {
        // Viewer-scoped: findOne returns null for a host outside the viewer's ACL scope, the same as
        // a nonexistent one, so a restricted viewer can never tell them apart.
        $host = $this->repository->findOne($command->id, $command->viewerId);
        if (! $host instanceof Host) {
            throw new HostNotFoundException([$command->id->value], 'id');
        }

        return $host;
    }

    /**
     * The same rules ran on the input, earlier: this is the authoritative check, against what exists now.
     * The check command is not listed: loading it, for the Centreon Monitoring Agent rule, is its check.
     */
    private function assertReferencesExist(PatchHostCommand $command): void
    {
        if ($command->pollerId instanceof PollerId) {
            $this->references->assertPollerAccessible($command->pollerId, $command->viewerId);
        }

        if ($command->severityId instanceof HostSeverityId) {
            $this->references->assertSeverityAccessible($command->severityId, $command->viewerId);
        }

        if ($command->timezoneId instanceof TimezoneId) {
            $this->references->assertTimezoneExists($command->timezoneId);
        }

        if ($command->extendedInformations instanceof ExtendedInformationsChanges && $command->extendedInformations->iconId instanceof MediaId) {
            $this->references->assertMediaExists($command->extendedInformations->iconId);
        }

        if ($command->schedulingOptions instanceof SchedulingOptionsChanges && $command->schedulingOptions->checkTimeperiodId instanceof TimePeriodId) {
            $this->references->assertTimePeriodExists($command->schedulingOptions->checkTimeperiodId);
        }

        if ($command->notifications instanceof NotificationsChanges && $command->notifications->periodId instanceof TimePeriodId) {
            $this->references->assertTimePeriodExists($command->notifications->periodId);
        }

        if ($command->dataProcessing instanceof DataProcessingChanges && $command->dataProcessing->eventHandlerCommandId instanceof CommandId) {
            $this->references->assertCommandExists($command->dataProcessing->eventHandlerCommandId);
        }
    }

    private function assertNameIsAvailable(PatchHostCommand $command, Host $host): void
    {
        if (
            ! $command->name instanceof NoValue
            && $command->name->value !== $host->name->value
            && $this->repository->isNameUsedByHostOrTemplate($command->name, $host->id())
        ) {
            throw new HostAlreadyExistsException(['name' => $command->name->value]);
        }
    }

    /**
     * Arguments are provided on their own, they apply to the command the host already has. Left with
     * none, they have nothing to belong to.
     */
    private function assertArgumentsHaveACheckCommand(PatchHostCommand $command, Host $host): void
    {
        if (! $command->checkOptions instanceof CheckOptionsChanges || ! is_array($command->checkOptions->args) || $command->checkOptions->args === []) {
            return;
        }

        $commandId = $command->checkOptions->checkCommandId instanceof NoValue
            ? $host->checkOptions->checkCommandId
            : $command->checkOptions->checkCommandId;

        if (! $commandId instanceof CommandId) {
            throw new CheckArgumentsRequireACommandException();
        }
    }

    private function applyChanges(Host $host, PatchHostCommand $command): Host
    {
        return $host->with(
            name: $command->name,
            address: $command->address,
            pollerId: $command->pollerId,
            alias: $command->alias,
            snmpVersion: $command->snmpVersion,
            timezoneId: $command->timezoneId,
            severityId: $command->severityId,
            extendedInformations: $command->extendedInformations instanceof NoValue
                ? new NoValue()
                : $command->extendedInformations->applyTo($host->extendedInformations),
            schedulingOptions: $command->schedulingOptions instanceof NoValue
                ? new NoValue()
                : $command->schedulingOptions->applyTo($host->schedulingOptions),
            dataProcessing: $this->dataProcessing($host, $command),
            checkOptions: $command->checkOptions instanceof NoValue
                ? new NoValue()
                : $command->checkOptions->applyTo($host->checkOptions),
            // With the platform option disabled, the additive inheritance flags the host already has are
            // reset along with any notification change, like the creation does.
            notifications: $command->notifications instanceof NoValue
                ? new NoValue()
                : $this->additiveInheritanceModeApplier->apply($command->notifications->applyTo($host->notifications)),
            activated: $command->activated,
        );
    }

    /**
     * The plaintext community to write to the vault, null when there is nothing to write: no
     * community given, no vault, or a reference to the vault from the caller, which is never trusted.
     */
    private function communityToVault(PatchHostCommand $command): ?string
    {
        $community = $command->snmpCommunity;

        if (! is_string($community) || ! $this->vault->isEnabled() || $this->vault->isVaultPath($community)) {
            return null;
        }

        return $community;
    }

    /**
     * Without a vault the community is stored as is, like on creation. A reference to the vault given
     * by the caller is ignored: the host keeps the community it has.
     */
    private function withSnmpCommunity(Host $hostBefore, Host $updatedHost, PatchHostCommand $command, ?string $secretToVault): Host
    {
        $community = $command->snmpCommunity;

        if ($community instanceof NoValue) {
            return $updatedHost;
        }

        if ($community === null) {
            return $updatedHost->with(snmpCommunity: null);
        }

        if ($secretToVault !== null) {
            return $updatedHost->with(snmpCommunity: new SnmpCommunity($this->storeInVault($hostBefore, $secretToVault)));
        }

        if (! $this->vault->isEnabled()) {
            return $updatedHost->with(snmpCommunity: new SnmpCommunity($community));
        }

        return $updatedHost;
    }

    /**
     * Written under the host's own vault entry (the one shared with its password macros), or a new
     * one when it has none yet.
     */
    private function storeInVault(Host $hostBefore, string $community): string
    {
        $credentials = VaultCredentials::empty()->set(VaultKeyEnum::HostSnmpCommunity, $community);

        try {
            $stored = $this->vaultCredentialWriter->persist(
                VaultPathEnum::MonitoringHosts,
                $credentials,
                $hostBefore->getVaultUuid($this->vault),
            );
        } catch (\Throwable $exception) {
            throw VaultWriteFailedException::forHost($hostBefore->id(), $exception);
        }

        return $stored[VaultKeyEnum::HostSnmpCommunity->value];
    }

    private function dataProcessing(Host $host, PatchHostCommand $command): NoValue|DataProcessing
    {
        $dataProcessing = $command->dataProcessing instanceof NoValue
            ? $host->dataProcessing
            : $command->dataProcessing->applyTo($host->dataProcessing);

        if ($this->providedCheckCommand($command)?->isCentreonMonitoringAgent() === true) {
            return $dataProcessing->withCentreonMonitoringAgentFreshness();
        }

        return $command->dataProcessing instanceof NoValue ? new NoValue() : $dataProcessing;
    }

    /**
     * The check command the update gives the host, null when it provides none or removes the current
     * one. Throws CommandNotFoundException when it does not exist.
     */
    private function providedCheckCommand(PatchHostCommand $command): ?Command
    {
        if (! $command->checkOptions instanceof CheckOptionsChanges || ! $command->checkOptions->checkCommandId instanceof CommandId) {
            return null;
        }

        return $this->commandRepository->getById($command->checkOptions->checkCommandId);
    }

    /**
     * Nothing is written when nothing changed. A plain enable/disable keeps the narrow write and its
     * own event; anything else is a mass change, which carries the previous poller so that it is
     * flagged too. A secret rewritten in the vault counts as a change even when the host keeps the very
     * same reference, since the path of a vaulted value only depends on its entry and its key.
     */
    private function saveAndNotify(Host $hostBefore, Host $hostAfter, int $updatedBy, bool $secretRewritten): void
    {
        $relationsChanged = ! $hostAfter->hasSameRelationsAs($hostBefore) || ! $this->hasSameContactsAs($hostAfter, $hostBefore);

        if ($secretRewritten || $relationsChanged || ! $hostAfter->hasSameConfigurationAs($hostBefore)) {
            $this->repository->update($hostAfter);
            if ($relationsChanged) {
                $this->repository->replaceRelations($hostAfter);
            }

            $this->eventBus->fire(new HostMassChanged(
                $hostAfter,
                $updatedBy,
                previousPollerId: $hostAfter->pollerId->value !== $hostBefore->pollerId->value ? $hostBefore->pollerId : null,
            ));
            $this->releaseCredentialsOf($hostBefore, $hostAfter);

            return;
        }

        if ($hostAfter->activated !== $hostBefore->activated) {
            $this->repository->updateActivationStatus($hostAfter->id(), $hostAfter->activated);
            $this->eventBus->fire(
                $hostAfter->activated
                    ? new HostEnabled($hostAfter, $updatedBy)
                    : new HostDisabled($hostAfter, $updatedBy),
            );
        }
    }

    /**
     * A community removed from the host leaves its secret in the vault. It is removed once the update
     * is committed: with its entry when the host keeps no other secret in it, alone otherwise. The
     * purge is best effort: a leftover secret is harmless, and the update is already saved.
     */
    private function releaseCredentialsOf(Host $hostBefore, Host $hostAfter): void
    {
        if (
            ! $hostBefore->snmpCommunity instanceof SnmpCommunity
            || ! $this->vault->isVaultPath($hostBefore->snmpCommunity->value)
            || $hostAfter->snmpCommunity instanceof SnmpCommunity
        ) {
            return;
        }

        $this->eventBus->fire(new HostVaultPurgeRequested(
            $hostBefore,
            bestEffort: true,
            keys: $hostAfter->releasesVaultEntryOf($hostBefore, $this->vault) ? [] : [VaultKeyEnum::HostSnmpCommunity->value],
        ));
    }

    private function hasSameContactsAs(Host $host, Host $other): bool
    {
        return ($host->notifications ?? Notifications::default())->hasSameContactsAs($other->notifications ?? Notifications::default());
    }
}
