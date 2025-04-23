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

namespace Tests\Core\HostTemplate\Application\UseCase\PartialUpdateHostTemplate;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Option\Option;
use Centreon\Domain\Option\OptionService;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\CommandMacro\Application\Repository\ReadCommandMacroRepositoryInterface;
use Core\CommandMacro\Domain\Model\CommandMacro;
use Core\CommandMacro\Domain\Model\CommandMacroType;
use Core\Common\Application\Converter\YesNoDefaultConverter;
use Core\Common\Application\Repository\ReadVaultRepositoryInterface;
use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
use Core\Host\Application\Converter\HostEventConverter;
use Core\Host\Domain\Model\HostEvent;
use Core\Host\Domain\Model\SnmpVersion;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\HostCategory\Application\Repository\WriteHostCategoryRepositoryInterface;
use Core\HostCategory\Domain\Model\HostCategory;
use Core\HostTemplate\Application\Exception\HostTemplateException;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\HostTemplate\Application\Repository\WriteHostTemplateRepositoryInterface;
use Core\HostTemplate\Application\UseCase\PartialUpdateHostTemplate\PartialUpdateHostTemplate;
use Core\HostTemplate\Application\UseCase\PartialUpdateHostTemplate\PartialUpdateHostTemplateRequest;
use Core\HostTemplate\Application\UseCase\PartialUpdateHostTemplate\PartialUpdateHostTemplateValidation;
use Core\HostTemplate\Domain\Model\HostTemplate;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Macro\Application\Repository\ReadHostMacroRepositoryInterface;
use Core\Macro\Application\Repository\WriteHostMacroRepositoryInterface;
use Core\Macro\Domain\Model\Macro;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

beforeEach(function (): void {
    $this->readHostTemplateRepository = $this->createMock(ReadHostTemplateRepositoryInterface::class);
    $this->readHostMacroRepository = $this->createMock(ReadHostMacroRepositoryInterface::class);
    $this->readCommandMacroRepository = $this->createMock(ReadCommandMacroRepositoryInterface::class);
    $this->writeHostMacroRepository = $this->createMock(WriteHostMacroRepositoryInterface::class);
    $this->dataStorageEngine = $this->createMock(DataStorageEngineInterface::class);
    $this->user = $this->createMock(ContactInterface::class);

    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->presenter = new DefaultPresenter($this->presenterFormatter);

    $this->useCase = new PartialUpdateHostTemplate(
        $this->readHostTemplateRepository = $this->createMock(ReadHostTemplateRepositoryInterface::class),
        $this->readHostMacroRepository = $this->createMock(ReadHostMacroRepositoryInterface::class),
        $this->readCommandMacroRepository = $this->createMock(ReadCommandMacroRepositoryInterface::class),
        $this->writeHostMacroRepository = $this->createMock(WriteHostMacroRepositoryInterface::class),
        $this->readHostCategoryRepository = $this->createMock(ReadHostCategoryRepositoryInterface::class),
        $this->writeHostCategoryRepository = $this->createMock(WriteHostCategoryRepositoryInterface::class),
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->writeHostTemplateRepository = $this->createMock(WriteHostTemplateRepositoryInterface::class),
        $this->validation = $this->createMock(PartialUpdateHostTemplateValidation::class),
        $this->optionService = $this->createMock(OptionService::class),
        $this->dataStorageEngine = $this->createMock(DataStorageEngineInterface::class),
        $this->user = $this->createMock(ContactInterface::class),
        $this->writeVaultRepository = $this->createMock(WriteVaultRepositoryInterface::class),
        $this->readVaultRepository = $this->createMock(ReadVaultRepositoryInterface::class),
    );

    $this->inheritanceModeOption = new Option();
    $this->inheritanceModeOption->setName('inheritanceMode')->setValue('1');

    // Settup host template
    $this->hostTemplateId = 1;
    $this->checkCommandId = 1;

    $this->originalHostTemplate = new HostTemplate(
        id: $this->hostTemplateId,
        name: 'host_template_name',
        alias: 'host_template_alias',
        snmpVersion: SnmpVersion::Three,
        snmpCommunity: 'someCommunity',
        noteUrl: 'some note url',
        note: 'a note',
        actionUrl: 'some action url',
        comment: 'a comment'
    );

    $this->request = new PartialUpdateHostTemplateRequest();
    $this->request->name = $this->originalHostTemplate->getName() . ' edit  ';
    $this->request->alias = $this->originalHostTemplate->getAlias() . ' edit  ';
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

    $this->editedHostTemplate = new HostTemplate(
        id: $this->hostTemplateId,
        name: $this->request->name,
        alias: $this->request->alias,
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
        isLocked: false,
    );

    // Settup parent templates
    $this->parentTemplates = [2, 3];
    $this->request->templates = $this->parentTemplates;

    // Settup macros
    $this->macroA = new Macro($this->hostTemplateId, 'macroNameA', 'macroValueA');
    $this->macroA->setOrder(0);
    $this->macroB = new Macro($this->hostTemplateId, 'macroNameB', 'macroValueB');
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
});

