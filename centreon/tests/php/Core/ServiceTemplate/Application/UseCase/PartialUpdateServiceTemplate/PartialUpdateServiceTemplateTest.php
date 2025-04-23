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

namespace Tests\Core\ServiceTemplate\Application\UseCase\PartialUpdateServiceTemplate;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Option\OptionService;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Command\Application\Repository\ReadCommandRepositoryInterface;
use Core\CommandMacro\Application\Repository\ReadCommandMacroRepositoryInterface;
use Core\CommandMacro\Domain\Model\CommandMacroType;
use Core\Common\Application\Repository\ReadVaultRepositoryInterface;
use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
use Core\Common\Domain\YesNoDefault;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Macro\Application\Repository\ReadServiceMacroRepositoryInterface;
use Core\Macro\Application\Repository\WriteServiceMacroRepositoryInterface;
use Core\Macro\Domain\Model\Macro;
use Core\PerformanceGraph\Application\Repository\ReadPerformanceGraphRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\ServiceCategory\Application\Repository\ReadServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Application\Repository\WriteServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Domain\Model\ServiceCategory;
use Core\ServiceGroup\Application\Repository\ReadServiceGroupRepositoryInterface;
use Core\ServiceGroup\Application\Repository\WriteServiceGroupRepositoryInterface;
use Core\ServiceSeverity\Application\Repository\ReadServiceSeverityRepositoryInterface;
use Core\ServiceTemplate\Application\Exception\ServiceTemplateException;
use Core\ServiceTemplate\Application\Repository\ReadServiceTemplateRepositoryInterface;
use Core\ServiceTemplate\Application\Repository\WriteServiceTemplateRepositoryInterface;
use Core\ServiceTemplate\Application\UseCase\PartialUpdateServiceTemplate\MacroDto;
use Core\ServiceTemplate\Application\UseCase\PartialUpdateServiceTemplate\ParametersValidation;
use Core\ServiceTemplate\Application\UseCase\PartialUpdateServiceTemplate\PartialUpdateServiceTemplate;
use Core\ServiceTemplate\Application\UseCase\PartialUpdateServiceTemplate\PartialUpdateServiceTemplateRequest;
use Core\ServiceTemplate\Application\UseCase\PartialUpdateServiceTemplate\ServiceGroupDto;
use Core\ServiceTemplate\Domain\Model\NotificationType;
use Core\ServiceTemplate\Domain\Model\ServiceTemplate;
use Core\ServiceTemplate\Domain\Model\ServiceTemplateInheritance;
use Core\ServiceTemplate\Infrastructure\Model\NotificationTypeConverter;
use Core\ServiceTemplate\Infrastructure\Model\YesNoDefaultConverter;
use Core\TimePeriod\Application\Repository\ReadTimePeriodRepositoryInterface;
use Core\ViewImg\Application\Repository\ReadViewImgRepositoryInterface;
use Exception;

beforeEach(closure: function (): void {
    $this->presenter = new DefaultPresenter(
        $this->createMock(PresenterFormatterInterface::class)
    );

    $this->useCase = new PartialUpdateServiceTemplate(
        $this->writeServiceTemplateRepository = $this->createMock(WriteServiceTemplateRepositoryInterface::class),
        $this->readServiceCategoryRepository = $this->createMock(ReadServiceCategoryRepositoryInterface::class),
        $this->writeServiceCategoryRepository = $this->createMock(WriteServiceCategoryRepositoryInterface::class),
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->readServiceTemplateRepository = $this->createMock(ReadServiceTemplateRepositoryInterface::class),
        $this->readServiceMacroRepository = $this->createMock(ReadServiceMacroRepositoryInterface::class),
        $this->writeServiceMacroRepository = $this->createMock(WriteServiceMacroRepositoryInterface::class),
        $this->readCommandMacroRepository = $this->createMock(ReadCommandMacroRepositoryInterface::class),
        $this->writeServiceGroupRepository = $this->createMock(WriteServiceGroupRepositoryInterface::class),
        $this->readServiceGroupRepository = $this->createMock(ReadServiceGroupRepositoryInterface::class),
        $this->validation = $this->createMock(ParametersValidation::class),
        $this->user = $this->createMock(ContactInterface::class),
        $this->storageEngine = $this->createMock(DataStorageEngineInterface::class),
        $this->optionService = $this->createMock(OptionService::class),
        $this->writeVaultRepository = $this->createMock(WriteVaultRepositoryInterface::class),
        $this->readVaultRepository = $this->createMock(ReadVaultRepositoryInterface::class),
    );
});

