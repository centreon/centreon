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

namespace Tests\Core\ServiceTemplate\Application\UseCase\AddServiceTemplate;

use Centreon\Domain\Common\Assertion\AssertionException;
use Centreon\Domain\Contact\Contact;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\CommandMacro\Domain\Model\CommandMacroType;
use Core\Common\Domain\YesNoDefault;
use Core\Macro\Domain\Model\Macro;
use Core\ServiceGroup\Domain\Model\ServiceGroup;
use Core\ServiceGroup\Domain\Model\ServiceGroupRelation;
use Core\ServiceTemplate\Application\Exception\ServiceTemplateException;
use Core\ServiceTemplate\Application\UseCase\AddServiceTemplate\AddServiceTemplateRequest;
use Core\ServiceTemplate\Application\UseCase\AddServiceTemplate\AddServiceTemplateResponse;
use Core\ServiceTemplate\Application\UseCase\AddServiceTemplate\MacroDto;
use Core\ServiceTemplate\Application\UseCase\AddServiceTemplate\ServiceGroupDto;
use Core\ServiceTemplate\Domain\Model\NotificationType;
use Core\ServiceTemplate\Domain\Model\ServiceTemplate;
use Core\ServiceTemplate\Domain\Model\ServiceTemplateInheritance;

beforeEach(closure: function (): void {
    Mock::create($this);
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
    $request->hostTemplateIds = [];
    $request->serviceCategories = [];
    $request->serviceGroups = [];

    return $request;
}

it('should present a ForbiddenResponse when the user has insufficient rights', function (): void {
    Mock::setMock($this, [
        'user' => [[
            'expected' => [[Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, false]],
        ]],
    ]);

    ($this->addUseCase)(new AddServiceTemplateRequest(), $this->useCasePresenter);

    expect($this->useCasePresenter->response)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->useCasePresenter->response->getMessage())
        ->toBe(ServiceTemplateException::addNotAllowed()->getMessage());
});

it('should present an ErrorResponse when the service template name already exists', function (): void {
    $name = 'fake_name';

    Mock::setMock($this, [
        'user' => [[
            'expected' => [[Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, true]],
        ]],
        'readServiceTemplateRepository' => [
            [
                'method' => 'existsByName',
                'arguments' => $name,
                'expected' => true,
            ],
        ],
    ]);

    $this->readServiceTemplateRepository
        ->expects($this->once())
        ->method('existsByName')
        ->with($name)
        ->willReturn(true);

    $request = new AddServiceTemplateRequest();
    $request->name = $name;
    ($this->addUseCase)($request, $this->useCasePresenter);
    expect($this->useCasePresenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->useCasePresenter->response->getMessage())
        ->toBe(ServiceTemplateException::nameAlreadyExists($name)->getMessage());
});

it('should present a ConflictResponse when the severity ID is not valid', function (): void {
    $request = new AddServiceTemplateRequest();
    $request->name = 'fake_name';
    $request->severityId = 1;

    Mock::setMock($this, [
        'user' => [[
            'expected' => [[Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, true]],
        ]],
        'readServiceTemplateRepository' => [
            [
                'method' => 'existsByName',
                'arguments' => $request->name,
                'expected' => false,
            ],
        ],
    ]);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidSeverity')
        ->willThrowException(ServiceTemplateException::idDoesNotExist('severity_id', $request->severityId));

    ($this->addUseCase)($request, $this->useCasePresenter);
    expect($this->useCasePresenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->useCasePresenter->response->getMessage())
        ->toBe(ServiceTemplateException::idDoesNotExist('severity_id', $request->severityId)->getMessage());
});

it('should present a ConflictResponse when the performance graph ID is not valid', function (): void {
    $request = new AddServiceTemplateRequest();
    $request->name = 'fake_name';
    $request->severityId = 1;
    $request->graphTemplateId = 1;

    Mock::setMock($this, [
        'user' => [[
            'expected' => [[Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, true]],
        ]],
        'readServiceTemplateRepository' => [
            [
                'method' => 'existsByName',
                'arguments' => $request->name,
                'expected' => false,
            ],
        ],
    ]);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidPerformanceGraph')
        ->willThrowException(ServiceTemplateException::idDoesNotExist('graph_template_id', $request->severityId));

    ($this->addUseCase)($request, $this->useCasePresenter);
    expect($this->useCasePresenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->useCasePresenter->response->getMessage())
        ->toBe(ServiceTemplateException::idDoesNotExist('graph_template_id', $request->severityId)->getMessage());
});

