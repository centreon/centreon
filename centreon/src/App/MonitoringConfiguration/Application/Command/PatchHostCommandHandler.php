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
use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\DataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SnmpCommunity;
use App\MonitoringConfiguration\Domain\Event\HostCredentialsReleased;
use App\MonitoringConfiguration\Domain\Event\HostDisabled;
use App\MonitoringConfiguration\Domain\Event\HostEnabled;
use App\MonitoringConfiguration\Domain\Event\HostMassChanged;
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
        private VaultInterface $vault,
        private VaultCredentialWriter $vaultCredentialWriter,
        private AdditiveInheritanceModeApplier $additiveInheritanceModeApplier,
        private EventBus $eventBus,
    ) {
    }

    public function __invoke(PatchHostCommand $command): Host
    {
        $host = $this->getHost($command);
        $this->assertNameIsAvailable($command, $host);

        // The vault write comes last, once every validation and every invariant of the new host has
        // passed: a rejected update must never leave an orphan secret behind.
        $updatedHost = $this->withSnmpCommunity($host, $this->applyChanges($host, $command), $command);
        $this->saveAndNotify($host, $updatedHost, $command->updatedBy);

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

    private function assertNameIsAvailable(PatchHostCommand $command, Host $host): void
    {
        if (
            ! $command->name instanceof NoValue
            && $command->name->value !== $host->name->value
            && $this->repository->isNameUsedByHostOrTemplate($command->name)
        ) {
            throw new HostAlreadyExistsException(['name' => $command->name->value]);
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
            notifications: $command->notifications instanceof NoValue
                ? new NoValue()
                : $this->additiveInheritanceModeApplier->apply($command->notifications->applyTo($host->notifications)),
            activated: $command->activated,
        );
    }

    /**
     * A reference to the vault is never trusted from the caller: it is ignored, the host keeps the
     * community it has. Without a vault the community is stored as is, like on creation.
     */
    private function withSnmpCommunity(Host $hostBefore, Host $updatedHost, PatchHostCommand $command): Host
    {
        $community = $command->snmpCommunity;

        if ($community instanceof NoValue) {
            return $updatedHost;
        }

        if ($community === null) {
            return $updatedHost->with(snmpCommunity: null);
        }

        if (! $this->vault->isEnabled()) {
            return $updatedHost->with(snmpCommunity: new SnmpCommunity($community));
        }

        if ($this->vault->isVaultPath($community)) {
            return $updatedHost;
        }

        return $updatedHost->with(snmpCommunity: new SnmpCommunity($this->storeInVault($hostBefore, $community)));
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
     * one. Its existence has already been checked with the other references.
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
     * flagged too.
     */
    private function saveAndNotify(Host $hostBefore, Host $hostAfter, int $updatedBy): void
    {
        if (! $hostAfter->hasSameConfigurationAs($hostBefore)) {
            $this->repository->update($hostAfter);
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
     * is committed: with its entry when the host keeps no other secret in it, alone otherwise.
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

        $this->eventBus->fire(new HostCredentialsReleased(
            $hostBefore,
            $hostAfter->releasesVaultEntryOf($hostBefore, $this->vault) ? [] : [VaultKeyEnum::HostSnmpCommunity->value],
        ));
    }
}