it('should present a ForbiddenResponse when the user has insufficient rights', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, false],
            ]
        );

    ($this->useCase)(new PartialUpdateServiceTemplateRequest(1), $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(ServiceTemplateException::updateNotAllowed()->getMessage());
});

it('should present a NotFoundResponse when the service template does not exist', function (): void {
    $request = new PartialUpdateServiceTemplateRequest(1);
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, true],
            ]
        );

    $accessGroups = [2];

    $this->readAccessGroupRepository
        ->expects($this->any())
        ->method('findByContact')
        ->with($this->user)
        ->willReturn($accessGroups);

    $this->readServiceTemplateRepository
        ->expects($this->once())
        ->method('findByIdAndAccessGroups')
        ->with($request->id)
        ->willReturn(null);

    ($this->useCase)($request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe((new NotFoundResponse('Service template'))->getMessage());
});

it('should present a ConflictResponse when a host template does not exist', function (): void {
    $request = new PartialUpdateServiceTemplateRequest(1);
    $request->hostTemplates = [1, 8];
    $accessGroups = [9, 11];

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, true],
            ]
        );

    $this->readAccessGroupRepository
        ->expects($this->once())
        ->method('findByContact')
        ->with($this->user)
        ->willReturn($accessGroups);

    $this->readServiceTemplateRepository
        ->expects($this->once())
        ->method('findByIdAndAccessGroups')
        ->with($request->id)
        ->willReturn(new ServiceTemplate(1, 'fake_name', 'fake_alias'));

    $exception = ServiceTemplateException::idsDoNotExist('host_templates', [$request->hostTemplates[1]]);
    $this->validation
        ->expects($this->once())
        ->method('assertHostTemplateIds')
        ->with($request->hostTemplates)
        ->willThrowException($exception);

    ($this->useCase)($request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(ServiceTemplateException::idsDoNotExist('host_templates', [$request->hostTemplates[1]])->getMessage());
});

it('should present an ErrorResponse when an error occurs during host templates unlink', function (): void {
    $request = new PartialUpdateServiceTemplateRequest(1);
    $request->hostTemplates = [1, 8];
    $accessGroups = [9, 11];

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, true],
            ]
        );

    $this->readAccessGroupRepository
        ->expects($this->once())
        ->method('findByContact')
        ->with($this->user)
        ->willReturn($accessGroups);

    $this->readServiceTemplateRepository
        ->expects($this->once())
        ->method('findByIdAndAccessGroups')
        ->with($request->id)
        ->willReturn(new ServiceTemplate(1, 'fake_name', 'fake_alias'));

    $this->validation
        ->expects($this->once())
        ->method('assertHostTemplateIds')
        ->with($request->hostTemplates);

    $this->writeServiceTemplateRepository
        ->expects($this->once())
        ->method('unlinkHosts')
        ->with($request->id)
        ->willThrowException(new Exception());

    ($this->useCase)($request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(ServiceTemplateException::errorWhileUpdating()->getMessage());
});

it('should present a ErrorResponse when an error occurs during host templates link', function (): void {
    $request = new PartialUpdateServiceTemplateRequest(1);
    $request->hostTemplates = [1, 8];
    $accessGroups = [9, 11];

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, true],
            ]
        );

    $this->readAccessGroupRepository
        ->expects($this->once())
        ->method('findByContact')
        ->with($this->user)
        ->willReturn($accessGroups);

    $this->readServiceTemplateRepository
        ->expects($this->once())
        ->method('findByIdAndAccessGroups')
        ->with($request->id)
        ->willReturn(new ServiceTemplate(1, 'fake_name', 'fake_alias'));

    $this->validation
        ->expects($this->once())
        ->method('assertHostTemplateIds')
        ->with($request->hostTemplates);

    $this->writeServiceTemplateRepository
        ->expects($this->once())
        ->method('unlinkHosts')
        ->with($request->id);

    $this->writeServiceTemplateRepository
        ->expects($this->once())
        ->method('linkToHosts')
        ->with($request->id, $request->hostTemplates)
        ->willThrowException(new Exception());

    ($this->useCase)($request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(ServiceTemplateException::errorWhileUpdating()->getMessage());
});

