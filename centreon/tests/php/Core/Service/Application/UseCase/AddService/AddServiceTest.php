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

namespace Tests\Core\Service\Application\UseCase\AddService;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Option\Option;
use Centreon\Domain\Option\OptionService;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\CommandMacro\Application\Repository\ReadCommandMacroRepositoryInterface;
use Core\Common\Application\Repository\ReadVaultRepositoryInterface;
use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
use Core\Common\Domain\YesNoDefault;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Macro\Application\Repository\ReadServiceMacroRepositoryInterface;
use Core\Macro\Application\Repository\WriteServiceMacroRepositoryInterface;
use Core\Macro\Domain\Model\Macro;
use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use Core\MonitoringServer\Application\Repository\WriteMonitoringServerRepositoryInterface;
use Core\MonitoringServer\Model\MonitoringServer;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Service\Application\Exception\ServiceException;
use Core\Service\Application\Repository\ReadServiceRepositoryInterface;
use Core\Service\Application\Repository\WriteRealTimeServiceRepositoryInterface;
use Core\Service\Application\Repository\WriteServiceRepositoryInterface;
use Core\Service\Application\UseCase\AddService\AddService;
use Core\Service\Application\UseCase\AddService\AddServiceRequest;
use Core\Service\Application\UseCase\AddService\AddServiceResponse;
use Core\Service\Application\UseCase\AddService\AddServiceValidation;
use Core\Service\Application\UseCase\AddService\MacroDto;
use Core\Service\Domain\Model\NotificationType;
use Core\Service\Domain\Model\Service;
use Core\ServiceCategory\Application\Repository\ReadServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Application\Repository\WriteServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Domain\Model\ServiceCategory;
use Core\ServiceGroup\Application\Repository\ReadServiceGroupRepositoryInterface;
use Core\ServiceGroup\Application\Repository\WriteServiceGroupRepositoryInterface;
use Core\ServiceGroup\Domain\Model\ServiceGroup;
use Core\ServiceGroup\Domain\Model\ServiceGroupRelation;
use Core\ServiceTemplate\Domain\Model\ServiceTemplateInheritance;
use Tests\Core\Service\Infrastructure\API\AddService\AddServicePresenterStub;

beforeEach(function (): void {
    $this->useCasePresenter = new AddServicePresenterStub(
        $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class));
    $this->addUseCase = new AddService(
        $this->readMonitoringServerRepository = $this->createMock(ReadMonitoringServerRepositoryInterface::class),
        $this->writeMonitoringServerRepository = $this->createMock(WriteMonitoringServerRepositoryInterface::class),
        $this->readServiceRepository = $this->createMock(ReadServiceRepositoryInterface::class),
        $this->writeServiceRepository = $this->createMock(WriteServiceRepositoryInterface::class),
        $this->readServiceMacroRepository = $this->createMock(ReadServiceMacroRepositoryInterface::class),
        $this->readCommandMacroRepository = $this->createMock(ReadCommandMacroRepositoryInterface::class),
        $this->writeServiceMacroRepository = $this->createMock(WriteServiceMacroRepositoryInterface::class),
        $this->storageEngine = $this->createMock(DataStorageEngineInterface::class),
        $this->readServiceCategoryRepository = $this->createMock(ReadServiceCategoryRepositoryInterface::class),
        $this->writeServiceCategoryRepository = $this->createMock(WriteServiceCategoryRepositoryInterface::class),
        $this->readServiceGroupRepository = $this->createMock(ReadServiceGroupRepositoryInterface::class),
        $this->writeServiceGroupRepository = $this->createMock(WriteServiceGroupRepositoryInterface::class),
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->validation = $this->createMock(AddServiceValidation::class),
        $this->optionService = $this->createMock(OptionService::class),
        $this->user = $this->createMock(ContactInterface::class),
        $this->isCloudPlatform = false,
        $this->writeVaultRepository = $this->createMock(WriteVaultRepositoryInterface::class),
        $this->readVaultRepository = $this->createMock(ReadVaultRepositoryInterface::class),
        $this->writeRealTimeServiceRepository = $this->createMock(WriteRealTimeServiceRepositoryInterface::class),
    );

    $this->request = new AddServiceRequest();

    $this->inheritanceModeOption = new Option();
    $this->inheritanceModeOption->setName('inheritanceMode')->setValue('1');
});

