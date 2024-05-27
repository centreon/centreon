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

namespace Tests\Core\ServiceSeverity\Application\UseCase\AddServiceSeverity;

use Centreon\Domain\Common\Assertion\AssertionException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Option\Option;
use Centreon\Domain\Option\OptionService;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
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
use Core\HostTemplate\Application\UseCase\AddHostTemplate\AddHostTemplate;
use Core\HostTemplate\Application\UseCase\AddHostTemplate\AddHostTemplateRequest;
use Core\HostTemplate\Application\UseCase\AddHostTemplate\AddHostTemplateResponse;
use Core\HostTemplate\Application\UseCase\AddHostTemplate\AddHostTemplateValidation;
use Core\HostTemplate\Domain\Model\HostTemplate;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Macro\Application\Repository\ReadHostMacroRepositoryInterface;
use Core\Macro\Application\Repository\WriteHostMacroRepositoryInterface;
use Core\Macro\Domain\Model\Macro;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Tests\Core\HostTemplate\Infrastructure\API\AddHostTemplate\AddHostTemplatePresenterStub;

beforeEach(function (): void {
    $this->presenter = new AddHostTemplatePresenterStub(
        $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class)
    );

    $this->useCase = new AddHostTemplate(
        $this->writeHostTemplateRepository = $this->createMock(WriteHostTemplateRepositoryInterface::class),
        $this->readHostTemplateRepository = $this->createMock(ReadHostTemplateRepositoryInterface::class),
        $this->readHostCategoryRepository = $this->createMock(ReadHostCategoryRepositoryInterface::class),
        $this->writeHostCategoryRepository = $this->createMock(WriteHostCategoryRepositoryInterface::class),
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->readHostMacroRepository = $this->createMock(ReadHostMacroRepositoryInterface::class),
        $this->readCommandMacroRepository = $this->createMock(ReadCommandMacroRepositoryInterface::class),
        $this->writeHostMacroRepository = $this->createMock(WriteHostMacroRepositoryInterface::class),
        $this->dataStorageEngine = $this->createMock(DataStorageEngineInterface::class),
        $this->optionService = $this->createMock(OptionService::class),
        $this->user = $this->createMock(ContactInterface::class),
        $this->validation = $this->createMock(AddHostTemplateValidation::class),
        $this->writeVaultRepository = $this->createMock(WriteVaultRepositoryInterface::class),
        $this->readVaultRepository = $this->createMock(ReadVaultRepositoryInterface::class),
    );

    $this->inheritanceModeOption = new Option();
    $this->inheritanceModeOption->setName('inheritanceMode')->setValue('1');

    // Settup host template
    $this->request = new AddHostTemplateRequest();
    $this->request->name = '  host template name  ';
    $this->request->alias = '  host-template-alias  ';
    $this->request->snmpVersion = SnmpVersion::Two->value;
    $this->request->snmpCommunity = 'snmpCommunity-value';
    $this->request->timezoneId = 1;
    $this->request->severityId = 1;
    $this->request->checkCommandId = 1;
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
    $this->request->noteUrl = 'noteUrl-value';
    $this->request->note = 'note-value';
    $this->request->actionUrl = 'actionUrl-value';
    $this->request->iconId = 1;
    $this->request->iconAlternative = 'iconAlternative-value';
    $this->request->comment = 'comment-value';

    $this->hostTemplate = new HostTemplate(
        id: 1,
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

    // Settup categories
    $this->categories = [
        $this->categoryA = new HostCategory(12, 'cat-name-A', 'cat-alias-A'),
        $this->categoryB = new HostCategory(13, 'cat-name-B', 'cat-alias-B'),
    ];
    $this->request->categories = [$this->categoryA->getId(), $this->categoryB->getId()];

    // Settup parent templates
    $this->request->templates = [4, 8];
    $this->parentTemplates = [
        ['id' => 4, 'name' => 'template-A'],
        ['id' => 8, 'name' => 'template-B'],
    ];

    // Settup macros
    $this->macroA = new Macro($this->hostTemplate->getId(), 'macroNameA', 'macroValueA');
    $this->macroA->setOrder(0);
    $this->macroB = new Macro($this->hostTemplate->getId(), 'macroNameB', 'macroValueB');
    $this->macroB->setOrder(1);
    $this->commandMacro = new CommandMacro(1, CommandMacroType::Host, 'commandMacroName');
    $this->commandMacros = [
        $this->commandMacro->getName() => $this->commandMacro,
    ];
    $this->hostMacros = [
        $this->macroA->getName() => $this->macroA,
        $this->macroB->getName() => $this->macroB,
    ];
    $this->inheritanceInfos = [
        ['parent_id' => 4, 'child_id' => 1, 'order' => 1],
        ['parent_id' => 8, 'child_id' => 1, 'order' => 2],
    ];
    $this->request->macros = [
        [
            'name' => $this->macroA->getName(),
            'value' => $this->macroA->getValue(),
            'is_password' => $this->macroA->isPassword(),
            'description' => $this->macroA->getDescription(),
        ],
        [
            'name' => $this->macroB->getName(),
            'value' => $this->macroB->getValue(),
            'is_password' => $this->macroB->isPassword(),
            'description' => $this->macroB->getDescription(),
        ],
    ];
});

