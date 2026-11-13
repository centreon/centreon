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
use App\MonitoringConfiguration\Application\Service\ServiceTemplateLicenseGuard;
use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\GraphTemplate\GraphTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostState;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceCheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroChange;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotifications;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceCategory\ServiceCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceGroup\ServiceGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceSeverity\ServiceSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceSeverity\ServiceSeverityName;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\StandardMacro\StandardMacro;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapId;
use App\MonitoringConfiguration\Domain\Event\ServiceDisabled;
use App\MonitoringConfiguration\Domain\Event\ServiceEnabled;
use App\MonitoringConfiguration\Domain\Event\ServiceUpdated;
use App\MonitoringConfiguration\Domain\Event\ServiceVaultPurgeRequested;
use App\MonitoringConfiguration\Domain\Exception\CommandNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\ContactGroupNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\DuplicateServiceMacroNameException;
use App\MonitoringConfiguration\Domain\Exception\EventHandlerArgumentsRequireAHandlerException;
use App\MonitoringConfiguration\Domain\Exception\GraphTemplateNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\MediaNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\NotificationContactNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\ReservedServiceMacroNameException;
use App\MonitoringConfiguration\Domain\Exception\ServiceAlreadyExistsException;
use App\MonitoringConfiguration\Domain\Exception\ServiceCategoryNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\ServiceCheckArgumentsPartlyFilledException;
use App\MonitoringConfiguration\Domain\Exception\ServiceCheckCommandMissingException;
use App\MonitoringConfiguration\Domain\Exception\ServiceExtendedInformationsTooLongException;
use App\MonitoringConfiguration\Domain\Exception\ServiceGroupNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\ServiceMacroNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\ServiceMacroPasswordUnmaskedException;
use App\MonitoringConfiguration\Domain\Exception\ServiceMacroValueIsAVaultReferenceException;
use App\MonitoringConfiguration\Domain\Exception\ServiceMacroValueRequiredException;
use App\MonitoringConfiguration\Domain\Exception\ServiceNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\ServiceSeverityNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\ServiceTemplateNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\TimePeriodNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\TrapNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\UnlicensedServiceTemplatesException;
use App\MonitoringConfiguration\Domain\Exception\VaultWriteFailedException;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\MonitoringConfiguration\Domain\Repository\ContactGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\GraphTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\MonitoringConfiguration\Domain\Repository\MediaRepository;
use App\MonitoringConfiguration\Domain\Repository\NotificationContactRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceCategoryRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceSeverityRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\StandardMacroRepository;
use App\MonitoringConfiguration\Domain\Repository\TimePeriodRepository;
use App\MonitoringConfiguration\Domain\Repository\TrapRepository;
use App\MonitoringConfiguration\Domain\Service\InheritedServiceMacrosResolver;
use App\MonitoringConfiguration\Domain\Service\ServiceMacroChangesResolver;
use App\Security\Domain\Aggregate\AccessScope;
use App\Security\Domain\Aggregate\ActingUser;
use App\Security\Domain\Service\AccessScopeResolver;
use App\Shared\Application\Command\AsCommandHandler;
use App\Shared\Domain\Aggregate\AggregateRootId;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Event\EventBus;
use App\Shared\Domain\NoValue;
use App\Shared\Domain\VaultInterface;
use Psr\Log\LoggerInterface;

/**
 * Full replace (PUT) of a single service. Deliberately duplicates {@see CreateServiceCommandHandler}'s checks rather
 * than sharing them, so the create path is never coupled to a change made for the update path, and checks in the
 * same order: the references, then the rules depending on several of them, then the form bounds, then the name on
 * the target host (the service itself left out), then the macros, then the vault, then the write.
 *
 * The update-specific parts: loading the target (not found when absent or out of the actor's scope), keeping what
 * the actor cannot see (service groups, categories, contacts, contact groups, a hidden severity), resolving the
 * submitted macros against the ones the service already owns, reusing (or releasing) its vault entry, and firing
 * {@see ServiceUpdated} with the previous host and its poller when the service moved.
 *
 * The new service is a copy of the stored one changed through its business methods, then compared with it: an update
 * that changes nothing writes nothing and fires nothing.
 */
