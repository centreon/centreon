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

namespace Tests\Core\Host\Application\UseCase\PartialUpdateHost;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Option\Option;
use Centreon\Domain\Option\OptionService;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\CommandMacro\Application\Repository\ReadCommandMacroRepositoryInterface;
use Core\CommandMacro\Domain\Model\CommandMacro;
use Core\CommandMacro\Domain\Model\CommandMacroType;
use Core\Common\Application\Converter\YesNoDefaultConverter;
use Core\Common\Application\Repository\ReadVaultRepositoryInterface;
use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
use Core\Host\Application\Converter\HostEventConverter;
use Core\Host\Application\Exception\HostException;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\Host\Application\Repository\WriteHostRepositoryInterface;
use Core\Host\Application\UseCase\PartialUpdateHost\PartialUpdateHost;
use Core\Host\Application\UseCase\PartialUpdateHost\PartialUpdateHostRequest;
use Core\Host\Application\UseCase\PartialUpdateHost\PartialUpdateHostValidation;
use Core\Host\Domain\Model\Host;
use Core\Host\Domain\Model\HostEvent;
use Core\Host\Domain\Model\SnmpVersion;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\HostCategory\Application\Repository\WriteHostCategoryRepositoryInterface;
use Core\HostCategory\Domain\Model\HostCategory;
use Core\HostGroup\Application\Repository\ReadHostGroupRepositoryInterface;
use Core\HostGroup\Application\Repository\WriteHostGroupRepositoryInterface;
use Core\HostGroup\Domain\Model\HostGroup;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Macro\Application\Repository\ReadHostMacroRepositoryInterface;
use Core\Macro\Application\Repository\WriteHostMacroRepositoryInterface;
use Core\Macro\Domain\Model\Macro;
use Core\MonitoringServer\Application\Repository\WriteMonitoringServerRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Tests\Core\Host\Infrastructure\API\PartialUpdateHost\PartialUpdateHostPresenterStub;

