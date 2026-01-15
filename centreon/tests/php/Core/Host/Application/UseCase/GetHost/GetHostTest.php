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

namespace Tests\Core\Host\Application\UseCase\GetHost;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Host\Application\Exception\HostException;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\Host\Application\UseCase\GetHost\GetHost;
use Core\Host\Application\UseCase\GetHost\GetHostResponse;
use Core\Host\Domain\Model\Host;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\HostGroup\Application\Repository\ReadHostGroupRepositoryInterface;
use Tests\Core\Host\Infrastructure\API\GetHost\GetHostPresenterStub;
use Core\Macro\Application\Repository\ReadHostMacroRepositoryInterface;
use Core\HostCategory\Domain\Model\HostCategory;
use Core\Macro\Domain\Model\Macro;
use Core\HostGroup\Domain\Model\HostGroup;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\Domain\Common\GeoCoords;
use Core\Host\Domain\Model\SnmpVersion;
use Core\Host\Application\Converter\HostEventConverter;
use Core\Common\Application\Converter\YesNoDefaultConverter;
use Core\HostTemplate\Domain\Model\HostTemplate;
use Core\Host\Domain\Model\HostEvent;


beforeEach(function (): void {
    $this->usecase = new GetHost(
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->readHostRepository = $this->createMock(ReadHostRepositoryInterface::class),
        $this->readHostTemplateRepository = $this->createMock(ReadHostTemplateRepositoryInterface::class),
        $this->readHostCategoryRepository = $this->createMock(ReadHostCategoryRepositoryInterface::class),
        $this->readHostGroupRepository = $this->createMock(ReadHostGroupRepositoryInterface::class),
        $this->user = $this->createMock(ContactInterface::class),
        $this->readHostMacroRepository = $this->createMock(ReadHostMacroRepositoryInterface::class),

    );
    $this->presenter = new GetHostPresenterStub(
        $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class)
    );

    $this->host = new Host(
        id: 1,
        monitoringServerId: 1,
        name: 'host name',
        address: '127.0.0.1',
        snmpVersion: SnmpVersion::Two,
        geoCoordinates: GeoCoords::fromString('48.1,12.20'),
        alias: 'host-alias',
        snmpCommunity: 'snmpCommunity-value',
        noteUrl: 'noteUrl-value',
        note: 'note-value',
        actionUrl: 'actionUrl-value',
        iconAlternative: 'iconAlternative-value',
        comment: 'comment-value',
        checkCommandArgs: ['arg1', 'test2'], // $this->request->checkCommandArgs,
        eventHandlerCommandArgs: ['arg3', '  arg4'],
        notificationOptions: HostEventConverter::fromBitFlag(HostEventConverter::toBitFlag([HostEvent::Down, HostEvent::Unreachable])),
        timezoneId: 1,
        severityId: 1,
        checkCommandId: 1,
        checkTimeperiodId: 1,
        notificationTimeperiodId: 2,
        eventHandlerCommandId: 2,
        iconId: 1,
        maxCheckAttempts: 5,
        normalCheckInterval: 5,
        retryCheckInterval: 5,
        notificationInterval: 5,
        firstNotificationDelay: 5,
        recoveryNotificationDelay: 5,
        acknowledgementTimeout: 5,
        freshnessThreshold: 5,
        lowFlapThreshold: 5,
        highFlapThreshold: 5,
        activeCheckEnabled: YesNoDefaultConverter::fromScalar(1),
        passiveCheckEnabled: YesNoDefaultConverter::fromScalar(1),
        notificationEnabled: YesNoDefaultConverter::fromScalar(1),
        freshnessChecked: YesNoDefaultConverter::fromScalar(1),
        flapDetectionEnabled: YesNoDefaultConverter::fromScalar(1),
        eventHandlerEnabled: YesNoDefaultConverter::fromScalar(1),
        addInheritedContactGroup: true,
        addInheritedContact: true,
        isActivated: true,
    );

    $this->categories = [
        $this->categoryA = new HostCategory(12, 'cat-name-A', 'cat-alias-A'),
        $this->categoryB = new HostCategory(13, 'cat-name-B', 'cat-alias-B'),
    ];

    $this->macros = [
        $this->macroA = new Macro(null, $this->host->getId(), 'MACROA', 'A'),
        $this->macroB = new Macro(null, $this->host->getId(), 'MACROB', 'B'),
    ];
    $this->hostMacros = [
        $this->macroA->getName() => $this->macroA,
        $this->macroB->getName() => $this->macroB,
    ];

    $this->groups = [
        $this->groupA = new HostGroup(
            id: 6,
            name: 'grp-name-A',
            alias: 'grp-alias-A',
            iconId: null,
            geoCoords: null,
            comment: '',
            isActivated: true
        ),
        $this->groupB = new HostGroup(
            id: 7,
            name: 'grp-name-B',
            alias: 'grp-alias-B',
            iconId: null,
            geoCoords: null,
            comment: '',
            isActivated: true
        ),
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

    $this->readHostRepository
        ->expects($this->once())
        ->method('findById')
        ->willThrowException(new \Exception());

    ($this->usecase)($this->presenter, $this->host->getId());

    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(HostException::errorWhileRetrievingObject()->getMessage());
});

