<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Tests\Core\HostTemplate\Application\UseCase\GetHostTemplate;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\HostTemplate\Application\Exception\HostTemplateException;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\HostTemplate\Application\UseCase\GetHostTemplate\GetHostTemplate;
use Core\HostTemplate\Application\UseCase\GetHostTemplate\GetHostTemplateResponse;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Tests\Core\HostTemplate\Infrastructure\API\GetHostTemplate\GetHostTemplatePresenterStub;
use Core\Macro\Application\Repository\ReadHostMacroRepositoryInterface;
use Core\HostCategory\Domain\Model\HostCategory;
use Core\Macro\Domain\Model\Macro;
use Core\Common\Application\Converter\YesNoDefaultConverter;
use Core\HostTemplate\Domain\Model\HostTemplate;
use Core\Host\Domain\Model\SnmpVersion;
use Core\Host\Application\Converter\HostEventConverter;
use Core\Host\Domain\Model\HostEvent;


beforeEach(function (): void {
    $this->usecase = new GetHostTemplate(
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->readHostTemplateRepository = $this->createMock(ReadHostTemplateRepositoryInterface::class),
        $this->readHostCategoryRepository = $this->createMock(ReadHostCategoryRepositoryInterface::class),
        $this->user = $this->createMock(ContactInterface::class),
        $this->readHostMacroRepository = $this->createMock(ReadHostMacroRepositoryInterface::class),

    );
    $this->presenter = new GetHostTemplatePresenterStub(
        $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class)
    );

    $this->hostTemplate = new HostTemplate(
        id: 1,
        name: 'host template name',
        alias: 'host-template-alias',
        snmpVersion: SnmpVersion::from(SnmpVersion::Two->value),
        snmpCommunity: 'snmpCommunity-value',
        timezoneId: 1,
        severityId: 1,
        checkCommandId: 1,
        checkCommandArgs: ['arg1', 'test2'],
        checkTimeperiodId: 1,
        maxCheckAttempts: 5,
        normalCheckInterval: 5,
        retryCheckInterval: 5,
        activeCheckEnabled: YesNoDefaultConverter::fromScalar(1),
        passiveCheckEnabled: YesNoDefaultConverter::fromScalar(1),
        notificationEnabled: YesNoDefaultConverter::fromScalar(1),
        notificationOptions: HostEventConverter::fromBitFlag(HostEventConverter::toBitFlag([HostEvent::Down, HostEvent::Unreachable])),
        notificationInterval: 5,
        notificationTimeperiodId: 2,
        addInheritedContactGroup: true,
        addInheritedContact: true,
        firstNotificationDelay: 5,
        recoveryNotificationDelay: 5,
        acknowledgementTimeout: 5,
        freshnessChecked: YesNoDefaultConverter::fromScalar(1),
        freshnessThreshold: 5,
        flapDetectionEnabled: YesNoDefaultConverter::fromScalar(1),
        lowFlapThreshold: 5,
        highFlapThreshold: 5,
        eventHandlerEnabled: YesNoDefaultConverter::fromScalar(1),
        eventHandlerCommandId: 2,
        eventHandlerCommandArgs: ['arg3', '  arg4'],
        noteUrl: 'noteUrl-value',
        note: 'note-value',
        actionUrl: 'actionUrl-value',
        iconId: 1,
        iconAlternative: 'iconAlternative-value',
        comment: 'comment-value',
        isLocked: false,
    );

    $this->categories = [
        $this->categoryA = new HostCategory(12, 'cat-name-A', 'cat-alias-A'),
        $this->categoryB = new HostCategory(13, 'cat-name-B', 'cat-alias-B'),
    ];

    $this->macros = [
        $this->macroA = new Macro(null, $this->hostTemplate->getId(), 'MACROA', 'A'),
        $this->macroB = new Macro(null, $this->hostTemplate->getId(), 'MACROB', 'B'),
    ];
    $this->hostMacros = [
        $this->macroA->getName() => $this->macroA,
        $this->macroB->getName() => $this->macroB,
    ];

    $this->parentTemplateIds = [4, 8];
    $this->parentTemplates = [
        ['id' => 4, 'name' => 'template-A'],
        ['id' => 8, 'name' => 'template-B'],
    ];

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
        ->willThrowException(new \Exception());

    ($this->usecase)($this->presenter, $this->hostTemplate->getId());

    expect($this->presenter->response)
        ->toBeInstanceOf(class: ErrorResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(HostTemplateException::errorWhileRetrievingObject()->getMessage());
});