beforeEach(function (): void {
     $this->presenter = new PartialUpdateHostPresenterStub($this->createMock(PresenterFormatterInterface::class));

    $this->useCase = new PartialUpdateHost(
        writeHostRepository: $this->writeHostRepository = $this->createMock(WriteHostRepositoryInterface::class),
        readHostRepository: $this->readHostRepository = $this->createMock(ReadHostRepositoryInterface::class),
        writeMonitoringServerRepository: $this->writeMonitoringServerRepository = $this->createMock(WriteMonitoringServerRepositoryInterface::class),
        readHostCategoryRepository: $this->readHostCategoryRepository = $this->createMock(ReadHostCategoryRepositoryInterface::class),
        readHostGroupRepository: $this->readHostGroupRepository = $this->createMock(ReadHostGroupRepositoryInterface::class),
        writeHostCategoryRepository: $this->writeHostCategoryRepository = $this->createMock(WriteHostCategoryRepositoryInterface::class),
        writeHostGroupRepository: $this->writeHostGroupRepository = $this->createMock(WriteHostGroupRepositoryInterface::class),
        readAccessGroupRepository: $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        readHostMacroRepository: $this->readHostMacroRepository = $this->createMock(ReadHostMacroRepositoryInterface::class),
        readCommandMacroRepository: $this->readCommandMacroRepository = $this->createMock(ReadCommandMacroRepositoryInterface::class),
        writeHostMacroRepository: $this->writeHostMacroRepository = $this->createMock(WriteHostMacroRepositoryInterface::class),
        dataStorageEngine: $this->dataStorageEngine = $this->createMock(DataStorageEngineInterface::class),
        optionService: $this->optionService = $this->createMock(OptionService::class),
        user: $this->user = $this->createMock(ContactInterface::class),
        validation: $this->validation = $this->createMock(PartialUpdateHostValidation::class),
        writeVaultRepository: $this->writeVaultRepository = $this->createMock(WriteVaultRepositoryInterface::class),
        readVaultRepository: $this->readVaultRepository = $this->createMock(ReadVaultRepositoryInterface::class),
    );

    $this->inheritanceModeOption = new Option();
    $this->inheritanceModeOption->setName('inheritanceMode')->setValue('1');

    // Settup host template
    $this->hostId = 1;
    $this->checkCommandId = 1;

    $this->originalHost = new Host(
        id: $this->hostId,
        monitoringServerId: 2,
        name: 'host_template_name',
        alias: 'host_template_alias',
        address: '127.0.0.1',
        snmpVersion: SnmpVersion::Three,
        snmpCommunity: 'someCommunity',
        noteUrl: 'some note url',
        note: 'a note',
        actionUrl: 'some action url',
        comment: 'a comment'
    );

    $this->request = new PartialUpdateHostRequest();
    $this->request->monitoringServerId = 3;
    $this->request->name = $this->originalHost->getName() . ' edit  ';
    $this->request->alias = $this->originalHost->getAlias() . ' edit  ';
    $this->request->address = '1.2.3.4';
    $this->request->snmpVersion = SnmpVersion::Two->value;
    $this->request->snmpCommunity = 'snmpCommunity-value edit';
    $this->request->timezoneId = 1;
    $this->request->severityId = 1;
    $this->request->checkCommandId = $this->checkCommandId;
    $this->request->checkCommandArgs = ['arg1', 'arg2'];
    $this->request->checkTimeperiodId = 1;
    $this->request->maxCheckAttempts = 5;
    $this->request->normalCheckInterval = 5;
    $this->request->retryCheckInterval = 5;
    $this->request->activeCheckEnabled = 1;
    $this->request->passiveCheckEnabled = 1;
    $this->request->notificationEnabled = 1;
    $this->request->notificationOptions = HostEventConverter::toBitFlag([HostEvent::Down, HostEvent::Unreachable]);
    $this->request->notificationInterval = 5;
    $this->request->notificationTimeperiodId = 2;
    $this->request->addInheritedContactGroup = true;
    $this->request->addInheritedContact = true;
    $this->request->firstNotificationDelay = 5;
    $this->request->recoveryNotificationDelay = 5;
    $this->request->acknowledgementTimeout = 5;
    $this->request->freshnessChecked = 1;
    $this->request->freshnessThreshold = 5;
    $this->request->flapDetectionEnabled = 1;
    $this->request->lowFlapThreshold = 5;
    $this->request->highFlapThreshold = 5;
    $this->request->eventHandlerEnabled = 1;
    $this->request->eventHandlerCommandId = 2;
    $this->request->eventHandlerCommandArgs = ['arg3', '  arg4'];
    $this->request->noteUrl = 'noteUrl-value edit';
    $this->request->note = 'note-value edit';
    $this->request->actionUrl = 'actionUrl-value edit';
    $this->request->iconId = 1;
    $this->request->iconAlternative = 'iconAlternative-value';
    $this->request->comment = 'comment-value edit';

    $this->editedHost = new Host(
        id: $this->hostId,
        monitoringServerId: $this->request->monitoringServerId,
        name: $this->request->name,
        alias: $this->request->alias,
        address: $this->request->address,
        snmpVersion: SnmpVersion::from($this->request->snmpVersion),
        snmpCommunity: $this->request->snmpCommunity,
        timezoneId: $this->request->timezoneId,
        severityId: $this->request->severityId,
        checkCommandId: $this->request->checkCommandId,
        checkCommandArgs: ['arg1', 'test2'],
        checkTimeperiodId: $this->request->checkTimeperiodId,
        maxCheckAttempts: $this->request->maxCheckAttempts,
        normalCheckInterval: $this->request->normalCheckInterval,
        retryCheckInterval: $this->request->retryCheckInterval,
        activeCheckEnabled: YesNoDefaultConverter::fromScalar($this->request->activeCheckEnabled),
        passiveCheckEnabled: YesNoDefaultConverter::fromScalar($this->request->passiveCheckEnabled),
        notificationEnabled: YesNoDefaultConverter::fromScalar($this->request->notificationEnabled),
        notificationOptions: HostEventConverter::fromBitFlag($this->request->notificationOptions),
        notificationInterval: $this->request->notificationInterval,
        notificationTimeperiodId: $this->request->notificationTimeperiodId,
        addInheritedContactGroup: $this->request->addInheritedContactGroup,
        addInheritedContact: $this->request->addInheritedContact,
        firstNotificationDelay: $this->request->firstNotificationDelay,
        recoveryNotificationDelay: $this->request->recoveryNotificationDelay,
        acknowledgementTimeout: $this->request->acknowledgementTimeout,
        freshnessChecked: YesNoDefaultConverter::fromScalar($this->request->freshnessChecked),
        freshnessThreshold: $this->request->freshnessThreshold,
        flapDetectionEnabled: YesNoDefaultConverter::fromScalar($this->request->flapDetectionEnabled),
        lowFlapThreshold: $this->request->lowFlapThreshold,
        highFlapThreshold: $this->request->highFlapThreshold,
        eventHandlerEnabled: YesNoDefaultConverter::fromScalar($this->request->eventHandlerEnabled),
        eventHandlerCommandId: $this->request->eventHandlerCommandId,
        eventHandlerCommandArgs: $this->request->eventHandlerCommandArgs,
        noteUrl: $this->request->noteUrl,
        note: $this->request->note,
        actionUrl: $this->request->actionUrl,
        iconId: $this->request->iconId,
        iconAlternative: $this->request->iconAlternative,
        comment: $this->request->comment,
    );

    // Settup parent templates
    $this->parentTemplates = [2, 3];
    $this->request->templates = $this->parentTemplates;

    // Settup macros
    $this->macroA = new Macro($this->hostId, 'macroNameA', 'macroValueA');
    $this->macroA->setOrder(0);
    $this->macroB = new Macro($this->hostId, 'macroNameB', 'macroValueB');
    $this->macroB->setOrder(1);
    $this->commandMacro = new CommandMacro(1, CommandMacroType::Host, 'commandMacroName');
    $this->commandMacros = [
        $this->commandMacro->getName() => $this->commandMacro,
    ];
    $this->hostMacros = [
        $this->macroA->getName() => $this->macroA,
        $this->macroB->getName() => $this->macroB,
    ];
    $this->inheritanceLineIds = [
        ['child_id' => 1, 'parent_id' => $this->parentTemplates[0], 'order' => 0],
        ['child_id' => $this->parentTemplates[0], 'parent_id' => $this->parentTemplates[1], 'order' => 1],
    ];

    $this->request->macros = [
        [
            'name' => $this->macroA->getName(),
            'value' => $this->macroA->getValue() . '_edit',
            'is_password' => $this->macroA->isPassword(),
            'description' => $this->macroA->getDescription(),
        ],
        [
            'name' => 'macroNameC',
            'value' => 'macroValueC',
            'is_password' => false,
            'description' => null,
        ],
    ];

    // Settup categories
    $this->categoryA = new HostCategory(1, 'cat-name-A', 'cat-alias-A');
    $this->categoryB = new HostCategory(2, 'cat-name-B', 'cat-alias-B');

    $this->request->categories = [$this->categoryB->getId()];

    // Settup groups
    $this->groups = [
        $this->groupA = new HostGroup(6, 'grp-name-A', 'grp-alias-A', '', '', '', null, null, null, null, '', true),
        $this->groupB = new HostGroup(7, 'grp-name-B', 'grp-alias-B', '', '', '', null, null, null, null, '', true),
    ];
    $this->request->groups = [$this->groupA->getId(), $this->groupB->getId()];
});