// Generic usecase tests

it('should present a ForbiddenResponse when a user has insufficient rights', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(false);

    ($this->useCase)($this->request, $this->presenter, $this->hostTemplateId);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(HostTemplateException::writeActionsNotAllowed()->getMessage());
});

it('should present an ErrorResponse when an exception is thrown', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('findById')
        ->willThrowException(new \Exception);

    ($this->useCase)($this->request, $this->presenter, $this->hostTemplateId);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(HostTemplateException::partialUpdateHostTemplate()->getMessage());
});

it('should present a NotFoundResponse when the host template does not exist', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('findByIdAndAccessGroups')
        ->willReturn(null);

    ($this->useCase)($this->request, $this->presenter, $this->hostTemplateId);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe('Host template not found');
});

it('should present a InvalidArgumentResponse when the host template is locked', function (): void {
    $hostTemplate = new HostTemplate(id: 1, name: 'tpl-name', alias: 'tpl-alias', isLocked: true);
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($hostTemplate);

    ($this->useCase)($this->request, $this->presenter, $this->hostTemplateId);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(InvalidArgumentResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(HostTemplateException::hostIsLocked($hostTemplate->getId())->getMessage());
});

 // Tests for host template

it('should present a ConflictResponse when name is already used', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->originalHostTemplate);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidName')
        ->willThrowException(
            HostTemplateException::nameAlreadyExists(
                HostTemplate::formatName($this->request->name),
                $this->request->name
            )
        );

    ($this->useCase)($this->request, $this->presenter, $this->hostTemplateId);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(
            HostTemplateException::nameAlreadyExists(
                HostTemplate::formatName($this->request->name),
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
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('findByIdAndAccessGroups')
        ->willReturn($this->originalHostTemplate);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidSeverity')
        ->willThrowException(
            HostTemplateException::idDoesNotExist('severityId', $this->request->severityId)
        );

    ($this->useCase)($this->request, $this->presenter, $this->hostTemplateId);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(HostTemplateException::idDoesNotExist('severityId', $this->request->severityId)->getMessage());
});

it('should present a ConflictResponse when a host timezone ID is not valid', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->originalHostTemplate);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidTimezone')
        ->willThrowException(
            HostTemplateException::idDoesNotExist('timezoneId', $this->request->timezoneId)
        );

    ($this->useCase)($this->request, $this->presenter, $this->hostTemplateId);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(HostTemplateException::idDoesNotExist('timezoneId', $this->request->timezoneId)->getMessage());
});

