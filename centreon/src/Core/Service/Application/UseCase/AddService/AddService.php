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
use Core\CommandMacro\Application\Repository\ReadCommandMacroRepositoryInterface;
use Core\CommandMacro\Domain\Model\CommandMacro;
use Core\CommandMacro\Domain\Model\CommandMacroType;
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
use Core\Service\Application\Repository\ReadServiceRepositoryInterface;
use Core\Service\Application\Repository\WriteRealTimeServiceRepositoryInterface;
use Core\Service\Application\Repository\WriteServiceRepositoryInterface;
use Core\Service\Domain\Model\NewService;
use Core\Service\Domain\Model\Service;
use Core\Service\Domain\Model\ServiceInheritance;
use Core\ServiceCategory\Application\Repository\ReadServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Application\Repository\WriteServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Domain\Model\ServiceCategory;
use Core\ServiceGroup\Application\Repository\ReadServiceGroupRepositoryInterface;
use Core\ServiceGroup\Application\Repository\WriteServiceGroupRepositoryInterface;
use Core\ServiceGroup\Domain\Model\ServiceGroup;
use Core\ServiceGroup\Domain\Model\ServiceGroupRelation;

final class AddService
{
    use LoggerTrait;

    /** @var AccessGroup[] */
    private array $accessGroups = [];

    public function __construct(
        private readonly ReadMonitoringServerRepositoryInterface $readMonitoringServerRepository,
        private readonly WriteMonitoringServerRepositoryInterface $writeMonitoringServerRepository,
        private readonly ReadServiceRepositoryInterface $readServiceRepository,
        private readonly WriteServiceRepositoryInterface $writeServiceRepository,
        private readonly ReadServiceMacroRepositoryInterface $readServiceMacroRepository,
        private readonly ReadCommandMacroRepositoryInterface $readCommandMacroRepository,
        private readonly WriteServiceMacroRepositoryInterface $writeServiceMacroRepository,
        private readonly DataStorageEngineInterface $storageEngine,
        private readonly ReadServiceCategoryRepositoryInterface $readServiceCategoryRepository,
        private readonly WriteServiceCategoryRepositoryInterface $writeServiceCategoryRepository,
        private readonly ReadServiceGroupRepositoryInterface $readServiceGroupRepository,
        private readonly WriteServiceGroupRepositoryInterface $writeServiceGroupRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly AddServiceValidation $validation,
        private readonly OptionService $optionService,
        private readonly ContactInterface $user,
        private readonly bool $isCloudPlatform,
        private readonly WriteRealTimeServiceRepositoryInterface $writeRealTimeServiceRepository,
    ) {
    }

