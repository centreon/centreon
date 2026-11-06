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
use App\MonitoringConfiguration\Domain\Aggregate\GraphTemplate\GraphTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostState;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
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
use App\MonitoringConfiguration\Domain\Event\ServiceCreated;
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
use App\Security\Domain\Aggregate\ActingUser;
use App\Security\Domain\Service\AccessScopeResolver;
use App\Shared\Application\Command\AsCommandHandler;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Event\EventBus;
use App\Shared\Domain\VaultInterface;

/**
 * Creates a real service directly linked to one host. Every reference and form rule the input validates is checked
 * again here, as the authority (the state may change between the validation and the write); the Cloud platform
 * rules stay the input's, as for hosts. The order is: the references,
 * then the rules depending on several of them (license of the template, check command and event handler of the
 * template chain), then the form bounds, then the name, then the own macros. References come before the name so
 * that an actor whose access is restricted cannot learn that a name exists on a host they cannot use. Every rule
 * is checked before the password macros are written to the vault, itself before the service is saved: a refused
 * request leaves no secret behind, and a vault failure saves nothing.
 *
 * A reference that no longer exists is reported with the input field as criterion, so the client gets the same
 * answer as from the input validation; a nested reference (time period, icon, command, contact, contact group)
 * answers not found, as on hosts.
 */
#[AsCommandHandler]
final readonly class CreateServiceCommandHandler
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
    ) {
    }

    /**
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
    public function __invoke(CreateServiceCommand $command): Service
    {
        // Within the actor's host scope, and only its activation and poller: the service is written after that
        // poller is locked, and the poller is flagged only while the host is enabled. The rest of the host is not
        // loaded, so none of its other stored data can make the creation fail.
        $host = $this->hostRepository->findState($command->hostId, $command->actor);
        if (! $host instanceof HostState) {
            throw new HostNotFoundException([$command->hostId->value], 'hostId');
        }

        $this->assertTemplateExists($command->templateId);
        $this->templateLicenseGuard->assertLicensed($command->templateId);
        $this->assertCategoriesExist($command->categoryIds, $command->actor);
        $this->assertSeverityExists($command->severityId, $command->actor);
        $this->assertServiceGroupsExist($command->serviceGroupIds, $command->actor);
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
        $this->assertExtendedInformationsFitTheForm($command->extendedInformations);

        if ($this->repository->isNameUsedOnHost($command->name, $command->hostId)) {
            throw new ServiceAlreadyExistsException(['name' => $command->name->value]);
        }

        $macroChanges = array_values($command->macroChanges->toArray());
        $this->assertSubmittedMacrosAreAllowed($macroChanges);
        // What the service inherits from the submitted template and its effective check command: the one set on the
        // service, else the first one of the template chain.
        $macros = $this->macroChangesResolver->resolve(
            $macroChanges,
            [],
            $this->inheritedMacrosResolver->resolve($command->templateId, $command->checkCommandId),
        );
        // The vault is written last, once every rule passed, and before the service is saved.
        $checkOptions = new ServiceCheckOptions($command->checkCommandId, $command->checkCommandArgs, $this->vaultPasswords($macros));

        $service = new Service(
            id: null,
            name: $command->name,
            hostId: $command->hostId,
            hostActivated: $host->activated,
            hostPollerId: $host->pollerId,
            activated: true,
            templateId: $command->templateId,
            checkOptions: $checkOptions,
            schedulingOptions: $command->schedulingOptions,
            notifications: $this->additiveInheritanceModeApplier->applyToService(
                $this->withHostContactsOnlyIfAsked($command->notifications, $command->actor),
            ),
            // A Centreon Monitoring Agent command set on the service pushes its results: its freshness is always
            // checked. One inherited from the template does not count, as legacy.
            dataProcessing: $checkCommand?->isCentreonMonitoringAgent() === true
                ? $command->dataProcessing->withCentreonMonitoringAgentFreshness()
                : $command->dataProcessing,
            extendedInformations: $command->extendedInformations,
            graphTemplateId: $command->graphTemplateId,
            severityId: $command->severityId,
            serviceGroupIds: $command->serviceGroupIds,
            categoryIds: $command->categoryIds,
            trapIds: $command->trapIds,
        );

        $this->repository->add($service);

        $this->eventBus->fire(new ServiceCreated($service, $command->actor->userId->value));

        return $service;
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
     * A failure there means the service itself was not saved, reported as an upstream failure (HTTP 502) rather
     * than an internal error.
     *
     * @param list<ServiceMacro> $macros
     *
     * @throws VaultWriteFailedException
     *
     * @return list<ServiceMacro>
     */
    private function vaultPasswords(array $macros): array
    {
        try {
            return $this->macroSecretsSynchronizer->synchronize($macros, [], null);
        } catch (\Throwable $exception) {
            throw VaultWriteFailedException::forService(null, $exception);
        }
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
     */
    private function assertCategoriesExist(Collection $categoryIds, ActingUser $actor): void
    {
        if (count($categoryIds) === 0) {
            return;
        }

        $missingIds = $this->accessScopes->serviceCategories($actor)->missing(
            $categoryIds,
            array_keys($this->serviceCategoryRepository->findNamesByIds($categoryIds)->toArray()),
        );

        if ($missingIds !== []) {
            throw new ServiceCategoryNotFoundException($missingIds);
        }
    }

    private function assertSeverityExists(?ServiceSeverityId $severityId, ActingUser $actor): void
    {
        if (! $severityId instanceof ServiceSeverityId) {
            return;
        }

        $missingIds = $this->accessScopes->serviceSeverities($actor)->missing(
            [$severityId],
            $this->serviceSeverityRepository->findNameById($severityId) instanceof ServiceSeverityName ? [$severityId->value] : [],
        );

        if ($missingIds !== []) {
            throw new ServiceSeverityNotFoundException($severityId->value);
        }
    }

    /**
     * @param Collection<ServiceGroupId> $serviceGroupIds
     */
    private function assertServiceGroupsExist(Collection $serviceGroupIds, ActingUser $actor): void
    {
        if (count($serviceGroupIds) === 0) {
            return;
        }

        $missingIds = $this->accessScopes->serviceGroups($actor)->missing(
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
     * Imported LDAP groups are contact groups like the others.
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
     * need an event handler, set on it or provided by its template chain. The first value found walking up the
     * chain wins.
     */
    private function assertCommandsAreProvided(CreateServiceCommand $command, ServiceCheckOptions $checkOptions): void
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
     * A service notifying the contacts of its host only has no contact of its own: the ones the actor can see
     * are cleared, those they cannot see stay linked.
     */
    private function withHostContactsOnlyIfAsked(?ServiceNotifications $notifications, ActingUser $actor): ?ServiceNotifications
    {
        if (! $notifications instanceof ServiceNotifications || ! $notifications->useOnlyContactsFromHost) {
            return $notifications;
        }

        return $notifications->withHostContactsOnly(
            $this->accessScopes->contacts($actor),
            $this->accessScopes->contactGroups($actor),
        );
    }
}
