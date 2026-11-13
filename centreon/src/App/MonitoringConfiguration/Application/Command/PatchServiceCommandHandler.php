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
use App\MonitoringConfiguration\Application\Service\ServiceMacroSecretsSynchronizer;
use App\MonitoringConfiguration\Application\Service\ServiceMacrosUpdater;
use App\MonitoringConfiguration\Application\Service\ServicePlatformGuard;
use App\MonitoringConfiguration\Application\Service\ServiceReferencesChecker;
use App\MonitoringConfiguration\Application\Service\ServiceRelationsUpdater;
use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\GraphTemplate\GraphTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostState;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceCheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceDataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotifications;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceSeverity\ServiceSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Event\ServiceDisabled;
use App\MonitoringConfiguration\Domain\Event\ServiceEnabled;
use App\MonitoringConfiguration\Domain\Event\ServiceMassChanged;
use App\MonitoringConfiguration\Domain\Event\ServiceVaultPurgeRequested;
use App\MonitoringConfiguration\Domain\Exception\EventHandlerArgumentsRequireAHandlerException;
use App\MonitoringConfiguration\Domain\Exception\ServiceAlreadyExistsException;
use App\MonitoringConfiguration\Domain\Exception\ServiceCheckArgumentsPartlyFilledException;
use App\MonitoringConfiguration\Domain\Exception\ServiceCheckCommandMissingException;
use App\MonitoringConfiguration\Domain\Exception\ServiceExtendedInformationsTooLongException;
use App\MonitoringConfiguration\Domain\Exception\ServiceNameForbiddenCharactersException;
use App\MonitoringConfiguration\Domain\Exception\ServiceNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\VaultWriteFailedException;
use App\MonitoringConfiguration\Domain\Repository\ServiceRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceTemplateRepository;
use App\Security\Domain\Service\AccessScopeResolver;
use App\Shared\Application\Command\AsCommandHandler;
use App\Shared\Domain\Event\EventBus;
use App\Shared\Domain\NoValue;
use App\Shared\Domain\VaultInterface;
use Psr\Log\LoggerInterface;

/**
 * A partial update of a single service: the fields it provides are checked and applied to the stored service, in
 * this order: the references, the relations, the name on the target host, the rules spanning several fields (only
 * when the update provides one of them), the own macros, then the vault, then the write.
 *
 * A change of the activation alone is a narrow write with its own event; anything else is a mass change, which
 * rewrites the whole service.
 */
