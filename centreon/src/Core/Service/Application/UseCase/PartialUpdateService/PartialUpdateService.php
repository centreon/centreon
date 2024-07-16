<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\Service\Application\UseCase\PartialUpdateService;

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Option\OptionService;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\CommandMacro\Application\Repository\ReadCommandMacroRepositoryInterface;
use Core\CommandMacro\Domain\Model\CommandMacro;
use Core\CommandMacro\Domain\Model\CommandMacroType;
use Core\Common\Application\Repository\ReadVaultRepositoryInterface;
use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
use Core\Common\Application\Type\NoValue;
use Core\Common\Application\UseCase\VaultTrait;
use Core\Common\Infrastructure\Repository\AbstractVaultRepository;
use Core\Domain\Common\GeoCoords;
use Core\Macro\Application\Repository\ReadServiceMacroRepositoryInterface;
use Core\Macro\Application\Repository\WriteServiceMacroRepositoryInterface;
use Core\Macro\Domain\Model\Macro;
use Core\Macro\Domain\Model\MacroDifference;
use Core\Macro\Domain\Model\MacroManager;
use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use Core\MonitoringServer\Application\Repository\WriteMonitoringServerRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\Service\Application\Exception\ServiceException;
use Core\Service\Application\Model\NotificationTypeConverter;
use Core\Service\Application\Model\YesNoDefaultConverter;
use Core\Service\Application\Repository\ReadServiceRepositoryInterface;
use Core\Service\Application\Repository\WriteServiceRepositoryInterface;
use Core\Service\Domain\Model\Service;
use Core\Service\Domain\Model\ServiceInheritance;
use Core\ServiceCategory\Application\Repository\ReadServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Application\Repository\WriteServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Domain\Model\ServiceCategory;
use Core\ServiceGroup\Application\Repository\ReadServiceGroupRepositoryInterface;
use Core\ServiceGroup\Application\Repository\WriteServiceGroupRepositoryInterface;
use Core\ServiceGroup\Domain\Model\ServiceGroupRelation;
use Utility\Difference\BasicDifference;

final class PartialUpdateService
{
    use LoggerTrait,VaultTrait;
    private const VERTICAL_INHERITANCE_MODE = 1;

    /** @var AccessGroup[] */
    private array $accessGroups;

    public function __construct(
        private readonly ReadMonitoringServerRepositoryInterface $readMonitoringServerRepository,
        private readonly WriteMonitoringServerRepositoryInterface $writeMonitoringServerRepository,
        private readonly ReadServiceRepositoryInterface $readServiceRepository,
        private readonly WriteServiceRepositoryInterface $writeServiceRepository,
        private readonly ReadServiceMacroRepositoryInterface $readServiceMacroRepository,
        private readonly ReadCommandMacroRepositoryInterface $readCommandMacroRepository,
        private readonly WriteServiceMacroRepositoryInterface $writeServiceMacroRepository,
        private readonly ReadServiceCategoryRepositoryInterface $readServiceCategoryRepository,
        private readonly WriteServiceCategoryRepositoryInterface $writeServiceCategoryRepository,
        private readonly ReadServiceGroupRepositoryInterface $readServiceGroupRepository,
        private readonly WriteServiceGroupRepositoryInterface $writeServiceGroupRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly DataStorageEngineInterface $dataStorageEngine,
        private readonly PartialUpdateServiceValidation $validation,
        private readonly OptionService $optionService,
        private readonly ContactInterface $user,
        private readonly bool $isCloudPlatform,
        private readonly WriteVaultRepositoryInterface $writeVaultRepository,
        private readonly ReadVaultRepositoryInterface $readVaultRepository,
    ) {
        $this->writeVaultRepository->setCustomPath(AbstractVaultRepository::SERVICE_VAULT_PATH);
    }