it('should present a ForbiddenResponse when user has insufficient rights', function (): void {
    $this->user
        ->expects($this->exactly(2))
        ->method('hasTopologyRole')
        ->willReturn(false);

    ($this->usecase)($this->presenter, $this->hostTemplate->getId());

    expect($this->presenter->response)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(HostTemplateException::accessNotAllowed()->getMessage());
});

it('should present a GetHostTemplateResponse with non-admin user', function (): void {
    $this->user
        ->expects($this->exactly(2))
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_HOSTS_TEMPLATES_READ, false],
                [Contact::ROLE_CONFIGURATION_HOSTS_TEMPLATES_READ_WRITE, true],
            ]
        );
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

    $this->readAccessGroupRepository
        ->expects($this->once())
        ->method('findByContact');

    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('findByIdAndAccessGroups')
        ->willReturn($this->hostTemplate);

    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('findByHostAndAccessGroups')
        ->willReturn($this->categories);
    
    $this->readHostTemplateRepository
    ->expects($this->once())
    ->method('findByHostId')
    ->willReturn($this->parentTemplateIds);

    $this->readHostTemplateRepository
    ->expects($this->once())
    ->method('findNamesByIds')
    ->willReturn(
        array_combine(
                array_map((fn ($row) => $row['id']), $this->parentTemplates),
                array_map((fn ($row) => $row['name']), $this->parentTemplates)
        )
    );

    $this->readHostMacroRepository
        ->expects($this->once())
        ->method('findByHostId')
        ->willReturn($this->macros);

    ($this->usecase)($this->presenter, $this->hostTemplate->getId());

    $response = $this->presenter->response;
    expect($response)->toBeInstanceOf(GetHostTemplateResponse::class)
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
            (fn ($category) => ['id' => $category->getId(), 'name' => $category->getName()]),
            $this->categories
        ))
        ->and($response->templates)
        ->toBe($this->parentTemplates)
        ->and($response->macros)
        ->toBe(array_map(
            (fn ($macro) => [
                'id' => $macro->getId(),
                'name' => $macro->getName(),
                'value' => $macro->getValue(),
                'isPassword' => $macro->isPassword(),
                'description' => $macro->getDescription(),
            ]),
            $this->macros
        ));
    
});

it('should present a GetHostTemplateResponse with admin user', function (): void {
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
        ->willReturn($this->hostTemplate);

    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('findByHost')
        ->willReturn($this->categories);

    
    $this->readHostTemplateRepository
    ->expects($this->once())
    ->method('findByHostId')
    ->willReturn($this->parentTemplateIds);

    $this->readHostTemplateRepository
    ->expects($this->once())
    ->method('findNamesByIds')
    ->willReturn(
        array_combine(
                array_map((fn ($row) => $row['id']), $this->parentTemplates),
                array_map((fn ($row) => $row['name']), $this->parentTemplates)
        )
    );

    $this->readHostMacroRepository
        ->expects($this->once())
        ->method('findByHostId')
        ->willReturn($this->macros);

    

    ($this->usecase)($this->presenter, $this->hostTemplate->getId());


    $response = $this->presenter->response;

    expect($response)->toBeInstanceOf(GetHostTemplateResponse::class)
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
            (fn ($category) => ['id' => $category->getId(), 'name' => $category->getName()]),
            $this->categories
        ))
        ->and($response->templates)
        ->toBe($this->parentTemplates)
        ->and($response->macros)
        ->toBe(array_map(
            (fn ($macro) => [
                'id' => $macro->getId(),
                'name' => $macro->getName(),
                'value' => $macro->getValue(),
                'isPassword' => $macro->isPassword(),
                'description' => $macro->getDescription(),
            ]),
            $this->macros
        ));
   
});