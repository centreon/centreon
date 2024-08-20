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

namespace Core\ServiceTemplate\Application\UseCase\PartialUpdateServiceTemplate;

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Option\OptionService;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
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
use Core\HostTemplate\Application\Exception\HostTemplateException;
use Core\Macro\Application\Repository\ReadServiceMacroRepositoryInterface;
use Core\Macro\Application\Repository\WriteServiceMacroRepositoryInterface;
use Core\Macro\Domain\Model\Macro;
use Core\Macro\Domain\Model\MacroDifference;
use Core\Macro\Domain\Model\MacroManager;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\ServiceCategory\Application\Repository\ReadServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Application\Repository\WriteServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Domain\Model\ServiceCategory;
use Core\ServiceGroup\Application\Repository\ReadServiceGroupRepositoryInterface;
use Core\ServiceGroup\Application\Repository\WriteServiceGroupRepositoryInterface;
use Core\ServiceGroup\Domain\Model\ServiceGroupRelation;
use Core\ServiceTemplate\Application\Exception\ServiceTemplateException;
use Core\ServiceTemplate\Application\Model\NotificationTypeConverter;
use Core\ServiceTemplate\Application\Model\YesNoDefaultConverter;
use Core\ServiceTemplate\Application\Repository\ReadServiceTemplateRepositoryInterface;
use Core\ServiceTemplate\Application\Repository\WriteServiceTemplateRepositoryInterface;
use Core\ServiceTemplate\Domain\Model\ServiceTemplate;
use Core\ServiceTemplate\Domain\Model\ServiceTemplateInheritance;
use Utility\Difference\BasicDifference;

final class PartialUpdateServiceTemplate
{
    use LoggerTrait,VaultTrait;

    /** @var AccessGroup[] */
    private array $accessGroups = [];

    public function __construct(
        private readonly WriteServiceTemplateRepositoryInterface $writeRepository,
        private readonly ReadServiceCategoryRepositoryInterface $readServiceCategoryRepository,
        private readonly WriteServiceCategoryRepositoryInterface $writeServiceCategoryRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ReadServiceTemplateRepositoryInterface $readServiceTemplateRepository,
        private readonly ReadServiceMacroRepositoryInterface $readServiceMacroRepository,
        private readonly WriteServiceMacroRepositoryInterface $writeServiceMacroRepository,
        private readonly ReadCommandMacroRepositoryInterface $readCommandMacroRepository,
        private readonly WriteServiceGroupRepositoryInterface $writeServiceGroupRepository,
        private readonly ReadServiceGroupRepositoryInterface $readServiceGroupRepository,
        private readonly ParametersValidation $validation,
        private readonly ContactInterface $user,
        private readonly DataStorageEngineInterface $storageEngine,
        private readonly OptionService $optionService,
        private readonly WriteVaultRepositoryInterface $writeVaultRepository,
        private readonly ReadVaultRepositoryInterface $readVaultRepository,
    ) {
        $this->writeVaultRepository->setCustomPath(AbstractVaultRepository::SERVICE_VAULT_PATH);
    }

    public function __invoke(
        PartialUpdateServiceTemplateRequest $request,
        PresenterInterface $presenter
    ): void {
        try {
            $this->info('Update the service template', ['request' => $request]);
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE)) {
                $this->error(
                    "User doesn't have sufficient rights to update a service template",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(ServiceTemplateException::updateNotAllowed())
                );

                return;
            }

            if (! $this->user->isAdmin()) {
                $this->accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
                $serviceTemplate = $this->readServiceTemplateRepository->findByIdAndAccessGroups(
                    $request->id,
                    $this->accessGroups
                );
            } else {
                $serviceTemplate = $this->readServiceTemplateRepository->findById($request->id);
            }

            if ($serviceTemplate === null) {
                $this->error('Service template not found', ['service_template_id' => $request->id]);
                $presenter->setResponseStatus(new NotFoundResponse('Service template'));

                return;
            }

            $this->updatePropertiesInTransaction($request, $serviceTemplate);