it('should present a ErrorResponse when an error occurs during service groups link', function (): void {
    $request = new PartialUpdateServiceTemplateRequest(1);
    $request->serviceGroups = [new ServiceGroupDto(1, 2)];

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, true],
            ]
        );

    $this->readServiceTemplateRepository
        ->expects($this->once())
        ->method('findById')
        ->with($request->id)
        ->willReturn(new ServiceTemplate(1, 'fake_name', 'fake_alias'));

    $this->user
        ->expects($this->exactly(2))
        ->method('isAdmin')
        ->willReturn(true);

    $this->readServiceGroupRepository
        ->expects($this->once())
        ->method('findByService')
        ->willThrowException(new Exception());

    ($this->useCase)($request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(ServiceTemplateException::errorWhileUpdating()->getMessage());
});

it('should present a NoContentResponse when everything has gone well for an admin user', function (): void {
    $request = new PartialUpdateServiceTemplateRequest(20);
    $request->name = 'fake_name2';
    $request->alias = 'fake_alias2';
    $request->commandArguments = ['A'];
    $request->eventHandlerArguments = ['B'];
    $notificationTypes = [NotificationType::DowntimeScheduled, NotificationType::Flapping];
    $request->notificationTypes = NotificationTypeConverter::toBits($notificationTypes);
    $request->isContactAdditiveInheritance = true;
    $request->isContactGroupAdditiveInheritance = true;
    $request->activeChecksEnabled = YesNoDefaultConverter::toInt(YesNoDefault::No);
    $request->passiveCheckEnabled = YesNoDefaultConverter::toInt(YesNoDefault::Yes);
    $request->volatility = YesNoDefaultConverter::toInt(YesNoDefault::No);
    $request->checkFreshness = YesNoDefaultConverter::toInt(YesNoDefault::Yes);
    $request->eventHandlerEnabled = YesNoDefaultConverter::toInt(YesNoDefault::No);
    $request->flapDetectionEnabled = YesNoDefaultConverter::toInt(YesNoDefault::Yes);
    $request->notificationsEnabled = YesNoDefaultConverter::toInt(YesNoDefault::No);
    $request->comment = 'new comment';
    $request->note = 'new note';
    $request->noteUrl = 'new note url';
    $request->actionUrl = 'new action url';
    $request->iconAlternativeText = 'icon alternative text';
    $request->graphTemplateId = 100;
    $request->serviceTemplateParentId = 101;
    $request->commandId = 102;
    $request->eventHandlerId = 103;
    $request->notificationTimePeriodId = 104;
    $request->checkTimePeriodId = 105;
    $request->iconId = 106;
    $request->severityId = 107;
    $request->maxCheckAttempts = 108;
    $request->normalCheckInterval = 109;
    $request->retryCheckInterval = 110;
    $request->freshnessThreshold = 111;
    $request->lowFlapThreshold = 48;
    $request->highFlapThreshold = 52;
    $request->notificationInterval = 112;
    $request->recoveryNotificationDelay = 113;
    $request->firstNotificationDelay = 114;
    $request->acknowledgementTimeout = 115;
    $request->hostTemplates = [1, 8];
    $request->serviceCategories = [2, 3];
    $request->macros = [
        new MacroDto('MACROA', 'A', false, null),
        new MacroDto('MACROB', 'B1', false, null),
    ];

    $serviceTemplate = new ServiceTemplate(
        id: $request->id,
        name: 'fake_name',
        alias: 'fake_alias',
        commandId: 99,
    );

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, true],
            ]
        );

    $this->readServiceTemplateRepository
        ->expects($this->once())
        ->method('findById')
        ->with($request->id)
        ->willReturn($serviceTemplate);

    $this->writeServiceTemplateRepository
        ->expects($this->once())
        ->method('unlinkHosts')
        ->with($request->id);

    $this->writeServiceTemplateRepository
        ->expects($this->once())
        ->method('linkToHosts')
        ->with($request->id, $request->hostTemplates);

    $this->user
        ->expects($this->exactly(2))
        ->method('isAdmin')
        ->willReturn(true);

    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('findByService')
        ->with($request->id)
        ->willReturn(
            array_map(
                fn (int $id): ServiceCategory => new ServiceCategory($id, 'name', 'alias'),
                $request->serviceCategories
            )
        );

    $this->writeServiceCategoryRepository
        ->expects($this->once())
        ->method('unlinkFromService')
        ->with($request->id, []);

    $this->writeServiceCategoryRepository
        ->expects($this->once())
        ->method('linkToService')
        ->with($request->id, []);

    $serviceTemplateInheritances = [
        new ServiceTemplateInheritance(9, $serviceTemplate->getId()),
        new ServiceTemplateInheritance(8, 9),
        new ServiceTemplateInheritance(1, 8),
    ];

    $this->readServiceTemplateRepository
        ->expects($this->once())
        ->method('findParents')
        ->with($request->id)
        ->willReturn($serviceTemplateInheritances);

    $macroA = new Macro($serviceTemplate->getId(), 'MACROA', 'A');
    $macroA->setDescription('');

    $macroB = new Macro($serviceTemplate->getId(), 'MACROB', 'B');
    $macroB->setDescription('');

    $this->readServiceMacroRepository
        ->expects($this->once())
        ->method('findByServiceIds')
        ->with($serviceTemplate->getId(), 9, 8, 1)
        ->willReturn([$macroA, $macroB]);

    $this->readCommandMacroRepository
        ->expects($this->once())
        ->method('findByCommandIdAndType')
        ->with($request->commandId, CommandMacroType::Service)
        ->willReturn([]);

    $this->writeServiceMacroRepository
        ->expects($this->once())
        ->method('update')
        ->with(new Macro($serviceTemplate->getId(), 'MACROB', 'B1'));

    $this->writeServiceMacroRepository
        ->expects($this->never())
        ->method('add');

    $this->writeServiceMacroRepository
        ->expects($this->never())
        ->method('delete');

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidName')
        ->with($serviceTemplate->getName(), $request->name);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidPerformanceGraph')
        ->with($request->graphTemplateId);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidServiceTemplate')
        ->with($request->serviceTemplateParentId);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidCommand')
        ->with($request->commandId);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidEventHandler')
        ->with($request->eventHandlerId);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidNotificationTimePeriod')
        ->with($request->notificationTimePeriodId);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidTimePeriod')
        ->with($request->checkTimePeriodId);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidIcon')
        ->with($request->iconId);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidSeverity')
        ->with($request->severityId);

    $this->validation
        ->expects($this->once())
        ->method('assertHostTemplateIds')
        ->with($request->hostTemplates);

    $this->validation
        ->expects($this->once())
        ->method('assertServiceCategories')
        ->with($request->serviceCategories, $this->user, []);

    $this->writeServiceTemplateRepository
        ->expects($this->once())
        ->method('update')
        ->with($serviceTemplate);

    ($this->useCase)($request, $this->presenter);

    expect($serviceTemplate->getName())->toBe($request->name)
        ->and($serviceTemplate->getAlias())->toBe($request->alias)
        ->and($serviceTemplate->getCommandArguments())->toBe($request->commandArguments)
        ->and($serviceTemplate->getEventHandlerArguments())->toBe($request->eventHandlerArguments)
        ->and(
            NotificationTypeConverter::toBits(
                $serviceTemplate->getNotificationTypes()
            )
        )->toBe(NotificationTypeConverter::toBits($notificationTypes))
        ->and($serviceTemplate->isContactAdditiveInheritance())->toBe(false)
        ->and($serviceTemplate->isContactGroupAdditiveInheritance())->toBe(false)
        ->and($serviceTemplate->getActiveChecks())->toBe(
            \Core\ServiceTemplate\Application\Model\YesNoDefaultConverter::fromInt($request->activeChecksEnabled)
        )->and($serviceTemplate->getPassiveCheck())->toBe(
            \Core\ServiceTemplate\Application\Model\YesNoDefaultConverter::fromInt($request->passiveCheckEnabled)
        )->and($serviceTemplate->getVolatility())->toBe(
            \Core\ServiceTemplate\Application\Model\YesNoDefaultConverter::fromInt($request->volatility)
        )->and($serviceTemplate->getCheckFreshness())->toBe(
            \Core\ServiceTemplate\Application\Model\YesNoDefaultConverter::fromInt($request->checkFreshness)
        )->and($serviceTemplate->getEventHandlerEnabled())->toBe(
            \Core\ServiceTemplate\Application\Model\YesNoDefaultConverter::fromInt($request->eventHandlerEnabled)
        )->and($serviceTemplate->getFlapDetectionEnabled())->toBe(
            \Core\ServiceTemplate\Application\Model\YesNoDefaultConverter::fromInt($request->flapDetectionEnabled)
        )->and($serviceTemplate->getNotificationsEnabled())->toBe(
            \Core\ServiceTemplate\Application\Model\YesNoDefaultConverter::fromInt($request->notificationsEnabled)
        )->and($serviceTemplate->getComment())->toBe($request->comment)
        ->and($serviceTemplate->getNote())->toBe($request->note)
        ->and($serviceTemplate->getNoteUrl())->toBe($request->noteUrl)
        ->and($serviceTemplate->getActionUrl())->toBe($request->actionUrl)
        ->and($serviceTemplate->getIconAlternativeText())->toBe($request->iconAlternativeText)
        ->and($serviceTemplate->getGraphTemplateId())->toBe($request->graphTemplateId)
        ->and($serviceTemplate->getServiceTemplateParentId())->toBe($request->serviceTemplateParentId)
        ->and($serviceTemplate->getCommandId())->toBe($request->commandId)
        ->and($serviceTemplate->getEventHandlerId())->toBe($request->eventHandlerId)
        ->and($serviceTemplate->getNotificationTimePeriodId())->toBe($request->notificationTimePeriodId)
        ->and($serviceTemplate->getCheckTimePeriodId())->toBe($request->checkTimePeriodId)
        ->and($serviceTemplate->getIconId())->toBe($request->iconId)
        ->and($serviceTemplate->getSeverityId())->toBe($request->severityId)
        ->and($serviceTemplate->getMaxCheckAttempts())->toBe($request->maxCheckAttempts)
        ->and($serviceTemplate->getNormalCheckInterval())->toBe($request->normalCheckInterval)
        ->and($serviceTemplate->getRetryCheckInterval())->toBe($request->retryCheckInterval)
        ->and($serviceTemplate->getFreshnessThreshold())->toBe($request->freshnessThreshold)
        ->and($serviceTemplate->getLowFlapThreshold())->toBe($request->lowFlapThreshold)
        ->and($serviceTemplate->getHighFlapThreshold())->toBe($request->highFlapThreshold)
        ->and($serviceTemplate->getNotificationInterval())->toBe($request->notificationInterval)
        ->and($serviceTemplate->getRecoveryNotificationDelay())->toBe($request->recoveryNotificationDelay)
        ->and($serviceTemplate->getFirstNotificationDelay())->toBe($request->firstNotificationDelay)
        ->and($serviceTemplate->getAcknowledgementTimeout())->toBe($request->acknowledgementTimeout);
});

