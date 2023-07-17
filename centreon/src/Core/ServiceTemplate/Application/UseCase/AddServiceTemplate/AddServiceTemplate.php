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
use Core\Command\Application\Repository\ReadCommandRepositoryInterface;
use Core\Command\Domain\Model\CommandType;
use Core\CommandMacro\Application\Repository\ReadCommandMacroRepositoryInterface;
use Core\CommandMacro\Domain\Model\CommandMacro;
use Core\CommandMacro\Domain\Model\CommandMacroType;
use Core\Common\Domain\TrimmedString;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\Macro\Application\Repository\ReadServiceMacroRepositoryInterface;
use Core\Macro\Application\Repository\WriteServiceMacroRepositoryInterface;
use Core\Macro\Domain\Model\Macro;
use Core\Macro\Domain\Model\MacroDifference;
use Core\Macro\Domain\Model\MacroManager;
use Core\PerformanceGraph\Application\Repository\ReadPerformanceGraphRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\ServiceCategory\Application\Repository\ReadServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Application\Repository\WriteServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Domain\Model\ServiceCategory;
use Core\ServiceSeverity\Application\Repository\ReadServiceSeverityRepositoryInterface;
use Core\ServiceTemplate\Application\Exception\ServiceTemplateException;
use Core\ServiceTemplate\Application\Repository\ReadServiceTemplateRepositoryInterface;
use Core\ServiceTemplate\Application\Repository\WriteServiceTemplateRepositoryInterface;
use Core\ServiceTemplate\Domain\Model\NewServiceTemplate;
use Core\ServiceTemplate\Domain\Model\ServiceTemplate;
use Core\ServiceTemplate\Domain\Model\ServiceTemplateInheritance;
use Core\TimePeriod\Application\Repository\ReadTimePeriodRepositoryInterface;
use Core\ViewImg\Application\Repository\ReadViewImgRepositoryInterface;

final class AddServiceTemplate
{
    use LoggerTrait;

    /** @var AccessGroup[] */
    private array $accessGroups;

    public function __construct(
        private readonly ReadServiceTemplateRepositoryInterface $readServiceTemplateRepository,
        private readonly WriteServiceTemplateRepositoryInterface $writeServiceTemplateRepository,
        private readonly ReadServiceSeverityRepositoryInterface $serviceSeverityRepository,
        private readonly ReadPerformanceGraphRepositoryInterface $performanceGraphRepository,
        private readonly ReadCommandRepositoryInterface $commandRepository,
        private readonly ReadTimePeriodRepositoryInterface $timePeriodRepository,
        private readonly ReadViewImgRepositoryInterface $imageRepository,
        private readonly ReadHostTemplateRepositoryInterface $readHostTemplateRepository,
        private readonly ReadServiceMacroRepositoryInterface $readServiceMacroRepository,
        private readonly ReadCommandMacroRepositoryInterface $readCommandMacroRepository,
        private readonly WriteServiceMacroRepositoryInterface $writeServiceMacroRepository,
        private readonly DataStorageEngineInterface $storageEngine,
        private readonly ReadServiceCategoryRepositoryInterface $readServiceCategoryRepository,
        private readonly WriteServiceCategoryRepositoryInterface $writeServiceCategoryRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly OptionService $optionService,
        private readonly ContactInterface $user
    ) {
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
            }

            $nameToCheck = new TrimmedString(ServiceTemplate::formatName($request->name));
            if ($this->readServiceTemplateRepository->existsByName($nameToCheck)) {
                $presenter->presentResponse(
                    new ConflictResponse(ServiceTemplateException::nameAlreadyExists((string) $nameToCheck))
                );

                return;
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
            } else {
                $serviceCategories = $this->readServiceCategoryRepository->findByServiceAndAccessGroups(
                    $newServiceTemplateId,
                    $this->accessGroups
                );
            }

            $presenter->presentResponse($this->createResponse($serviceTemplate, $serviceCategories));
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
     * @param int|null $serviceTemplateId
     *
     * @throws ServiceTemplateException
     * @throws \Throwable
     */
    public function assertIsValidServiceTemplate(?int $serviceTemplateId): void
    {
        if ($serviceTemplateId !== null && ! $this->readServiceTemplateRepository->exists($serviceTemplateId)) {
            $this->error('Service template does not exist', ['service_template_id' => $serviceTemplateId]);

            throw ServiceTemplateException::idDoesNotExist('service_template_id', $serviceTemplateId);
        }
    }

    /**
     * @param int|null $commandId
     *
     * @throws ServiceTemplateException
     */
    public function assertIsValidCommand(?int $commandId): void
    {
        if (
            $commandId !== null
            && ! $this->commandRepository->existsByIdAndCommandType($commandId, CommandType::Check)
        ) {
            $this->error('Check command does not exist', ['check_command_id' => $commandId]);

            throw ServiceTemplateException::idDoesNotExist('check_command_id', $commandId);
        }
    }

    /**
     * @param int|null $eventHandlerId
     *
     * @throws ServiceTemplateException
     */
    public function assertIsValidEventHandler(?int $eventHandlerId): void
    {
        if ($eventHandlerId !== null && ! $this->commandRepository->exists($eventHandlerId)) {
            $this->error('Event handler command does not exist', ['event_handler_command_id' => $eventHandlerId]);

            throw ServiceTemplateException::idDoesNotExist('event_handler_command_id', $eventHandlerId);
        }
    }