            $presenter->setResponseStatus(new NoContentResponse());
        } catch (ServiceTemplateException $ex) {
            $presenter->setResponseStatus(
                match ($ex->getCode()) {
                    ServiceTemplateException::CODE_CONFLICT => new ConflictResponse($ex),
                    default => new ErrorResponse($ex),
                }
            );
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(new ErrorResponse(ServiceTemplateException::errorWhileUpdating()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @param PartialUpdateServiceTemplateRequest $request
     *
     * @throws \Throwable
     */
    private function linkServiceTemplateToHostTemplates(PartialUpdateServiceTemplateRequest $request): void
    {
        if (! is_array($request->hostTemplates)) {
            return;
        }

        $this->validation->assertHostTemplateIds($request->hostTemplates);

        $this->info('Unlink existing host templates from service template', [
            'service_template_id' => $request->id,
            'host_templates' => $request->hostTemplates,
        ]);
        $this->writeRepository->unlinkHosts($request->id);
        $this->info('Link host templates to service template', [
            'service_template_id' => $request->id,
            'host_templates' => $request->hostTemplates,
        ]);
        $this->writeRepository->linkToHosts($request->id, $request->hostTemplates);
    }

    /**
     * @param PartialUpdateServiceTemplateRequest $request
     *
     * @throws \Throwable
     */
    private function linkServiceTemplateToServiceCategories(PartialUpdateServiceTemplateRequest $request): void
    {
        if (! is_array($request->serviceCategories)) {
            return;
        }

        $this->validation->assertServiceCategories($request->serviceCategories, $this->user, $this->accessGroups);

        if ($this->user->isAdmin()) {
            $originalServiceCategories = $this->readServiceCategoryRepository->findByService($request->id);
        } else {
            $originalServiceCategories = $this->readServiceCategoryRepository->findByServiceAndAccessGroups(
                $request->id,
                $this->accessGroups
            );
        }
        $this->info('Original service categories found', ['service_categories' => $originalServiceCategories]);

        $originalServiceCategoriesIds = array_map(
            static fn(ServiceCategory $serviceCategory): int => $serviceCategory->getId(),
            $originalServiceCategories
        );

        $serviceCategoryDifferences = new BasicDifference(
            $originalServiceCategoriesIds,
            array_unique($request->serviceCategories)
        );

        $serviceCategoriesToAdd = $serviceCategoryDifferences->getAdded();
        $serviceCategoriesToRemove = $serviceCategoryDifferences->getRemoved();

        $this->info(
            'Unlink existing service categories from service',
            ['service_categories' => $serviceCategoriesToRemove]
        );
        $this->writeServiceCategoryRepository->unlinkFromService($request->id, $serviceCategoriesToRemove);

        $this->info(
            'Link existing service categories to service',
            ['service_categories' => $serviceCategoriesToAdd]
        );
        $this->writeServiceCategoryRepository->linkToService($request->id, $serviceCategoriesToAdd);
    }

    /**
     * @param PartialUpdateServiceTemplateRequest $request
     *
     * @throws \Throwable
     */
    private function linkServiceTemplateToServiceGroups(PartialUpdateServiceTemplateRequest $request): void
    {
        if (! is_array($request->serviceGroups)) {
            return;
        }

        $this->info(
            'Link existing service groups to service template',
            ['service_template_id' => $request->id, 'service_groups' => $request->serviceGroups]
        );

        $this->validation->assertServiceGroups($request->serviceGroups, $request->id, $this->user, $this->accessGroups);

        $serviceGroupRelations = [];
        $serviceGroupDtos = $this->removeDuplicatesServiceGroups($request->serviceGroups);
        foreach ($serviceGroupDtos as $serviceGroup) {
            $serviceGroupRelations[] = new ServiceGroupRelation(
                serviceGroupId: $serviceGroup->serviceGroupId,
                serviceId: $request->id,
                hostId: $serviceGroup->hostTemplateId
            );
        }

        if ($this->user->isAdmin()) {
            $originalGroups = $this->readServiceGroupRepository->findByService($request->id);
        } else {
            $originalGroups = $this->readServiceGroupRepository->findByServiceAndAccessGroups(
                $request->id,
                $this->accessGroups
            );
        }

        $this->info('Delete existing service groups relations');
        $this->writeServiceGroupRepository->unlink(array_column($originalGroups, 'relation'));

        $this->info('Create new service groups relations');
        $this->writeServiceGroupRepository->link($serviceGroupRelations);
    }

    /**
     * @param PartialUpdateServiceTemplateRequest $request
     * @param ServiceTemplate $serviceTemplate
     *
     * @throws ServiceTemplateException
     * @throws \Throwable
     */
    private function updatePropertiesInTransaction(
        PartialUpdateServiceTemplateRequest $request,
        ServiceTemplate $serviceTemplate
    ): void {
        $this->debug('Start transaction');
        $this->storageEngine->startTransaction();
        try {
            if ($this->writeVaultRepository->isVaultConfigured()) {
                $this->retrieveServiceUuidFromVault($serviceTemplate->getId());
            }

            $this->updateServiceTemplate($serviceTemplate, $request);
            $this->linkServiceTemplateToHostTemplates($request);
            $this->linkServiceTemplateToServiceCategories($request);
            $this->linkServiceTemplateToServiceGroups($request);
            $this->updateMacros($request, $serviceTemplate);

            $this->debug('Commit transaction');
            $this->storageEngine->commitTransaction();
        } catch (\Throwable $ex) {
            $this->debug('Rollback transaction');
            $this->storageEngine->rollbackTransaction();

            throw $ex;
        }
    }

    /**
     * @param PartialUpdateServiceTemplateRequest $request
     * @param ServiceTemplate $serviceTemplate
     *
     * @throws AssertionFailedException
     * @throws \Throwable
     */
    private function updateMacros(PartialUpdateServiceTemplateRequest $request, ServiceTemplate $serviceTemplate): void
    {
        if (! is_array($request->macros)) {
            return;
        }

        $this->info('Add macros', ['service_template_id' => $request->id]);

        /**
         * @var array<string,Macro> $inheritedMacros
         * @var array<string,CommandMacro> $commandMacros
         */
        [$directMacros, $inheritedMacros, $commandMacros] = $this->findAllMacros(
            $request->id,
            $serviceTemplate->getCommandId()
        );

        $macros = [];
        foreach ($request->macros as $macro) {
            $macro = MacroFactory::create($macro, $request->id, $directMacros, $inheritedMacros);
            $macros[$macro->getName()] = $macro;
        }

        $macrosDiff = new MacroDifference();
        $macrosDiff->compute($directMacros, $inheritedMacros, $commandMacros, $macros);

        MacroManager::setOrder($macrosDiff, $macros, []);

        foreach ($macrosDiff->removedMacros as $macro) {
            $this->info('Delete the macro ' . $macro->getName());
            $this->updateMacroInVault($macro, 'DELETE');
            $this->writeServiceMacroRepository->delete($macro);
        }

        foreach ($macrosDiff->updatedMacros as $macro) {
            $this->info('Update the macro ' . $macro->getName());
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
            $this->info('Add the macro ' . $macro->getName());

            $macro = $this->updateMacroInVault($macro, 'INSERT');
            $this->writeServiceMacroRepository->add($macro);
        }
    }

    /**
     * @param int $serviceTemplateId
     * @param int|null $checkCommandId
     *
     * @throws \Throwable
     *
     * @return array{
     *      array<string,Macro>,
     *      array<string,Macro>,
     *      array<string,CommandMacro>
     * }
     */
    private function findAllMacros(int $serviceTemplateId, ?int $checkCommandId): array
    {
        $serviceTemplateInheritances = $this->readServiceTemplateRepository->findParents($serviceTemplateId);
        $inheritanceLine = ServiceTemplateInheritance::createInheritanceLine(
            $serviceTemplateId,
            $serviceTemplateInheritances
        );
        $existingMacros = $this->readServiceMacroRepository->findByServiceIds($serviceTemplateId, ...$inheritanceLine);

        [$directMacros, $inheritedMacros] = Macro::resolveInheritance(
            $existingMacros,
            $inheritanceLine,
            $serviceTemplateId
        );

        /** @var array<string,CommandMacro> $commandMacros */
        $commandMacros = [];
        if ($checkCommandId !== null) {
            $existingCommandMacros = $this->readCommandMacroRepository->findByCommandIdAndType(
                $checkCommandId,
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
     * @param ServiceTemplate $serviceTemplate
     * @param PartialUpdateServiceTemplateRequest $request
     *
     * @throws \Throwable
     * @throws HostTemplateException
     */
    private function updateServiceTemplate(
        ServiceTemplate $serviceTemplate,
        PartialUpdateServiceTemplateRequest $request
    ): void {
        $inheritanceMode = $this->optionService->findSelectedOptions(['inheritance_mode']);
        $inheritanceMode = isset($inheritanceMode[0])
            ? (int) $inheritanceMode[0]->getValue()
            : 0;

        if (! $request->name instanceof NoValue) {
            $this->validation->assertIsValidName($serviceTemplate->getName(), $request->name);
            $serviceTemplate->setName($request->name);
        }

        if (! $request->alias instanceof NoValue) {
            $serviceTemplate->setAlias($request->alias);
        }

        if (! $request->commandArguments instanceof NoValue) {
            $serviceTemplate->setCommandArguments($request->commandArguments);
        }

        if (! $request->eventHandlerArguments instanceof NoValue) {
            $serviceTemplate->setEventHandlerArguments($request->eventHandlerArguments);
        }

        if (! $request->notificationTypes instanceof NoValue) {
            $serviceTemplate->resetNotificationTypes();
            foreach (NotificationTypeConverter::fromBits($request->notificationTypes) as $notificationType) {
                $serviceTemplate->addNotificationType($notificationType);
            }
        }

        if (! $request->isContactAdditiveInheritance instanceof NoValue) {
            $serviceTemplate->setContactAdditiveInheritance(($inheritanceMode === 1) ? $request->isContactAdditiveInheritance : false);
        }

        if (! $request->isContactGroupAdditiveInheritance instanceof NoValue) {
            $serviceTemplate->setContactGroupAdditiveInheritance(($inheritanceMode === 1) ? $request->isContactGroupAdditiveInheritance : false);
        }

        if (! $request->activeChecksEnabled instanceof NoValue) {
            $serviceTemplate->setActiveChecks(YesNoDefaultConverter::fromInt($request->activeChecksEnabled));
        }

        if (! $request->passiveCheckEnabled instanceof NoValue) {
            $serviceTemplate->setPassiveCheck(YesNoDefaultConverter::fromInt($request->passiveCheckEnabled));
        }

        if (! $request->volatility instanceof NoValue) {
            $serviceTemplate->setVolatility(YesNoDefaultConverter::fromInt($request->volatility));
        }

        if (! $request->checkFreshness instanceof NoValue) {
            $serviceTemplate->setCheckFreshness(YesNoDefaultConverter::fromInt($request->checkFreshness));
        }

        if (! $request->eventHandlerEnabled instanceof NoValue) {
            $serviceTemplate->setEventHandlerEnabled(YesNoDefaultConverter::fromInt($request->eventHandlerEnabled));
        }

        if (! $request->flapDetectionEnabled instanceof NoValue) {
            $serviceTemplate->setFlapDetectionEnabled(YesNoDefaultConverter::fromInt($request->flapDetectionEnabled));
        }

        if (! $request->notificationsEnabled instanceof NoValue) {
            $serviceTemplate->setNotificationsEnabled(YesNoDefaultConverter::fromInt($request->notificationsEnabled));
        }

        if (! $request->comment instanceof NoValue) {
            $serviceTemplate->setComment($request->comment);
        }

        if (! $request->note instanceof NoValue) {
            $serviceTemplate->setNote($request->note);
        }

        if (! $request->noteUrl instanceof NoValue) {
            $serviceTemplate->setNoteUrl($request->noteUrl);
        }

        if (! $request->actionUrl instanceof NoValue) {
            $serviceTemplate->setActionUrl($request->actionUrl);
        }

        if (! $request->iconAlternativeText instanceof NoValue) {
            $serviceTemplate->setIconAlternativeText($request->iconAlternativeText);
        }

        if (! $request->graphTemplateId instanceof NoValue) {
            $this->validation->assertIsValidPerformanceGraph($request->graphTemplateId);
            $serviceTemplate->setGraphTemplateId($request->graphTemplateId);
        }

        if (! $request->serviceTemplateParentId instanceof NoValue) {
            $this->validation->assertIsValidServiceTemplate($request->serviceTemplateParentId);
            $serviceTemplate->setServiceTemplateParentId($request->serviceTemplateParentId);
        }

        if (! $request->commandId instanceof NoValue) {
            $this->validation->assertIsValidCommand($request->commandId);
            $serviceTemplate->setCommandId($request->commandId);
        }

        if (! $request->eventHandlerId instanceof NoValue) {
            $this->validation->assertIsValidEventHandler($request->eventHandlerId);
            $serviceTemplate->setEventHandlerId($request->eventHandlerId);
        }

        if (! $request->notificationTimePeriodId instanceof NoValue) {
            $this->validation->assertIsValidNotificationTimePeriod($request->notificationTimePeriodId);
            $serviceTemplate->setNotificationTimePeriodId($request->notificationTimePeriodId);
        }

        if (! $request->checkTimePeriodId instanceof NoValue) {
            $this->validation->assertIsValidTimePeriod($request->checkTimePeriodId);
            $serviceTemplate->setCheckTimePeriodId($request->checkTimePeriodId);
        }

        if (! $request->iconId instanceof NoValue) {
            $this->validation->assertIsValidIcon($request->iconId);
            $serviceTemplate->setIconId($request->iconId);
        }

        if (! $request->severityId instanceof NoValue) {
            $this->validation->assertIsValidSeverity($request->severityId);
            $serviceTemplate->setSeverityId($request->severityId);
        }

        if (! $request->maxCheckAttempts instanceof NoValue) {
            $serviceTemplate->setMaxCheckAttempts($request->maxCheckAttempts);
        }

        if (! $request->normalCheckInterval instanceof NoValue) {
            $serviceTemplate->setNormalCheckInterval($request->normalCheckInterval);
        }

        if (! $request->retryCheckInterval instanceof NoValue) {
            $serviceTemplate->setRetryCheckInterval($request->retryCheckInterval);
        }

        if (! $request->freshnessThreshold instanceof NoValue) {
            $serviceTemplate->setFreshnessThreshold($request->freshnessThreshold);
        }

        if (! $request->lowFlapThreshold instanceof NoValue) {
            $serviceTemplate->setLowFlapThreshold($request->lowFlapThreshold);
        }

        if (! $request->highFlapThreshold instanceof NoValue) {
            $serviceTemplate->setHighFlapThreshold($request->highFlapThreshold);
        }

        if (! $request->notificationInterval instanceof NoValue) {
            $serviceTemplate->setNotificationInterval($request->notificationInterval);
        }

        if (! $request->recoveryNotificationDelay instanceof NoValue) {
            $serviceTemplate->setRecoveryNotificationDelay($request->recoveryNotificationDelay);
        }

        if (! $request->firstNotificationDelay instanceof NoValue) {
            $serviceTemplate->setFirstNotificationDelay($request->firstNotificationDelay);
        }

        if (! $request->acknowledgementTimeout instanceof NoValue) {
            $serviceTemplate->setAcknowledgementTimeout($request->acknowledgementTimeout);
        }

        $this->writeRepository->update($serviceTemplate);
    }

    /**
     * @param ServiceGroupDto[] $serviceGroupDtos
     *
     * @return ServiceGroupDto[]
     */
    private function removeDuplicatesServiceGroups(array $serviceGroupDtos): array
    {
        $uniqueList = [];
        foreach ($serviceGroupDtos as $item) {
            $uniqueList[$item->serviceGroupId . '_' . $item->hostTemplateId] = $item;
        }

        return array_values($uniqueList);
    }

    /**
     * @param int $serviceTemplateId
     *
     * @throws \Throwable
     */
    private function retrieveServiceUuidFromVault(int $serviceTemplateId): void
    {
        $macros = $this->readServiceMacroRepository->findByServiceIds($serviceTemplateId);
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
            $vaultPath = $this->writeVaultRepository->upsert(
                $this->uuid ?? null,
                $action === 'INSERT' ? ['_SERVICE' . $macro->getName() => $macro->getValue()] : [],
                $action === 'DELETE' ? ['_SERVICE' . $macro->getName() => $macro->getValue()] : [],
            );
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