it('should present a ConflictResponse when a timeperiod ID is not valid', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('findByIdAndAccessGroups')
        ->willReturn($this->originalHostTemplate);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidTimePeriod')
        ->willThrowException(
            HostTemplateException::idDoesNotExist(
                'checkTimeperiodId',
                $this->request->checkTimeperiodId
            )
        );

    ($this->useCase)($this->request, $this->presenter, $this->hostTemplateId);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(
            HostTemplateException::idDoesNotExist(
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
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->originalHostTemplate);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidCommand')
        ->willThrowException(
            HostTemplateException::idDoesNotExist(
                'checkCommandId',
                $this->request->checkCommandId
            )
        );

    ($this->useCase)($this->request, $this->presenter, $this->hostTemplateId);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(
            HostTemplateException::idDoesNotExist(
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
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('findByIdAndAccessGroups')
        ->willReturn($this->originalHostTemplate);

    $this->validation
        ->expects($this->once())
        ->method('assertIsValidIcon')
        ->willThrowException(
            HostTemplateException::idDoesNotExist(
                'iconId',
                $this->request->iconId
            )
        );

    ($this->useCase)($this->request, $this->presenter, $this->hostTemplateId);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(
            HostTemplateException::idDoesNotExist(
                'iconId',
                $this->request->iconId
            )->getMessage()
        );
});

// Tests for parents templates

it('should present a ConflictResponse when a parent template ID is not valid', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->originalHostTemplate);

    // Host template
    $this->optionService
        ->expects($this->once())
        ->method('findSelectedOptions')
        ->willReturn(['inheritance_mode' => $this->inheritanceModeOption]);
    $this->writeHostTemplateRepository
        ->expects($this->once())
        ->method('update');

    // Parent templates
    $this->validation
        ->expects($this->once())
        ->method('assertAreValidTemplates')
        ->willThrowException(HostTemplateException::idsDoNotExist('templates', $this->request->templates));

    ($this->useCase)($this->request, $this->presenter, $this->hostTemplateId );

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(
            HostTemplateException::idsDoNotExist(
                'templates',
                 $this->request->templates
            )->getMessage()
        );
});

it('should present a ConflictResponse when a parent template create a circular inheritance', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->originalHostTemplate);

    // Host template
    $this->optionService
        ->expects($this->once())
        ->method('findSelectedOptions')
        ->willReturn(['inheritance_mode' => $this->inheritanceModeOption]);
    $this->writeHostTemplateRepository
        ->expects($this->once())
        ->method('update');

    // Parent templates
    $this->validation
        ->expects($this->once())
        ->method('assertAreValidTemplates')
        ->willThrowException(HostTemplateException::circularTemplateInheritance());

    ($this->useCase)($this->request, $this->presenter, $this->hostTemplateId );

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(
            HostTemplateException::circularTemplateInheritance()->getMessage()
        );
});

// Tests for categories

it('should present a ConflictResponse when a host category does not exist', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->originalHostTemplate);

    // Host template
    $this->optionService
        ->expects($this->once())
        ->method('findSelectedOptions')
        ->willReturn(['inheritance_mode' => $this->inheritanceModeOption]);
    $this->writeHostTemplateRepository
        ->expects($this->once())
        ->method('update');

    // Parent templates
    $this->writeHostTemplateRepository
        ->expects($this->once())
        ->method('deleteParents');
    $this->writeHostTemplateRepository
        ->expects($this->exactly(2))
        ->method('addParent');

    // Macros
    $this->readHostTemplateRepository
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

    // Categories
    $this->validation
        ->expects($this->once())
        ->method('assertAreValidCategories')
        ->willThrowException(HostTemplateException::idsDoNotExist('categories', $this->request->categories));

    ($this->useCase)($this->request, $this->presenter, $this->hostTemplateId);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(HostTemplateException::idsDoNotExist('categories', $this->request->categories)->getMessage());
});

// Test for successful request

it('should present a NoContentResponse on success', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->exactly(2))
        ->method('isAdmin')
        ->willReturn(true);
    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->originalHostTemplate);

    // Host template
    $this->optionService
        ->expects($this->once())
        ->method('findSelectedOptions')
        ->willReturn(['inheritance_mode' => $this->inheritanceModeOption]);

    $this->validation->expects($this->once())->method('assertIsValidSeverity');
    $this->validation->expects($this->once())->method('assertIsValidTimezone');
    $this->validation->expects($this->exactly(2))->method('assertIsValidTimePeriod');
    $this->validation->expects($this->exactly(2))->method('assertIsValidCommand');
    $this->validation->expects($this->once())->method('assertIsValidIcon');

    $this->writeHostTemplateRepository
        ->expects($this->once())
        ->method('update');

    // Parent templates
    $this->validation
        ->expects($this->once())
        ->method('assertAreValidTemplates');
    $this->writeHostTemplateRepository
        ->expects($this->once())
        ->method('deleteParents');
    $this->writeHostTemplateRepository
        ->expects($this->exactly(2))
        ->method('addParent');

    // Macros
    $this->readHostTemplateRepository
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

    ($this->useCase)($this->request, $this->presenter, $this->hostTemplateId);

    expect($this->presenter->getResponseStatus())->toBeInstanceOf(NoContentResponse::class);
});
