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
use App\MonitoringConfiguration\Application\Service\HostMacrosUpdater;
use App\MonitoringConfiguration\Application\Service\HostReferencesChecker;
use App\MonitoringConfiguration\Application\Service\HostRelationsUpdater;
use App\MonitoringConfiguration\Application\Service\HostTemplateServicesCleaner;
use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\DataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroChange;
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
use App\MonitoringConfiguration\Domain\Event\HostServicesDeploymentRequested;
use App\MonitoringConfiguration\Domain\Event\HostVaultPurgeRequested;
use App\MonitoringConfiguration\Domain\Exception\CheckArgumentsRequireACommandException;
use App\MonitoringConfiguration\Domain\Exception\HostAlreadyExistsException;
use App\MonitoringConfiguration\Domain\Exception\HostNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\VaultWriteFailedException;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\Security\Domain\Aggregate\ActingUser;
use App\Shared\Application\Command\AsCommandHandler;
use App\Shared\Application\Vault\VaultCredentialWriter;
use App\Shared\Domain\Event\EventBus;
use App\Shared\Domain\NoValue;
use App\Shared\Domain\Vault\VaultCredentials;
use App\Shared\Domain\Vault\VaultKeyEnum;
use App\Shared\Domain\Vault\VaultPathEnum;
use App\Shared\Domain\VaultInterface;
use Psr\Log\LoggerInterface;

