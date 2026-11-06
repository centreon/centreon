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
use App\MonitoringConfiguration\Application\Service\HostTemplateMacroSecretsSynchronizer;
use App\MonitoringConfiguration\Application\Service\HostTemplateMacrosUpdater;
use App\MonitoringConfiguration\Application\Service\HostTemplateReferencesChecker;
use App\MonitoringConfiguration\Application\Service\HostTemplateRelationsUpdater;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateChange;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateCheckOptionsChange;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateDataProcessingChange;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateExtendedInformationsChange;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateMacro;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateMacroChange;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateNotifications;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateNotificationsChange;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateSchedulingOptionsChange;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationOptionEnum;
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
use App\Shared\Domain\Collection;
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
        private HostTemplateRelationsUpdater $relations,
        private HostTemplateMacrosUpdater $macros,
        private HostTemplateMacroSecretsSynchronizer $macroSecrets,
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
     * that changes nothing saves nothing and fires nothing. A change of the relations, the macros or the
     * contacts only is saved and flags the pollers, but is not logged.
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
        $relations = $this->relations->resolve($template, $command);
        $change = $this->keepTheSeverityOutOfScope($template, $change, $command->actor);
        $this->assertNameIsAvailable($template, $change);
        $change = $this->applyTheMonitoringAgentRule($change);
        $change = $this->applyTheOptions($template, $change, $command->notificationOptions);
        $change = $this->applyTheInheritanceMode($template, $change);

        $changed = $template->withChange($change)->withRelations($relations);
        if ($command->macros instanceof HostTemplateMacroChanges) {
            $changed = $changed->withMacros($this->macros->resolve($changed, $command->macros));
        }

        $previousVaultUuid = $this->vault->isEnabled() ? $template->getVaultUuid($this->vault) : null;
        $vaultUuid = $previousVaultUuid;
        try {
            ['community' => $snmpCommunity, 'secretRewritten' => $secretRewritten] = $this->moveTheCommunityToTheVault($template, $command->snmpCommunity);
            $changed = $changed->withChange(new HostTemplateChange(snmpCommunity: $snmpCommunity));
            if ($snmpCommunity instanceof SnmpCommunity && $this->vault->isEnabled() && $this->vault->isVaultPath($snmpCommunity->value)) {
                $vaultUuid = $this->vault->extractUuid($snmpCommunity->value);
            }

            if ($command->macros instanceof HostTemplateMacroChanges && $this->vault->isEnabled()) {
                $changed = $this->moveTheMacrosToTheVault($template, $changed, $vaultUuid);
                // Every vaulted macro is now under the template's own entry.
                $vaultUuid ??= $changed->getVaultUuid($this->vault);
            }

            $this->save($template, $changed, $command, $secretRewritten);
        } catch (\Throwable $exception) {
            $this->recoverTheVault($template, $command, $previousVaultUuid, $vaultUuid, $exception);

            throw $exception;
        }

        return $changed;
    }

    private function save(HostTemplate $template, HostTemplate $changed, PatchHostTemplateCommand $command, bool $secretRewritten): void
    {
        $passwordRewritten = $this->providesAPassword($command);
        $relationsChanged = ! $changed->hasSameRelationsAs($template) || ! $changed->hasSameContactsAs($template);
        $macrosChanged = ! $changed->hasSameMacrosAs($template);
        $loggedChange = $secretRewritten || ! $changed->hasSameLoggedConfigurationAs($template);

        if (! $loggedChange && ! $passwordRewritten && ! $relationsChanged && ! $macrosChanged) {
            return;
        }

        $this->repository->update($changed);
        if ($relationsChanged) {
            $this->repository->replaceRelations($changed);
        }
        if ($macrosChanged) {
            $this->repository->replaceMacros($changed);
        }

        $this->eventBus->fire(new HostTemplateUpdated($changed, $command->actor->userId->value, loggable: $loggedChange));

        if ($this->vault->isEnabled() && $changed->releasesVaultEntryOf($template, $this->vault)) {
            $this->eventBus->fire(new HostTemplateVaultPurgeRequested($template, bestEffort: true));
        }
    }

    /**
     * The options follow the list rules: replaced, added to or removed from.
     *
     * @param NoValue|ListChange<NotificationOptionEnum> $options
     */
    private function applyTheOptions(HostTemplate $template, HostTemplateChange $change, NoValue|ListChange $options): HostTemplateChange
    {
        if ($options instanceof NoValue) {
            return $change;
        }

        $notifications = $change->notifications instanceof HostTemplateNotificationsChange
            ? $change->notifications
            : new HostTemplateNotificationsChange();

        return $change->withNotifications($notifications->withOptions(
            $options->applyTo(($template->notifications ?? new HostTemplateNotifications())->options),
        ));
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

        return $change->withNotifications(
            $notifications->withAdditiveInheritance($applied->contactAdditiveInheritance, $applied->contactGroupAdditiveInheritance),
        );
    }

    /**
     * A vault reference is ignored: none is ever returned, so none can come back legitimately. A secret
     * written counts as a change even when its reference stays the same (same entry, same key).
     *
     * @return array{community: NoValue|SnmpCommunity|null, secretRewritten: bool}
     */
    private function moveTheCommunityToTheVault(HostTemplate $template, NoValue|string|null $community): array
    {
        if ($community instanceof NoValue) {
            return ['community' => new NoValue(), 'secretRewritten' => false];
        }

        if (! $this->vault->isEnabled()) {
            return ['community' => $community === null ? null : new SnmpCommunity($community), 'secretRewritten' => false];
        }

        $vaultUuid = $template->getVaultUuid($this->vault);
        if ($community === null) {
            if ($vaultUuid !== null && $template->snmpCommunity instanceof SnmpCommunity && $this->vault->isVaultPath($template->snmpCommunity->value)) {
                $this->writeToTheVault($template, VaultCredentials::empty()->clear(VaultKeyEnum::HostSnmpCommunity), $vaultUuid);
            }

            return ['community' => null, 'secretRewritten' => false];
        }

        if ($this->vault->isVaultPath($community)) {
            return ['community' => new NoValue(), 'secretRewritten' => false];
        }

        $stored = $this->writeToTheVault($template, VaultCredentials::empty()->set(VaultKeyEnum::HostSnmpCommunity, $community), $vaultUuid);

        return ['community' => new SnmpCommunity($stored[VaultKeyEnum::HostSnmpCommunity->value]), 'secretRewritten' => true];
    }

    /**
     * The password macros share the template's vault entry, the one the community may just have
     * created, or a new one.
     */
    private function moveTheMacrosToTheVault(HostTemplate $template, HostTemplate $changed, ?string $vaultUuid): HostTemplate
    {
        try {
            $macros = $this->macroSecrets->synchronize(
                array_values($changed->checkOptions->macros->toArray()),
                array_values($template->checkOptions->macros->toArray()),
                $vaultUuid,
            );
        } catch (\Throwable $exception) {
            throw VaultWriteFailedException::forHostTemplate($template->id(), $exception);
        }

        return $changed->withMacros(new Collection($macros, HostTemplateMacro::class));
    }

    /**
     * A password given with a value is written to the vault under the same reference: it still counts.
     */
    private function providesAPassword(PatchHostTemplateCommand $command): bool
    {
        return $this->vault->isEnabled()
            && $command->macros instanceof HostTemplateMacroChanges
            && array_any($command->macros->changes, static fn (HostTemplateMacroChange $change): bool => $change->isPassword && $change->value !== null);
    }

    /**
     * An entry minted for this write is deleted; an entry the template already had cannot be put back
     * as it was, so the divergence is logged. Best effort: never thrown over the cause.
     */
    private function recoverTheVault(HostTemplate $template, PatchHostTemplateCommand $command, ?string $previousVaultUuid, ?string $vaultUuid, \Throwable $cause): void
    {
        if (! $this->vault->isEnabled()) {
            return;
        }

        try {
            if ($previousVaultUuid === null && $vaultUuid !== null) {
                $this->vaultCredentialWriter->delete(VaultPathEnum::MonitoringHosts, $vaultUuid);

                return;
            }

            if ($previousVaultUuid !== null && (! $command->snmpCommunity instanceof NoValue || $command->macros instanceof HostTemplateMacroChanges)) {
                $this->logger->warning(
                    'A host template update failed after writing to its vault entry: the vault may now diverge from the host template stored in the database.',
                    ['host_template_id' => $template->id()->value, 'exception' => $cause],
                );
            }
        } catch (\Throwable $recoveryException) {
            try {
                $this->logger->warning('The vault entry minted for a failed host template update could not be purged.', [
                    'host_template_id' => $template->id()->value,
                    'exception' => $recoveryException,
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