// Generic usecase tests

it('should present a ForbiddenResponse when a user has insufficient rights', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(false);

    ($this->useCase)($this->request, $this->presenter, $this->hostId);

    expect($this->presenter->response)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(HostException::editNotAllowed()->getMessage());
});

it('should present an ErrorResponse when an exception is thrown', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);
    $this->readAccessGroupRepository
        ->expects($this->once())
        ->method('findByContact')
        ->willThrowException(new \Exception);

    ($this->useCase)($this->request, $this->presenter, $this->hostId);

    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(HostException::editHost()->getMessage());
});

it('should present a NotFoundResponse when the host does not exist', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->exactly(2))
        ->method('isAdmin')
        ->willReturn(true);
    $this->readHostRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn(null);

    ($this->useCase)($this->request, $this->presenter, $this->hostId);

    expect($this->presenter->response)
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe('Host not found');
});

 // Tests for host

it('should present a ConflictResponse when name is already used', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->exactly(2))
        ->method('isAdmin')
        ->willReturn(true);
    $this->readHostRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->originalHost);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidName')
        ->willThrowException(
            HostException::nameAlreadyExists(
                Host::formatName($this->request->name),
                $this->request->name
            )
        );

    ($this->useCase)($this->request, $this->presenter, $this->hostId);

    expect($this->presenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(
            HostException::nameAlreadyExists(
                Host::formatName($this->request->name),
                $this->request->name
            )->getMessage()
        );
});

it('should present a ConflictResponse when host severity ID is not valid', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->exactly(2))
        ->method('isAdmin')
        ->willReturn(true);
    $this->readHostRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->originalHost);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidSeverity')
        ->willThrowException(
            HostException::idDoesNotExist('severityId', $this->request->severityId)
        );

    ($this->useCase)($this->request, $this->presenter, $this->hostId);

    expect($this->presenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(HostException::idDoesNotExist('severityId', $this->request->severityId)->getMessage());
});