it('should present a ForbiddenResponse when user has insufficient rights', function (): void {
    $this->user
        ->expects($this->exactly(2))
        ->method('hasTopologyRole')
        ->willReturn(false);

    ($this->usecase)($this->presenter, $this->host->getId());

    expect($this->presenter->response)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(HostException::accessNotAllowed()->getMessage());
});

it('should present a GetHostResponse with non-admin user', function (): void {
    $this->user
        ->expects($this->exactly(2))
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_HOSTS_READ, false],
                [Contact::ROLE_CONFIGURATION_HOSTS_WRITE, true],
            ]
        );
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

    $this->readAccessGroupRepository
        ->expects($this->once())
        ->method('findByContact');

    $this->readHostRepository
        ->expects($this->once())
        ->method('existsByAccessGroups')
        ->willReturn(true);

    $this->readHostRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->host);

    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('findByHostAndAccessGroups')
        ->willReturn($this->categories);
    
    $this->readHostGroupRepository
        ->expects($this->once())
        ->method('findByHostAndAccessGroups')
        ->willReturn($this->groups);

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

    ($this->usecase)($this->presenter, $this->host->getId());

    $response = $this->presenter->response;
    expect($response)->toBeInstanceOf(GetHostResponse::class)
        ->and($response->id)
        ->toBe($this->host->getId())
        ->and($response->monitoringServerId)
        ->toBe($this->host->getMonitoringServerId())
        ->and($response->name)
        ->toBe($this->host->getName())
        ->and($response->address)
        ->toBe($this->host->getAddress())
        ->and($response->snmpVersion)
        ->toBe($this->host->getSnmpVersion()->value)
        ->and($response->geoCoords)
        ->toBe($this->host->getGeoCoordinates()?->__toString())
        ->and($response->alias)
        ->toBe($this->host->getAlias())
        ->and($response->snmpCommunity)
        ->toBe($this->host->getSnmpCommunity())
        ->and($response->noteUrl)
        ->toBe($this->host->getNoteUrl())
        ->and($response->note)
        ->toBe($this->host->getNote())
        ->and($response->actionUrl)
        ->toBe($this->host->getActionUrl())
        ->and($response->iconAlternative)
        ->toBe($this->host->getIconAlternative())
        ->and($response->comment)
        ->toBe($this->host->getComment())
        ->and($response->eventHandlerCommandArgs)
        ->toBe($this->host->getEventHandlerCommandArgs())
        ->and($response->checkCommandArgs)
        ->toBe($this->host->getCheckCommandArgs())
        ->and($response->notificationOptions)
        ->toBe(HostEventConverter::toBitFlag($this->host->getNotificationOptions()))
        ->and($response->timezoneId)
        ->toBe($this->host->getTimezoneId())
        ->and($response->severityId)
        ->toBe($this->host->getSeverityId())
        ->and($response->checkCommandId)
        ->toBe($this->host->getCheckCommandId())
        ->and($response->checkTimeperiodId)
        ->toBe($this->host->getCheckTimeperiodId())
        ->and($response->notificationTimeperiodId)
        ->toBe($this->host->getNotificationTimeperiodId())
        ->and($response->eventHandlerCommandId)
        ->toBe($this->host->getEventHandlerCommandId())
        ->and($response->iconId)
        ->toBe($this->host->getIconId())
        ->and($response->maxCheckAttempts)
        ->toBe($this->host->getMaxCheckAttempts())
        ->and($response->normalCheckInterval)
        ->toBe($this->host->getNormalCheckInterval())
        ->and($response->retryCheckInterval)
        ->toBe($this->host->getRetryCheckInterval())
        ->and($response->notificationInterval)
        ->toBe($this->host->getNotificationInterval())
        ->and($response->firstNotificationDelay)
        ->toBe($this->host->getFirstNotificationDelay())
        ->and($response->recoveryNotificationDelay)
        ->toBe($this->host->getRecoveryNotificationDelay())
        ->and($response->acknowledgementTimeout)
        ->toBe($this->host->getAcknowledgementTimeout())
        ->and($response->freshnessThreshold)
        ->toBe($this->host->getFreshnessThreshold())
        ->and($response->lowFlapThreshold)
        ->toBe($this->host->getLowFlapThreshold())
        ->and($response->highFlapThreshold)
        ->toBe($this->host->getHighFlapThreshold())
        ->and($response->activeCheckEnabled)
        ->toBe(YesNoDefaultConverter::toInt($this->host->getActiveCheckEnabled()))
        ->and($response->passiveCheckEnabled)
        ->toBe(YesNoDefaultConverter::toInt($this->host->getPassiveCheckEnabled()))
        ->and($response->notificationEnabled)
        ->toBe(YesNoDefaultConverter::toInt($this->host->getNotificationEnabled()))
        ->and($response->freshnessChecked)
        ->toBe(YesNoDefaultConverter::toInt($this->host->getFreshnessChecked()))
        ->and($response->flapDetectionEnabled)
        ->toBe(YesNoDefaultConverter::toInt($this->host->getFlapDetectionEnabled()))
        ->and($response->eventHandlerEnabled)
        ->toBe(YesNoDefaultConverter::toInt($this->host->getEventHandlerEnabled()))
        ->and($response->categories)
        ->toBe(array_map(
            (fn ($category) => ['id' => $category->getId(), 'name' => $category->getName()]),
            $this->categories
        ))
        ->and($response->groups)
        ->toBe(array_map(
            (fn ($group) => ['id' => $group->getId(), 'name' => $group->getName()]),
            $this->groups
        ))
        ->and($response->templates)
        ->toBe(array_map(
            (fn ($template) => ['id' => $template['id'], 'name' => $template['name']]),
            $this->parentTemplates
        ))
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
        ))
        ->and($response->addInheritedContactGroup)
        ->toBe($this->host->addInheritedContactGroup())
        ->and($response->addInheritedContact)
        ->toBe($this->host->addInheritedContact())
        ->and($response->isActivated)
        ->toBe($this->host->isActivated());
    
});

