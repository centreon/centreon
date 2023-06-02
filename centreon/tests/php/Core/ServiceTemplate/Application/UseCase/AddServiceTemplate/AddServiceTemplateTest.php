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

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Option\OptionService;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Command\Application\Repository\ReadCommandRepositoryInterface;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\PerformanceGraph\Application\Repository\ReadPerformanceGraphRepositoryInterface;
use Core\ServiceSeverity\Application\Repository\ReadServiceSeverityRepositoryInterface;
use Core\ServiceTemplate\Application\Exception\ServiceTemplateException;
use Core\ServiceTemplate\Application\Repository\ReadServiceTemplateRepositoryInterface;
use Core\ServiceTemplate\Application\Repository\WriteServiceTemplateRepositoryInterface;
use Core\ServiceTemplate\Application\UseCase\AddServiceTemplate\AddServiceTemplateRequest;
use Core\TimePeriod\Application\Repository\ReadTimePeriodRepositoryInterface;
use Core\ViewImg\Application\Repository\ReadViewImgRepositoryInterface;
use Core\ServiceTemplate\Application\UseCase\AddServiceTemplate\AddServiceTemplate;
use Tests\Core\ServiceTemplate\Infrastructure\API\AddServiceTemplate\AddServiceTemplatePresenterStub;
use Core\Application\Common\UseCase\InvalidArgumentResponse;

beforeEach(closure: function (): void {
    $this->readServiceTemplateRepository = $this->createMock(ReadServiceTemplateRepositoryInterface::class);
    $this->writeServiceTemplateRepository = $this->createMock(WriteServiceTemplateRepositoryInterface::class);
    $this->serviceSeverityRepository = $this->createMock(ReadServiceSeverityRepositoryInterface::class);
    $this->performanceGraphRepository = $this->createMock(ReadPerformanceGraphRepositoryInterface::class);
    $this->commandRepository = $this->createMock(ReadCommandRepositoryInterface::class);
    $this->timePeriodRepository = $this->createMock(ReadTimePeriodRepositoryInterface::class);
    $this->imageRepository = $this->createMock(ReadViewImgRepositoryInterface::class);
    $this->optionService = $this->createMock(OptionService::class);
    $this->user = $this->createMock(ContactInterface::class);
    $this->presenter = new AddServiceTemplatePresenterStub(
        $this->createMock(PresenterFormatterInterface::class)
    );

    $this->useCase = new AddServiceTemplate(
        $this->readServiceTemplateRepository,
        $this->writeServiceTemplateRepository,
        $this->serviceSeverityRepository,
        $this->performanceGraphRepository,
        $this->commandRepository,
        $this->timePeriodRepository,
        $this->imageRepository,
        $this->optionService,
        $this->user
    );
});

function createAddServiceTemplateRequest(): AddServiceTemplateRequest
{
    $request = new AddServiceTemplateRequest();
    $request->name = 'fake_name';
    $request->alias = 'fake_alias';
    $request->comment = null;
    $request->note = null;
    $request->noteUrl = null;
    $request->actionUrl = null;
    $request->iconAlternativeText = null;
    $request->graphTemplateId = null;
    $request->serviceTemplateParentId = null;
    $request->commandId = null;
    $request->eventHandlerId = null;
    $request->notificationTimePeriodId = null;
    $request->checkTimePeriodId = null;
    $request->iconId = null;
    $request->severityId = null;
    $request->maxCheckAttempts = null;
    $request->normalCheckInterval = null;
    $request->retryCheckInterval = null;
    $request->freshnessThreshold = null;
    $request->lowFlapThreshold = null;
    $request->highFlapThreshold = null;
    $request->notificationInterval = null;
    $request->recoveryNotificationDelay = null;
    $request->firstNotificationDelay = null;
    $request->acknowledgementTimeout = null;

    return $request;
}

it('should present a ForbiddenResponse when the user has insufficient rights', function () {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, false],
            ]
        );

    ($this->useCase)(new AddServiceTemplateRequest(), $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(ServiceTemplateException::addNotAllowed()->getMessage());
});

