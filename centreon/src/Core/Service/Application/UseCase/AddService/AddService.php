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

namespace Core\Service\Application\UseCase\AddService;

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
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\Macro\Application\Repository\ReadServiceMacroRepositoryInterface;
use Core\Macro\Application\Repository\WriteServiceMacroRepositoryInterface;
use Core\Macro\Domain\Model\Macro;
use Core\Macro\Domain\Model\MacroDifference;
use Core\Macro\Domain\Model\MacroManager;
use Core\PerformanceGraph\Application\Repository\ReadPerformanceGraphRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\Service\Application\Exception\ServiceException;
use Core\Service\Application\Repository\ReadServiceRepositoryInterface;
use Core\Service\Application\Repository\WriteServiceRepositoryInterface;
use Core\Service\Domain\Model\NewService;
use Core\Service\Domain\Model\Service;
use Core\Service\Domain\Model\ServiceInheritance;
use Core\ServiceCategory\Application\Repository\ReadServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Application\Repository\WriteServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Domain\Model\ServiceCategory;
use Core\ServiceSeverity\Application\Repository\ReadServiceSeverityRepositoryInterface;
use Core\ServiceTemplate\Application\Repository\ReadServiceTemplateRepositoryInterface;
use Core\TimePeriod\Application\Repository\ReadTimePeriodRepositoryInterface;
use Core\ViewImg\Application\Repository\ReadViewImgRepositoryInterface;

final class AddService
{
    use LoggerTrait;

    /** @var AccessGroup[] */
    private array $accessGroups;

    public function __construct(
        private readonly ReadServiceTemplateRepositoryInterface $readServiceTemplateRepository,
        private readonly ReadServiceRepositoryInterface $readServiceRepository,
        private readonly WriteServiceRepositoryInterface $writeServiceRepository,
        private readonly ReadServiceSeverityRepositoryInterface $serviceSeverityRepository,
        private readonly ReadPerformanceGraphRepositoryInterface $performanceGraphRepository,
        private readonly ReadCommandRepositoryInterface $commandRepository,
        private readonly ReadTimePeriodRepositoryInterface $timePeriodRepository,
        private readonly ReadViewImgRepositoryInterface $imageRepository,
        private readonly ReadHostRepositoryInterface $readHostRepository,
        private readonly ReadServiceMacroRepositoryInterface $readServiceMacroRepository,
        private readonly ReadCommandMacroRepositoryInterface $readCommandMacroRepository,
        private readonly WriteServiceMacroRepositoryInterface $writeServiceMacroRepository,
        private readonly DataStorageEngineInterface $storageEngine,
        private readonly ReadServiceCategoryRepositoryInterface $readServiceCategoryRepository,
        private readonly WriteServiceCategoryRepositoryInterface $writeServiceCategoryRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly OptionService $optionService,
        private readonly ContactInterface $user,
        private readonly bool $isCloudPlatform,
    ) {
    }