it('should present a ConflictResponse when the service template ID is not valid', function (): void {
    $request = new AddServiceTemplateRequest();
    $request->name = 'fake_name';
    $request->severityId = 1;
    $request->graphTemplateId = 1;
    $request->serviceTemplateParentId = 1;

    Mock::setMock($this, [
        'user' => [[
            'expected' => [[Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, true]],
        ]],
        'readServiceTemplateRepository' => [
            [
                'method' => 'existsByName',
                'arguments' => $request->name,
                'expected' => false,
            ],
        ],
    ]);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidServiceTemplate')
        ->willThrowException(ServiceTemplateException::idDoesNotExist('service_template_id', $request->severityId));

    ($this->addUseCase)($request, $this->useCasePresenter);
    expect($this->useCasePresenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->useCasePresenter->response->getMessage())
        ->toBe(ServiceTemplateException::idDoesNotExist('service_template_id', $request->severityId)->getMessage());
});

it('should present a ConflictResponse when the command ID is not valid', function (): void {
    $request = new AddServiceTemplateRequest();
    $request->name = 'fake_name';
    $request->severityId = 1;
    $request->graphTemplateId = 1;
    $request->serviceTemplateParentId = 1;
    $request->commandId = 1;

    Mock::setMock($this, [
        'user' => [[
            'expected' => [[Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, true]],
        ]],
        'readServiceTemplateRepository' => [
            [
                'method' => 'existsByName',
                'arguments' => $request->name,
                'expected' => false,
            ],
        ],
    ]);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidCommand')
        ->willThrowException(ServiceTemplateException::idDoesNotExist('check_command_id', $request->severityId));

    ($this->addUseCase)($request, $this->useCasePresenter);
    expect($this->useCasePresenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->useCasePresenter->response->getMessage())
        ->toBe(ServiceTemplateException::idDoesNotExist('check_command_id', $request->severityId)->getMessage());
});

it('should present a ConflictResponse when the event handler ID is not valid', function (): void {
    $request = new AddServiceTemplateRequest();
    $request->name = 'fake_name';
    $request->severityId = 1;
    $request->graphTemplateId = 1;
    $request->serviceTemplateParentId = 1;
    $request->commandId = 1;
    $request->eventHandlerId = 12;

    Mock::setMock($this, [
        'user' => [[
            'expected' => [[Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, true]],
        ]],
        'readServiceTemplateRepository' => [
            [
                'method' => 'existsByName',
                'arguments' => $request->name,
                'expected' => false,
            ],
        ],
    ]);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidCommand')
        ->willThrowException(
            ServiceTemplateException::idDoesNotExist(
                'event_handler_command_id',
                $request->eventHandlerId
            )
        );

    ($this->addUseCase)($request, $this->useCasePresenter);
    expect($this->useCasePresenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->useCasePresenter->response->getMessage())
        ->toBe(
            ServiceTemplateException::idDoesNotExist(
                'event_handler_command_id',
                $request->eventHandlerId
            )->getMessage()
        );
});

it('should present a ConflictResponse when the time period ID is not valid', function (): void {
    $request = new AddServiceTemplateRequest();
    $request->name = 'fake_name';
    $request->severityId = 1;
    $request->graphTemplateId = 1;
    $request->serviceTemplateParentId = 1;
    $request->commandId = 1;
    $request->eventHandlerId = 12;
    $request->checkTimePeriodId = 13;

    Mock::setMock($this, [
        'user' => [[
            'expected' => [[Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, true]],
        ]],
        'readServiceTemplateRepository' => [
            [
                'method' => 'existsByName',
                'arguments' => $request->name,
                'expected' => false,
            ],
        ],
    ]);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidTimePeriod')
        ->willThrowException(
            ServiceTemplateException::idDoesNotExist(
                'check_timeperiod_id',
                $request->checkTimePeriodId
            )
        );

    ($this->addUseCase)($request, $this->useCasePresenter);
    expect($this->useCasePresenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->useCasePresenter->response->getMessage())
        ->toBe(
            ServiceTemplateException::idDoesNotExist(
                'check_timeperiod_id',
                $request->checkTimePeriodId
            )->getMessage()
        );
});

