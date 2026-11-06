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
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\DataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\SnmpCommunity;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
use App\MonitoringConfiguration\Domain\Event\HostTemplateUpdated;
use App\MonitoringConfiguration\Domain\Event\HostTemplateVaultPurgeRequested;
use App\MonitoringConfiguration\Domain\Exception\CheckArgumentsRequireACommandException;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateAlreadyExistsException;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\VaultWriteFailedException;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
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
        private HostReferencesChecker $references,
        private CommandRepository $commandRepository,
        private AdditiveInheritanceModeApplier $additiveInheritanceModeApplier,
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

        $this->assertReferencesExist($command);
        $this->assertNameIsAvailable($template, $command);
        $this->assertArgumentsHaveACheckCommand($template, $command);

        $changed = $template->with(
            name: $command->name,
            alias: $command->alias,
            snmpVersion: $command->snmpVersion,
            timezoneId: $command->timezoneId,
            severityId: $this->severityToApply($template, $command),
            checkOptions: $command->checkOptions instanceof NoValue
                ? new NoValue()
                : $command->checkOptions->applyTo($template->checkOptions),
            extendedInformations: $command->extendedInformations instanceof NoValue
                ? new NoValue()
                : $command->extendedInformations->applyTo($template->extendedInformations),
            schedulingOptions: $command->schedulingOptions instanceof NoValue
                ? new NoValue()
                : $command->schedulingOptions->applyTo($template->schedulingOptions),
            dataProcessing: $this->dataProcessing($template, $command),
            // With the platform option disabled, the additive inheritance flags the template already has are
            // reset along with any notification change.
            notifications: $command->notifications instanceof NoValue
                ? new NoValue()
                : $this->additiveInheritanceModeApplier->apply($command->notifications->applyTo($template->notifications)),
        );

        ['community' => $snmpCommunity, 'secretRewritten' => $secretRewritten, 'vaultWritten' => $vaultWritten] = $this->moveTheCommunityToTheVault($template, $command->snmpCommunity);
        $changed = $changed->with(snmpCommunity: $snmpCommunity);

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
    private function assertReferencesExist(PatchHostTemplateCommand $command): void
    {
        if ($command->severityId instanceof HostSeverityId) {
            $this->references->assertSeverityAccessible($command->severityId, $command->actor);
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

    /**
     * Clearing the severity keeps one the actor cannot see: they could not have chosen to remove it.
     */
    private function severityToApply(HostTemplate $template, PatchHostTemplateCommand $command): NoValue|HostSeverityId|null
    {
        if (
            $command->severityId === null
            && $template->severityId instanceof HostSeverityId
            && ! $this->accessScopes->hostSeverities($command->actor)->allows($template->severityId)
        ) {
            return new NoValue();
        }

        return $command->severityId;
    }

    private function assertNameIsAvailable(HostTemplate $template, PatchHostTemplateCommand $command): void
    {
        if (
            $command->name instanceof HostTemplateName
            && $command->name->value !== $template->name->value
            && $this->repository->isNameUsedByHostOrTemplate($command->name, $template->id())
        ) {
            throw HostTemplateAlreadyExistsException::forName($command->name->value);
        }
    }

    /**
     * Arguments sent on their own apply to the command the template already has.
     */
    private function assertArgumentsHaveACheckCommand(HostTemplate $template, PatchHostTemplateCommand $command): void
    {
        if (! $command->checkOptions instanceof CheckOptionsChanges || ! is_array($command->checkOptions->args) || $command->checkOptions->args === []) {
            return;
        }

        $commandId = $command->checkOptions->checkCommandId instanceof NoValue
            ? $template->checkOptions->checkCommandId
            : $command->checkOptions->checkCommandId;

        if (! $commandId instanceof CommandId) {
            throw new CheckArgumentsRequireACommandException();
        }
    }

    /**
     * A template checked by a Centreon Monitoring Agent pushes its results: freshness is always checked,
     * with a fixed threshold. Throws CommandNotFoundException when the check command given does not exist.
     */
    private function dataProcessing(HostTemplate $template, PatchHostTemplateCommand $command): NoValue|DataProcessing
    {
        $dataProcessing = $command->dataProcessing instanceof NoValue
            ? $template->dataProcessing
            : $command->dataProcessing->applyTo($template->dataProcessing);

        if (
            $command->checkOptions instanceof CheckOptionsChanges
            && $command->checkOptions->checkCommandId instanceof CommandId
            && $this->commandRepository->getById($command->checkOptions->checkCommandId)->isCentreonMonitoringAgent()
        ) {
            return $dataProcessing->withCentreonMonitoringAgentFreshness();
        }

        return $command->dataProcessing instanceof NoValue ? new NoValue() : $dataProcessing;
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