it('should present an ErrorResponse when a generic exception is thrown', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->optionService
        ->expects($this->once())
        ->method('findSelectedOptions')
        ->willThrowException(new \Exception());

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(HostTemplateException::addHostTemplate()->getMessage());
});

it('should present a ForbiddenResponse when a user has insufficient rights', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(false);

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(HostTemplateException::addNotAllowed()->getMessage());
});

it('should present a ConflictResponse when name is already used', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->validation
        ->expects($this->once())
        ->method('assertIsValidName')
        ->willThrowException(
            HostTemplateException::nameAlreadyExists(
                HostTemplate::formatName($this->request->name),
                $this->request->name
            )
        );

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->response->getMessage())
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
    $this->validation
        ->expects($this->once())
        ->method('assertIsValidSeverity')
        ->willThrowException(
            HostTemplateException::idDoesNotExist('severityId', $this->request->severityId)
        );

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(HostTemplateException::idDoesNotExist('severityId', $this->request->severityId)->getMessage());
});

it('should present a ConflictResponse when a host timezone ID is not valid', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->validation
        ->expects($this->once())
        ->method('assertIsValidTimezone')
        ->willThrowException(
            HostTemplateException::idDoesNotExist('timezoneId', $this->request->timezoneId)
        );

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(HostTemplateException::idDoesNotExist('timezoneId', $this->request->timezoneId)->getMessage());
});

it('should present a ConflictResponse when a timeperiod ID is not valid', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->validation
        ->expects($this->once())
        ->method('assertIsValidTimePeriod')
        ->willThrowException(
            HostTemplateException::idDoesNotExist(
                'checkTimeperiodId',
                $this->request->checkTimeperiodId
            )
        );

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->response->getMessage())
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
    $this->validation
        ->expects($this->once())
        ->method('assertIsValidCommand')
        ->willThrowException(
            HostTemplateException::idDoesNotExist(
                'checkCommandId',
                $this->request->checkCommandId
            )
        );

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->response->getMessage())
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
    $this->validation
        ->expects($this->once())
        ->method('assertIsValidIcon')
        ->willThrowException(
            HostTemplateException::idDoesNotExist(
                'iconId',
                $this->request->iconId
            )
        );

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(
            HostTemplateException::idDoesNotExist(
                'iconId',
                $this->request->iconId
            )->getMessage()
        );
});

it('should present an InvalidArgumentResponse when a field assert failed', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->optionService
        ->expects($this->once())
        ->method('findSelectedOptions')
        ->willReturn(['inheritance_mode' => $this->inheritanceModeOption]);

    $this->request->alias = '';

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(InvalidArgumentResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(AssertionException::notEmptyString('NewHostTemplate::alias')->getMessage());
});