    /**
     * @param AddServiceRequest $request
     * @param AddServicePresenterInterface $presenter
     */
    public function __invoke(AddServiceRequest $request, AddServicePresenterInterface $presenter): void
    {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE)) {
                $this->error(
                    "User doesn't have sufficient rights to add a service template",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->presentResponse(
                    new ForbiddenResponse(ServiceException::addNotAllowed())
                );

                return;
            }

            if (! $this->user->isAdmin()) {
                $this->accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
            }

            $this->assertParameters($request);
            $newServiceId = $this->createService($request);

            $this->info('New service template created', ['service_template_id' => $newServiceId]);
            $service = $this->readServiceRepository->findById($newServiceId);
            if (! $service) {
                $presenter->presentResponse(
                    new ErrorResponse(ServiceException::errorWhileRetrieving())
                );

                return;
            }
            if ($this->user->isAdmin()) {
                $serviceCategories = $this->readServiceCategoryRepository->findByService($newServiceId);
            } else {
                $serviceCategories = $this->readServiceCategoryRepository->findByServiceAndAccessGroups(
                    $newServiceId,
                    $this->accessGroups
                );
            }

            $presenter->presentResponse($this->createResponse($service, $serviceCategories));
        } catch (AssertionFailedException $ex) {
            $presenter->presentResponse(new InvalidArgumentResponse($ex));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (ServiceException $ex) {
            $presenter->presentResponse(
                match ($ex->getCode()) {
                    ServiceException::CODE_CONFLICT => new ConflictResponse($ex),
                    default => new ErrorResponse($ex),
                }
            );
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->presentResponse(new ErrorResponse(ServiceException::errorWhileAdding($ex)));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @param int|null $serviceTemplateId
     *
     * @throws ServiceException
     * @throws \Throwable
     */
    public function assertIsValidServiceTemplate(?int $serviceTemplateId): void
    {
        if ($serviceTemplateId !== null && ! $this->readServiceTemplateRepository->exists($serviceTemplateId)) {
            $this->error('Service does not exist', ['service_template_id' => $serviceTemplateId]);

            throw ServiceException::idDoesNotExist('service_template_id', $serviceTemplateId);
        }
    }

    /**
     * @param int|null $commandId
     * @param int|null $serviceTemplateId
     *
     * @throws ServiceException
     */
    public function assertIsValidCommandForOnPremPlatform(?int $commandId, ?int $serviceTemplateId): void
    {
        if ($commandId === null && $serviceTemplateId === null) {
            throw ServiceException::checkCommandCannotBeNull();
        }
        if ($commandId !== null && ! $this->commandRepository->existsByIdAndCommandType($commandId, CommandType::Check))
        {
            $this->error('The check command does not exist', ['check_command_id' => $commandId]);

            throw ServiceException::idDoesNotExist('check_command_id', $commandId);
        }
    }

    /**
     * @param int|null $eventHandlerId
     *
     * @throws ServiceException
     */
    public function assertIsValidEventHandler(?int $eventHandlerId): void
    {
        if ($eventHandlerId !== null && ! $this->commandRepository->exists($eventHandlerId)) {
            $this->error('Event handler command does not exist', ['event_handler_command_id' => $eventHandlerId]);

            throw ServiceException::idDoesNotExist('event_handler_command_id', $eventHandlerId);
        }
    }

    /**
     * @param int|null $timePeriodId
     *
     * @throws ServiceException
     * @throws \Throwable
     */
    public function assertIsValidTimePeriod(?int $timePeriodId): void
    {
        if ($timePeriodId !== null && ! $this->timePeriodRepository->exists($timePeriodId)) {
            $this->error('Time period does not exist', ['check_timeperiod_id' => $timePeriodId]);

            throw ServiceException::idDoesNotExist('check_timeperiod_id', $timePeriodId);
        }
    }

    /**
     * @param int|null $iconId
     *
     * @throws ServiceException
     * @throws \Throwable
     */
    public function assertIsValidIcon(?int $iconId): void
    {
        if ($iconId !== null && ! $this->imageRepository->existsOne($iconId)) {
            $this->error('Icon does not exist', ['icon_id' => $iconId]);

            throw ServiceException::idDoesNotExist('icon_id', $iconId);
        }
    }

    /**
     * @param int|null $notificationTimePeriodId
     *
     * @throws ServiceException
     * @throws \Throwable
     */
    public function assertIsValidNotificationTimePeriod(?int $notificationTimePeriodId): void
    {
        if ($notificationTimePeriodId !== null && ! $this->timePeriodRepository->exists($notificationTimePeriodId)) {
            $this->error(
                'Notification time period does not exist',
                ['notification_timeperiod_id' => $notificationTimePeriodId]
            );

            throw ServiceException::idDoesNotExist('notification_timeperiod_id', $notificationTimePeriodId);
        }
    }

    /**
     * @param int $serviceId
     * @param AddServiceRequest $request
     *
     * @throws AssertionFailedException
     * @throws \Throwable
     */
    private function addMacros(int $serviceId, AddServiceRequest $request): void
    {
        $this->info('Add macros', ['service_id' => $serviceId]);

        /**
         * @var array<string,Macro> $inheritedMacros
         * @var array<string,CommandMacro> $commandMacros
         */
        [$inheritedMacros, $commandMacros] = $this->findAllInheritedMacros($serviceId, $request->commandId);

        $macros = [];
        foreach ($request->macros as $macro) {
            $macro = MacroFactory::create($macro, $serviceId, $inheritedMacros);
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
     * @param AddServiceRequest $request
     *
     * @throws \Exception
     * @throws AssertionFailedException
     *
     * @return NewService
     */
    private function createNewService(AddServiceRequest $request): NewService
    {
        $inheritanceMode = $this->optionService->findSelectedOptions(['inheritance_mode']);
        $inheritanceMode = isset($inheritanceMode['inheritance_mode'])
            ? $inheritanceMode['inheritance_mode']->getValue()
            : 0;

        return NewServiceFactory::create((int) $inheritanceMode, $request, $this->isCloudPlatform);
    }

    /**
     * @param int|null $severityId
     *
     * @throws ServiceException
     * @throws \Throwable
     */
    private function assertIsValidSeverity(?int $severityId): void
    {
        if ($severityId !== null && ! $this->serviceSeverityRepository->exists($severityId)) {
            $this->error('Service severity does not exist', ['severity_id' => $severityId]);

            throw ServiceException::idDoesNotExist('severity_id', $severityId);
        }
    }

    /**
     * @param int|null $graphTemplateId
     *
     * @throws ServiceException
     * @throws \Throwable
     */
    private function assertIsValidPerformanceGraph(?int $graphTemplateId): void
    {
        if ($graphTemplateId !== null && ! $this->performanceGraphRepository->exists($graphTemplateId)) {
            $this->error('Performance graph does not exist', ['graph_template_id' => $graphTemplateId]);

            throw ServiceException::idDoesNotExist('graph_template_id', $graphTemplateId);
        }
    }

    /**
     * @param list<int> $hostIds
     *
     * @throws ServiceException
     */
    private function assertIsValidHostsForOnPremPlatform(array $hostIds): void
    {
        if ($hostIds !== []) {
            $hostIds = array_unique($hostIds);
            $hostTemplateIdsFound = $this->user->isAdmin()
                ? $this->readHostRepository->findAllExistingIds($hostIds)
                : $this->readHostRepository->findAllExistingIdsByAccessGroups($hostIds, $this->accessGroups);

            if ([] !== ($diff = array_diff($hostIds, $hostTemplateIdsFound))) {
                throw ServiceException::idsDoesNotExist('hosts', $diff);
            }
        }
    }

    /**
     * A host is mandatory for the SaaS platform.
     *
     * @param int $hostId
     *
     * @throws ServiceException
     */
    private function assertIsValidHostForSaasPlatform(int $hostId): void
    {
        $hostTemplateIdsFound = $this->user->isAdmin()
                ? $this->readHostRepository->findAllExistingIds([$hostId])
                : $this->readHostRepository->findAllExistingIdsByAccessGroups([$hostId], $this->accessGroups);
        if ([] === $hostTemplateIdsFound) {
            throw ServiceException::idDoesNotExist('host', $hostId);
        }
    }

    /**
     * @param list<int> $serviceCategoriesIds
     *
     * @throws ServiceException
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
            throw ServiceException::idsDoesNotExist('service_categories', $idsNotFound);
        }
    }

    /**
     * @param int $serviceId
     * @param AddServiceRequest $request
     *
     * @throws \Throwable
     */
    private function linkServiceToServiceCategories(int $serviceId, AddServiceRequest $request): void
    {
        if (empty($request->serviceCategories)) {

            return;
        }

        $this->info(
            'Link existing service categories to service',
            ['service_categories' => $request->serviceCategories]
        );
        $this->writeServiceCategoryRepository->linkToService($serviceId, $request->serviceCategories);
    }

    /**
     * @param Service $service
     * @param ServiceCategory[] $serviceCategories
     *
     * @throws \Throwable
     *
     * @return AddServiceResponse
     */
    private function createResponse(Service $service, array $serviceCategories): AddServiceResponse
    {
        $macros = $this->readServiceMacroRepository->findByServiceIds($service->getId());

        $response = new AddServiceResponse();
        $response->id = $service->getId();
        $response->name = $service->getName();
        $response->commandArguments = $service->getCommandArguments();
        $response->eventHandlerArguments = $service->getEventHandlerArguments();
        $response->notificationTypes = $service->getNotificationTypes();
        $response->isContactAdditiveInheritance = $service->isContactAdditiveInheritance();
        $response->isContactGroupAdditiveInheritance = $service->isContactGroupAdditiveInheritance();
        $response->isActivated = $service->isActivated();
        $response->activeChecks = $service->getActiveChecks();
        $response->passiveCheck = $service->getPassiveCheck();
        $response->volatility = $service->getVolatility();
        $response->checkFreshness = $service->getCheckFreshness();
        $response->eventHandlerEnabled = $service->getEventHandlerEnabled();
        $response->flapDetectionEnabled = $service->getFlapDetectionEnabled();
        $response->notificationsEnabled = $service->getNotificationsEnabled();
        $response->comment = $service->getComment();
        $response->note = $service->getNote();
        $response->noteUrl = $service->getNoteUrl();
        $response->actionUrl = $service->getActionUrl();
        $response->iconAlternativeText = $service->getIconAlternativeText();
        $response->graphTemplateId = $service->getGraphTemplateId();
        $response->serviceTemplateId = $service->getServiceTemplateParentId();
        $response->commandId = $service->getCommandId();
        $response->eventHandlerId = $service->getEventHandlerId();
        $response->notificationTimePeriodId = $service->getNotificationTimePeriodId();
        $response->checkTimePeriodId = $service->getCheckTimePeriodId();
        $response->iconId = $service->getIconId();
        $response->severityId = $service->getSeverityId();
        if ($this->isCloudPlatform) {
            $hostIds = $service->getHostIds();
            $hostId = array_shift($hostIds);
            if ($hostId === null) {
                throw new \Exception('blablabla');
            }
            $response->hostId = $hostId;
        } else {
            $response->hostIds = $service->getHostIds();
        }
        $response->maxCheckAttempts = $service->getMaxCheckAttempts();
        $response->normalCheckInterval = $service->getNormalCheckInterval();
        $response->retryCheckInterval = $service->getRetryCheckInterval();
        $response->freshnessThreshold = $service->getFreshnessThreshold();
        $response->lowFlapThreshold = $service->getLowFlapThreshold();
        $response->highFlapThreshold = $service->getHighFlapThreshold();
        $response->notificationInterval = $service->getNotificationInterval();
        $response->recoveryNotificationDelay = $service->getRecoveryNotificationDelay();
        $response->firstNotificationDelay = $service->getFirstNotificationDelay();
        $response->acknowledgementTimeout = $service->getAcknowledgementTimeout();
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
     * @param AddServiceRequest $request
     *
     * @throws ServiceException
     * @throws \Throwable
     */
    private function assertParameters(AddServiceRequest $request): void
    {
        $this->assertIsValidSeverity($request->severityId);
        $this->assertIsValidPerformanceGraph($request->graphTemplateId);
        $this->assertIsValidServiceTemplate($request->serviceTemplateParentId);
        $this->assertIsValidEventHandler($request->eventHandlerId);
        $this->assertIsValidTimePeriod($request->checkTimePeriodId);
        $this->assertIsValidNotificationTimePeriod($request->notificationTimePeriodId);
        $this->assertIsValidIcon($request->iconId);
        if ($this->isCloudPlatform) {
            // No assertion on the check command as it will be inherited from the service template.
            $this->assertIsValidHostForSaasPlatform($request->hostId);
        } else {
            $this->assertIsValidCommandForOnPremPlatform($request->commandId, $request->serviceTemplateParentId);
            $this->assertIsValidHostsForOnPremPlatform($request->hostIds);
        }
        $this->assertServiceName($request); // Should be called after assertion on host IDs
        $this->assertIsValidServiceCategories($request->serviceCategories);
    }

    /**
     * @param AddServiceRequest $request
     *
     * @throws AssertionFailedException
     * @throws ServiceException
     * @throws \Throwable
     *
     * @return int
     */
    private function createService(AddServiceRequest $request): int
    {
        $newServiceTemplate = $this->createNewService($request);
        $this->storageEngine->startTransaction();
        try {
            $newServiceTemplateId = $this->writeServiceRepository->add($newServiceTemplate);
            $this->addMacros($newServiceTemplateId, $request);
            $this->linkServiceToServiceCategories($newServiceTemplateId, $request);
            $this->storageEngine->commitTransaction();

            return $newServiceTemplateId;
        } catch (\Throwable $ex) {
            $this->storageEngine->rollbackTransaction();

            throw $ex;
        }
    }

    /**
     * @param int $serviceId
     * @param int|null $checkCommandId
     *
     * @throws \Throwable
     *
     * @return array{
     *      array<string,Macro>,
     *      array<string,CommandMacro>
     * }
     */
    private function findAllInheritedMacros(int $serviceId, ?int $checkCommandId): array
    {
        $serviceTemplateInheritances = $this->readServiceRepository->findParents($serviceId);
        $inheritanceLine = ServiceInheritance::createInheritanceLine(
            $serviceId,
            $serviceTemplateInheritances
        );
        $existingMacros = $this->readServiceMacroRepository->findByServiceIds(...$inheritanceLine);

        [, $inheritedMacros] = Macro::resolveInheritance($existingMacros, $inheritanceLine, $serviceId);

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

    /**
     * @param AddServiceRequest $request
     *
     * @throws \Throwable
     */
    private function assertServiceName(AddServiceRequest $request): void
    {
        $nameToCheck = new TrimmedString(Service::formatName($request->name));
        if ($this->isCloudPlatform) {
            $serviceNamesByHost = $this->readServiceRepository->findServiceNamesByHost($request->hostId);
            if ($serviceNamesByHost === null) {
                // Should not be called if this assertion is called after assertion on host IDs
                throw ServiceException::idDoesNotExist('host', $request->hostId);
            }

            if ($serviceNamesByHost->contains($nameToCheck)) {
                throw ServiceException::nameAlreadyExists((string) $nameToCheck, $request->hostId);
            }
        } else {
            $serviceNamesByHosts = $this->readServiceRepository->findServiceNamesByHosts($request->hostIds);
            foreach ($serviceNamesByHosts as $serviceNamesByHost) {
                if ($serviceNamesByHost->contains($nameToCheck)) {
                    throw ServiceException::nameAlreadyExists((string) $nameToCheck, $serviceNamesByHost->getHostId());
                }
            }
        }
    }
}