it('should present a ForbiddenResponse when the user has insufficient rights', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(false);

    ($this->addUseCase)(new AddServiceRequest(), $this->useCasePresenter);

    expect($this->useCasePresenter->response)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->useCasePresenter->response->getMessage())
        ->toBe(ServiceException::addNotAllowed()->getMessage());
});

it('should present an ErrorResponse when the service name already exists', function (): void {
    $fakeName = 'fake_name';
    $hostId = 1;

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->validation
        ->expects($this->once())
        ->method('assertServiceName')
        ->willThrowException(
            ServiceException::nameAlreadyExists(
                Service::formatName($fakeName),
                $hostId
            )
        );

    $this->request->name = $fakeName;
    $this->request->hostId = $hostId;
    ($this->addUseCase)($this->request, $this->useCasePresenter);
    expect($this->useCasePresenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->useCasePresenter->response->getMessage())
        ->toBe(ServiceException::nameAlreadyExists($fakeName, $hostId)->getMessage());
});

it('should present a ConflictResponse when the severity ID is not valid', function (): void {
    $request = new AddServiceRequest();
    $request->name = 'fake_name';
    $request->severityId = 1;

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidSeverity')
        ->willThrowException(ServiceException::idDoesNotExist('severity_id', $request->severityId));

    ($this->addUseCase)($request, $this->useCasePresenter);
    expect($this->useCasePresenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->useCasePresenter->response->getMessage())
        ->toBe(ServiceException::idDoesNotExist('severity_id', $request->severityId)->getMessage());
});

it('should present a ConflictResponse when the performance graph ID is not valid', function (): void {
    $request = new AddServiceRequest();
    $request->name = 'fake_name';
    $request->severityId = 1;
    $request->graphTemplateId = 1;

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidPerformanceGraph')
        ->willThrowException(ServiceException::idDoesNotExist('graph_template_id', $request->severityId));

    ($this->addUseCase)($request, $this->useCasePresenter);
    expect($this->useCasePresenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->useCasePresenter->response->getMessage())
        ->toBe(ServiceException::idDoesNotExist('graph_template_id', $request->severityId)->getMessage());
});

it('should present a ConflictResponse when the service template ID is not valid', function (): void {
    $request = new AddServiceRequest();
    $request->name = 'fake_name';
    $request->severityId = 1;
    $request->graphTemplateId = 1;
    $request->serviceTemplateParentId = 1;

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidServiceTemplate')
        ->willThrowException(ServiceException::idDoesNotExist('service_template_id', $request->severityId));

    ($this->addUseCase)($request, $this->useCasePresenter);
    expect($this->useCasePresenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->useCasePresenter->response->getMessage())
        ->toBe(ServiceException::idDoesNotExist('service_template_id', $request->severityId)->getMessage());
});

it('should present a ConflictResponse when the command ID is not valid', function (): void {
    $request = new AddServiceRequest();
    $request->name = 'fake_name';
    $request->severityId = 1;
    $request->graphTemplateId = 1;
    $request->serviceTemplateParentId = 1;
    $request->commandId = 1;

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidCommandForOnPremPlatform')
        ->willThrowException(ServiceException::idDoesNotExist('check_command_id', $request->severityId));

    ($this->addUseCase)($request, $this->useCasePresenter);
    expect($this->useCasePresenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->useCasePresenter->response->getMessage())
        ->toBe(ServiceException::idDoesNotExist('check_command_id', $request->severityId)->getMessage());
});