it('should present a ConflictResponse when a host category ID is not valid', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->optionService
        ->expects($this->once())
        ->method('findSelectedOptions')
        ->willReturn(['inheritance_mode' => $this->inheritanceModeOption]);

    $this->validation
        ->expects($this->once())
        ->method('assertAreValidCategories')
        ->willThrowException(
            HostTemplateException::idsDoNotExist(
                'categories',
                [$this->request->categories[1]]
            )
        );

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(
            HostTemplateException::idsDoNotExist(
                'categories',
                [$this->request->categories[1]]
            )->getMessage()
        );
});

it('should present a ConflictResponse when a parent template ID is not valid', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->optionService
        ->expects($this->once())
        ->method('findSelectedOptions')
        ->willReturn(['inheritance_mode' => $this->inheritanceModeOption]);

    $this->validation
        ->expects($this->once())
        ->method('assertAreValidTemplates')
        ->willThrowException(
            HostTemplateException::idsDoNotExist('templates', $this->request->templates)
        );

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(
            HostTemplateException::idsDoNotExist(
                'templates',
                 $this->request->templates
            )->getMessage()
        );
});

it('should present an ErrorResponse if the newly created host template cannot be retrieved', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->optionService
        ->expects($this->once())
        ->method('findSelectedOptions')
        ->willReturn(['inheritance_mode' => $this->inheritanceModeOption]);

    $this->writeHostTemplateRepository
        ->expects($this->once())
        ->method('add')
        ->willReturn(1);

    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn(null);

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(HostTemplateException::errorWhileRetrievingObject()->getMessage());
});