it('should present a ConflictResponse when the notification time period ID is not valid', function (): void {
    $request = new AddServiceTemplateRequest();
    $request->name = 'fake_name';
    $request->severityId = 1;
    $request->graphTemplateId = 1;
    $request->serviceTemplateParentId = 1;
    $request->commandId = 1;
    $request->eventHandlerId = 12;
    $request->checkTimePeriodId = 13;
    $request->notificationTimePeriodId = 14;

    Mock::setMock($this, [
        'user' => [[
            'expected' => [[Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, true]],
        ]],
        'readServiceTemplateRepository' => [
            [
                'method' => 'existsByName',
                'arguments' => $request->name,
                'expected' => false,
            ],
        ],
    ]);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidNotificationTimePeriod')
        ->willThrowException(
            ServiceTemplateException::idDoesNotExist(
                'notification_timeperiod_id',
                $request->notificationTimePeriodId
            )
        );

    ($this->addUseCase)($request, $this->useCasePresenter);
    expect($this->useCasePresenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->useCasePresenter->response->getMessage())
        ->toBe(
            ServiceTemplateException::idDoesNotExist(
                'notification_timeperiod_id',
                $request->notificationTimePeriodId
            )->getMessage()
        );
});

it('should present a ConflictResponse when the icon ID is not valid', function (): void {
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

    Mock::setMock($this, [
        'user' => [[
            'expected' => [[Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, true]],
        ]],
        'readServiceTemplateRepository' => [
            [
                'method' => 'existsByName',
                'arguments' => $request->name,
                'expected' => false,
            ],
        ],
    ]);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidIcon')
        ->willThrowException(
            ServiceTemplateException::idDoesNotExist(
                'icon_id',
                $request->iconId
            )
        );

    ($this->addUseCase)($request, $this->useCasePresenter);
    expect($this->useCasePresenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->useCasePresenter->response->getMessage())
        ->toBe(
            ServiceTemplateException::idDoesNotExist(
                'icon_id',
                $request->iconId
            )->getMessage()
        );
});

it('should present a ConflictResponse when the host template IDs are not valid', function (): void {
    $request = new AddServiceTemplateRequest();
    $request->name = 'fake_name';
    $request->alias = 'fake_alias';
    $request->severityId = 1;
    $request->graphTemplateId = 1;
    $request->serviceTemplateParentId = 1;
    $request->commandId = 1;
    $request->eventHandlerId = 12;
    $request->checkTimePeriodId = 13;
    $request->notificationTimePeriodId = 14;
    $request->iconId = 15;
    $request->hostTemplateIds = [2, 3];

    Mock::setMock($this, [
        'user' => [[
            'expected' => [[Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, true]],
        ]],
        'readServiceTemplateRepository' => [
            [
                'method' => 'existsByName',
                'arguments' => $request->name,
                'expected' => false,
            ],
        ],
    ]);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidHostTemplates')
        ->willThrowException(
            ServiceTemplateException::idsDoNotExist(
                'host_templates',
                [$request->hostTemplateIds[1]]
            )
        );

    ($this->addUseCase)($request, $this->useCasePresenter);
    expect($this->useCasePresenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->useCasePresenter->response->getMessage())
        ->toBe(
            ServiceTemplateException::idsDoNotExist(
                'host_templates',
                [$request->hostTemplateIds[1]]
            )->getMessage()
        );
});

it('should present a ConflictResponse when the service category IDs are not valid', function (): void {
    $request = new AddServiceTemplateRequest();
    $request->name = 'fake_name';
    $request->alias = 'fake_alias';
    $request->severityId = 1;
    $request->graphTemplateId = 1;
    $request->serviceTemplateParentId = 1;
    $request->commandId = 1;
    $request->eventHandlerId = 12;
    $request->checkTimePeriodId = 13;
    $request->notificationTimePeriodId = 14;
    $request->iconId = 15;
    $request->serviceCategories = [2, 3];

    Mock::setMock($this, [
        'user' => [[
            'expected' => [[Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, true]],
        ]],
        'readServiceTemplateRepository' => [
            [
                'method' => 'existsByName',
                'arguments' => $request->name,
                'expected' => false,
            ],
        ],
    ]);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidServiceCategories')
        ->willThrowException(
            ServiceTemplateException::idsDoNotExist(
                'service_categories',
                [$request->serviceCategories[1]]
            )
        );

    ($this->addUseCase)($request, $this->useCasePresenter);
    expect($this->useCasePresenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->useCasePresenter->response->getMessage())
        ->toBe(
            ServiceTemplateException::idsDoNotExist(
                'service_categories',
                [$request->serviceCategories[1]]
            )->getMessage()
        );
});