it('should present a ConflictResponse when a host timezone ID is not valid', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->exactly(2))
        ->method('isAdmin')
        ->willReturn(true);
    $this->readHostRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->originalHost);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidTimezone')
        ->willThrowException(
            HostException::idDoesNotExist('timezoneId', $this->request->timezoneId)
        );

    ($this->useCase)($this->request, $this->presenter, $this->hostId);

    expect($this->presenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(HostException::idDoesNotExist('timezoneId', $this->request->timezoneId)->getMessage());
});

it('should present a ConflictResponse when a timeperiod ID is not valid', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->exactly(2))
        ->method('isAdmin')
        ->willReturn(true);
    $this->readHostRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->originalHost);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidTimePeriod')
        ->willThrowException(
            HostException::idDoesNotExist(
                'checkTimeperiodId',
                $this->request->checkTimeperiodId
            )
        );

    ($this->useCase)($this->request, $this->presenter, $this->hostId);

    expect($this->presenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(
            HostException::idDoesNotExist(
                'checkTimeperiodId',
                $this->request->checkTimeperiodId
            )->getMessage()
        );
});

it('should present a ConflictResponse when a command ID is not valid', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->exactly(2))
        ->method('isAdmin')
        ->willReturn(true);
    $this->readHostRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->originalHost);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidCommand')
        ->willThrowException(
            HostException::idDoesNotExist(
                'checkCommandId',
                $this->request->checkCommandId
            )
        );

    ($this->useCase)($this->request, $this->presenter, $this->hostId);

    expect($this->presenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(
            HostException::idDoesNotExist(
                'checkCommandId',
                $this->request->checkCommandId
            )->getMessage()
        );
});

it('should present a ConflictResponse when the host icon ID is not valid', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->exactly(2))
        ->method('isAdmin')
        ->willReturn(true);
    $this->readHostRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->originalHost);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidIcon')
        ->willThrowException(
            HostException::idDoesNotExist(
                'iconId',
                $this->request->iconId
            )
        );

    ($this->useCase)($this->request, $this->presenter, $this->hostId);

    expect($this->presenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(
            HostException::idDoesNotExist(
                'iconId',
                $this->request->iconId
            )->getMessage()
        );
});

// Tests for categories

it('should present a ConflictResponse when a host category does not exist', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->exactly(2))
        ->method('isAdmin')
        ->willReturn(true);
    $this->readHostRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->originalHost);

    // Host
    $this->optionService
        ->expects($this->once())
        ->method('findSelectedOptions')
        ->willReturn([$this->inheritanceModeOption]);
    $this->writeHostRepository
        ->expects($this->once())
        ->method('update');

    // Categories
    $this->validation
        ->expects($this->once())
        ->method('assertAreValidCategories')
        ->willThrowException(HostException::idsDoNotExist('categories', $this->request->categories));

    ($this->useCase)($this->request, $this->presenter, $this->hostId);

    expect($this->presenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(HostException::idsDoNotExist('categories', $this->request->categories)->getMessage());
});

// Tests for groups

it('should present a ConflictResponse when a host group does not exist', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->exactly(3))
        ->method('isAdmin')
        ->willReturn(true);
    $this->readHostRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->originalHost);

    // Host
    $this->optionService
        ->expects($this->once())
        ->method('findSelectedOptions')
        ->willReturn([$this->inheritanceModeOption]);
    $this->writeHostRepository
        ->expects($this->once())
        ->method('update');

    // Categories
    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('findByHost')
        ->willReturn([]);
    $this->writeHostCategoryRepository
        ->expects($this->once())
        ->method('linkToHost');

    // Groups
    $this->validation
        ->expects($this->once())
        ->method('assertAreValidGroups')
        ->willThrowException(HostException::idsDoNotExist('groups', $this->request->groups));

    ($this->useCase)($this->request, $this->presenter, $this->hostId);

    expect($this->presenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(HostException::idsDoNotExist('groups', $this->request->groups)->getMessage());
});

// Tests for parents templates

it('should present a ConflictResponse when a parent template ID is not valid', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->exactly(4))
        ->method('isAdmin')
        ->willReturn(true);
    $this->readHostRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->originalHost);

    // Host
    $this->optionService
        ->expects($this->once())
        ->method('findSelectedOptions')
        ->willReturn([$this->inheritanceModeOption]);
    $this->writeHostRepository
        ->expects($this->once())
        ->method('update');

    // Categories
    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('findByHost')
        ->willReturn([]);
    $this->writeHostCategoryRepository
        ->expects($this->once())
        ->method('linkToHost');

    // Groups
    $this->readHostGroupRepository
        ->expects($this->once())
        ->method('findByHost')
        ->willReturn([]);
    $this->writeHostGroupRepository
        ->expects($this->once())
        ->method('linkToHost');

    // Parent templates
    $this->validation
        ->expects($this->once())
        ->method('assertAreValidTemplates')
        ->willThrowException(HostException::idsDoNotExist('templates', $this->request->templates));

    ($this->useCase)($this->request, $this->presenter, $this->hostId );

    expect($this->presenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(
            HostException::idsDoNotExist(
                'templates',
                $this->request->templates
            )->getMessage()
        );
});