it('should return created object on success (with admin user)', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->validation->expects($this->once())->method('assertIsValidName');
    $this->validation->expects($this->once())->method('assertIsValidSeverity');
    $this->validation->expects($this->once())->method('assertIsValidTimezone');
    $this->validation->expects($this->exactly(2))->method('assertIsValidTimePeriod');
    $this->validation->expects($this->exactly(2))->method('assertIsValidCommand');
    $this->validation->expects($this->once())->method('assertIsValidIcon');
    $this->optionService
        ->expects($this->once())
        ->method('findSelectedOptions')
        ->willReturn(['inheritance_mode' => $this->inheritanceModeOption]);
    $this->writeHostTemplateRepository
        ->expects($this->once())
        ->method('add')
        ->willReturn($this->hostTemplate->getId());

    $this->validation->expects($this->once())->method('assertAreValidCategories');
    $this->writeHostCategoryRepository
        ->expects($this->once())
        ->method('linkToHost');

    $this->validation->expects($this->once())->method('assertAreValidTemplates');
    $this->writeHostTemplateRepository
        ->expects($this->exactly(2))
        ->method('addParent');

    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('findParents')
        ->willReturn($this->inheritanceInfos);
    $this->readHostMacroRepository
        ->expects($this->once())
        ->method('findByHostIds')
        ->willReturn([]);
    $this->readCommandMacroRepository
        ->expects($this->once())
        ->method('findByCommandIdAndType')
        ->willReturn($this->commandMacros);
    $this->writeHostMacroRepository
        ->expects($this->exactly(2))
        ->method('add');

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->hostTemplate);
    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('findByHost')
        ->willReturn($this->categories);
    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('findNamesByIds')
        ->willReturn(
            array_combine(
                array_map((fn($row) => $row['id']), $this->parentTemplates),
                array_map((fn($row) => $row['name']), $this->parentTemplates)
            )
        );
    $this->readHostMacroRepository
        ->expects($this->once())
        ->method('findByHostId')
        ->willReturn($this->hostMacros);

    ($this->useCase)($this->request, $this->presenter);

    $response = $this->presenter->response;

    expect($response)->toBeInstanceOf(AddHostTemplateResponse::class)
        ->and($response->id)
        ->toBe($this->hostTemplate->getId())
        ->and($response->name)
        ->toBe($this->hostTemplate->getName())
        ->and($response->alias)
        ->toBe($this->hostTemplate->getAlias())
        ->and($response->snmpVersion)
        ->toBe($this->hostTemplate->getSnmpVersion()->value)
        ->and($response->timezoneId)
        ->toBe($this->hostTemplate->getTimezoneId())
        ->and($response->severityId)
        ->toBe($this->hostTemplate->getSeverityId())
        ->and($response->checkCommandId)
        ->toBe($this->hostTemplate->getCheckCommandId())
        ->and($response->checkCommandArgs)
        ->toBe($this->hostTemplate->getCheckCommandArgs())
        ->and($response->checkTimeperiodId)
        ->toBe($this->hostTemplate->getCheckTimeperiodId())
        ->and($response->maxCheckAttempts)
        ->toBe($this->hostTemplate->getMaxCheckAttempts())
        ->and($response->normalCheckInterval)
        ->toBe($this->hostTemplate->getNormalCheckInterval())
        ->and($response->retryCheckInterval)
        ->toBe($this->hostTemplate->getRetryCheckInterval())
        ->and($response->activeCheckEnabled)
        ->toBe(YesNoDefaultConverter::toInt($this->hostTemplate->getActiveCheckEnabled()))
        ->and($response->passiveCheckEnabled)
        ->toBe(YesNoDefaultConverter::toInt($this->hostTemplate->getPassiveCheckEnabled()))
        ->and($response->notificationEnabled)
        ->toBe(YesNoDefaultConverter::toInt($this->hostTemplate->getNotificationEnabled()))
        ->and($response->notificationOptions)
        ->toBe(HostEventConverter::toBitFlag($this->hostTemplate->getNotificationOptions()))
        ->and($response->notificationInterval)
        ->toBe($this->hostTemplate->getNotificationInterval())
        ->and($response->notificationTimeperiodId)
        ->toBe($this->hostTemplate->getNotificationTimeperiodId())
        ->and($response->addInheritedContactGroup)
        ->toBe($this->hostTemplate->addInheritedContactGroup())
        ->and($response->addInheritedContact)
        ->toBe($this->hostTemplate->addInheritedContact())
        ->and($response->firstNotificationDelay)
        ->toBe($this->hostTemplate->getFirstNotificationDelay())
        ->and($response->recoveryNotificationDelay)
        ->toBe($this->hostTemplate->getRecoveryNotificationDelay())
        ->and($response->acknowledgementTimeout)
        ->toBe($this->hostTemplate->getAcknowledgementTimeout())
        ->and($response->freshnessChecked)
        ->toBe(YesNoDefaultConverter::toInt($this->hostTemplate->getFreshnessChecked()))
        ->and($response->freshnessThreshold)
        ->toBe($this->hostTemplate->getFreshnessThreshold())
        ->and($response->flapDetectionEnabled)
        ->toBe(YesNoDefaultConverter::toInt($this->hostTemplate->getFlapDetectionEnabled()))
        ->and($response->lowFlapThreshold)
        ->toBe($this->hostTemplate->getLowFlapThreshold())
        ->and($response->highFlapThreshold)
        ->toBe($this->hostTemplate->getHighFlapThreshold())
        ->and($response->eventHandlerEnabled)
        ->toBe(YesNoDefaultConverter::toInt($this->hostTemplate->getEventHandlerEnabled()))
        ->and($response->eventHandlerCommandId)
        ->toBe($this->hostTemplate->getEventHandlerCommandId())
        ->and($response->eventHandlerCommandArgs)
        ->toBe($this->hostTemplate->getEventHandlerCommandArgs())
        ->and($response->noteUrl)
        ->toBe($this->hostTemplate->getNoteUrl())
        ->and($response->note)
        ->toBe($this->hostTemplate->getNote())
        ->and($response->actionUrl)
        ->toBe($this->hostTemplate->getActionUrl())
        ->and($response->iconId)
        ->toBe($this->hostTemplate->getIconId())
        ->and($response->iconAlternative)
        ->toBe($this->hostTemplate->getIconAlternative())
        ->and($response->comment)
        ->toBe($this->hostTemplate->getComment())
        ->and($response->categories)
        ->toBe(array_map(
            (fn($category) => ['id' => $category->getId(), 'name' => $category->getName()]),
            $this->categories
        ))
        ->and($response->templates)
        ->toBe($this->parentTemplates)
        ->and($response->macros)
        ->toBe(array_map(
            (fn($macro) => [
                'name' => $macro->getName(),
                'value' => $macro->getValue(),
                'isPassword' => $macro->isPassword(),
                'description' => $macro->getDescription(),
            ]),
            $this->hostMacros
        ));
});