it('should present a ErrorResponse when the user name already exists', function () {
    $this->user
    ->expects($this->once())
    ->method('hasTopologyRole')
    ->willReturnMap(
        [
            [Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, true],
        ]
    );

    $name = 'fake_name';

    $this->readServiceTemplateRepository
        ->expects($this->once())
        ->method('existsByName')
        ->with($name)
        ->willReturn(true);

    $request = new AddServiceTemplateRequest();
    $request->name = $name;
    ($this->useCase)($request, $this->presenter);
    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(ServiceTemplateException::nameAlreadyExists($name)->getMessage());
});

it('should present a ConflictResponse when the severity ID is not valid', function () {
    $request = new AddServiceTemplateRequest();
    $request->name = 'fake_name';
    $request->severityId = 1;

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
        ->method('existsByName')
        ->with($request->name)
        ->willReturn(false);

    $this->serviceSeverityRepository
        ->expects($this->once())
        ->method('exists')
        ->with($request->severityId)
        ->willReturn(false);

    ($this->useCase)($request, $this->presenter);
    expect($this->presenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(ServiceTemplateException::idDoesNotExist('severity_id', $request->severityId)->getMessage());
});

it('should present a ConflictResponse when the performance graph ID is not valid', function () {
    $request = new AddServiceTemplateRequest();
    $request->name = 'fake_name';
    $request->severityId = 1;
    $request->graphTemplateId = 1;

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
        ->method('existsByName')
        ->with($request->name)
        ->willReturn(false);

    $this->serviceSeverityRepository
        ->expects($this->once())
        ->method('exists')
        ->with($request->severityId)
        ->willReturn(true);

    $this->performanceGraphRepository
        ->expects($this->once())
        ->method('exists')
        ->with($request->graphTemplateId)
        ->willReturn(false);

    ($this->useCase)($request, $this->presenter);
    expect($this->presenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(ServiceTemplateException::idDoesNotExist('graph_template_id', $request->severityId)->getMessage());
});

it('should present a ConflictResponse when the service template ID is not valid', function () {
    $request = new AddServiceTemplateRequest();
    $request->name = 'fake_name';
    $request->severityId = 1;
    $request->graphTemplateId = 1;
    $request->serviceTemplateParentId = 1;

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
        ->method('existsByName')
        ->with($request->name)
        ->willReturn(false);

    $this->serviceSeverityRepository
        ->expects($this->once())
        ->method('exists')
        ->with($request->severityId)
        ->willReturn(true);

    $this->performanceGraphRepository
        ->expects($this->once())
        ->method('exists')
        ->with($request->graphTemplateId)
        ->willReturn(true);

    $this->readServiceTemplateRepository
        ->expects($this->once())
        ->method('exists')
        ->with($request->serviceTemplateParentId)
        ->willReturn(false);

    ($this->useCase)($request, $this->presenter);
    expect($this->presenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(ServiceTemplateException::idDoesNotExist('service_template_id', $request->severityId)->getMessage());
});

it('should present a ConflictResponse when the command ID is not valid', function () {
    $request = new AddServiceTemplateRequest();
    $request->name = 'fake_name';
    $request->severityId = 1;
    $request->graphTemplateId = 1;
    $request->serviceTemplateParentId = 1;
    $request->commandId = 1;

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
        ->method('existsByName')
        ->with($request->name)
        ->willReturn(false);

    $this->serviceSeverityRepository
        ->expects($this->once())
        ->method('exists')
        ->with($request->severityId)
        ->willReturn(true);

    $this->performanceGraphRepository
        ->expects($this->once())
        ->method('exists')
        ->with($request->graphTemplateId)
        ->willReturn(true);

    $this->readServiceTemplateRepository
        ->expects($this->once())
        ->method('exists')
        ->with($request->serviceTemplateParentId)
        ->willReturn(true);

    $this->commandRepository
        ->expects($this->once())
        ->method('exists')
        ->with($request->commandId)
        ->willReturn(false);

    ($this->useCase)($request, $this->presenter);
    expect($this->presenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(ServiceTemplateException::idDoesNotExist('check_command_id', $request->severityId)->getMessage());
});

it('should present a ConflictResponse when the event handler ID is not valid', function () {
    $request = new AddServiceTemplateRequest();
    $request->name = 'fake_name';
    $request->severityId = 1;
    $request->graphTemplateId = 1;
    $request->serviceTemplateParentId = 1;
    $request->commandId = 1;
    $request->eventHandlerId = 12;

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
        ->method('existsByName')
        ->with($request->name)
        ->willReturn(false);

    $this->serviceSeverityRepository
        ->expects($this->once())
        ->method('exists')
        ->with($request->severityId)
        ->willReturn(true);

    $this->performanceGraphRepository
        ->expects($this->once())
        ->method('exists')
        ->with($request->graphTemplateId)
        ->willReturn(true);

    $this->readServiceTemplateRepository
        ->expects($this->once())
        ->method('exists')
        ->with($request->serviceTemplateParentId)
        ->willReturn(true);

    $this->commandRepository
        ->expects($this->exactly(2))
        ->method('exists')
        ->will($this->returnValueMap(
            [
                [$request->commandId, true],
                [$request->eventHandlerId, false]
            ]
        ));

    ($this->useCase)($request, $this->presenter);
    expect($this->presenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(
            ServiceTemplateException::idDoesNotExist(
                'event_handler_command_id',
                $request->eventHandlerId
            )->getMessage()
        );
});

it('should present a ConflictResponse when the time period ID is not valid', function () {
    $request = new AddServiceTemplateRequest();
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
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, true],
            ]
        );

    $this->readServiceTemplateRepository
        ->expects($this->once())
        ->method('existsByName')
        ->with($request->name)
        ->willReturn(false);

    $this->serviceSeverityRepository
        ->expects($this->once())
        ->method('exists')
        ->with($request->severityId)
        ->willReturn(true);

    $this->performanceGraphRepository
        ->expects($this->once())
        ->method('exists')
        ->with($request->graphTemplateId)
        ->willReturn(true);

    $this->readServiceTemplateRepository
        ->expects($this->once())
        ->method('exists')
        ->with($request->serviceTemplateParentId)
        ->willReturn(true);

    $this->commandRepository
        ->expects($this->exactly(2))
        ->method('exists')
        ->will($this->returnValueMap(
            [
                [$request->commandId, true],
                [$request->eventHandlerId, true]
            ]
        ));

    $this->timePeriodRepository
        ->expects($this->once())
        ->method('exists')
        ->with($request->checkTimePeriodId)
        ->willReturn(false);

    ($this->useCase)($request, $this->presenter);
    expect($this->presenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(
            ServiceTemplateException::idDoesNotExist(
                'check_timeperiod_id',
                $request->checkTimePeriodId
            )->getMessage()
        );
});

it('should present a ConflictResponse when the notification time period ID is not valid', function () {
    $request = new AddServiceTemplateRequest();
    $request->name = 'fake_name';
    $request->severityId = 1;
    $request->graphTemplateId = 1;
    $request->serviceTemplateParentId = 1;
    $request->commandId = 1;
    $request->eventHandlerId = 12;
    $request->checkTimePeriodId = 13;
    $request->notificationTimePeriodId = 14;

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
        ->method('existsByName')
        ->with($request->name)
        ->willReturn(false);

    $this->serviceSeverityRepository
        ->expects($this->once())
        ->method('exists')
        ->with($request->severityId)
        ->willReturn(true);

    $this->performanceGraphRepository
        ->expects($this->once())
        ->method('exists')
        ->with($request->graphTemplateId)
        ->willReturn(true);

    $this->readServiceTemplateRepository
        ->expects($this->once())
        ->method('exists')
        ->with($request->serviceTemplateParentId)
        ->willReturn(true);

    $this->commandRepository
        ->expects($this->exactly(2))
        ->method('exists')
        ->will($this->returnValueMap(
            [
                [$request->commandId, true],
                [$request->eventHandlerId, true]
            ]
        ));

    $this->timePeriodRepository
        ->expects($this->exactly(2))
        ->method('exists')
        ->will($this->returnValueMap(
            [
                [$request->checkTimePeriodId, true],
                [$request->notificationTimePeriodId, false]
            ]
        ));

    ($this->useCase)($request, $this->presenter);
    expect($this->presenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(
            ServiceTemplateException::idDoesNotExist(
                'notification_timeperiod_id',
                $request->notificationTimePeriodId
            )->getMessage()
        );
});

it('should present a ConflictResponse when the icon ID is not valid', function () {
    $request = new AddServiceTemplateRequest();
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
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, true],
            ]
        );

    $this->readServiceTemplateRepository
        ->expects($this->once())
        ->method('existsByName')
        ->with($request->name)
        ->willReturn(false);

    $this->serviceSeverityRepository
        ->expects($this->once())
        ->method('exists')
        ->with($request->severityId)
        ->willReturn(true);

    $this->performanceGraphRepository
        ->expects($this->once())
        ->method('exists')
        ->with($request->graphTemplateId)
        ->willReturn(true);

    $this->readServiceTemplateRepository
        ->expects($this->once())
        ->method('exists')
        ->with($request->serviceTemplateParentId)
        ->willReturn(true);

    $this->commandRepository
        ->expects($this->exactly(2))
        ->method('exists')
        ->will($this->returnValueMap(
            [
                [$request->commandId, true],
                [$request->eventHandlerId, true]
            ]
        ));

    $this->timePeriodRepository
        ->expects($this->exactly(2))
        ->method('exists')
        ->will($this->returnValueMap(
            [
                [$request->checkTimePeriodId, true],
                [$request->notificationTimePeriodId, true]
            ]
        ));

    $this->imageRepository
        ->expects($this->once())
        ->method('existsOne')
        ->with($request->iconId)
        ->willReturn(false);

    ($this->useCase)($request, $this->presenter);
    expect($this->presenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(
            ServiceTemplateException::idDoesNotExist(
                'icon_id',
                $request->iconId
            )->getMessage()
        );
});

it('should present an InvalidArgumentResponse when data are not valid', function () {
    $request = new AddServiceTemplateRequest();
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
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, true],
            ]
        );

    $this->readServiceTemplateRepository
        ->expects($this->once())
        ->method('existsByName')
        ->with($request->name)
        ->willReturn(false);

    $this->serviceSeverityRepository
        ->expects($this->once())
        ->method('exists')
        ->with($request->severityId)
        ->willReturn(true);

    $this->performanceGraphRepository
        ->expects($this->once())
        ->method('exists')
        ->with($request->graphTemplateId)
        ->willReturn(true);

    $this->readServiceTemplateRepository
        ->expects($this->once())
        ->method('exists')
        ->with($request->serviceTemplateParentId)
        ->willReturn(true);

    $this->commandRepository
        ->expects($this->exactly(2))
        ->method('exists')
        ->will($this->returnValueMap(
            [
                [$request->commandId, true],
                [$request->eventHandlerId, true]
            ]
        ));

    $this->timePeriodRepository
        ->expects($this->exactly(2))
        ->method('exists')
        ->will($this->returnValueMap(
            [
                [$request->checkTimePeriodId, true],
                [$request->notificationTimePeriodId, true]
            ]
        ));

    $this->imageRepository
        ->expects($this->once())
        ->method('existsOne')
        ->with($request->iconId)
        ->willReturn(true);

    ($this->useCase)($request, $this->presenter);
    expect($this->presenter->response)
        ->toBeInstanceOf(InvalidArgumentResponse::class)
        ->and($this->presenter->response->getMessage());
});

it('should present an ErrorResponse when an exception is thrown', function () {
    $request = createAddServiceTemplateRequest();

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, true],
            ]
        );

    $this->writeServiceTemplateRepository
        ->expects($this->once())
        ->method('add')
        ->willThrowException(new \Exception());

    ($this->useCase)($request, $this->presenter);
    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->response->getMessage());
});
