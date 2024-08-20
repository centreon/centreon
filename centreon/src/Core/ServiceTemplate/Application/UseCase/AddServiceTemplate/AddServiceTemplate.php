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

namespace Core\ServiceTemplate\Application\UseCase\AddServiceTemplate;

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
use Core\CommandMacro\Application\Repository\ReadCommandMacroRepositoryInterface;
use Core\CommandMacro\Domain\Model\CommandMacro;
use Core\CommandMacro\Domain\Model\CommandMacroType;
use Core\Common\Application\Repository\ReadVaultRepositoryInterface;
use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
use Core\Common\Application\UseCase\VaultTrait;
use Core\Common\Domain\TrimmedString;
use Core\Common\Infrastructure\Repository\AbstractVaultRepository;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
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
use Core\ServiceGroup\Domain\Model\ServiceGroup;
use Core\ServiceGroup\Domain\Model\ServiceGroupRelation;
use Core\ServiceTemplate\Application\Exception\ServiceTemplateException;
use Core\ServiceTemplate\Application\Repository\ReadServiceTemplateRepositoryInterface;
use Core\ServiceTemplate\Application\Repository\WriteServiceTemplateRepositoryInterface;
use Core\ServiceTemplate\Domain\Model\NewServiceTemplate;
use Core\ServiceTemplate\Domain\Model\ServiceTemplate;
use Core\ServiceTemplate\Domain\Model\ServiceTemplateInheritance;

final class AddServiceTemplate
{
    use LoggerTrait,VaultTrait;

    /** @var AccessGroup[] */
    private array $accessGroups;

    public function __construct(
        private readonly ReadHostTemplateRepositoryInterface $readHostTemplateRepository,
        private readonly ReadServiceTemplateRepositoryInterface $readServiceTemplateRepository,
        private readonly WriteServiceTemplateRepositoryInterface $writeServiceTemplateRepository,
        private readonly ReadServiceMacroRepositoryInterface $readServiceMacroRepository,
        private readonly ReadCommandMacroRepositoryInterface $readCommandMacroRepository,
        private readonly WriteServiceMacroRepositoryInterface $writeServiceMacroRepository,
        private readonly DataStorageEngineInterface $storageEngine,
        private readonly ReadServiceCategoryRepositoryInterface $readServiceCategoryRepository,
        private readonly WriteServiceCategoryRepositoryInterface $writeServiceCategoryRepository,
        private readonly ReadServiceGroupRepositoryInterface $readServiceGroupRepository,
        private readonly WriteServiceGroupRepositoryInterface $writeServiceGroupRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly AddServiceTemplateValidation $validation,
        private readonly OptionService $optionService,
        private readonly ContactInterface $user,
        private readonly WriteVaultRepositoryInterface $writeVaultRepository,
        private readonly ReadVaultRepositoryInterface $readVaultRepository,
    ) {
        $this->writeVaultRepository->setCustomPath(AbstractVaultRepository::SERVICE_VAULT_PATH);
    }

