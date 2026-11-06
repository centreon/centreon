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

use App\MonitoringConfiguration\Application\Service\HostTemplateAdditiveInheritanceModeApplier;
use App\MonitoringConfiguration\Application\Service\HostTemplateReferencesChecker;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateChange;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateCheckOptionsChange;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateDataProcessingChange;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateExtendedInformationsChange;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateNotifications;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateNotificationsChange;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateSchedulingOptionsChange;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\SnmpCommunity;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
use App\MonitoringConfiguration\Domain\Event\HostTemplateUpdated;
use App\MonitoringConfiguration\Domain\Event\HostTemplateVaultPurgeRequested;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateAlreadyExistsException;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\VaultWriteFailedException;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\Security\Domain\Aggregate\ActingUser;
use App\Security\Domain\Service\AccessScopeResolver;
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
final readonly class PatchHostTemplateCommandHandler
{
    public function __construct(
        private HostTemplateRepository $repository,
        private HostTemplateReferencesChecker $references,
        private CommandRepository $commandRepository,
        private HostTemplateAdditiveInheritanceModeApplier $additiveInheritanceModeApplier,
        private AccessScopeResolver $accessScopes,
        private VaultInterface $vault,
        private VaultCredentialWriter $vaultCredentialWriter,
        private EventBus $eventBus,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Every check runs before the vault is written, so a refused write leaves no secret behind. A write
     * that changes nothing saves nothing and fires nothing.
     */
    public function __invoke(PatchHostTemplateCommand $command): HostTemplate
    {
        $template = $this->repository->findOne($command->id);
        if (! $template instanceof HostTemplate) {
            throw new HostTemplateNotFoundException([$command->id->value], 'id');
        }

        // The lock is refused before this handler checks any reference.
        $template->assertUnlocked();

        $change = $command->change;
        $this->assertReferencesExist($change, $command->actor);
        $change = $this->keepTheSeverityOutOfScope($template, $change, $command->actor);
        $this->assertNameIsAvailable($template, $change);
        $change = $this->applyTheMonitoringAgentRule($change);
        $change = $this->applyTheInheritanceMode($template, $change);

        $changed = $template->withChange($change);

        ['community' => $snmpCommunity, 'secretRewritten' => $secretRewritten, 'vaultWritten' => $vaultWritten] = $this->moveTheCommunityToTheVault($template, $command->snmpCommunity);
        $changed = $changed->withChange(new HostTemplateChange(snmpCommunity: $snmpCommunity));

        if (! $secretRewritten && $changed->hasSameConfigurationAs($template)) {
            return $changed;
        }

        try {
            $this->repository->update($changed);
            $this->eventBus->fire(new HostTemplateUpdated($changed, $command->actor->userId->value));
        } catch (\Throwable $exception) {
            if ($vaultWritten) {
                $this->undoTheVaultWrite($template, $changed, $exception);
            }

            throw $exception;
        }

        if ($this->vault->isEnabled() && $changed->releasesVaultEntryOf($template, $this->vault)) {
            $this->eventBus->fire(new HostTemplateVaultPurgeRequested($template, bestEffort: true));
        }

        return $changed;
    }

    /**
     * The input ran the same rules earlier: this is the authoritative check, against what exists now.
     * The check command is checked when the Centreon Monitoring Agent rule loads it.
     */
    private function assertReferencesExist(HostTemplateChange $change, ActingUser $actor): void
    {
        if ($change->severityId instanceof HostSeverityId) {
            $this->references->assertSeverityAccessible($change->severityId, $actor);
        }

        if ($change->timezoneId instanceof TimezoneId) {
            $this->references->assertTimezoneExists($change->timezoneId);
        }

        if ($change->extendedInformations instanceof HostTemplateExtendedInformationsChange && $change->extendedInformations->iconId instanceof MediaId) {
            $this->references->assertMediaExists($change->extendedInformations->iconId);
        }

        if ($change->schedulingOptions instanceof HostTemplateSchedulingOptionsChange && $change->schedulingOptions->checkTimeperiodId instanceof TimePeriodId) {
            $this->references->assertTimePeriodExists($change->schedulingOptions->checkTimeperiodId);
        }

        if ($change->notifications instanceof HostTemplateNotificationsChange && $change->notifications->periodId instanceof TimePeriodId) {
            $this->references->assertTimePeriodExists($change->notifications->periodId);
        }

        if ($change->dataProcessing instanceof HostTemplateDataProcessingChange && $change->dataProcessing->eventHandlerCommandId instanceof CommandId) {
            $this->references->assertCommandExists($change->dataProcessing->eventHandlerCommandId);
        }
    }

    /**
     * Clearing the severity keeps one the actor cannot see: they could not have chosen to remove it.
     */
    private function keepTheSeverityOutOfScope(HostTemplate $template, HostTemplateChange $change, ActingUser $actor): HostTemplateChange
    {
        if (
            $change->severityId === null
            && $template->severityId instanceof HostSeverityId
            && ! $this->accessScopes->hostSeverities($actor)->allows($template->severityId)
        ) {
            return $change->withSeverityId(new NoValue());
        }

        return $change;
    }

    private function assertNameIsAvailable(HostTemplate $template, HostTemplateChange $change): void
    {
        if (
            $change->name instanceof HostTemplateName
            && $change->name->value !== $template->name->value
            && $this->repository->isNameUsedByHostOrTemplate($change->name, $template->id())
        ) {
            throw HostTemplateAlreadyExistsException::forName($change->name->value);
        }
    }

    /**
     * Throws CommandNotFoundException when the check command given does not exist.
     */
    private function applyTheMonitoringAgentRule(HostTemplateChange $change): HostTemplateChange
    {
        if (! $change->checkOptions instanceof HostTemplateCheckOptionsChange || ! $change->checkOptions->checkCommandId instanceof CommandId) {
            return $change;
        }

        if (! $this->commandRepository->getById($change->checkOptions->checkCommandId)->isCentreonMonitoringAgent()) {
            return $change;
        }

        $dataProcessing = $change->dataProcessing instanceof HostTemplateDataProcessingChange
            ? $change->dataProcessing
            : new HostTemplateDataProcessingChange();

        return $change->withDataProcessing($dataProcessing->withCentreonMonitoringAgentFreshness());
    }

    /**
     * With the platform option disabled, the additive inheritance flags the template already has are
     * reset along with any notification change.
     */
    private function applyTheInheritanceMode(HostTemplate $template, HostTemplateChange $change): HostTemplateChange
    {
        $notifications = $change->notifications;
        if (! $notifications instanceof HostTemplateNotificationsChange) {
            return $change;
        }

        $applied = $this->additiveInheritanceModeApplier->apply($notifications->applyTo($template->notifications));
        if (! $applied instanceof HostTemplateNotifications) {
            return $change;
        }

        return $change->withNotifications(new HostTemplateNotificationsChange(
            enabled: $notifications->enabled,
            interval: $notifications->interval,
            periodId: $notifications->periodId,
            firstDelay: $notifications->firstDelay,
            recoveryDelay: $notifications->recoveryDelay,
            contactAdditiveInheritance: $applied->contactAdditiveInheritance,
            contactGroupAdditiveInheritance: $applied->contactGroupAdditiveInheritance,
        ));
    }

    /**
     * A vault reference is ignored: none is ever returned, so none can come back legitimately. A secret
     * written counts as a change even when its reference stays the same (same entry, same key).
     *
     * @return array{community: NoValue|SnmpCommunity|null, secretRewritten: bool, vaultWritten: bool}
     */
    private function moveTheCommunityToTheVault(HostTemplate $template, NoValue|string|null $community): array
    {
        if ($community instanceof NoValue) {
            return ['community' => new NoValue(), 'secretRewritten' => false, 'vaultWritten' => false];
        }

        if (! $this->vault->isEnabled()) {
            return ['community' => $community === null ? null : new SnmpCommunity($community), 'secretRewritten' => false, 'vaultWritten' => false];
        }

        $vaultUuid = $template->getVaultUuid($this->vault);
        if ($community === null) {
            if ($vaultUuid !== null && $template->snmpCommunity instanceof SnmpCommunity && $this->vault->isVaultPath($template->snmpCommunity->value)) {
                $this->writeToTheVault($template, VaultCredentials::empty()->clear(VaultKeyEnum::HostSnmpCommunity), $vaultUuid);

                return ['community' => null, 'secretRewritten' => false, 'vaultWritten' => true];
            }

            return ['community' => null, 'secretRewritten' => false, 'vaultWritten' => false];
        }

        if ($this->vault->isVaultPath($community)) {
            return ['community' => new NoValue(), 'secretRewritten' => false, 'vaultWritten' => false];
        }

        $stored = $this->writeToTheVault($template, VaultCredentials::empty()->set(VaultKeyEnum::HostSnmpCommunity, $community), $vaultUuid);

        return ['community' => new SnmpCommunity($stored[VaultKeyEnum::HostSnmpCommunity->value]), 'secretRewritten' => true, 'vaultWritten' => true];
    }

    /**
     * Best effort, never thrown over the cause: an entry minted by this write is purged; an existing one
     * cannot be restored, so its divergence from the database is logged.
     */
    private function undoTheVaultWrite(HostTemplate $before, HostTemplate $changed, \Throwable $cause): void
    {
        try {
            $mintedUuid = $before->getVaultUuid($this->vault) === null ? $changed->getVaultUuid($this->vault) : null;
            if ($mintedUuid !== null) {
                $this->vaultCredentialWriter->delete(VaultPathEnum::MonitoringHosts, $mintedUuid);

                return;
            }

            $this->logger->warning(
                'A host template update failed after writing to its vault entry: the vault may now diverge from the host template stored in the database.',
                ['host_template_id' => $before->id()->value, 'exception' => $cause],
            );
        } catch (\Throwable $vaultException) {
            try {
                $this->logger->warning('The vault entry minted for a failed host template update could not be purged.', [
                    'host_template_id' => $before->id()->value,
                    'exception' => $vaultException,
                ]);
            } catch (\Throwable) {
            }
        }
    }

    /**
     * @return array<string, string> the reference stored for each key written
     */
    private function writeToTheVault(HostTemplate $template, VaultCredentials $credentials, ?string $vaultUuid): array
    {
        try {
            return $this->vaultCredentialWriter->persist(VaultPathEnum::MonitoringHosts, $credentials, $vaultUuid);
        } catch (\Throwable $exception) {
            throw VaultWriteFailedException::forHostTemplate($template->id(), $exception);
        }
    }
}