    /**
     * @param AddServiceRequest $request
     * @param AddServicePresenterInterface $presenter
     */
    public function __invoke(AddServiceRequest $request, AddServicePresenterInterface $presenter): void
    {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_SERVICES_WRITE)) {
                $this->error(
                    "User doesn't have sufficient rights to add a service",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->presentResponse(
                    new ForbiddenResponse(ServiceException::addNotAllowed())
                );

                return;
            }

            if (! $this->user->isAdmin()) {
                $this->accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
                $this->validation->accessGroups = $this->accessGroups;
            }

            $this->assertParameters($request);
            $newServiceId = $this->createService($request);

            if ($this->accessGroups !== []) {
                $this->writeRealTimeServiceRepository->addServiceToResourceAcls(
                    $request->hostId,
                    $newServiceId,
                    $this->accessGroups
                );
            }

            $this->info('New service created', ['service_id' => $newServiceId]);
            $service = $this->readServiceRepository->findById($newServiceId);
            if (! $service) {
                $presenter->presentResponse(
                    new ErrorResponse(ServiceException::errorWhileRetrieving())
                );

                return;
            }
            if ($this->user->isAdmin()) {
                $serviceCategories = $this->readServiceCategoryRepository->findByService($newServiceId);
                $serviceGroups = $this->readServiceGroupRepository->findByService($newServiceId);
            } else {
                $serviceCategories = $this->readServiceCategoryRepository->findByServiceAndAccessGroups(
                    $newServiceId,
                    $this->accessGroups
                );
                $serviceGroups = $this->readServiceGroupRepository->findByServiceAndAccessGroups(
                    $newServiceId,
                    $this->accessGroups
                );
            }

            $presenter->presentResponse($this->createResponse($service, $serviceCategories, $serviceGroups));
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
        $inheritanceMode = isset($inheritanceMode[0])
            ? (int) $inheritanceMode[0]->getValue()
            : 0;

        return NewServiceFactory::create((int) $inheritanceMode, $request, $this->isCloudPlatform);
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
     * @param int $serviceId
     * @param AddServiceRequest $request
     *
     * @throws \Throwable
     */
    private function linkServiceToServiceGroups(int $serviceId, AddServiceRequest $request): void
    {
        if (empty($request->serviceGroups)) {

            return;
        }

        $this->info(
            'Link existing service groups to service',
            ['service_groups' => $request->serviceGroups]
        );

        $serviceGroupRelations = [];
        foreach ($request->serviceGroups as $serviceGroupId) {
            $serviceGroupRelations[] = new ServiceGroupRelation(
                $serviceGroupId,
                $serviceId,
                $request->hostId
            );
        }

        $this->writeServiceGroupRepository->link($serviceGroupRelations);
    }

    /**
     * @param Service $service
     * @param ServiceCategory[] $serviceCategories
     * @param array<array{relation:ServiceGroupRelation,serviceGroup:ServiceGroup}> $serviceGroups
     *
     * @throws \Throwable
     *
     * @return AddServiceResponse
     */
    private function createResponse(Service $service, array $serviceCategories, array $serviceGroups): AddServiceResponse
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
        $response->hostId = $service->getHostId();
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
        $response->geoCoords = $service->getGeoCoords()?->__toString();
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

        $response->groups = array_map(
            fn(array $group) => [
                'id' => $group['serviceGroup']->getId(),
                'name' => $group['serviceGroup']->getName(),
            ],
            $serviceGroups,
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
        $this->validation->assertIsValidSeverity($request->severityId);
        $this->validation->assertIsValidPerformanceGraph($request->graphTemplateId);
        $this->validation->assertIsValidServiceTemplate($request->serviceTemplateParentId);
        $this->validation->assertIsValidEventHandler($request->eventHandlerId);
        $this->validation->assertIsValidTimePeriod($request->checkTimePeriodId);
        $this->validation->assertIsValidNotificationTimePeriod($request->notificationTimePeriodId);
        $this->validation->assertIsValidIcon($request->iconId);
        $this->validation->assertIsValidHost($request->hostId);
        $this->validation->assertIsValidServiceCategories($request->serviceCategories);
        // No assertion on the check command for Saas platform as it will be inherited from the service template.
        if (! $this->isCloudPlatform) {
            $this->validation->assertIsValidCommandForOnPremPlatform($request->commandId, $request->serviceTemplateParentId);
        }

        // Should be called after assertion on host IDs
        $this->validation->assertServiceName($request);
        $this->validation->assertIsValidServiceGroups($request->serviceGroups, $request->hostId);
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
            $newServiceId = $this->writeServiceRepository->add($newServiceTemplate);
            $this->addMacros($newServiceId, $request);
            $this->linkServiceToServiceCategories($newServiceId, $request);
            $this->linkServiceToServiceGroups($newServiceId, $request);

            if (($monitoringServer = $this->readMonitoringServerRepository->findByHost($request->hostId)))
            {
                $this->writeMonitoringServerRepository->notifyConfigurationChange($monitoringServer->getId());
            }

            $this->storageEngine->commitTransaction();

            return $newServiceId;
        } catch (\Throwable $ex) {
            $this->error("Rollback of 'Add Service' transaction.");

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
}