it('should present a ConflictResponse when the service group IDs are not valid', function (): void {
    $request = new AddServiceTemplateRequest();
    $request->name = 'fake_name';
    $request->alias = 'fake_alias';
    $request->severityId = 1;
    $request->graphTemplateId = 1;
    $request->serviceTemplateParentId = 1;
    $request->commandId = 1;
    $request->eventHandlerId = 12;
    $request->checkTimePeriodId = 13;
    $request->notificationTimePeriodId = 14;
    $request->iconId = 15;
    $request->hostTemplateIds = [4];
    $request->serviceGroups = [2, 3];

    Mock::setMock($this, [
        'user' => [[
            'expected' => [[Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, true]],
        ]],
        'readServiceTemplateRepository' => [
            [
                'method' => 'existsByName',
                'arguments' => $request->name,
                'expected' => false,
            ],
        ],
    ]);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidServiceGroups')
        ->willThrowException(
            ServiceTemplateException::idsDoNotExist(
                'service_groups',
                [$request->serviceGroups[1]]
            )
        );

    ($this->addUseCase)($request, $this->useCasePresenter);
    expect($this->useCasePresenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->useCasePresenter->response->getMessage())
        ->toBe(
            ServiceTemplateException::idsDoNotExist(
                'service_groups',
                [$request->serviceGroups[1]]
            )->getMessage()
        );
});

it('should present a ConflictResponse when trying to set service group IDs without host template IDs', function (): void {
    $request = new AddServiceTemplateRequest();
    $request->name = 'fake_name';
    $request->alias = 'fake_alias';
    $request->severityId = 1;
    $request->graphTemplateId = 1;
    $request->serviceTemplateParentId = 1;
    $request->commandId = 1;
    $request->eventHandlerId = 12;
    $request->checkTimePeriodId = 13;
    $request->notificationTimePeriodId = 14;
    $request->iconId = 15;
    $request->hostTemplateIds = [];
    $request->serviceGroups = [2, 3];

    Mock::setMock($this, [
        'user' => [[
            'expected' => [[Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, true]],
        ]],
        'readServiceTemplateRepository' => [
            [
                'method' => 'existsByName',
                'arguments' => $request->name,
                'expected' => false,
            ],
        ],
    ]);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidServiceGroups')
        ->willThrowException(ServiceTemplateException::invalidServiceGroupAssociation());

    ($this->addUseCase)($request, $this->useCasePresenter);
    expect($this->useCasePresenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->useCasePresenter->response->getMessage())
        ->toBe(
            ServiceTemplateException::invalidServiceGroupAssociation()->getMessage()
        );
});

it('should present an InvalidArgumentResponse when data are not valid', function (): void {
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

    Mock::setMock($this, [
        'user' => [[
            'expected' => [[Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, true]],
        ]],
        'readServiceTemplateRepository' => [
            [
                'method' => 'existsByName',
                'arguments' => $request->name,
                'expected' => false,
            ],
        ],
    ]);

    // An error will be raised because the alias is empty
    ($this->addUseCase)($request, $this->useCasePresenter);
    expect($this->useCasePresenter->response)
        ->toBeInstanceOf(InvalidArgumentResponse::class)
        ->and($this->useCasePresenter->response->getMessage())
        ->toBe(AssertionException::notEmptyString('NewServiceTemplate::alias')->getMessage());
});

it('should present an ErrorResponse when an exception is thrown', function (): void {
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

    ($this->addUseCase)($request, $this->useCasePresenter);
    expect($this->useCasePresenter->response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->useCasePresenter->response->getMessage())
        ->toBe(ServiceTemplateException::errorWhileAdding(new \Exception())->getMessage());
});