it('should return created object on success (with non-admin user)', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->validation->expects($this->once())->method('assertIsValidName');
    $this->validation->expects($this->once())->method('assertIsValidSeverity');
    $this->validation->expects($this->once())->method('assertIsValidTimezone');
    $this->validation->expects($this->exactly(2))->method('assertIsValidTimePeriod');
    $this->validation->expects($this->exactly(2))->method('assertIsValidCommand');
    $this->validation->expects($this->once())->method('assertIsValidIcon');
    $this->optionService
        ->expects($this->once())
        ->method('findSelectedOptions')
        ->willReturn(['inheritance_mode' => $this->inheritanceModeOption]);
    $this->writeHostTemplateRepository
        ->expects($this->once())
        ->method('add')
        ->willReturn($this->hostTemplate->getId());

    $this->validation->expects($this->once())->method('assertAreValidCategories');
    $this->writeHostCategoryRepository
        ->expects($this->once())
        ->method('linkToHost');

    $this->validation->expects($this->once())->method('assertAreValidTemplates');
    $this->writeHostTemplateRepository
        ->expects($this->exactly(2))
        ->method('addParent');

    $this->validation->expects($this->once())->method('assertAreValidTemplates');
    $this->writeHostTemplateRepository
        ->expects($this->exactly(2))
        ->method('addParent');

    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('findParents')
        ->willReturn($this->inheritanceInfos);
    $this->readHostMacroRepository
        ->expects($this->once())
        ->method('findByHostIds')
        ->willReturn([]);
    $this->readCommandMacroRepository
        ->expects($this->once())
        ->method('findByCommandIdAndType')
        ->willReturn($this->commandMacros);
    $this->writeHostMacroRepository
        ->expects($this->exactly(2))
        ->method('add');

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);
    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->hostTemplate);
    $this->readAccessGroupRepository
        ->expects($this->once())
        ->method('findByContact');
    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('findByHostAndAccessGroups')
        ->willReturn($this->categories);
    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('findNamesByIds')
        ->willReturn(
            array_combine(
                array_map((fn($row) => $row['id']), $this->parentTemplates),
                array_map((fn($row) => $row['name']), $this->parentTemplates)
            )
        );
    $this->readHostMacroRepository
        ->expects($this->once())
        ->method('findByHostId')
        ->willReturn($this->hostMacros);

    ($this->useCase)($this->request, $this->presenter);

    $response = $this->presenter->response;

    expect($response)->toBeInstanceOf(AddHostTemplateResponse::class)
        ->and($response->id)
        ->toBe($this->hostTemplate->getId())
        ->and($response->name)
        ->toBe($this->hostTemplate->getName())
        ->and($response->alias)
        ->toBe($this->hostTemplate->getAlias())
        ->and($response->snmpVersion)
        ->toBe($this->hostTemplate->getSnmpVersion()->value)
        ->and($response->timezoneId)
        ->toBe($this->hostTemplate->getTimezoneId())
        ->and($response->severityId)
        ->toBe($this->hostTemplate->getSeverityId())
        ->and($response->checkCommandId)
        ->toBe($this->hostTemplate->getCheckCommandId())
        ->and($response->checkCommandArgs)
        ->toBe($this->hostTemplate->getCheckCommandArgs())
        ->and($response->checkTimeperiodId)
        ->toBe($this->hostTemplate->getCheckTimeperiodId())
        ->and($response->maxCheckAttempts)
        ->toBe($this->hostTemplate->getMaxCheckAttempts())
        ->and($response->normalCheckInterval)
        ->toBe($this->hostTemplate->getNormalCheckInterval())
        ->and($response->retryCheckInterval)
        ->toBe($this->hostTemplate->getRetryCheckInterval())
        ->and($response->activeCheckEnabled)
        ->toBe(YesNoDefaultConverter::toInt($this->hostTemplate->getActiveCheckEnabled()))
        ->and($response->passiveCheckEnabled)
        ->toBe(YesNoDefaultConverter::toInt($this->hostTemplate->getPassiveCheckEnabled()))
        ->and($response->notificationEnabled)
        ->toBe(YesNoDefaultConverter::toInt($this->hostTemplate->getNotificationEnabled()))
        ->and($response->notificationOptions)
        ->toBe(HostEventConverter::toBitFlag($this->hostTemplate->getNotificationOptions()))
        ->and($response->notificationInterval)
        ->toBe($this->hostTemplate->getNotificationInterval())
        ->and($response->notificationTimeperiodId)
        ->toBe($this->hostTemplate->getNotificationTimeperiodId())
        ->and($response->addInheritedContactGroup)
        ->toBe($this->hostTemplate->addInheritedContactGroup())
        ->and($response->addInheritedContact)
        ->toBe($this->hostTemplate->addInheritedContact())
        ->and($response->firstNotificationDelay)
        ->toBe($this->hostTemplate->getFirstNotificationDelay())
        ->and($response->recoveryNotificationDelay)
        ->toBe($this->hostTemplate->getRecoveryNotificationDelay())
        ->and($response->acknowledgementTimeout)
        ->toBe($this->hostTemplate->getAcknowledgementTimeout())
        ->and($response->freshnessChecked)
        ->toBe(YesNoDefaultConverter::toInt($this->hostTemplate->getFreshnessChecked()))
        ->and($response->freshnessThreshold)
        ->toBe($this->hostTemplate->getFreshnessThreshold())
        ->and($response->flapDetectionEnabled)
        ->toBe(YesNoDefaultConverter::toInt($this->hostTemplate->getFlapDetectionEnabled()))
        ->and($response->lowFlapThreshold)
        ->toBe($this->hostTemplate->getLowFlapThreshold())
        ->and($response->highFlapThreshold)
        ->toBe($this->hostTemplate->getHighFlapThreshold())
        ->and($response->eventHandlerEnabled)
        ->toBe(YesNoDefaultConverter::toInt($this->hostTemplate->getEventHandlerEnabled()))
        ->and($response->eventHandlerCommandId)
        ->toBe($this->hostTemplate->getEventHandlerCommandId())
        ->and($response->eventHandlerCommandArgs)
        ->toBe($this->hostTemplate->getEventHandlerCommandArgs())
        ->and($response->noteUrl)
        ->toBe($this->hostTemplate->getNoteUrl())
        ->and($response->note)
        ->toBe($this->hostTemplate->getNote())
        ->and($response->actionUrl)
        ->toBe($this->hostTemplate->getActionUrl())
        ->and($response->iconId)
        ->toBe($this->hostTemplate->getIconId())
        ->and($response->iconAlternative)
        ->toBe($this->hostTemplate->getIconAlternative())
        ->and($response->comment)
        ->toBe($this->hostTemplate->getComment())
        ->and($response->categories)
        ->toBe(array_map(
            (fn($category) => ['id' => $category->getId(), 'name' => $category->getName()]),
            $this->categories
        ))
        ->and($response->templates)
        ->toBe($this->parentTemplates)
        ->and($response->macros)
        ->toBe(array_map(
            (fn($macro) => [
                'name' => $macro->getName(),
                'value' => $macro->getValue(),
                'isPassword' => $macro->isPassword(),
                'description' => $macro->getDescription(),
            ]),
            $this->hostMacros
        ));
});