it('should present a ConflictResponse when the event handler ID is not valid', function (): void {
    $request = new AddServiceRequest();
    $request->name = 'fake_name';
    $request->severityId = 1;
    $request->graphTemplateId = 1;
    $request->serviceTemplateParentId = 1;
    $request->commandId = 1;
    $request->eventHandlerId = 12;

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidEventHandler')
        ->willThrowException(
            ServiceException::idDoesNotExist(
                'event_handler_command_id',
                $request->eventHandlerId
            )
        );

    ($this->addUseCase)($request, $this->useCasePresenter);
    expect($this->useCasePresenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->useCasePresenter->response->getMessage())
        ->toBe(
            ServiceException::idDoesNotExist(
                'event_handler_command_id',
                $request->eventHandlerId
            )->getMessage()
        );
});

it('should present a ConflictResponse when the time period ID is not valid', function (): void {
    $request = new AddServiceRequest();
    $request->name = 'fake_name';
    $request->severityId = 1;
    $request->graphTemplateId = 1;
    $request->serviceTemplateParentId = 1;
    $request->commandId = 1;
    $request->eventHandlerId = 12;
    $request->checkTimePeriodId = 13;

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidTimePeriod')
        ->willThrowException(
            ServiceException::idDoesNotExist(
                'check_timeperiod_id',
                $request->checkTimePeriodId
            )
        );

    ($this->addUseCase)($request, $this->useCasePresenter);
    expect($this->useCasePresenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->useCasePresenter->response->getMessage())
        ->toBe(
            ServiceException::idDoesNotExist(
                'check_timeperiod_id',
                $request->checkTimePeriodId
            )->getMessage()
        );
});

it('should present a ConflictResponse when the icon ID is not valid', function (): void {
    $request = new AddServiceRequest();
    $request->name = 'fake_name';
    $request->severityId = 1;
    $request->graphTemplateId = 1;
    $request->serviceTemplateParentId = 1;
    $request->commandId = 1;
    $request->eventHandlerId = 12;
    $request->checkTimePeriodId = 13;
    $request->notificationTimePeriodId = 14;
    $request->iconId = 15;

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidIcon')
        ->willThrowException(
            ServiceException::idDoesNotExist(
                'icon_id',
                $request->iconId
            )
        );

    ($this->addUseCase)($request, $this->useCasePresenter);
    expect($this->useCasePresenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->useCasePresenter->response->getMessage())
        ->toBe(
            ServiceException::idDoesNotExist(
                'icon_id',
                $request->iconId
            )->getMessage()
        );
});

it('should present a ConflictResponse when the host ID is not valid', function (): void {
    $request = new AddServiceRequest();
    $request->name = 'fake_name';
    $request->severityId = 1;
    $request->graphTemplateId = 1;
    $request->serviceTemplateParentId = 1;
    $request->commandId = 1;
    $request->eventHandlerId = 12;
    $request->checkTimePeriodId = 13;
    $request->notificationTimePeriodId = 14;
    $request->iconId = 15;
    $request->hostId = 2;

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidHost')
        ->willThrowException(
            ServiceException::idDoesNotExist(
                'host_id',
                $request->hostId
            )
        );

    ($this->addUseCase)($request, $this->useCasePresenter);
    expect($this->useCasePresenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->useCasePresenter->response->getMessage())
        ->toBe(
            ServiceException::idDoesNotExist(
                'host_id',
                $request->hostId
            )->getMessage()
        );
});

it('should present a ConflictResponse when the service category IDs are not valid', function (): void {
    $request = new AddServiceRequest();
    $request->name = 'fake_name';
    $request->severityId = 1;
    $request->graphTemplateId = 1;
    $request->serviceTemplateParentId = 1;
    $request->commandId = 1;
    $request->eventHandlerId = 12;
    $request->checkTimePeriodId = 13;
    $request->notificationTimePeriodId = 14;
    $request->iconId = 15;
    $request->serviceCategories = [2, 3];

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidServiceCategories')
        ->willThrowException(
            ServiceException::idsDoNotExist(
                'service_categories',
                [$request->serviceCategories[1]]
            )
        );

    ($this->addUseCase)($request, $this->useCasePresenter);
    expect($this->useCasePresenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->useCasePresenter->response->getMessage())
        ->toBe(
            ServiceException::idsDoNotExist(
                'service_categories',
                [$request->serviceCategories[1]]
            )->getMessage()
        );
});