    /**
     * @param int|null $timePeriodId
     *
     * @throws ServiceTemplateException
     * @throws \Throwable
     */
    public function assertIsValidTimePeriod(?int $timePeriodId): void
    {
        if ($timePeriodId !== null && ! $this->timePeriodRepository->exists($timePeriodId)) {
            $this->error('Time period does not exist', ['check_timeperiod_id' => $timePeriodId]);

            throw ServiceTemplateException::idDoesNotExist('check_timeperiod_id', $timePeriodId);
        }
    }

    /**
     * @param int|null $iconId
     *
     * @throws ServiceTemplateException
     * @throws \Throwable
     */
    public function assertIsValidIcon(?int $iconId): void
    {
        if ($iconId !== null && ! $this->imageRepository->existsOne($iconId)) {
            $this->error('Icon does not exist', ['icon_id' => $iconId]);

            throw ServiceTemplateException::idDoesNotExist('icon_id', $iconId);
        }
    }

    /**
     * @param int|null $notificationTimePeriodId
     *
     * @throws ServiceTemplateException
     * @throws \Throwable
     */
    public function assertIsValidNotificationTimePeriod(?int $notificationTimePeriodId): void
    {
        if ($notificationTimePeriodId !== null && ! $this->timePeriodRepository->exists($notificationTimePeriodId)) {
            $this->error(
                'Notification time period does not exist',
                ['notification_timeperiod_id' => $notificationTimePeriodId]
            );

            throw ServiceTemplateException::idDoesNotExist('notification_timeperiod_id', $notificationTimePeriodId);
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
        $inheritanceMode = isset($inheritanceMode['inheritance_mode'])
            ? $inheritanceMode['inheritance_mode']->getValue()
            : 0;

        return NewServiceTemplateFactory::create((int) $inheritanceMode, $request);
    }

    /**
     * @param int|null $severityId
     *
     * @throws ServiceTemplateException
     * @throws \Throwable
     */
    private function assertIsValidSeverity(?int $severityId): void
    {
        if ($severityId !== null && ! $this->serviceSeverityRepository->exists($severityId)) {
            $this->error('Service severity does not exist', ['severity_id' => $severityId]);

            throw ServiceTemplateException::idDoesNotExist('severity_id', $severityId);
        }
    }

    /**
     * @param int|null $graphTemplateId
     *
     * @throws ServiceTemplateException
     * @throws \Throwable
     */
    private function assertIsValidPerformanceGraph(?int $graphTemplateId): void
    {
        if ($graphTemplateId !== null && ! $this->performanceGraphRepository->exists($graphTemplateId)) {
            $this->error('Performance graph does not exist', ['graph_template_id' => $graphTemplateId]);

            throw ServiceTemplateException::idDoesNotExist('graph_template_id', $graphTemplateId);
        }
    }

    /**
     * @param list<int> $hostTemplateIds
     *
     * @throws ServiceTemplateException
     */
    private function assertIsValidHostTemplates(array $hostTemplateIds): void
    {
        if (! empty($hostTemplateIds)) {
            $hostTemplateIds = array_unique($hostTemplateIds);
            $hostTemplateIdsFound = $this->readHostTemplateRepository->findAllExistingIds($hostTemplateIds);
            if ([] !== ($diff = array_diff($hostTemplateIds, $hostTemplateIdsFound))) {
                throw ServiceTemplateException::idsDoesNotExist('host_templates', $diff);
            }
        }
    }

    /**
     * @param AddServiceTemplateRequest $request
     * @param array $serviceCategoriesIds
     *
     * @throws ServiceTemplateException
     * @throws \Throwable
     */
    private function assertIsValidServiceCategories(array $serviceCategoriesIds): void
    {
        if (empty($serviceCategoriesIds)) {

            return;
        }

        if ($this->user->isAdmin()) {
            $serviceCategoriesIdsFound = $this->readServiceCategoryRepository->findAllExistingIds(
                $serviceCategoriesIds
            );
        } else {
            $serviceCategoriesIdsFound = $this->readServiceCategoryRepository->findAllExistingIdsByAccessGroups(
                $serviceCategoriesIds,
                $this->accessGroups
            );
        }

        if ([] !== ($idsNotFound = array_diff($serviceCategoriesIds, $serviceCategoriesIdsFound))) {
            throw ServiceTemplateException::idsDoesNotExist('service_categories', $idsNotFound);
        }
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
     * @param ServiceTemplate $serviceTemplate
     * @param ServiceCategory[] $serviceCategories
     *
     * @throws \Throwable
     *
     * @return AddServiceTemplateResponse
     */
    private function createResponse(ServiceTemplate $serviceTemplate, array $serviceCategories): AddServiceTemplateResponse
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
        $response->isActivated = $serviceTemplate->isActivated();
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
        $this->assertIsValidSeverity($request->severityId);
        $this->assertIsValidPerformanceGraph($request->graphTemplateId);
        $this->assertIsValidServiceTemplate($request->serviceTemplateParentId);
        $this->assertIsValidCommand($request->commandId);
        $this->assertIsValidEventHandler($request->eventHandlerId);
        $this->assertIsValidTimePeriod($request->checkTimePeriodId);
        $this->assertIsValidNotificationTimePeriod($request->notificationTimePeriodId);
        $this->assertIsValidIcon($request->iconId);
        $this->assertIsValidHostTemplates($request->hostTemplateIds);
        $this->assertIsValidServiceCategories($request->serviceCategories);
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
            $this->storageEngine->commitTransaction();

            return $newServiceTemplateId;
        } catch (\Throwable $ex) {
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

        return [$inheritedMacros, $commandMacros];
    }
}