it('should present an AddServiceTemplateResponse when everything has gone well', function (): void {
    $request = new AddServiceTemplateRequest();
    $request->name = 'fake_name';
    $request->alias = 'fake_alias';
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
    $request->serviceGroups = [
        new ServiceGroupDto(2, 1),
    ];
    $newServiceTemplateId = 99;
    $serviceTemplateInheritances = [
        new ServiceTemplateInheritance(9, 99),
        new ServiceTemplateInheritance(8, 9),
        new ServiceTemplateInheritance(1, 8),
    ];

    $macroA = new Macro($newServiceTemplateId, 'MACROA', 'A');
    $macroA->setDescription('');

    $macroB = new Macro($newServiceTemplateId, 'MACROB', 'B');
    $macroB->setDescription('');

    $serviceGroup = new ServiceGroup(1, 'SG-name', 'SG-alias', null, '', true);
    $serviceGroupRelation = new ServiceGroupRelation(
        serviceGroupId: $serviceGroup->getId(),
        serviceId: $newServiceTemplateId,
        hostId: 2
    );
    $hostTemplateName = 'HostTemplateName';

    $this->serviceTemplateFound = new ServiceTemplate(
        id: $newServiceTemplateId,
        name: $request->name,
        alias: $request->alias,
        commandArguments: ['a', 'b'],
        eventHandlerArguments: ['c', 'd'],
        notificationTypes: [NotificationType::Unknown],
        hostTemplateIds: [2, 3],
        contactAdditiveInheritance: true,
        contactGroupAdditiveInheritance: true,
        isLocked: true,
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

    Mock::setMock($this, [
        'user' => [[
            'expected' => [[Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, true]],
        ]],
        'readServiceTemplateRepository' => [
            [
                'method' => 'existsByName',
                'arguments' => $request->name,
                'expected' => false,
            ],
            [
                'method' => 'findById',
                'arguments' => $newServiceTemplateId,
                'expected' => $this->serviceTemplateFound,
            ],
            [
                'method' => 'findParents',
                'arguments' => $newServiceTemplateId,
                'expected' => $serviceTemplateInheritances,
            ],
        ],
        'writeServiceTemplateRepository' => [[
            'expected' => $newServiceTemplateId,
        ]],
        'readServiceMacroRepository' => [[
            'method' => 'findByServiceIds',
            'expected' => [
                [9, 8, 1, []],
                [$newServiceTemplateId, [$macroA, $macroB]],
            ],
        ]],
        'readCommandMacroRepository' => [[
            'method' => 'findByCommandIdAndType',
            'arguments' => [$request->commandId, CommandMacroType::Service],
            'expected' => [],
        ]],
        'writeServiceMacroRepository' => [[
            'method' => 'add',
            'expected' => [[$macroA], [$macroB]],
        ]],
        'writeServiceGroupRepository' => [[
            'method' => 'link',
            'expected' => [$serviceGroupRelation],
        ]],
        'readServiceGroupRepository' => [[
            'method' => 'findByService',
            'expected' => [
                [$newServiceTemplateId, [['relation' => $serviceGroupRelation, 'serviceGroup' => $serviceGroup]]]
            ],
        ]],
        'readHostTemplateRepository' => [[
            'method' => 'findNamesByIds',
            'expected' => [
                [[$serviceGroupRelation->getHostId()], [$serviceGroupRelation->getHostId() => $hostTemplateName]],
            ],
        ]],
    ]);

    $this->user
        ->expects($this->exactly(2))
        ->method('isAdmin')
        ->willReturn(true);

    ($this->addUseCase)($request, $this->useCasePresenter);
    $dto = $this->useCasePresenter->response;
    expect($dto)->toBeInstanceOf(AddServiceTemplateResponse::class);
    expect($dto->id)->toBe($this->serviceTemplateFound->getId());
    expect($dto->name)->toBe($this->serviceTemplateFound->getName());
    expect($dto->alias)->toBe($this->serviceTemplateFound->getAlias());
    expect($dto->comment)->toBe($this->serviceTemplateFound->getComment());
    expect($dto->serviceTemplateId)->toBe($this->serviceTemplateFound->getServiceTemplateParentId());
    expect($dto->commandId)->toBe($this->serviceTemplateFound->getCommandId());
    expect($dto->commandArguments)->toBe($this->serviceTemplateFound->getCommandArguments());
    expect($dto->checkTimePeriodId)->toBe($this->serviceTemplateFound->getCheckTimePeriodId());
    expect($dto->maxCheckAttempts)->toBe($this->serviceTemplateFound->getMaxCheckAttempts());
    expect($dto->normalCheckInterval)->toBe($this->serviceTemplateFound->getNormalCheckInterval());
    expect($dto->retryCheckInterval)->toBe($this->serviceTemplateFound->getRetryCheckInterval());
    expect($dto->activeChecks)->toBe($this->serviceTemplateFound->getActiveChecks());
    expect($dto->passiveCheck)->toBe($this->serviceTemplateFound->getPassiveCheck());
    expect($dto->volatility)->toBe($this->serviceTemplateFound->getVolatility());
    expect($dto->notificationsEnabled)->toBe($this->serviceTemplateFound->getNotificationsEnabled());
    expect($dto->isContactAdditiveInheritance)->toBe($this->serviceTemplateFound->isContactAdditiveInheritance());
    expect($dto->isContactGroupAdditiveInheritance)
        ->toBe($this->serviceTemplateFound->isContactGroupAdditiveInheritance());
    expect($dto->notificationInterval)->toBe($this->serviceTemplateFound->getNotificationInterval());
    expect($dto->notificationTimePeriodId)->toBe($this->serviceTemplateFound->getNotificationTimePeriodId());
    expect($dto->notificationTypes)->toBe($this->serviceTemplateFound->getNotificationTypes());
    expect($dto->firstNotificationDelay)->toBe($this->serviceTemplateFound->getFirstNotificationDelay());
    expect($dto->recoveryNotificationDelay)->toBe($this->serviceTemplateFound->getRecoveryNotificationDelay());
    expect($dto->acknowledgementTimeout)->toBe($this->serviceTemplateFound->getAcknowledgementTimeout());
    expect($dto->checkFreshness)->toBe($this->serviceTemplateFound->getCheckFreshness());
    expect($dto->freshnessThreshold)->toBe($this->serviceTemplateFound->getFreshnessThreshold());
    expect($dto->flapDetectionEnabled)->toBe($this->serviceTemplateFound->getFlapDetectionEnabled());
    expect($dto->lowFlapThreshold)->toBe($this->serviceTemplateFound->getLowFlapThreshold());
    expect($dto->highFlapThreshold)->toBe($this->serviceTemplateFound->getHighFlapThreshold());
    expect($dto->eventHandlerEnabled)->toBe($this->serviceTemplateFound->getEventHandlerEnabled());
    expect($dto->eventHandlerId)->toBe($this->serviceTemplateFound->getEventHandlerId());
    expect($dto->eventHandlerArguments)->toBe($this->serviceTemplateFound->getEventHandlerArguments());
    expect($dto->graphTemplateId)->toBe($this->serviceTemplateFound->getGraphTemplateId());
    expect($dto->note)->toBe($this->serviceTemplateFound->getNote());
    expect($dto->noteUrl)->toBe($this->serviceTemplateFound->getNoteUrl());
    expect($dto->actionUrl)->toBe($this->serviceTemplateFound->getActionUrl());
    expect($dto->iconId)->toBe($this->serviceTemplateFound->getIconId());
    expect($dto->iconAlternativeText)->toBe($this->serviceTemplateFound->getIconAlternativeText());
    expect($dto->severityId)->toBe($this->serviceTemplateFound->getSeverityId());
    expect($dto->isLocked)->toBe($this->serviceTemplateFound->isLocked());
    expect($dto->hostTemplateIds)->toBe($this->serviceTemplateFound->getHostTemplateIds());
    foreach ($dto->macros as $index => $expectedMacro) {
        expect($expectedMacro->name)->toBe($request->macros[$index]->name)
            ->and($expectedMacro->value)->toBe($request->macros[$index]->value)
            ->and($expectedMacro->isPassword)->toBe($request->macros[$index]->isPassword)
            ->and($expectedMacro->description)->toBe('');
    }
    expect($dto->groups)->toBe(
       [[
        'serviceGroupId' => $request->serviceGroups[0]->serviceGroupId,
        'serviceGroupName' => $serviceGroup->getName(),
        'hostTemplateId' => $request->serviceGroups[0]->hostTemplateId,
        'hostTemplateName' => $hostTemplateName,
       ]]
    );

});
