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
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateSnmpCommunity;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
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

        // Before the references: a locked template must not reveal whether a referenced id exists.
        $template->assertUnlocked();

        $change = $command->change;
        $this->assertReferencesExist($change, $command->actor);
        $change = $this->keepTheSeverityOutOfScope($template, $change, $command->actor);
        $this->assertNameIsAvailable($template, $change);
        $change = $this->applyTheMonitoringAgentRule($change);
        $change = $this->applyTheInheritanceMode($template, $change);

        $changed = $template->withChange($change);

        [$snmpCommunity, $secretRewritten] = $this->moveTheCommunityToTheVault($template, $command->snmpCommunity);
        $changed = $changed->withChange(new HostTemplateChange(snmpCommunity: $snmpCommunity));

        if (! $secretRewritten && $changed->hasSameConfigurationAs($template)) {
            return $changed;
        }

        $this->repository->update($changed);
        $this->eventBus->fire(new HostTemplateUpdated($changed, $command->actor->userId->value));

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
     * Left out keeps the community, null clears it (and its key in the template's vault entry), a
     * plaintext replaces it. A vault reference given is ignored: no community is ever echoed back, so a
     * client has no legitimate one to send. A secret written counts as a change even when the stored
     * reference stays the same, since the path of a vaulted value only depends on its entry and its key.
     *
     * @return array{NoValue|HostTemplateSnmpCommunity|null, bool}
     */
    private function moveTheCommunityToTheVault(HostTemplate $template, NoValue|string|null $community): array
    {
        if ($community instanceof NoValue) {
            return [new NoValue(), false];
        }

        if (! $this->vault->isEnabled()) {
            return [$community === null ? null : new HostTemplateSnmpCommunity($community), false];
        }

        $vaultUuid = $template->getVaultUuid($this->vault);
        if ($community === null) {
            if ($vaultUuid !== null && $template->snmpCommunity instanceof HostTemplateSnmpCommunity && $this->vault->isVaultPath($template->snmpCommunity->value)) {
                $this->writeToTheVault($template, VaultCredentials::empty()->clear(VaultKeyEnum::HostSnmpCommunity), $vaultUuid);
            }

            return [null, false];
        }

        if ($this->vault->isVaultPath($community)) {
            return [new NoValue(), false];
        }

        $stored = $this->writeToTheVault($template, VaultCredentials::empty()->set(VaultKeyEnum::HostSnmpCommunity, $community), $vaultUuid);

        return [new HostTemplateSnmpCommunity($stored[VaultKeyEnum::HostSnmpCommunity->value]), true];
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