it('should present a GetHostResponse with admin user', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    
    $this->readHostRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->host);

    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('findByHost')
        ->willReturn($this->categories);
    
    $this->readHostGroupRepository
        ->expects($this->once())
        ->method('findByHost')
        ->willReturn($this->groups);

    
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

    

    ($this->usecase)($this->presenter, $this->host->getId());


    $response = $this->presenter->response;

    expect($response)->toBeInstanceOf(GetHostResponse::class)
        ->and($response->id)
        ->toBe($this->host->getId())
        ->and($response->monitoringServerId)
        ->toBe($this->host->getMonitoringServerId())
        ->and($response->name)
        ->toBe($this->host->getName())
        ->and($response->address)
        ->toBe($this->host->getAddress())
        ->and($response->snmpVersion)
        ->toBe($this->host->getSnmpVersion()->value)
        ->and($response->geoCoords)
        ->toBe($this->host->getGeoCoordinates()?->__toString())
        ->and($response->alias)
        ->toBe($this->host->getAlias())
        ->and($response->snmpCommunity)
        ->toBe($this->host->getSnmpCommunity())
        ->and($response->noteUrl)
        ->toBe($this->host->getNoteUrl())
        ->and($response->note)
        ->toBe($this->host->getNote())
        ->and($response->actionUrl)
        ->toBe($this->host->getActionUrl())
        ->and($response->iconAlternative)
        ->toBe($this->host->getIconAlternative())
        ->and($response->comment)
        ->toBe($this->host->getComment())
        ->and($response->eventHandlerCommandArgs)
        ->toBe($this->host->getEventHandlerCommandArgs())
        ->and($response->checkCommandArgs)
        ->toBe($this->host->getCheckCommandArgs())
        ->and($response->notificationOptions)
        ->toBe(HostEventConverter::toBitFlag($this->host->getNotificationOptions()))
        ->and($response->timezoneId)
        ->toBe($this->host->getTimezoneId())
        ->and($response->severityId)
        ->toBe($this->host->getSeverityId())
        ->and($response->checkCommandId)
        ->toBe($this->host->getCheckCommandId())
        ->and($response->checkTimeperiodId)
        ->toBe($this->host->getCheckTimeperiodId())
        ->and($response->notificationTimeperiodId)
        ->toBe($this->host->getNotificationTimeperiodId())
        ->and($response->eventHandlerCommandId)
        ->toBe($this->host->getEventHandlerCommandId())
        ->and($response->iconId)
        ->toBe($this->host->getIconId())
        ->and($response->maxCheckAttempts)
        ->toBe($this->host->getMaxCheckAttempts())
        ->and($response->normalCheckInterval)
        ->toBe($this->host->getNormalCheckInterval())
        ->and($response->retryCheckInterval)
        ->toBe($this->host->getRetryCheckInterval())
        ->and($response->notificationInterval)
        ->toBe($this->host->getNotificationInterval())
        ->and($response->firstNotificationDelay)
        ->toBe($this->host->getFirstNotificationDelay())
        ->and($response->recoveryNotificationDelay)
        ->toBe($this->host->getRecoveryNotificationDelay())
        ->and($response->acknowledgementTimeout)
        ->toBe($this->host->getAcknowledgementTimeout())
        ->and($response->freshnessThreshold)
        ->toBe($this->host->getFreshnessThreshold())
        ->and($response->lowFlapThreshold)
        ->toBe($this->host->getLowFlapThreshold())
        ->and($response->highFlapThreshold)
        ->toBe($this->host->getHighFlapThreshold())
        ->and($response->activeCheckEnabled)
        ->toBe(YesNoDefaultConverter::toInt($this->host->getActiveCheckEnabled()))
        ->and($response->passiveCheckEnabled)
        ->toBe(YesNoDefaultConverter::toInt($this->host->getPassiveCheckEnabled()))
        ->and($response->notificationEnabled)
        ->toBe(YesNoDefaultConverter::toInt($this->host->getNotificationEnabled()))
        ->and($response->freshnessChecked)
        ->toBe(YesNoDefaultConverter::toInt($this->host->getFreshnessChecked()))
        ->and($response->flapDetectionEnabled)
        ->toBe(YesNoDefaultConverter::toInt($this->host->getFlapDetectionEnabled()))
        ->and($response->eventHandlerEnabled)
        ->toBe(YesNoDefaultConverter::toInt($this->host->getEventHandlerEnabled()))
        ->and($response->categories)
        ->toBe(array_map(
            (fn ($category) => ['id' => $category->getId(), 'name' => $category->getName()]),
            $this->categories
        ))
        ->and($response->groups)
        ->toBe(array_map(
            (fn ($group) => ['id' => $group->getId(), 'name' => $group->getName()]),
            $this->groups
        ))
        ->and($response->templates)
        ->toBe(array_map(
            (fn ($template) => ['id' => $template['id'], 'name' => $template['name']]),
            $this->parentTemplates
        ))
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
        ))
        ->and($response->addInheritedContactGroup)
        ->toBe($this->host->addInheritedContactGroup())
        ->and($response->addInheritedContact)
        ->toBe($this->host->addInheritedContact())
        ->and($response->isActivated)
        ->toBe($this->host->isActivated());
   
});