#[AsCommandHandler]
final readonly class PatchServiceCommandHandler
{
    public function __construct(
        private ServiceRepository $repository,
        private ServiceTemplateRepository $serviceTemplateRepository,
        private ServicePlatformGuard $platform,
        private ServiceReferencesChecker $references,
        private ServiceRelationsUpdater $relations,
        private ServiceMacrosUpdater $macros,
        private ServiceMacroSecretsSynchronizer $macroSecrets,
        private AdditiveInheritanceModeApplier $additiveInheritanceModeApplier,
        private AccessScopeResolver $accessScopes,
        private VaultInterface $vault,
        private EventBus $eventBus,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * The secrets are written to the vault before the service is saved, so that an unreachable vault saves nothing.
     * The vault is outside the transaction: when the service cannot be saved after that, the database rolls back but
     * the vault keeps what was written. That divergence is logged as a warning rather than left silent. A failure of
     * the commit itself, after this handler returns, is not covered.
     */
    public function __invoke(PatchServiceCommand $command): void
    {
        // Actor-scoped: findOne returns null for a service outside the actor's ACL scope, the same as a
        // nonexistent one, so a restricted actor can never tell them apart.
        $service = $this->repository->findOne($command->id, $command->actor);
        if (! $service instanceof Service) {
            throw new ServiceNotFoundException([$command->id->value], 'id');
        }

        $this->platform->assertAvailable($command);

        // The references come before the name: a restricted actor must not be able to tell a duplicate name from a
        // resource they cannot access.
        [$host, $checkCommand] = $this->assertReferencesExist($command, $service);
        $updated = clone $service;
        $this->relations->applyTo($updated, $command);
        $this->assertNameIsAvailable($command, $service);

        $this->applyChanges($updated, $command, $host, $checkCommand);
        $this->assertRulesAcrossFieldsHold($command, $updated);

        $vaultWritten = false;
        $passwordRewritten = false;
        if ($command->macros instanceof ServiceMacroChanges) {
            $resolved = $this->macros->applyTo($updated, $service->checkOptions->macros, $command->macros);
            [$macros, $passwordRewritten, $vaultWritten] = $this->vaultPasswords($service, $resolved);
            $updated->changeCheckOptions(new ServiceCheckOptions($updated->checkOptions->checkCommandId, $updated->checkOptions->args, $macros));
        }

        try {
            $this->saveAndNotify($service, $updated, $command, $passwordRewritten);
        } catch (\Throwable $exception) {
            if ($vaultWritten) {
                $this->logger->warning(
                    'A service update failed after writing to its vault entry: the vault may now diverge from the service stored in the database.',
                    ['service_id' => $command->id->value, 'exception' => $exception],
                );
            }

            throw $exception;
        }
    }

    /**
     * @return array{?HostState, ?Command} the state of the host the service moves to, and the check command it is
     *                                     given, when the update provides them
     */
    private function assertReferencesExist(PatchServiceCommand $command, Service $service): array
    {
        $host = $command->hostId instanceof HostId ? $this->references->getAccessibleHost($command->hostId, $command->actor) : null;

        if ($command->templateId instanceof ServiceTemplateId) {
            $this->references->assertTemplateAllowed($command->templateId, $service->templateId);
        }

        if ($command->severityId instanceof ServiceSeverityId) {
            $this->references->assertSeverityAccessible($command->severityId, $command->actor);
        }

        if ($command->graphTemplateId instanceof GraphTemplateId) {
            $this->references->assertGraphTemplateExists($command->graphTemplateId);
        }

        if ($command->schedulingOptions instanceof ServiceSchedulingOptionsChanges && $command->schedulingOptions->checkTimeperiodId instanceof TimePeriodId) {
            $this->references->assertTimePeriodExists($command->schedulingOptions->checkTimeperiodId);
        }

        if ($command->notifications instanceof ServiceNotificationsChanges && $command->notifications->periodId instanceof TimePeriodId) {
            $this->references->assertTimePeriodExists($command->notifications->periodId);
        }

        if ($command->extendedInformations instanceof ServiceExtendedInformationsChanges && $command->extendedInformations->iconId instanceof MediaId) {
            $this->references->assertMediaExists($command->extendedInformations->iconId);
        }

        if ($command->dataProcessing instanceof ServiceDataProcessingChanges && $command->dataProcessing->eventHandlerCommandId instanceof CommandId) {
            $this->references->assertCommandExists($command->dataProcessing->eventHandlerCommandId);
        }

        $checkCommand = $command->checkOptions instanceof ServiceCheckOptionsChanges && $command->checkOptions->checkCommandId instanceof CommandId
            ? $this->references->getCheckCommand($command->checkOptions->checkCommandId)
            : null;

        return [$host, $checkCommand];
    }

    /**
     * A name provided must hold no forbidden character. It must be free on the host the service ends up on, the
     * service itself left out; a name and a host left as they are need no check, even when legacy stored a duplicate.
     */
    private function assertNameIsAvailable(PatchServiceCommand $command, Service $service): void
    {
        if ($command->name instanceof ServiceName && $command->name->hasForbiddenCharacters()) {
            throw new ServiceNameForbiddenCharactersException($command->name->value);
        }

        $name = NoValue::resolve($command->name, $service->name);
        $hostId = NoValue::resolve($command->hostId, $service->hostId);
        if ($name->value === $service->name->value && $hostId->value === $service->hostId->value) {
            return;
        }

        if ($this->repository->isNameUsedOnHost($name, $hostId, $service->id())) {
            throw new ServiceAlreadyExistsException(['name' => $name->value]);
        }
    }

    private function applyChanges(Service $service, PatchServiceCommand $command, ?HostState $host, ?Command $checkCommand): void
    {
        if ($command->name instanceof ServiceName) {
            $service->rename($command->name);
        }

        if ($command->hostId instanceof HostId && $host instanceof HostState) {
            $service->moveToHost($command->hostId, $host);
        }

        if (is_bool($command->activated)) {
            $command->activated ? $service->enable() : $service->disable();
        }

        if (! $command->templateId instanceof NoValue) {
            $service->changeTemplate($command->templateId);
        }

        if ($command->checkOptions instanceof ServiceCheckOptionsChanges) {
            $service->changeCheckOptions($command->checkOptions->applyTo($service->checkOptions));
        }

        if ($command->schedulingOptions instanceof ServiceSchedulingOptionsChanges) {
            $service->changeSchedulingOptions($command->schedulingOptions->applyTo($service->schedulingOptions));
        }

        $notifications = $this->notifications($service, $command);
        if (! $notifications instanceof NoValue) {
            $service->changeNotifications($notifications);
        }

        $dataProcessing = $this->dataProcessing($service, $command, $checkCommand);
        if ($dataProcessing instanceof ServiceDataProcessing) {
            $service->changeDataProcessing($dataProcessing);
        }

        if ($command->extendedInformations instanceof ServiceExtendedInformationsChanges) {
            $service->changeExtendedInformations($command->extendedInformations->applyTo($service->extendedInformations));
        }

        if (! $command->graphTemplateId instanceof NoValue) {
            $service->changeGraphTemplate($command->graphTemplateId);
        }

        if (! $command->severityId instanceof NoValue) {
            $service->changeRelations($command->severityId, $service->serviceGroupIds, $service->categoryIds, $service->trapIds);
        }
    }

    /**
     * Once the notifications are changed, the rules of a write apply to what they become: a service notifying the
     * contacts of its host only has none of its own (those the actor cannot see stay linked), and the additive
     * inheritance flags are reset when the platform option disables them.
     */
    private function notifications(Service $service, PatchServiceCommand $command): NoValue|ServiceNotifications|null
    {
        if (
            $command->notifications instanceof NoValue
            && $command->contactIds instanceof NoValue
            && $command->contactGroupIds instanceof NoValue
        ) {
            return new NoValue();
        }

        $notifications = $command->notifications instanceof ServiceNotificationsChanges
            ? $command->notifications->applyTo($service->notifications)
            : $service->notifications ?? ServiceNotificationsChanges::none();

        if ($notifications->useOnlyContactsFromHost) {
            $notifications = $notifications->withHostContactsOnly(
                $this->accessScopes->contacts($command->actor),
                $this->accessScopes->contactGroups($command->actor),
            );
        }

        return $this->additiveInheritanceModeApplier->applyToService($notifications);
    }

    /**
     * A Centreon Monitoring Agent check command given to the service pushes its results: its freshness is always
     * checked, whatever the same update says. One inherited from the template does not count.
     */
    private function dataProcessing(Service $service, PatchServiceCommand $command, ?Command $checkCommand): NoValue|ServiceDataProcessing
    {
        $dataProcessing = $command->dataProcessing instanceof ServiceDataProcessingChanges
            ? $command->dataProcessing->applyTo($service->dataProcessing)
            : $service->dataProcessing;

        if ($checkCommand?->isCentreonMonitoringAgent() === true) {
            return $dataProcessing->withCentreonMonitoringAgentFreshness();
        }

        return $command->dataProcessing instanceof NoValue ? new NoValue() : $dataProcessing;
    }

    /**
     * Checked on the service as the update leaves it, and only when the update provides one of the fields a rule
     * involves: a stored service already breaking a rule is not refused for an unrelated change.
     *
     * - a check command, its own or provided by its template chain, for the check command, its arguments and the
     *   template;
     * - every argument filled or none;
     * - an event handler, its own or provided by its template chain, for the event handler arguments it holds;
     * - the form bounds of the notes and URLs provided.
     */
    private function assertRulesAcrossFieldsHold(PatchServiceCommand $command, Service $service): void
    {
        $checkOptions = $command->checkOptions instanceof ServiceCheckOptionsChanges ? $command->checkOptions : new ServiceCheckOptionsChanges();
        $dataProcessing = $command->dataProcessing instanceof ServiceDataProcessingChanges ? $command->dataProcessing : new ServiceDataProcessingChanges();
        $templateChanged = ! $command->templateId instanceof NoValue;

        $needsCheckCommand = $templateChanged
            || ! $checkOptions->checkCommandId instanceof NoValue
            || (is_array($checkOptions->args) && $checkOptions->args !== []);
        $needsEventHandler = $templateChanged
            || ! $dataProcessing->eventHandlerCommandId instanceof NoValue
            || is_array($dataProcessing->eventHandlerArgs);

        if ($needsCheckCommand || $needsEventHandler) {
            $line = $service->templateId instanceof ServiceTemplateId
                ? $this->serviceTemplateRepository->findInheritanceLine($service->templateId)->toArray()
                : [];

            if (
                $needsCheckCommand
                && ! $service->checkOptions->checkCommandId instanceof CommandId
                && ! array_any($line, static fn (ServiceTemplate $template): bool => $template->checkCommandId instanceof CommandId)
            ) {
                throw new ServiceCheckCommandMissingException($service->templateId?->value);
            }

            if (
                $needsEventHandler
                && $service->dataProcessing->eventHandlerArgs !== []
                && ! $service->dataProcessing->eventHandlerCommandId instanceof CommandId
                && ! array_any($line, static fn (ServiceTemplate $template): bool => $template->eventHandlerCommandId instanceof CommandId)
            ) {
                throw new EventHandlerArgumentsRequireAHandlerException();
            }
        }

        if (is_array($checkOptions->args) && $service->checkOptions->hasPartlyFilledArguments()) {
            throw new ServiceCheckArgumentsPartlyFilledException();
        }

        if ($command->extendedInformations instanceof ServiceExtendedInformationsChanges) {
            $fields = array_values(array_intersect(
                $service->extendedInformations?->exceedsFormBounds() ?? [],
                $command->extendedInformations->providedTexts(),
            ));
            if ($fields !== []) {
                throw new ServiceExtendedInformationsTooLongException($fields);
            }
        }
    }

    /**
     * Moves every password macro of the resolved ones under the service's own vault entry, leaving a `secret::`
     * reference in its place, and removes the keys no macro references any more, before the service is saved.
     *
     * @param list<ServiceMacro> $resolved the own macros the service must hold
     *
     * @throws VaultWriteFailedException
     *
     * @return array{list<ServiceMacro>, bool, bool} the macros to store, whether a password was rewritten in the
     *                                               vault, and whether the vault may have been written to
     */
    private function vaultPasswords(Service $service, array $resolved): array
    {
        $current = $service->checkOptions->macros;
        $vaultUuid = $this->vault->isEnabled() ? $service->getVaultUuid($this->vault) : null;

        try {
            $macros = $this->macroSecrets->synchronize($resolved, $current, $vaultUuid);
        } catch (\Throwable $exception) {
            throw VaultWriteFailedException::forService($service->id(), $exception);
        }

        $vaultWritten = $this->vault->isEnabled()
            && array_any([...$resolved, ...$current], static fn (ServiceMacro $macro): bool => $macro->isPassword);

        // The synchronizer only swaps the value of the macros it wrote to the vault, which may leave the very same
        // reference: the path of a vaulted value only depends on its entry and its key.
        $passwordRewritten = array_any(
            $macros,
            static fn (ServiceMacro $macro, int $index): bool => $macro->value !== $resolved[$index]->value,
        );

        return [$macros, $passwordRewritten, $vaultWritten];
    }

    /**
     * Nothing is written when nothing changed. A plain enable/disable keeps the narrow write and its own event;
     * anything else is a mass change, which carries the previous host and its poller when the service moved.
     */
    private function saveAndNotify(Service $before, Service $after, PatchServiceCommand $command, bool $passwordRewritten): void
    {
        $actorId = $command->actor->userId->value;
        if (
            $passwordRewritten
            || ! $after->hasSameConfigurationAs($before)
            || ! $after->hasSameRelationsAs($before)
            || $this->collapsesStoredSeverities($command, $after)
        ) {
            $this->repository->update($after);
            $this->repository->replaceRelationsAndMacros($after);

            $hostChanged = $before->hostId->value !== $after->hostId->value;
            $this->eventBus->fire(new ServiceMassChanged(
                $after,
                $actorId,
                previousPollerId: $hostChanged ? $this->previousPollerToFlag($before, $after) : null,
                previousHostId: $hostChanged ? $before->hostId : null,
            ));

            // The entry this update left without any secret is purged once the update is committed, so that a failed
            // update never deletes a live secret. Best effort: the update is already saved, and a leftover entry is
            // harmless.
            if ($this->vault->isEnabled() && $after->releasesVaultEntryOf($before, $this->vault)) {
                $this->eventBus->fire(new ServiceVaultPurgeRequested($before, bestEffort: true));
            }

            return;
        }

        if ($after->activated !== $before->activated) {
            $this->repository->updateActivationStatus($after->id(), $after->activated);
            $this->eventBus->fire(
                $after->activated
                    ? new ServiceEnabled($after, $actorId)
                    : new ServiceDisabled($after, $actorId),
            );
        }
    }

    /**
     * A legacy service may hold several severities, of which it shows the lowest: a severity provided leaves a single
     * one, so extra ones are a change.
     */
    private function collapsesStoredSeverities(PatchServiceCommand $command, Service $after): bool
    {
        if ($command->severityId instanceof NoValue) {
            return false;
        }

        $storedValues = array_values(array_unique(array_map(
            static fn (ServiceSeverityId $id): int => $id->value,
            $this->repository->findSeverityIds($after->id())->toArray(),
        )));

        return $storedValues !== ($after->severityId instanceof ServiceSeverityId ? [$after->severityId->value] : []);
    }

    /**
     * The poller the service leaves, flagged besides the one of its new host: only while the previous host was
     * enabled, and once when both hosts share it.
     */
    private function previousPollerToFlag(Service $before, Service $after): ?PollerId
    {
        $previous = $before->pollerToFlag();

        return $previous?->value === $after->pollerToFlag()?->value ? null : $previous;
    }
}