    /**
     * @param AddServiceTemplateRequest $request
     * @param AddServiceTemplatePresenterInterface $presenter
     */
    public function __invoke(AddServiceTemplateRequest $request, AddServiceTemplatePresenterInterface $presenter): void
    {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE)) {
                $this->error(
                    "User doesn't have sufficient rights to add a service template",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->presentResponse(
                    new ForbiddenResponse(ServiceTemplateException::addNotAllowed())
                );

                return;
            }

            if (! $this->user->isAdmin()) {
                $this->accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
                $this->validation->accessGroups = $this->accessGroups;
            }

            $formattedName = ServiceTemplate::formatName($request->name);
            if ($formattedName !== null) {
                $nameToCheck = new TrimmedString($formattedName);
                if ($this->readServiceTemplateRepository->existsByName($nameToCheck)) {
                    $presenter->presentResponse(
                        new ConflictResponse(ServiceTemplateException::nameAlreadyExists((string) $nameToCheck))
                    );

                    return;
                }
            }

            $this->assertParameters($request);
            $newServiceTemplateId = $this->createServiceTemplate($request);

            $this->info('New service template created', ['service_template_id' => $newServiceTemplateId]);
            $serviceTemplate = $this->readServiceTemplateRepository->findById($newServiceTemplateId);
            if (! $serviceTemplate) {
                $presenter->presentResponse(
                    new ErrorResponse(ServiceTemplateException::errorWhileRetrieving())
                );

                return;
            }
            if ($this->user->isAdmin()) {
                $serviceCategories = $this->readServiceCategoryRepository->findByService($newServiceTemplateId);
                $serviceGroups = $this->readServiceGroupRepository->findByService($newServiceTemplateId);
            } else {
                $serviceCategories = $this->readServiceCategoryRepository->findByServiceAndAccessGroups(
                    $newServiceTemplateId,
                    $this->accessGroups
                );
                $serviceGroups = $this->readServiceGroupRepository->findByServiceAndAccessGroups(
                    $newServiceTemplateId,
                    $this->accessGroups
                );
            }

            $presenter->presentResponse($this->createResponse($serviceTemplate, $serviceCategories, $serviceGroups));
        } catch (AssertionFailedException $ex) {
            $presenter->presentResponse(new InvalidArgumentResponse($ex));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (ServiceTemplateException $ex) {
            $presenter->presentResponse(
                match ($ex->getCode()) {
                    ServiceTemplateException::CODE_CONFLICT => new ConflictResponse($ex),
                    default => new ErrorResponse($ex),
                }
            );
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->presentResponse(new ErrorResponse(ServiceTemplateException::errorWhileAdding($ex)));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @param int $serviceTemplateId
     * @param AddServiceTemplateRequest $request
     *
     * @throws AssertionFailedException
     * @throws \Throwable
     */
    private function addMacros(int $serviceTemplateId, AddServiceTemplateRequest $request): void
    {
        $this->info('Add macros', ['service_template_id' => $serviceTemplateId]);

        /**
         * @var array<string,Macro> $inheritedMacros
         * @var array<string,CommandMacro> $commandMacros
         */
        [$inheritedMacros, $commandMacros] = $this->findAllInheritedMacros($serviceTemplateId, $request->commandId);

        $macros = [];
        foreach ($request->macros as $macro) {
            $macro = MacroFactory::create($macro, $serviceTemplateId, $inheritedMacros);
            $macros[$macro->getName()] = $macro;
        }

        $macrosDiff = new MacroDifference();
        $macrosDiff->compute([], $inheritedMacros, $commandMacros, $macros);

        MacroManager::setOrder($macrosDiff, $macros, []);

        foreach ($macrosDiff->addedMacros as $macro) {
            if ($macro->getDescription() === '') {
                $macro->setDescription(
                    isset($commandMacros[$macro->getName()])
                    ? $commandMacros[$macro->getName()]->getDescription()
                    : ''
                );
            }
            $this->info('Add the macro ' . $macro->getName());

            if ($this->writeVaultRepository->isVaultConfigured() === true && $macro->isPassword() === true) {
                $vaultPath = $this->writeVaultRepository->upsert(
                    $this->uuid ?? null,
                    ['_SERVICE' . $macro->getName() => $macro->getValue()],
                );
                $this->uuid ??= $this->getUuidFromPath($vaultPath);

                $inVaultMacro = new Macro($macro->getOwnerId(), $macro->getName(), $vaultPath);
                $inVaultMacro->setDescription($macro->getDescription());
                $inVaultMacro->setIsPassword($macro->isPassword());
                $inVaultMacro->setOrder($macro->getOrder());
                $macro = $inVaultMacro;
            }

            $this->writeServiceMacroRepository->add($macro);
        }
    }

    /**
     * @param AddServiceTemplateRequest $request
     *
     * @throws \Exception
     * @throws AssertionFailedException
     *
     * @return NewServiceTemplate
     */
    private function createNewServiceTemplate(AddServiceTemplateRequest $request): NewServiceTemplate
    {
        $inheritanceMode = $this->optionService->findSelectedOptions(['inheritance_mode']);
        $inheritanceMode = isset($inheritanceMode[0])
            ? (int) $inheritanceMode[0]->getValue()
            : 0;

        return NewServiceTemplateFactory::create((int) $inheritanceMode, $request);
    }

    /**
     * @param int $serviceTemplateId
     * @param AddServiceTemplateRequest $request
     *
     * @throws \Throwable
     */
    private function linkServiceTemplateToServiceCategories(int $serviceTemplateId, AddServiceTemplateRequest $request): void
    {
        if (empty($request->serviceCategories)) {

            return;
        }

        $this->info(
            'Link existing service categories to service',
            ['service_categories' => $request->serviceCategories]
        );
        $this->writeServiceCategoryRepository->linkToService($serviceTemplateId, $request->serviceCategories);
    }

    /**
     * @param int $serviceTemplateId
     * @param AddServiceTemplateRequest $request
     *
     * @throws \Throwable
     */
    private function linkServiceTemplateToServiceGroups(int $serviceTemplateId, AddServiceTemplateRequest $request): void
    {
        if (empty($request->serviceGroups)) {

            return;
        }

        $this->info(
            'Link existing service groups to service',
            ['service_groups' => $request->serviceGroups]
        );

        $serviceGroupRelations = [];
        $serviceGroupDtos = $this->removeDuplicates($request->serviceGroups);
        foreach ($serviceGroupDtos as $serviceGroup) {
            $serviceGroupRelations[] = new ServiceGroupRelation(
                serviceGroupId: $serviceGroup->serviceGroupId,
                serviceId: $serviceTemplateId,
                hostId: $serviceGroup->hostTemplateId
            );
        }

        $this->writeServiceGroupRepository->link($serviceGroupRelations);
    }

    /**
     * @param ServiceGroupDto[] $serviceGroupList
     *
     * @return ServiceGroupDto[]
     */
    private function removeDuplicates(array $serviceGroupList): array
    {
        $uniqueList = [];
        foreach ($serviceGroupList as $item) {
            $uniqueList[$item->serviceGroupId . '_' . $item->hostTemplateId] = $item;
        }

        return array_values($uniqueList);
    }

    /**
     * @param ServiceTemplate $serviceTemplate
     * @param ServiceCategory[] $serviceCategories
     * @param array<array{relation:ServiceGroupRelation,serviceGroup:ServiceGroup}> $serviceGroups
     *
     * @throws \Throwable
     *
     * @return AddServiceTemplateResponse
     */
    private function createResponse(ServiceTemplate $serviceTemplate, array $serviceCategories, array $serviceGroups): AddServiceTemplateResponse
    {
        $macros = $this->readServiceMacroRepository->findByServiceIds($serviceTemplate->getId());

        $response = new AddServiceTemplateResponse();
        $response->id = $serviceTemplate->getId();
        $response->name = $serviceTemplate->getName();
        $response->alias = $serviceTemplate->getAlias();
        $response->commandArguments = $serviceTemplate->getCommandArguments();
        $response->eventHandlerArguments = $serviceTemplate->getEventHandlerArguments();
        $response->notificationTypes = $serviceTemplate->getNotificationTypes();
        $response->isContactAdditiveInheritance = $serviceTemplate->isContactAdditiveInheritance();
        $response->isContactGroupAdditiveInheritance = $serviceTemplate->isContactGroupAdditiveInheritance();
        $response->isLocked = $serviceTemplate->isLocked();
        $response->activeChecks = $serviceTemplate->getActiveChecks();
        $response->passiveCheck = $serviceTemplate->getPassiveCheck();
        $response->volatility = $serviceTemplate->getVolatility();
        $response->checkFreshness = $serviceTemplate->getCheckFreshness();
        $response->eventHandlerEnabled = $serviceTemplate->getEventHandlerEnabled();
        $response->flapDetectionEnabled = $serviceTemplate->getFlapDetectionEnabled();
        $response->notificationsEnabled = $serviceTemplate->getNotificationsEnabled();
        $response->comment = $serviceTemplate->getComment();
        $response->note = $serviceTemplate->getNote();
        $response->noteUrl = $serviceTemplate->getNoteUrl();
        $response->actionUrl = $serviceTemplate->getActionUrl();
        $response->iconAlternativeText = $serviceTemplate->getIconAlternativeText();
        $response->graphTemplateId = $serviceTemplate->getGraphTemplateId();
        $response->serviceTemplateId = $serviceTemplate->getServiceTemplateParentId();
        $response->commandId = $serviceTemplate->getCommandId();
        $response->eventHandlerId = $serviceTemplate->getEventHandlerId();
        $response->notificationTimePeriodId = $serviceTemplate->getNotificationTimePeriodId();
        $response->checkTimePeriodId = $serviceTemplate->getCheckTimePeriodId();
        $response->iconId = $serviceTemplate->getIconId();
        $response->severityId = $serviceTemplate->getSeverityId();
        $response->hostTemplateIds = $serviceTemplate->getHostTemplateIds();
        $response->maxCheckAttempts = $serviceTemplate->getMaxCheckAttempts();
        $response->normalCheckInterval = $serviceTemplate->getNormalCheckInterval();
        $response->retryCheckInterval = $serviceTemplate->getRetryCheckInterval();
        $response->freshnessThreshold = $serviceTemplate->getFreshnessThreshold();
        $response->lowFlapThreshold = $serviceTemplate->getLowFlapThreshold();
        $response->highFlapThreshold = $serviceTemplate->getHighFlapThreshold();
        $response->notificationInterval = $serviceTemplate->getNotificationInterval();
        $response->recoveryNotificationDelay = $serviceTemplate->getRecoveryNotificationDelay();
        $response->firstNotificationDelay = $serviceTemplate->getFirstNotificationDelay();
        $response->acknowledgementTimeout = $serviceTemplate->getAcknowledgementTimeout();
        $response->macros = array_map(
            fn(Macro $macro): MacroDto => new MacroDto(
                $macro->getName(),
                $macro->getValue(),
                $macro->isPassword(),
                $macro->getDescription()
            ),
            $macros
        );

        $response->categories = array_map(
            fn(ServiceCategory $category) => ['id' => $category->getId(), 'name' => $category->getName()],
            $serviceCategories
        );

        $hostTemplateNames = $this->readHostTemplateRepository->findNamesByIds(array_map(
            fn(array $group): int => (int) $group['relation']->getHostId(),
            $serviceGroups
        ));
        $response->groups = array_map(
            fn(array $group) => [
                'serviceGroupId' => $group['serviceGroup']->getId(),
                'serviceGroupName' => $group['serviceGroup']->getName(),
                'hostTemplateId' => (int) $group['relation']->getHostId(),
                'hostTemplateName' => $hostTemplateNames[(int) $group['relation']->getHostId()],
            ],
            $serviceGroups,
        );

        return $response;
    }

    /**
     * @param AddServiceTemplateRequest $request
     *
     * @throws ServiceTemplateException
     * @throws \Throwable
     */
    private function assertParameters(AddServiceTemplateRequest $request): void
    {
        $this->validation->assertIsValidSeverity($request->severityId);
        $this->validation->assertIsValidPerformanceGraph($request->graphTemplateId);
        $this->validation->assertIsValidServiceTemplate($request->serviceTemplateParentId);
        $this->validation->assertIsValidCommand($request->commandId);
        $this->validation->assertIsValidEventHandler($request->eventHandlerId);
        $this->validation->assertIsValidTimePeriod($request->checkTimePeriodId);
        $this->validation->assertIsValidNotificationTimePeriod($request->notificationTimePeriodId);
        $this->validation->assertIsValidIcon($request->iconId);
        $this->validation->assertIsValidHostTemplates($request->hostTemplateIds);
        $this->validation->assertIsValidServiceCategories($request->serviceCategories);
        $this->validation->assertIsValidServiceGroups($request->serviceGroups, $request->hostTemplateIds);
    }

    /**
     * @param AddServiceTemplateRequest $request
     *
     * @throws AssertionFailedException
     * @throws ServiceTemplateException
     * @throws \Throwable
     *
     * @return int
     */
    private function createServiceTemplate(AddServiceTemplateRequest $request): int
    {
        $newServiceTemplate = $this->createNewServiceTemplate($request);
        $this->storageEngine->startTransaction();
        try {
            $newServiceTemplateId = $this->writeServiceTemplateRepository->add($newServiceTemplate);
            $this->addMacros($newServiceTemplateId, $request);
            $this->linkServiceTemplateToServiceCategories($newServiceTemplateId, $request);
            $this->linkServiceTemplateToServiceGroups($newServiceTemplateId, $request);
            $this->storageEngine->commitTransaction();

            return $newServiceTemplateId;
        } catch (\Throwable $ex) {
            $this->error("Rollback of 'Add Service Template' transaction.");

            $this->storageEngine->rollbackTransaction();

            throw $ex;
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
     *      array<string,CommandMacro>
     * }
     */
    private function findAllInheritedMacros(int $serviceTemplateId, ?int $checkCommandId): array
    {
        $serviceTemplateInheritances = $this->readServiceTemplateRepository->findParents($serviceTemplateId);
        $inheritanceLine = ServiceTemplateInheritance::createInheritanceLine(
            $serviceTemplateId,
            $serviceTemplateInheritances
        );
        $existingMacros = $this->readServiceMacroRepository->findByServiceIds(...$inheritanceLine);

        [, $inheritedMacros] = Macro::resolveInheritance($existingMacros, $inheritanceLine, $serviceTemplateId);

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
                ? $this->retrieveMacrosVaultValues($inheritedMacros)
                : $inheritedMacros,
            $commandMacros,
        ];
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