it('should present a ConflictResponse when a parent template creates a circular inheritance', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->exactly(4))
        ->method('isAdmin')
        ->willReturn(true);
    $this->readHostRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->originalHost);

    // Host
    $this->optionService
        ->expects($this->once())
        ->method('findSelectedOptions')
        ->willReturn([$this->inheritanceModeOption]);
    $this->writeHostRepository
        ->expects($this->once())
        ->method('update');

    // Categories
    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('findByHost')
        ->willReturn([]);
    $this->writeHostCategoryRepository
        ->expects($this->once())
        ->method('linkToHost');

    // Groups
    $this->readHostGroupRepository
        ->expects($this->once())
        ->method('findByHost')
        ->willReturn([]);
    $this->writeHostGroupRepository
        ->expects($this->once())
        ->method('linkToHost');

    // Parent templates
    $this->validation
        ->expects($this->once())
        ->method('assertAreValidTemplates')
        ->willThrowException(HostException::circularTemplateInheritance());

    ($this->useCase)($this->request, $this->presenter, $this->hostId );

    expect($this->presenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(
            HostException::circularTemplateInheritance()->getMessage()
        );
});

// Test for successful request

it('should present a NoContentResponse on success', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->exactly(4))
        ->method('isAdmin')
        ->willReturn(true);
    $this->readHostRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->originalHost);

    // Host
    $this->optionService
        ->expects($this->once())
        ->method('findSelectedOptions')
        ->willReturn([$this->inheritanceModeOption]);

    $this->validation->expects($this->once())->method('assertIsValidName');
    $this->validation->expects($this->once())->method('assertIsValidSeverity');
    $this->validation->expects($this->once())->method('assertIsValidTimezone');
    $this->validation->expects($this->exactly(2))->method('assertIsValidTimePeriod');
    $this->validation->expects($this->exactly(2))->method('assertIsValidCommand');
    $this->validation->expects($this->once())->method('assertIsValidIcon');

    $this->writeHostRepository
        ->expects($this->once())
        ->method('update');

    // Categories
    $this->validation
        ->expects($this->once())
        ->method('assertAreValidCategories');
    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('findByHost')
        ->willReturn([$this->categoryA]);
    $this->writeHostCategoryRepository
        ->expects($this->once())
        ->method('linkToHost');
    $this->writeHostCategoryRepository
        ->expects($this->once())
        ->method('unlinkFromHost');

    // Groups
    $this->validation
        ->expects($this->once())
        ->method('assertAreValidGroups');
    $this->readHostGroupRepository
        ->expects($this->once())
        ->method('findByHost')
        ->willReturn([$this->groupA]);
    $this->writeHostGroupRepository
        ->expects($this->once())
        ->method('linkToHost');
    $this->writeHostGroupRepository
        ->expects($this->once())
        ->method('unlinkFromHost');

    // Parent templates
    $this->validation
        ->expects($this->once())
        ->method('assertAreValidTemplates');
    $this->writeHostRepository
        ->expects($this->once())
        ->method('deleteParents');
    $this->writeHostRepository
        ->expects($this->exactly(2))
        ->method('addParent');

    // Macros
    $this->readHostRepository
        ->expects($this->once())
        ->method('findParents')
        ->willReturn($this->inheritanceLineIds);
    $this->readHostMacroRepository
        ->expects($this->once())
        ->method('findByHostIds')
        ->willReturn($this->hostMacros);
    $this->readCommandMacroRepository
        ->expects($this->once())
        ->method('findByCommandIdAndType')
        ->willReturn($this->commandMacros);
    $this->writeHostMacroRepository
        ->expects($this->once())
        ->method('delete');
    $this->writeHostMacroRepository
        ->expects($this->once())
        ->method('add');
    $this->writeHostMacroRepository
        ->expects($this->once())
        ->method('update');

    ($this->useCase)($this->request, $this->presenter, $this->hostId);

    expect($this->presenter->response)->toBeInstanceOf(NoContentResponse::class);
});