it('should present a ConflictResponse when the service group IDs are not valid', function (): void {
    $request = new AddServiceRequest();
    $request->name = 'fake_name';
    $request->severityId = 1;
    $request->graphTemplateId = 1;
    $request->serviceTemplateParentId = 1;
    $request->commandId = 1;
    $request->eventHandlerId = 12;
    $request->checkTimePeriodId = 13;
    $request->notificationTimePeriodId = 14;
    $request->iconId = 15;
    $request->hostId = 4;
    $request->serviceGroups = [2, 3];

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidServiceGroups')
        ->willThrowException(
            ServiceException::idsDoNotExist(
                'service_groups',
                [$request->serviceGroups[1]]
            )
        );

    ($this->addUseCase)($request, $this->useCasePresenter);
    expect($this->useCasePresenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->useCasePresenter->response->getMessage())
        ->toBe(
            ServiceException::idsDoNotExist(
                'service_groups',
                [$request->serviceGroups[1]]
            )->getMessage()
        );
});

it('should present an ErrorResponse when an exception is thrown', function (): void {
    $request = new AddServiceRequest();
    $request->name = 'fake_name';
    $request->severityId = 1;
    $request->graphTemplateId = 1;
    $request->serviceTemplateParentId = 1;
    $request->commandId = 1;
    $request->eventHandlerId = 12;
    $request->checkTimePeriodId = 13;
    $request->notificationTimePeriodId = 14;
    $request->iconId = 15;
    $request->hostId = 4;

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->optionService
        ->expects($this->once())
        ->method('findSelectedOptions')
        ->willThrowException(new \Exception());

    ($this->addUseCase)($request, $this->useCasePresenter);
    expect($this->useCasePresenter->response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->useCasePresenter->response->getMessage())
        ->toBe(ServiceException::errorWhileAdding(new \Exception())->getMessage());
});