it('should present a NoContentResponse when everything has gone well for a non-admin user', function (): void {
    $request = new PartialUpdateServiceTemplateRequest(1);
    $request->hostTemplates = [1, 8];
    $request->serviceCategories = [2, 3];
    $accessGroups = [9, 11];

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, true],
            ]
        );

    $serviceTemplate = new ServiceTemplate(1, 'fake_name', 'fake_alias');

    $this->user
        ->expects($this->exactly(2))
        ->method('isAdmin')
        ->willReturn(false);

    $this->readAccessGroupRepository
        ->expects($this->once())
        ->method('findByContact')
        ->with($this->user)
        ->willReturn($accessGroups);

    $this->readServiceTemplateRepository
        ->expects($this->once())
        ->method('findByIdAndAccessGroups')
        ->with($request->id, $accessGroups)
        ->willReturn($serviceTemplate);

    $this->writeServiceTemplateRepository
        ->expects($this->once())
        ->method('unlinkHosts')
        ->with($request->id);

    $this->writeServiceTemplateRepository
        ->expects($this->once())
        ->method('linkToHosts')
        ->with($request->id, $request->hostTemplates);

    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('findByServiceAndAccessGroups')
        ->with($request->id, $accessGroups)
        ->willReturn(
            array_map(
                fn (int $id): ServiceCategory => new ServiceCategory($id, 'name', 'alias'),
                $request->serviceCategories
            )
        );

    $this->writeServiceCategoryRepository
        ->expects($this->once())
        ->method('unlinkFromService')
        ->with($request->id, []);

    $this->writeServiceCategoryRepository
        ->expects($this->once())
        ->method('linkToService')
        ->with($request->id, []);

    $this->validation
        ->expects($this->once())
        ->method('assertHostTemplateIds')
        ->with($request->hostTemplates);

    $this->validation
        ->expects($this->once())
        ->method('assertServiceCategories')
        ->with($request->serviceCategories, $this->user, $accessGroups);

    ($this->useCase)($request, $this->presenter);

    expect($this->presenter->getResponseStatus())->toBeInstanceOf(NoContentResponse::class);
});