#[AsCommandHandler]
final readonly class PatchHostCommandHandler
{
    public function __construct(
        private HostRepository $repository,
        private CommandRepository $commandRepository,
        private HostReferencesChecker $references,
        private HostRelationsUpdater $relations,
        private HostTemplateServicesCleaner $templateServices,
        private HostMacrosUpdater $macros,
        private HostMacroSecretsSynchronizer $macroSecrets,
        private VaultInterface $vault,
        private VaultCredentialWriter $vaultCredentialWriter,
        private AdditiveInheritanceModeApplier $additiveInheritanceModeApplier,
        private EventBus $eventBus,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * The secrets are written to the vault before the host is saved, so that an unreachable vault saves
     * nothing. The vault is outside the transaction: when the host cannot be saved after that, the
     * database rolls back but the vault keeps what was written, which the stored references may now
     * resolve to (changed secret) or miss (cleared key). That divergence is logged as a warning rather
     * than left silent. A failure of the commit itself, after this handler returns, is not covered.
     */
    public function __invoke(PatchHostCommand $command): Host
    {
        $host = $this->getHost($command, $command->actor);
        // The references come before the name: a restricted actor must not be able to tell a duplicate
        // name from a resource they cannot access.
        $this->assertReferencesExist($command, $command->actor);
        $withRelations = $this->relations->applyTo($host, $command, $command->actor);
        $this->assertNameIsAvailable($command, $host);
        $this->assertArgumentsHaveACheckCommand($command, $host);

        $updatedHost = $this->applyChanges($withRelations, $command);
        $macros = $command->macros instanceof MacroChanges ? $this->macros->applyTo($updatedHost, $command->macros) : null;
        if ($macros !== null) {
            $updatedHost = $updatedHost->with(checkOptions: $updatedHost->checkOptions->with(macros: $macros));
        }

        // The vault write comes last, once every validation and every invariant of the new host has
        // passed: a rejected update must never leave an orphan secret behind.
        $updatedHost = $this->moveSecretsToTheVault($host, $updatedHost, $command, macrosChanged: $macros !== null);

        try {
            $this->saveAndNotify($host, $updatedHost, $command->actor->userId->value, secretRewritten: $this->providesASecret($command));
        } catch (\Throwable $exception) {
            if ($this->touchesTheVault($host, $updatedHost, $command)) {
                $this->logger->warning(
                    'A host update failed after writing to its vault entry: the vault may now diverge from the host stored in the database.',
                    ['host_id' => $command->id->value, 'exception' => $exception],
                );
            }

            throw $exception;
        }

        if ($command->deployServicesFromTemplates && count($updatedHost->templateIds) > 0) {
            $this->eventBus->fire(new HostServicesDeploymentRequested($updatedHost->id(), $command->actor->userId));
        }

        return $updatedHost;
    }

    private function getHost(PatchHostCommand $command, ActingUser $actor): Host
    {
        // Actor-scoped: findOne returns null for a host outside the actor's ACL scope, the same as
        // a nonexistent one, so a restricted actor can never tell them apart.
        $host = $this->repository->findOne($command->id, $actor);
        if (! $host instanceof Host) {
            throw new HostNotFoundException([$command->id->value], 'id');
        }

        return $host;
    }

    /**
     * The same rules ran on the input, earlier: this is the authoritative check, against what exists now.
     * The check command is not listed: loading it, for the Centreon Monitoring Agent rule, is its check.
     */
    private function assertReferencesExist(PatchHostCommand $command, ActingUser $actor): void
    {
        if ($command->pollerId instanceof PollerId) {
            $this->references->assertPollerAccessible($command->pollerId, $actor);
        }

        if ($command->severityId instanceof HostSeverityId) {
            $this->references->assertSeverityAccessible($command->severityId, $actor);
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
     * The community and the password macros share the vault entry of the host, a new one when it has none
     * yet: the community given is written to it, a community removed has its key removed, and the macros
     * go through {@see HostMacroSecretsSynchronizer}, with the entry the community may just have created.
     * A reference to the vault given by the caller is ignored: the host keeps the community it has.
     * Without a vault, nothing is vaulted and the community is stored as is, like on creation.
     */
    private function moveSecretsToTheVault(Host $before, Host $after, PatchHostCommand $command, bool $macrosChanged): Host
    {
        $community = $command->snmpCommunity;

        if (! $this->vault->isEnabled()) {
            return $community instanceof NoValue ? $after : $after->with(snmpCommunity: $community === null ? null : new SnmpCommunity($community));
        }

        $vaultUuid = $before->getVaultUuid($this->vault);
        $snmpCommunity = $after->snmpCommunity;
        if ($community === null) {
            $snmpCommunity = null;
            if ($vaultUuid !== null && $before->snmpCommunity instanceof SnmpCommunity && $this->vault->isVaultPath($before->snmpCommunity->value)) {
                $this->writeToTheVault($before, VaultCredentials::empty()->clear(VaultKeyEnum::HostSnmpCommunity), $vaultUuid);
            }
        } elseif (is_string($community) && ! $this->vault->isVaultPath($community)) {
            $stored = $this->writeToTheVault($before, VaultCredentials::empty()->set(VaultKeyEnum::HostSnmpCommunity, $community), $vaultUuid);
            $snmpCommunity = new SnmpCommunity($stored[VaultKeyEnum::HostSnmpCommunity->value]);
            $vaultUuid = $this->vault->extractUuid($snmpCommunity->value);
        }

        $macros = $after->checkOptions->macros;
        if ($macrosChanged) {
            try {
                $macros = $this->macroSecrets->synchronize(array_values($macros), array_values($before->checkOptions->macros), $vaultUuid);
            } catch (\Throwable $exception) {
                throw VaultWriteFailedException::forHost($before->id(), $exception);
            }
        }

        return $after->with(snmpCommunity: $snmpCommunity, checkOptions: $after->checkOptions->with(macros: $macros));
    }

    /**
     * @return array<string, string> the reference stored for each key written
     */
    private function writeToTheVault(Host $host, VaultCredentials $credentials, ?string $vaultUuid): array
    {
        try {
            return $this->vaultCredentialWriter->persist(VaultPathEnum::MonitoringHosts, $credentials, $vaultUuid);
        } catch (\Throwable $exception) {
            throw VaultWriteFailedException::forHost($host->id(), $exception);
        }
    }

    /**
     * A secret given is written to the vault, and the host keeps the very same reference when it already had
     * one, since the path of a vaulted value only depends on its entry and its key: the update still counts.
     * Any password given with a value counts, even when it ends up equal to the inherited macro or empty:
     * reporting a change that did not happen is harmless, missing one would leave the poller stale.
     */
    private function providesASecret(PatchHostCommand $command): bool
    {
        if (! $this->vault->isEnabled()) {
            return false;
        }

        if (is_string($command->snmpCommunity) && ! $this->vault->isVaultPath($command->snmpCommunity)) {
            return true;
        }

        return $command->macros instanceof MacroChanges
            && array_any($command->macros->changes, static fn (HostMacroChange $change): bool => $change->isPassword && $change->value !== null);
    }

    private function touchesTheVault(Host $before, Host $after, PatchHostCommand $command): bool
    {
        return $this->vault->isEnabled() && (
            $command->snmpCommunity === null
            || (is_string($command->snmpCommunity) && ! $this->vault->isVaultPath($command->snmpCommunity))
            || (
                $command->macros instanceof MacroChanges
                && array_any([...$before->checkOptions->macros, ...$after->checkOptions->macros], static fn (HostMacro $macro): bool => $macro->isPassword)
            )
        );
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
                $this->templateServices->cleanUp($hostBefore, $hostAfter, $updatedBy);
            }

            if (! $hostAfter->checkOptions->hasSameMacrosAs($hostBefore->checkOptions)) {
                $this->repository->replaceMacros($hostAfter);
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
     * A host left without any secret leaves its entry empty in the vault. The entry is removed once the
     * update is committed, so that a failed update never deletes the entry itself. The keys cleared along
     * the way, the community and the passwords removed or demoted, are cleared before the save, see
     * {@see self::__invoke()}. The purge is best effort: a leftover entry is harmless, and the update is
     * already saved.
     */
    private function releaseCredentialsOf(Host $hostBefore, Host $hostAfter): void
    {
        if ($this->vault->isEnabled() && $hostAfter->releasesVaultEntryOf($hostBefore, $this->vault)) {
            $this->eventBus->fire(new HostVaultPurgeRequested($hostBefore, bestEffort: true));
        }
    }

    private function hasSameContactsAs(Host $host, Host $other): bool
    {
        return ($host->notifications ?? Notifications::default())->hasSameContactsAs($other->notifications ?? Notifications::default());
    }
}