it('should present an AddServiceResponse when everything has gone well', function (): void {
    $request = new AddServiceRequest();
    $request->name = 'fake_name';
    $request->hostId = 1;
    $request->severityId = 1;
    $request->graphTemplateId = 1;
    $request->serviceTemplateParentId = 10;
    $request->commandId = 1;
    $request->eventHandlerId = 12;
    $request->checkTimePeriodId = 13;
    $request->notificationTimePeriodId = 14;
    $request->iconId = 15;
    $request->macros = [
        new MacroDto('MACROA', 'A', false, null),
        new MacroDto('MACROB', 'B', false, null),
    ];
    $request->serviceCategories = [12, 13];
    $request->serviceGroups = [15];

    $newServiceId = 99;
    $serviceTemplateInheritances = [
        new ServiceTemplateInheritance(9, 99),
        new ServiceTemplateInheritance(8, 9),
        new ServiceTemplateInheritance(1, 8),
    ];

    $categories = [
        $categoryA = new ServiceCategory(12, 'cat-name-A', 'cat-alias-A'),
        $categoryB = new ServiceCategory(13, 'cat-name-B', 'cat-alias-B'),
    ];

    $macroA = new Macro($newServiceId, 'MACROA', 'A');
    $macroB = new Macro($newServiceId, 'MACROB', 'B');

    $serviceGroup = new ServiceGroup(15, 'SG-name', 'SG-alias', null, '', true);
    $serviceGroupRelation = new ServiceGroupRelation(
        serviceGroupId: $serviceGroup->getId(),
        serviceId: $newServiceId,
        hostId: $request->hostId
    );

    $serviceFound = new Service(
        id: $newServiceId,
        name: $request->name,
        commandArguments: ['a', 'b'],
        eventHandlerArguments: ['c', 'd'],
        notificationTypes: [NotificationType::Unknown],
        hostId: 1,
        contactAdditiveInheritance: true,
        contactGroupAdditiveInheritance: true,
        isActivated: true,
        activeChecks: YesNoDefault::Yes,
        passiveCheck: YesNoDefault::No,
        volatility: YesNoDefault::Default,
        checkFreshness: YesNoDefault::Yes,
        eventHandlerEnabled: YesNoDefault::No,
        flapDetectionEnabled: YesNoDefault::Default,
        notificationsEnabled: YesNoDefault::Yes,
        comment: 'comment',
        note: 'note',
        noteUrl: 'note_url',
        actionUrl: 'action_url',
        iconAlternativeText: 'icon_aternative_text',
        graphTemplateId: $request->graphTemplateId,
        serviceTemplateParentId: $request->serviceTemplateParentId,
        commandId: $request->commandId,
        eventHandlerId: $request->eventHandlerId,
        notificationTimePeriodId: 6,
        checkTimePeriodId: $request->checkTimePeriodId,
        iconId: $request->iconId,
        severityId: $request->severityId,
        maxCheckAttempts: 5,
        normalCheckInterval: 1,
        retryCheckInterval: 3,
        freshnessThreshold: 1,
        lowFlapThreshold: 10,
        highFlapThreshold: 99,
        notificationInterval: $request->notificationTimePeriodId,
        recoveryNotificationDelay: 0,
        firstNotificationDelay: 0,
        acknowledgementTimeout: 0,
    );

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->exactly(2))
        ->method('isAdmin')
        ->willReturn(true);

    $this->validation->expects($this->once())->method('assertIsValidSeverity');
    $this->validation->expects($this->once())->method('assertIsValidPerformanceGraph');
    $this->validation->expects($this->once())->method('assertIsValidServiceTemplate');
    $this->validation->expects($this->once())->method('assertIsValidEventHandler');
    $this->validation->expects($this->once())->method('assertIsValidTimePeriod');
    $this->validation->expects($this->once())->method('assertIsValidNotificationTimePeriod');
    $this->validation->expects($this->once())->method('assertIsValidIcon');
    $this->validation->expects($this->once())->method('assertIsValidHost');
    $this->validation->expects($this->once())->method('assertIsValidCommandForOnPremPlatform');
    $this->validation->expects($this->once())->method('assertServiceName');
    $this->validation->expects($this->once())->method('assertIsValidServiceCategories');
    $this->validation->expects($this->once())->method('assertIsValidServiceGroups');

    $this->optionService
        ->expects($this->once())
        ->method('findSelectedOptions')
        ->willReturn(['inheritance_mode' => $this->inheritanceModeOption]);

    $this->writeServiceRepository
        ->expects($this->once())
        ->method('add')
        ->willReturn($newServiceId);

    $this->readServiceRepository
        ->expects($this->once())
        ->method('findParents')
        ->willReturn($serviceTemplateInheritances);
    $this->readServiceMacroRepository
        ->expects($this->exactly(2))
        ->method('findByServiceIds')
        ->willReturnMap(
            [
                [9, 8, 1, []],
                [$newServiceId, [$macroA, $macroB]],
            ],
        );
    $this->readCommandMacroRepository
        ->expects($this->once())
        ->method('findByCommandIdAndType')
        ->willReturn([]);
    $this->writeServiceMacroRepository
        ->expects($this->exactly(2))
        ->method('add');

    $this->writeServiceCategoryRepository
        ->expects($this->once())
        ->method('linkToService');

    $this->writeServiceGroupRepository
        ->expects($this->once())
        ->method('link');

    $this->readMonitoringServerRepository
        ->expects($this->once())
        ->method('findByHost')
        ->willReturn(new MonitoringServer(1, 'ms-name'));
    $this->writeMonitoringServerRepository
        ->expects($this->once())
        ->method('notifyConfigurationChange');

    $this->readServiceRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($serviceFound);
    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('findByService')
        ->willReturn($categories);
    $this->readServiceGroupRepository
        ->expects($this->once())
        ->method('findByService')
        ->willReturn([
            ['relation' => $serviceGroupRelation, 'serviceGroup' => $serviceGroup],
        ]);

    ($this->addUseCase)($request, $this->useCasePresenter);

    $dto = $this->useCasePresenter->response;
    expect($dto)->toBeInstanceOf(AddServiceResponse::class);
    expect($dto->id)->toBe($serviceFound->getId());
    expect($dto->name)->toBe($serviceFound->getName());
    expect($dto->comment)->toBe($serviceFound->getComment());
    expect($dto->hostId)->toBe($serviceFound->getHostId());
    expect($dto->serviceTemplateId)->toBe($serviceFound->getServiceTemplateParentId());
    expect($dto->commandId)->toBe($serviceFound->getCommandId());
    expect($dto->commandArguments)->toBe($serviceFound->getCommandArguments());
    expect($dto->checkTimePeriodId)->toBe($serviceFound->getCheckTimePeriodId());
    expect($dto->maxCheckAttempts)->toBe($serviceFound->getMaxCheckAttempts());
    expect($dto->normalCheckInterval)->toBe($serviceFound->getNormalCheckInterval());
    expect($dto->retryCheckInterval)->toBe($serviceFound->getRetryCheckInterval());
    expect($dto->activeChecks)->toBe($serviceFound->getActiveChecks());
    expect($dto->passiveCheck)->toBe($serviceFound->getPassiveCheck());
    expect($dto->volatility)->toBe($serviceFound->getVolatility());
    expect($dto->notificationsEnabled)->toBe($serviceFound->getNotificationsEnabled());
    expect($dto->isContactAdditiveInheritance)->toBe($serviceFound->isContactAdditiveInheritance());
    expect($dto->isContactGroupAdditiveInheritance)
        ->toBe($serviceFound->isContactGroupAdditiveInheritance());
    expect($dto->notificationInterval)->toBe($serviceFound->getNotificationInterval());
    expect($dto->notificationTimePeriodId)->toBe($serviceFound->getNotificationTimePeriodId());
    expect($dto->notificationTypes)->toBe($serviceFound->getNotificationTypes());
    expect($dto->firstNotificationDelay)->toBe($serviceFound->getFirstNotificationDelay());
    expect($dto->recoveryNotificationDelay)->toBe($serviceFound->getRecoveryNotificationDelay());
    expect($dto->acknowledgementTimeout)->toBe($serviceFound->getAcknowledgementTimeout());
    expect($dto->checkFreshness)->toBe($serviceFound->getCheckFreshness());
    expect($dto->freshnessThreshold)->toBe($serviceFound->getFreshnessThreshold());
    expect($dto->flapDetectionEnabled)->toBe($serviceFound->getFlapDetectionEnabled());
    expect($dto->lowFlapThreshold)->toBe($serviceFound->getLowFlapThreshold());
    expect($dto->highFlapThreshold)->toBe($serviceFound->getHighFlapThreshold());
    expect($dto->eventHandlerEnabled)->toBe($serviceFound->getEventHandlerEnabled());
    expect($dto->eventHandlerId)->toBe($serviceFound->getEventHandlerId());
    expect($dto->eventHandlerArguments)->toBe($serviceFound->getEventHandlerArguments());
    expect($dto->graphTemplateId)->toBe($serviceFound->getGraphTemplateId());
    expect($dto->note)->toBe($serviceFound->getNote());
    expect($dto->noteUrl)->toBe($serviceFound->getNoteUrl());
    expect($dto->actionUrl)->toBe($serviceFound->getActionUrl());
    expect($dto->iconId)->toBe($serviceFound->getIconId());
    expect($dto->iconAlternativeText)->toBe($serviceFound->getIconAlternativeText());
    expect($dto->severityId)->toBe($serviceFound->getSeverityId());
    expect($dto->isActivated)->toBe($serviceFound->isActivated());
    foreach ($dto->macros as $index => $expectedMacro) {
        expect($expectedMacro->name)->toBe($request->macros[$index]->name)
            ->and($expectedMacro->value)->toBe($request->macros[$index]->value)
            ->and($expectedMacro->isPassword)->toBe($request->macros[$index]->isPassword)
            ->and($expectedMacro->description)->toBe('');
    }
    expect($dto->groups)->toBe(
       [['id' => $serviceGroup->getId(), 'name' => $serviceGroup->getName()]]
    );
    expect($dto->categories)->toBe(
       [
           ['id' => $categoryA->getId(), 'name' => $categoryA->getName()],
           ['id' => $categoryB->getId(), 'name' => $categoryB->getName()],
       ]
    );
});