#[AsCommandHandler]
final readonly class UpdateServiceCommandHandler
{
    public function __construct(
        private ServiceRepository $repository,
        private HostRepository $hostRepository,
        private ServiceTemplateRepository $serviceTemplateRepository,
        private ServiceGroupRepository $serviceGroupRepository,
        private ServiceCategoryRepository $serviceCategoryRepository,
        private ServiceSeverityRepository $serviceSeverityRepository,
        private TrapRepository $trapRepository,
        private GraphTemplateRepository $graphTemplateRepository,
        private TimePeriodRepository $timePeriodRepository,
        private MediaRepository $mediaRepository,
        private CommandRepository $commandRepository,
        private NotificationContactRepository $contactRepository,
        private ContactGroupRepository $contactGroupRepository,
        private ServiceTemplateLicenseGuard $templateLicenseGuard,
        private AdditiveInheritanceModeApplier $additiveInheritanceModeApplier,
        private AccessScopeResolver $accessScopes,
        private EventBus $eventBus,
        private StandardMacroRepository $standardMacroRepository,
        private VaultInterface $vault,
        private InheritedServiceMacrosResolver $inheritedMacrosResolver,
        private ServiceMacroChangesResolver $macroChangesResolver,
        private ServiceMacroSecretsSynchronizer $macroSecretsSynchronizer,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * The secrets are written to the vault before the service is saved, so that an unreachable vault saves nothing
     * (HTTP 502). The vault is outside the transaction: when the update fails after a successful vault write, the
     * database rolls back but the vault keeps the new values. This divergence is logged as a warning rather than
     * left silent. A failure of the commit itself, after this handler returns, is not covered.
     *
     * @throws ServiceNotFoundException
     * @throws HostNotFoundException
     * @throws ServiceTemplateNotFoundException
     * @throws UnlicensedServiceTemplatesException
     * @throws ServiceCategoryNotFoundException
     * @throws ServiceSeverityNotFoundException
     * @throws ServiceGroupNotFoundException
     * @throws TrapNotFoundException
     * @throws GraphTemplateNotFoundException
     * @throws TimePeriodNotFoundException
     * @throws MediaNotFoundException
     * @throws NotificationContactNotFoundException
     * @throws ContactGroupNotFoundException
     * @throws CommandNotFoundException
     * @throws ServiceCheckCommandMissingException
     * @throws ServiceCheckArgumentsPartlyFilledException
     * @throws EventHandlerArgumentsRequireAHandlerException
     * @throws ServiceExtendedInformationsTooLongException
     * @throws ServiceAlreadyExistsException
     * @throws ReservedServiceMacroNameException
     * @throws ServiceMacroValueIsAVaultReferenceException
     * @throws DuplicateServiceMacroNameException
     * @throws ServiceMacroNotFoundException
     * @throws ServiceMacroPasswordUnmaskedException
     * @throws ServiceMacroValueRequiredException
     * @throws VaultWriteFailedException
     */
    public function __invoke(UpdateServiceCommand $command): Service
    {
        // Actor-scoped: a service outside the actor's scope is not found, the same as one that does not exist.
        $existing = $this->repository->findOne($command->id, $command->actor);
        if (! $existing instanceof Service) {
            throw new ServiceNotFoundException([$command->id->value], 'id');
        }

        // Within the actor's host scope, and only its activation and poller (see CreateServiceCommandHandler).
        $host = $this->hostRepository->findState($command->hostId, $command->actor);
        if (! $host instanceof HostState) {
            throw new HostNotFoundException([$command->hostId->value], 'hostId');
        }

        $this->assertTemplateExists($command->templateId);
        // The template the service already has is kept, even one it could no longer be given.
        $this->templateLicenseGuard->assertLicensed($command->templateId, $existing->templateId);

        // Resolved once: for the checks below, and to keep what the actor cannot see.
        $serviceGroupScope = $this->accessScopes->serviceGroups($command->actor);
        $categoryScope = $this->accessScopes->serviceCategories($command->actor);
        $severityScope = $this->accessScopes->serviceSeverities($command->actor);

        $this->assertCategoriesExist($command->categoryIds, $categoryScope);
        $this->assertSeverityExists($command->severityId, $severityScope);
        $this->assertServiceGroupsExist($command->serviceGroupIds, $serviceGroupScope);
        $this->assertTrapsExist($command->trapIds);
        $this->assertGraphTemplateExists($command->graphTemplateId);
        $this->assertTimePeriodExists($command->schedulingOptions->checkTimeperiodId);
        $this->assertTimePeriodExists($command->notifications?->periodId);
        $this->assertIconExists($command->extendedInformations?->iconId);
        $this->assertContactsExist($command->notifications, $command->actor);

        $checkCommand = $this->findCheckCommand($command->checkCommandId);
        $eventHandlerCommandId = $command->dataProcessing->eventHandlerCommandId;
        if ($eventHandlerCommandId instanceof CommandId) {
            $this->commandRepository->getById($eventHandlerCommandId);
        }

        $checkOptions = new ServiceCheckOptions($command->checkCommandId, $command->checkCommandArgs);
        $this->assertCommandsAreProvided($command, $checkOptions);
        if ($checkOptions->hasPartlyFilledArguments()) {
            throw new ServiceCheckArgumentsPartlyFilledException();
        }
        // A legacy text above the form bounds is refused even when resent unchanged: the update brings it back
        // into the bounds.
        $this->assertExtendedInformationsFitTheForm($command->extendedInformations);

        // Excludes the service itself: keeping its own name is not a conflict.
        if ($this->repository->isNameUsedOnHost($command->name, $command->hostId, $command->id)) {
            throw new ServiceAlreadyExistsException(['name' => $command->name->value]);
        }

        $macroChanges = array_values($command->macroChanges->toArray());
        $this->assertSubmittedMacrosAreAllowed($macroChanges);
        // Resolved against what the submitted template and its effective check command make the service inherit,
        // and against the macros it owns: a macro inherited only through the stored template is unknown.
        $resolvedMacros = $this->macroChangesResolver->resolve(
            $macroChanges,
            $existing->checkOptions->macros,
            $this->inheritedMacrosResolver->resolve($command->templateId, $command->checkCommandId),
        );

        // Everything read before the vault is written: a refused or failed read leaves no secret behind.
        $notifications = $command->notifications instanceof ServiceNotifications
            ? $this->additiveInheritanceModeApplier->applyToService(
                $this->withHostContactsOnlyIfAsked(
                    $this->preserveInaccessibleContacts($command->notifications, $existing->notifications, $command->actor),
                    $command->actor,
                ),
            )
            // Absent on Cloud, where notifications follow another model: the stored block is kept.
            : new NoValue();
        $severityId = $this->preserveInaccessibleSeverity($command->severityId, $existing->severityId, $severityScope);
        // A legacy service may hold several severities, which the service as loaded does not show (it carries the
        // lowest): the write leaves the single one kept, so extra ones are a change.
        $storedSeverityIds = $this->repository->findSeverityIds($existing->id());
        // A restricted actor never sees, so never resends, the groups and categories outside their scope: they are
        // kept rather than dropped by the full replace.
        $serviceGroupIds = $this->preserveInaccessible($command->serviceGroupIds, $existing->serviceGroupIds, $serviceGroupScope, ServiceGroupId::class);
        $categoryIds = $this->preserveInaccessible($command->categoryIds, $existing->categoryIds, $categoryScope, ServiceCategoryId::class);

        // One vault entry per service, shared by its password macros: an existing one is reused.
        [$macros, $passwordRewritten, $vaultWritten] = $this->vaultPasswords($existing, $resolvedMacros);

        try {
            $service = clone $existing;
            $service->rename($command->name);
            $service->moveToHost($command->hostId, $host);
            $command->activated ? $service->enable() : $service->disable();
            $service->changeTemplate($command->templateId);
            $service->changeCheckOptions(new ServiceCheckOptions($command->checkCommandId, $command->checkCommandArgs, $macros));
            $service->changeSchedulingOptions($command->schedulingOptions);
            if (! $notifications instanceof NoValue) {
                $service->changeNotifications($notifications);
            }
            // A Centreon Monitoring Agent command set on the service pushes its results: its freshness is always
            // checked. One inherited from the template does not count.
            $service->changeDataProcessing(
                $checkCommand?->isCentreonMonitoringAgent() === true
                    ? $command->dataProcessing->withCentreonMonitoringAgentFreshness()
                    : $command->dataProcessing,
            );
            $service->changeExtendedInformations($command->extendedInformations);
            $service->changeGraphTemplate($command->graphTemplateId);
            $service->changeRelations($severityId, $serviceGroupIds, $categoryIds, $command->trapIds);

            return $this->save($command, $existing, $service, $passwordRewritten, $this->keepsTheStoredSeverities($storedSeverityIds, $severityId));
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
     * Writes the service and fires its events, unless nothing changed: then nothing is written and nothing fires.
     *
     * @param bool $passwordRewritten whether a password was rewritten in the vault, which leaves the very same
     *                                reference on the service
     * @param bool $severitiesKept whether the stored severities are exactly the one the service keeps
     */
    private function save(
        UpdateServiceCommand $command,
        Service $existing,
        Service $service,
        bool $passwordRewritten,
        bool $severitiesKept,
    ): Service {
        $activationFlipped = $existing->activated !== $service->activated;
        $loggedChange = $this->hasLoggedChange($existing, $service);
        // Macros, contacts and relations are compared but never logged: they are the unlogged changes. A secret
        // rewritten under the service's entry leaves the very same reference, so it is tracked apart.
        // Extra legacy severities are a change, since the write leaves a single one.
        $unloggedChange = $passwordRewritten
            || ! $severitiesKept
            || ! $service->hasSameConfigurationAs($existing)
            || ! $service->hasSameRelationsAs($existing);

        if (! $activationFlipped && ! $loggedChange && ! $unloggedChange) {
            return $service;
        }

        $this->repository->update($service);
        $this->repository->replaceRelationsAndMacros($service);

        // Activity log as for hosts: 0, 1 or 2 lines, an enable/disable line when the activation flipped, a change
        // line when a logged property changed. The side-effect handlers (ACL reload, poller flag) catch every one of
        // these events through the AggregateUpdated supertype.
        if ($activationFlipped) {
            $this->eventBus->fire(
                $service->activated
                    ? new ServiceEnabled($service, $command->actor->userId->value)
                    : new ServiceDisabled($service, $command->actor->userId->value),
            );
        }

        if ($loggedChange) {
            // The host is a logged field, so a move always lands here: it carries the previous host, and the poller
            // of that host when it has to be flagged besides the new one.
            $hostChanged = $existing->hostId->value !== $service->hostId->value;
            $this->eventBus->fire(new ServiceUpdated(
                $service,
                $command->actor->userId->value,
                previousPollerId: $hostChanged ? $this->previousPollerToFlag($existing, $service) : null,
                previousHostId: $hostChanged ? $existing->hostId : null,
            ));
        } elseif (! $activationFlipped) {
            // Only properties the log leaves out changed: the side effects must still run, without a log line.
            // (When the activation also flipped, the enable/disable event above already runs them.)
            $this->eventBus->fire(new ServiceUpdated($service, $command->actor->userId->value, loggable: false));
        }

        // Deferred (post-commit) purge of the vault entry this update left without any secret: the stored service
        // still references it, and a rolled-back update never deletes a live secret. Best effort: the save
        // succeeded, and an orphan entry is harmless.
        if ($this->vault->isEnabled() && $service->releasesVaultEntryOf($existing, $this->vault)) {
            $this->eventBus->fire(new ServiceVaultPurgeRequested($existing, bestEffort: true));
        }

        return $service;
    }

    /**
     * Whether the levelled rows stored for the service are exactly the one severity it keeps (or none).
     *
     * @param Collection<ServiceSeverityId> $stored
     */
    private function keepsTheStoredSeverities(Collection $stored, ?ServiceSeverityId $kept): bool
    {
        $storedValues = array_values(array_unique(array_map(static fn (ServiceSeverityId $id): int => $id->value, $stored->toArray())));

        return $storedValues === ($kept instanceof ServiceSeverityId ? [$kept->value] : []);
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

    /**
     * Whether a property the activity log records changed: everything {@see Service::hasSameConfigurationAs()}
     * compares but the macros and the notification contacts, which the log leaves out. Both are taken from $before
     * on the compared copy.
     */
    private function hasLoggedChange(Service $before, Service $after): bool
    {
        $comparable = clone $after;
        $comparable->changeCheckOptions(new ServiceCheckOptions($after->checkOptions->checkCommandId, $after->checkOptions->args, $before->checkOptions->macros));
        $comparable->changeNotifications($after->notifications?->withContacts(
            $before->notifications->contactIds ?? new Collection([], NotificationContactId::class),
            $before->notifications->contactGroupIds ?? new Collection([], ContactGroupId::class),
        ));

        return ! $comparable->hasSameConfigurationAs($before);
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
    private function vaultPasswords(Service $existing, array $resolved): array
    {
        $current = $existing->checkOptions->macros;
        $vaultUuid = $this->vault->isEnabled() ? $existing->getVaultUuid($this->vault) : null;

        try {
            $macros = $this->macroSecretsSynchronizer->synchronize($resolved, $current, $vaultUuid);
        } catch (\Throwable $exception) {
            throw VaultWriteFailedException::forService($existing->id(), $exception);
        }

        // The synchronizer leaves the vault untouched when no password macro is involved.
        $vaultWritten = $this->vault->isEnabled()
            && array_any([...$resolved, ...$current], static fn (ServiceMacro $macro): bool => $macro->isPassword);

        // The synchronizer only swaps the value of the macros it wrote to the vault.
        $passwordRewritten = array_any(
            $macros,
            static fn (ServiceMacro $macro, int $index): bool => $macro->value !== $resolved[$index]->value,
        );

        return [$macros, $passwordRewritten, $vaultWritten];
    }

    /**
     * The rules the input checked on the submitted macros, checked again as the authority: a macro cannot be named
     * like a reserved one, nor carry a vault reference as its value.
     *
     * @param list<ServiceMacroChange> $changes
     *
     * @throws ReservedServiceMacroNameException
     * @throws ServiceMacroValueIsAVaultReferenceException
     */
    private function assertSubmittedMacrosAreAllowed(array $changes): void
    {
        if ($changes === []) {
            return;
        }

        $reservedNames = array_map(
            static fn (StandardMacro $macro): string => $macro->name->value,
            iterator_to_array($this->standardMacroRepository->findAll(), false),
        );
        $reserved = [];
        $references = [];
        foreach ($changes as $change) {
            if (in_array($change->name->toStorageName(), $reservedNames, true)) {
                $reserved[] = $change->name->value;
            }

            if ($change->value !== null && $this->vault->isVaultPath($change->value)) {
                $references[] = $change->name->value;
            }
        }

        if ($reserved !== []) {
            throw new ReservedServiceMacroNameException($reserved);
        }

        if ($references !== []) {
            throw new ServiceMacroValueIsAVaultReferenceException($references);
        }
    }

    /**
     * Scopes a full-replace relation list to what the actor may change: ids outside the actor's scope are hidden
     * from them, so they can never be resent; they are kept from the stored service instead of being dropped. With
     * an unrestricted scope nothing is out of scope, so the submitted list is used as is.
     *
     * @template T of AggregateRootId
     *
     * @param Collection<T> $submitted
     * @param Collection<T> $existing
     * @param AccessScope<T> $scope
     * @param class-string<T> $className
     *
     * @return Collection<T>
     */
    private function preserveInaccessible(Collection $submitted, Collection $existing, AccessScope $scope, string $className): Collection
    {
        if ($scope->isUnrestricted()) {
            return $submitted;
        }

        $submittedValues = array_map(static fn (AggregateRootId $id): int => $id->value, $submitted->toArray());

        $preserved = array_filter(
            $existing->toArray(),
            static fn (AggregateRootId $id): bool => ! $scope->allows($id) && ! in_array($id->value, $submittedValues, true),
        );

        return new Collection([...$submitted->toArray(), ...array_values($preserved)], $className);
    }

    /**
     * {@see self::preserveInaccessible()} applied to the contacts and contact groups.
     */
    private function preserveInaccessibleContacts(ServiceNotifications $submitted, ?ServiceNotifications $existing, ActingUser $actor): ServiceNotifications
    {
        if (! $existing instanceof ServiceNotifications) {
            return $submitted;
        }

        return $submitted->withContacts(
            $this->preserveInaccessible($submitted->contactIds, $existing->contactIds, $this->accessScopes->contacts($actor), NotificationContactId::class),
            $this->preserveInaccessible($submitted->contactGroupIds, $existing->contactGroupIds, $this->accessScopes->contactGroups($actor), ContactGroupId::class),
        );
    }

    /**
     * The one severity the service keeps, as for hosts: the submitted one when there is one; otherwise a stored
     * severity the actor cannot see is kept, and one they can see is cleared. Every levelled row of the service is
     * then replaced by it.
     *
     * @param AccessScope<ServiceSeverityId> $scope
     */
    private function preserveInaccessibleSeverity(
        ?ServiceSeverityId $submitted,
        ?ServiceSeverityId $existing,
        AccessScope $scope,
    ): ?ServiceSeverityId {
        if ($submitted instanceof ServiceSeverityId || ! $existing instanceof ServiceSeverityId) {
            return $submitted;
        }

        return $scope->allows($existing) ? null : $existing;
    }

    /**
     * Never ACL-scoped: service templates are not, in legacy either.
     */
    private function assertTemplateExists(?ServiceTemplateId $templateId): void
    {
        if (! $templateId instanceof ServiceTemplateId) {
            return;
        }

        $names = $this->serviceTemplateRepository->findNamesByIds(new Collection([$templateId], ServiceTemplateId::class));
        if (! isset($names->toArray()[$templateId->value])) {
            throw new ServiceTemplateNotFoundException($templateId->value);
        }
    }

    /**
     * @param Collection<ServiceCategoryId> $categoryIds
     * @param AccessScope<ServiceCategoryId> $scope
     */
    private function assertCategoriesExist(Collection $categoryIds, AccessScope $scope): void
    {
        if (count($categoryIds) === 0) {
            return;
        }

        $missingIds = $scope->missing(
            $categoryIds,
            array_keys($this->serviceCategoryRepository->findNamesByIds($categoryIds)->toArray()),
        );

        if ($missingIds !== []) {
            throw new ServiceCategoryNotFoundException($missingIds);
        }
    }

    /**
     * @param AccessScope<ServiceSeverityId> $scope
     */
    private function assertSeverityExists(?ServiceSeverityId $severityId, AccessScope $scope): void
    {
        if (! $severityId instanceof ServiceSeverityId) {
            return;
        }

        $missingIds = $scope->missing(
            [$severityId],
            $this->serviceSeverityRepository->findNameById($severityId) instanceof ServiceSeverityName ? [$severityId->value] : [],
        );

        if ($missingIds !== []) {
            throw new ServiceSeverityNotFoundException($severityId->value);
        }
    }

    /**
     * @param Collection<ServiceGroupId> $serviceGroupIds
     * @param AccessScope<ServiceGroupId> $scope
     */
    private function assertServiceGroupsExist(Collection $serviceGroupIds, AccessScope $scope): void
    {
        if (count($serviceGroupIds) === 0) {
            return;
        }

        $missingIds = $scope->missing(
            $serviceGroupIds,
            array_keys($this->serviceGroupRepository->findNamesByIds($serviceGroupIds)->toArray()),
        );

        if ($missingIds !== []) {
            throw new ServiceGroupNotFoundException($missingIds);
        }
    }

    /**
     * Never ACL-scoped, as the trap selector.
     *
     * @param Collection<TrapId> $trapIds
     */
    private function assertTrapsExist(Collection $trapIds): void
    {
        if (count($trapIds) === 0) {
            return;
        }

        $missingIds = array_values(array_unique(array_diff(
            array_map(static fn (TrapId $id): int => $id->value, $trapIds->toArray()),
            array_keys($this->trapRepository->findByIds($trapIds)->toArray()),
        )));

        if ($missingIds !== []) {
            throw new TrapNotFoundException($missingIds);
        }
    }

    private function assertGraphTemplateExists(?GraphTemplateId $graphTemplateId): void
    {
        if (! $graphTemplateId instanceof GraphTemplateId) {
            return;
        }

        $names = $this->graphTemplateRepository->findNamesByIds(new Collection([$graphTemplateId], GraphTemplateId::class));
        if (! isset($names->toArray()[$graphTemplateId->value])) {
            throw new GraphTemplateNotFoundException($graphTemplateId->value);
        }
    }

    private function assertTimePeriodExists(?TimePeriodId $timePeriodId): void
    {
        if ($timePeriodId instanceof TimePeriodId && ! $this->timePeriodRepository->existsOne($timePeriodId)) {
            throw new TimePeriodNotFoundException($timePeriodId->value);
        }
    }

    private function assertIconExists(?MediaId $iconId): void
    {
        if ($iconId instanceof MediaId && ! $this->mediaRepository->existsOne($iconId)) {
            throw new MediaNotFoundException($iconId->value);
        }
    }

    /**
     * The submitted contacts and contact groups, before any clearing: each must exist and be in the actor's scope.
     */
    private function assertContactsExist(?ServiceNotifications $notifications, ActingUser $actor): void
    {
        if (! $notifications instanceof ServiceNotifications) {
            return;
        }

        if (count($notifications->contactIds) > 0) {
            $missingIds = $this->accessScopes->contacts($actor)->missing(
                $notifications->contactIds,
                array_keys($this->contactRepository->findNamesByIds($notifications->contactIds)->toArray()),
            );

            if ($missingIds !== []) {
                throw new NotificationContactNotFoundException($missingIds);
            }
        }

        if (count($notifications->contactGroupIds) > 0) {
            $missingIds = $this->accessScopes->contactGroups($actor)->missing(
                $notifications->contactGroupIds,
                array_keys($this->contactGroupRepository->findNamesByIds($notifications->contactGroupIds)->toArray()),
            );

            if ($missingIds !== []) {
                throw new ContactGroupNotFoundException($missingIds);
            }
        }
    }

    /**
     * A command of another type is refused as one that does not exist.
     *
     * @throws CommandNotFoundException
     */
    private function findCheckCommand(?CommandId $commandId): ?Command
    {
        if (! $commandId instanceof CommandId) {
            return null;
        }

        $command = $this->commandRepository->getById($commandId);
        if ($command->type !== CommandTypeEnum::Check) {
            throw new CommandNotFoundException(['id' => $commandId->value], 'This command does not exist or is not a check command.');
        }

        return $command;
    }

    /**
     * A service needs a check command, set on it or provided by its template chain; its event handler arguments
     * need an event handler, set on it or provided by its template chain.
     */
    private function assertCommandsAreProvided(UpdateServiceCommand $command, ServiceCheckOptions $checkOptions): void
    {
        $line = $command->templateId instanceof ServiceTemplateId
            ? $this->serviceTemplateRepository->findInheritanceLine($command->templateId)->toArray()
            : [];

        $inheritsCheckCommand = array_any($line, static fn (ServiceTemplate $template): bool => $template->checkCommandId instanceof CommandId);
        if (! $checkOptions->checkCommandId instanceof CommandId && ! $inheritsCheckCommand) {
            throw new ServiceCheckCommandMissingException($command->templateId?->value);
        }

        $inheritsEventHandler = array_any($line, static fn (ServiceTemplate $template): bool => $template->eventHandlerCommandId instanceof CommandId);
        if (
            $command->dataProcessing->eventHandlerArgs !== []
            && ! $command->dataProcessing->eventHandlerCommandId instanceof CommandId
            && ! $inheritsEventHandler
        ) {
            throw new EventHandlerArgumentsRequireAHandlerException();
        }
    }

    private function assertExtendedInformationsFitTheForm(?ServiceExtendedInformations $informations): void
    {
        $fields = $informations?->exceedsFormBounds() ?? [];
        if ($fields !== []) {
            throw new ServiceExtendedInformationsTooLongException($fields);
        }
    }

    /**
     * A service notifying the contacts of its host only has no contact of its own: the ones the actor can see are
     * cleared, those they cannot see stay linked.
     */
    private function withHostContactsOnlyIfAsked(ServiceNotifications $notifications, ActingUser $actor): ServiceNotifications
    {
        if (! $notifications->useOnlyContactsFromHost) {
            return $notifications;
        }

        return $notifications->withHostContactsOnly(
            $this->accessScopes->contacts($actor),
            $this->accessScopes->contactGroups($actor),
        );
    }
}