    /**
     * @param PartialUpdateServiceRequest $request
     * @param PresenterInterface $presenter
     * @param int $serviceId
     */
    public function __invoke(PartialUpdateServiceRequest $request, PresenterInterface $presenter, int $serviceId): void
    {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_SERVICES_WRITE)) {
                $this->error(
                    "User doesn't have sufficient rights to edit a service",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(ServiceException::editNotAllowed())
                );

                return;
            }

            if (! $this->user->isAdmin()) {
                $this->accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
                $this->validation->accessGroups = $this->accessGroups;
            }

            if (
                (
                    ! $this->user->isAdmin()
                    && ! $this->readServiceRepository->existsByAccessGroups($serviceId, $this->accessGroups)
                )
                || ! ($service = $this->readServiceRepository->findById($serviceId))
            ) {
                $this->error(
                    'Service not found',
                    ['service_id' => $serviceId]
                );
                $presenter->setResponseStatus(new NotFoundResponse('Service'));

                return;
            }

            $this->updatePropertiesInTransaction($request, $service);

            $presenter->setResponseStatus(new NoContentResponse());
        } catch (ServiceException $ex) {
            $presenter->setResponseStatus(
                match ($ex->getCode()) {
                    ServiceException::CODE_CONFLICT => new ConflictResponse($ex),
                    default => new ErrorResponse($ex),
                }
            );
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (AssertionFailedException $ex) {
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(new ErrorResponse(ServiceException::errorWhileEditing()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    private function updatePropertiesInTransaction(PartialUpdateServiceRequest $request, Service $service): void
    {
        $this->dataStorageEngine->startTransaction();
        try {

            if ($this->writeVaultRepository->isVaultConfigured()) {
                $this->retrieveServiceUuidFromVault($service->getId());
            }

            $previousMonitoringServer = $this->readMonitoringServerRepository->findByHost($service->getHostId());
            $this->updateService($request, $service);
            $this->updateCategories($request, $service);
            // Groups MUST be updated after the service as they are dependent on host ID.
            $this->updateGroups($request, $service);
            // Macros PUST be updated after the service as they are dependent on the template ID.
            $this->updateMacros($request, $service);

            $newMonitoringServer = $this->readMonitoringServerRepository->findByHost($service->getHostId());
            if (null !== $newMonitoringServer) {
                $this->writeMonitoringServerRepository->notifyConfigurationChange($newMonitoringServer->getId());
            }
            if (null !== $previousMonitoringServer) {
                // Host change implies a possible monitoring server change, notify previous monitoring server of configuration changes.
                 $this->writeMonitoringServerRepository->notifyConfigurationChange($previousMonitoringServer->getId());
            }

            $this->dataStorageEngine->commitTransaction();
        } catch (\Throwable $ex) {
            $this->error("Rollback of 'PartialUpdateService' transaction", ['trace' => $ex->getTraceAsString()]);
            $this->dataStorageEngine->rollbackTransaction();

            throw $ex;
        }
    }

    /**
     * @param PartialUpdateServiceRequest $dto
     * @param Service $service
     *
     * @throws \Throwable|AssertionFailedException|ServiceException
     */
    private function updateService(PartialUpdateServiceRequest $dto, Service $service): void
    {
        $this->info('PartialUpdateService: update service', ['service_id' => $service->getId()]);

        $inheritanceMode = $this->optionService->findSelectedOptions(['inheritance_mode']);
        $inheritanceMode = isset($inheritanceMode[0])
            ? (int) $inheritanceMode[0]->getValue()
            : null;

        if (! $dto->hostId instanceOf NoValue) {
            $this->validation->assertIsValidHost($dto->hostId);
            $service->setHostId($dto->hostId);
        }

        // Must be called AFTER host validation
        if (! $dto->name instanceOf NoValue) {
            $this->validation->assertIsValidName($dto->name, $service);
            $service->setName($dto->name);
        }

        if (! $dto->template instanceOf NoValue) {
            $this->validation->assertIsValidTemplate($dto->template);
            $service->setServiceTemplateParentId($dto->template);
        }

        if (! $dto->activeChecks instanceOf NoValue) {
            $service->setActiveChecks(YesNoDefaultConverter::fromInt($dto->activeChecks));
        }
        if (! $dto->passiveCheck instanceOf NoValue) {
            $service->setPassiveCheck(YesNoDefaultConverter::fromInt($dto->passiveCheck));
        }
        if (! $dto->volatility instanceOf NoValue) {
            $service->setVolatility(YesNoDefaultConverter::fromInt($dto->volatility));
        }
        if (! $dto->checkFreshness instanceOf NoValue) {
            $service->setCheckFreshness(YesNoDefaultConverter::fromInt($dto->checkFreshness));
        }
        if (! $dto->eventHandlerEnabled instanceOf NoValue) {
            $service->setEventHandlerEnabled(YesNoDefaultConverter::fromInt($dto->eventHandlerEnabled));
        }
        if (! $dto->flapDetectionEnabled instanceOf NoValue) {
            $service->setFlapDetectionEnabled(YesNoDefaultConverter::fromInt($dto->flapDetectionEnabled));
        }
        if (! $dto->notificationsEnabled instanceOf NoValue) {
            $service->setNotificationsEnabled(YesNoDefaultConverter::fromInt($dto->notificationsEnabled));
        }

        if (! $dto->comment instanceOf NoValue) {
            $service->setComment($dto->comment);
        }
        if (! $dto->note instanceOf NoValue) {
            $service->setNote($dto->note);
        }
        if (! $dto->noteUrl instanceOf NoValue) {
            $service->setNoteUrl($dto->noteUrl);
        }
        if (! $dto->actionUrl instanceOf NoValue) {
            $service->setActionUrl($dto->actionUrl);
        }
        if (! $dto->iconAlternativeText instanceOf NoValue) {
            $service->setIconAlternativeText($dto->iconAlternativeText);
        }

        if (! $dto->maxCheckAttempts instanceOf NoValue) {
            $service->setMaxCheckAttempts($dto->maxCheckAttempts);
        }
        if (! $dto->normalCheckInterval instanceOf NoValue) {
            $service->setNormalCheckInterval($dto->normalCheckInterval);
        }
        if (! $dto->retryCheckInterval instanceOf NoValue) {
            $service->setRetryCheckInterval($dto->retryCheckInterval);
        }
        if (! $dto->freshnessThreshold instanceOf NoValue) {
            $service->setFreshnessThreshold($dto->freshnessThreshold);
        }
        if (! $dto->lowFlapThreshold instanceOf NoValue) {
            $service->setLowFlapThreshold($dto->lowFlapThreshold);
        }
        if (! $dto->highFlapThreshold instanceOf NoValue) {
            $service->setHighFlapThreshold($dto->highFlapThreshold);
        }
        if (! $dto->notificationInterval instanceOf NoValue) {
            $service->setNotificationInterval($dto->notificationInterval);
        }
        if (! $dto->recoveryNotificationDelay instanceOf NoValue) {
            $service->setRecoveryNotificationDelay($dto->recoveryNotificationDelay);
        }
        if (! $dto->firstNotificationDelay instanceOf NoValue) {
            $service->setFirstNotificationDelay($dto->firstNotificationDelay);
        }
        if (! $dto->acknowledgementTimeout instanceOf NoValue) {
            $service->setAcknowledgementTimeout($dto->acknowledgementTimeout);
        }

        // Must be called AFTER template validation
        if (! $dto->commandId instanceOf NoValue) {
            if ($this->isCloudPlatform === false) {
                // No assertion on the check command for Saas platform as it will be inherited from the service template.
                $this->validation->assertIsValidCommand($dto->commandId, $service->getServiceTemplateParentId());
            }
            $service->setCommandId($dto->commandId);
        }
        if (! $dto->graphTemplateId instanceOf NoValue) {
            $this->validation->assertIsValidGraphTemplate($dto->graphTemplateId);
            $service->setGraphTemplateId($dto->graphTemplateId);
        }
        if (! $dto->eventHandlerId instanceOf NoValue) {
            $this->validation->assertIsValidEventHandler($dto->eventHandlerId);
            $service->setEventHandlerId($dto->eventHandlerId);
        }
        if (! $dto->notificationTimePeriodId instanceOf NoValue) {
            $this->validation->assertIsValidTimePeriod($dto->notificationTimePeriodId, 'notification_timeperiod_id');
            $service->setNotificationTimePeriodId($dto->notificationTimePeriodId);
        }
        if (! $dto->checkTimePeriodId instanceOf NoValue) {
            $this->validation->assertIsValidTimePeriod($dto->checkTimePeriodId, 'check_timeperiod_id');
            $service->setCheckTimePeriodId($dto->checkTimePeriodId);
        }
        if (! $dto->iconId instanceOf NoValue) {
            $this->validation->assertIsValidIcon($dto->iconId);
            $service->setIconId($dto->iconId);
        }
        if (! $dto->severityId instanceOf NoValue) {
            $this->validation->assertIsValidSeverity($dto->severityId);
            $service->setSeverityId($dto->severityId);
        }

        if (! $dto->isActivated instanceOf NoValue) {
            $service->setActivated($dto->isActivated);
        }

        if (! $dto->geoCoords instanceOf NoValue) {
            $service->setGeoCoords(
                $dto->geoCoords === '' || $dto->geoCoords === null
                    ? null
                    : GeoCoords::fromString($dto->geoCoords)
            );
        }
        if (! $dto->notificationTypes instanceOf NoValue) {
            $service->setNotificationTypes(
                $dto->notificationTypes === null
                    ? []
                    : NotificationTypeConverter::fromBits($dto->notificationTypes)
            );
        }

        if (! $dto->commandArguments instanceOf NoValue) {
            $service->setCommandArguments($dto->commandArguments);
        }
        if (! $dto->eventHandlerArguments instanceOf NoValue) {
            $service->setEventHandlerArguments($dto->eventHandlerArguments);
        }

        if (! $dto->isContactAdditiveInheritance instanceOf NoValue) {
            $service->setContactAdditiveInheritance(
                $inheritanceMode === self::VERTICAL_INHERITANCE_MODE ? $dto->isContactAdditiveInheritance : false
            );
        }
        if (! $dto->isContactGroupAdditiveInheritance instanceOf NoValue) {
            $service->setContactGroupAdditiveInheritance(
                $inheritanceMode === self::VERTICAL_INHERITANCE_MODE ? $dto->isContactGroupAdditiveInheritance : false
            );
        }

        $this->writeServiceRepository->update($service);
    }

    /**
     * @param PartialUpdateServiceRequest $dto
     * @param Service $service
     *
     * @throws \Throwable
     */
    private function updateCategories(PartialUpdateServiceRequest $dto, Service $service): void
    {
        $this->info(
            'PartialUpdateService: update categories',
            ['service_id' => $service->getId(), 'categories' => $dto->categories]
        );

        if ($dto->categories instanceOf NoValue) {
            $this->info('Categories not provided, nothing to update');

            return;
        }

        $categoryIds = array_unique($dto->categories);
        $this->validation->assertAreValidCategories($categoryIds);

        if ($this->user->isAdmin()) {
            $originalCategories = $this->readServiceCategoryRepository->findByService($service->getId());
        } else {
            $originalCategories = $this->readServiceCategoryRepository->findByServiceAndAccessGroups(
                $service->getId(),
                $this->accessGroups
            );
        }

        $originalCategoryIds = array_map(
            static fn(ServiceCategory $category): int => $category->getId(),
            $originalCategories
        );

        $categoryDiff = new BasicDifference($originalCategoryIds, $categoryIds);
        $addedCategories = $categoryDiff->getAdded();
        $removedCategories = $categoryDiff->getRemoved();

        $this->writeServiceCategoryRepository->linkToService($service->getId(), $addedCategories);
        $this->writeServiceCategoryRepository->unlinkFromService($service->getId(), $removedCategories);
    }

    /**
     * @param PartialUpdateServiceRequest $dto
     * @param Service $service
     *
     * @throws \Throwable
     */
    private function updateGroups(PartialUpdateServiceRequest $dto, Service $service): void
    {
        $this->info(
            'PartialUpdateService: update groups',
            ['service_id' => $service->getId(), 'groups' => $dto->groups]
        );

        if ($dto->groups instanceOf NoValue) {
            $this->info('Groups not provided, nothing to update');

            return;
        }

        $this->validation->assertAreValidGroups($dto->groups);

        if ($this->user->isAdmin()) {
            $originalGroups = $this->readServiceGroupRepository->findByService($service->getId());
        } else {
            $originalGroups = $this->readServiceGroupRepository->findByServiceAndAccessGroups(
                $service->getId(),
                $this->accessGroups
            );
        }
        $this->writeServiceGroupRepository->unlink(array_column($originalGroups, 'relation'));

        $groupRelations = [];
        foreach (array_unique($dto->groups) as $groupId) {
            $groupRelations[] = new ServiceGroupRelation(
                $groupId,
                $service->getId(),
                $service->getHostId()
            );
        }

        $this->writeServiceGroupRepository->link($groupRelations);
    }

    /**
     * @param PartialUpdateServiceRequest $dto
     * @param Service $service
     *
     * @throws \Throwable
     */
    private function updateMacros(PartialUpdateServiceRequest $dto, Service $service): void
    {
        $this->info(
            'PartialUpdateService: update macros',
            ['service_id' => $service->getId(), 'macros' => $dto->macros]
        );

        if ($dto->macros instanceof NoValue) {
            $this->info('Macros not provided, nothing to update');

            return;
        }

        /**
         * @var array<string,Macro> $directMacros
         * @var array<string,Macro> $inheritedMacros
         * @var array<string,CommandMacro> $commandMacros
         */
        [$directMacros, $inheritedMacros, $commandMacros] = $this->findOriginalMacros($service);

        $macros = [];
        foreach ($dto->macros as $data) {
            $macro = MacroFactory::create($data, $service->getId(), $directMacros, $inheritedMacros);
            $macros[$macro->getName()] = $macro;
        }

        $macrosDiff = new MacroDifference();
        $macrosDiff->compute($directMacros, $inheritedMacros, $commandMacros, $macros);

        MacroManager::setOrder($macrosDiff, $macros, $directMacros);

        foreach ($macrosDiff->removedMacros as $macro) {
            $this->updateMacroInVault($macro, 'DELETE');
            $this->writeServiceMacroRepository->delete($macro);
        }

        foreach ($macrosDiff->updatedMacros as $macro) {
            $macro = $this->updateMacroInVault($macro, 'INSERT');
            $this->writeServiceMacroRepository->update($macro);
        }

        foreach ($macrosDiff->addedMacros as $macro) {
            if ($macro->getDescription() === '') {
                $macro->setDescription(
                    isset($commandMacros[$macro->getName()])
                    ? $commandMacros[$macro->getName()]->getDescription()
                    : ''
                );
            }
            $macro = $this->updateMacroInVault($macro, 'INSERT');
            $this->writeServiceMacroRepository->add($macro);
        }

    }

    /**
     * Find macros of a service:
     *  - macros linked directly,
     *  - macros linked through template inheritance,
     *  - macros linked through command inheritance.
     *
     * @param Service $service
     *
     * @throws \Throwable
     *
     * @return array{
     *      array<string,Macro>,
     *      array<string,Macro>,
     *      array<string,CommandMacro>
     * }
     */
    private function findOriginalMacros(Service $service): array
    {
        $parentTemplates = $this->readServiceRepository->findParents($service->getId());
        $inheritanceLine = ServiceInheritance::createInheritanceLine($service->getId(), $parentTemplates);
        $existingMacros = $this->readServiceMacroRepository->findByServiceIds($service->getId(), ...$inheritanceLine);
        [$directMacros, $inheritedMacros] = Macro::resolveInheritance(
            $existingMacros,
            $inheritanceLine,
            $service->getId()
        );

        /** @var array<string,CommandMacro> $commandMacros */
        $commandMacros = [];
        if ($service->getCommandId() !== null) {
            $existingCommandMacros = $this->readCommandMacroRepository->findByCommandIdAndType(
                $service->getCommandId(),
                CommandMacroType::Service
            );

            $commandMacros = MacroManager::resolveInheritanceForCommandMacro($existingCommandMacros);
        }

        return [
            $this->writeVaultRepository->isVaultConfigured()
                ? $this->retrieveMacrosVaultValues($directMacros)
                : $directMacros,
            $this->writeVaultRepository->isVaultConfigured()
                ? $this->retrieveMacrosVaultValues($inheritedMacros)
                : $inheritedMacros,
            $commandMacros,
        ];
    }

    /**
     * @param int $serviceId
     *
     * @throws \Throwable
     */
    private function retrieveServiceUuidFromVault(int $serviceId): void
    {
        $macros = $this->readServiceMacroRepository->findByServiceIds($serviceId);
        foreach ($macros as $macro) {
            if (
                $macro->isPassword() === true
                && null !== ($this->uuid = $this->getUuidFromPath($macro->getValue()))
            ) {
                break;
            }
        }
    }

    /**
     * Upsert or delete macro for vault storage and return macro with updated value (aka vaultPath).
     *
     * @param Macro $macro
     * @param string $action
     *
     * @throws \Throwable
     *
     * @return Macro
     */
    private function updateMacroInVault(Macro $macro, string $action): Macro
    {
        if ($this->writeVaultRepository->isVaultConfigured() && $macro->isPassword() === true) {
            $macroPrefixName = '_SERVICE' . $macro->getName();
            $vaultPaths = $this->writeVaultRepository->upsert(
                $this->uuid ?? null,
                $action === 'INSERT' ? [$macroPrefixName => $macro->getValue()] : [],
                $action === 'DELETE' ? [$macroPrefixName => $macro->getValue()] : [],
            );
            $vaultPath = $vaultPaths[$macroPrefixName];
            $this->uuid ??= $this->getUuidFromPath($vaultPath);

            $inVaultMacro = new Macro($macro->getOwnerId(), $macro->getName(), $vaultPath);
            $inVaultMacro->setDescription($macro->getDescription());
            $inVaultMacro->setIsPassword($macro->isPassword());
            $inVaultMacro->setOrder($macro->getOrder());

            return $inVaultMacro;
        }

        return $macro;
    }

    /**
     * @param array<string,Macro> $macros
     *
     * @throws \Throwable
     *
     * @return array<string,Macro>
     */
    private function retrieveMacrosVaultValues(array $macros): array
    {
        $updatedMacros = [];
        foreach ($macros as $key => $macro) {
            if (false === $macro->isPassword()) {
                $updatedMacros[$key] = $macro;
                continue;
            }

            $vaultData = $this->readVaultRepository->findFromPath($macro->getValue());
            $vaultKey = '_SERVICE' . $macro->getName();
            if (isset($vaultData[$vaultKey])) {
                $inVaultMacro = new Macro($macro->getOwnerId(),$macro->getName(), $vaultData[$vaultKey]);
                $inVaultMacro->setDescription($macro->getDescription());
                $inVaultMacro->setIsPassword($macro->isPassword());
                $inVaultMacro->setOrder($macro->getOrder());

                $updatedMacros[$key] = $inVaultMacro;
            }
        }

        return $updatedMacros;
    }
}
