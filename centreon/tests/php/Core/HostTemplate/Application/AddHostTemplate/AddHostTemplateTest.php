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

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\AssertionException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Option\Option;
use Centreon\Domain\Option\OptionService;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\CreatedResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Command\Application\Repository\ReadCommandRepositoryInterface;
use Core\Common\Domain\CommandType;
use Core\Common\Domain\HostEvent;
use Core\Common\Domain\SnmpVersion;
use Core\Common\Domain\YesNoDefault;
use Core\HostSeverity\Application\Repository\ReadHostSeverityRepositoryInterface;
use Core\HostTemplate\Application\Exception\HostTemplateException;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\HostTemplate\Application\Repository\WriteHostTemplateRepositoryInterface;
use Core\HostTemplate\Application\UseCase\AddHostTemplate\AddHostTemplate;
use Core\HostTemplate\Application\UseCase\AddHostTemplate\AddHostTemplateRequest;
use Core\HostTemplate\Domain\Model\HostTemplate;
use Core\HostTemplate\Domain\Model\NewHostTemplate;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\ServiceSeverity\Application\Exception\ServiceSeverityException;
use Core\ServiceSeverity\Application\UseCase\AddServiceSeverity\AddServiceSeverity;
use Core\ServiceSeverity\Domain\Model\NewServiceSeverity;
use Core\TimePeriod\Application\Repository\ReadTimePeriodRepositoryInterface;
use Core\Timezone\Application\Repository\ReadTimezoneRepositoryInterface;
use Core\ViewImg\Application\Repository\ReadViewImgRepositoryInterface;

beforeEach(function (): void {
    $this->request = new AddHostTemplateRequest();
    $this->request->name = 'host template name';
    $this->request->alias = 'host-template-alias';
    $this->request->snmpVersion = SnmpVersion::Two->value;
    $this->request->snmpCommunity = 'snmpCommunity-value';
    $this->request->timezoneId = 1;
    $this->request->severityId = 1;
    $this->request->checkCommandId = 1;
    $this->request->checkCommandArgs = 'checkCommandArgs-value';
    $this->request->checkTimeperiodId = 1;
    $this->request->maxCheckAttempts = 5;
    $this->request->normalCheckInterval = 5;
    $this->request->retryCheckInterval = 5;
    $this->request->isActiveCheckEnabled = YesNoDefault::Yes->value;
    $this->request->isPassiveCheckEnabled = YesNoDefault::Yes->value;
    $this->request->isNotificationEnabled = YesNoDefault::Yes->value;
    $this->request->notificationOptions = HostEvent::toBitmask([HostEvent::Down, HostEvent::Unreachable]);
    $this->request->notificationInterval = 5;
    $this->request->notificationTimeperiodId = 2;
    $this->request->addInheritedContactGroup = true;
    $this->request->addInheritedContact = true;
    $this->request->firstNotificationDelay = 5;
    $this->request->recoveryNotificationDelay = 5;
    $this->request->acknowledgementTimeout = 5;
    $this->request->isFreshnessChecked = YesNoDefault::Yes->value;
    $this->request->freshnessThreshold = 5;
    $this->request->isFlapDetectionEnabled = YesNoDefault::Yes->value;
    $this->request->lowFlapThreshold = 5;
    $this->request->highFlapThreshold = 5;
    $this->request->isEventHandlerEnabled = YesNoDefault::Yes->value;
    $this->request->eventHandlerCommandId = 2;
    $this->request->eventHandlerCommandArgs = "eventHandlerCommandArgs\nvalue";
    $this->request->noteUrl = 'noteUrl-value';
    $this->request->note = 'note-value';
    $this->request->actionUrl = 'actionUrl-value';
    $this->request->iconId = 1;
    $this->request->iconAlternative = 'iconAlternative-value';
    $this->request->comment = 'comment-value';
    $this->request->isActivated = false;

    $this->presenter = new DefaultPresenter(
        $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class)
    );

    $this->useCase = new AddHostTemplate(
        $this->writeHostTemplateRepository = $this->createMock(WriteHostTemplateRepositoryInterface::class),
        $this->readHostTemplateRepository = $this->createMock(ReadHostTemplateRepositoryInterface::class),
        $this->readViewImgRepository = $this->createMock(ReadViewImgRepositoryInterface::class),
        $this->readTimePeriodRepository = $this->createMock(ReadTimePeriodRepositoryInterface::class),
        $this->readHostSeverityRepository = $this->createMock(ReadHostSeverityRepositoryInterface::class),
        $this->readTimezoneRepository = $this->createMock(ReadTimezoneRepositoryInterface::class),
        $this->readCommandRepository = $this->createMock(ReadCommandRepositoryInterface::class),
        $this->optionService = $this->createMock(OptionService::class),
        $this->user = $this->createMock(ContactInterface::class),
    );

    $this->inheritanceModeOption = new Option();
    $this->inheritanceModeOption->setName('inheritanceMode')->setValue('1');

    $this->hostTemplate = new HostTemplate(
        1,
        '  host template name  ',
        '  host-template-alias  ',
        SnmpVersion::Two,
        'snmpCommunity-value',
        1,
        1,
        1,
        'checkCommandArgs-value',
        1,
        5,
        5,
        5,
        YesNoDefault::Yes,
        YesNoDefault::Yes,
        YesNoDefault::Yes,
        [HostEvent::Down, HostEvent::Unreachable],
        5,
        2,
        true,
        true,
        5,
        5,
        5,
        YesNoDefault::Yes,
        5,
        YesNoDefault::Yes,
        5,
        5,
        YesNoDefault::Yes,
        2,
        "eventHandlerCommandArgs\nvalue",
        'noteUrl-value',
        'note-value',
        'actionUrl-value',
        1,
        'iconAlternative-value',
        'comment-value',
        false,
        true,
    );
});

it('should present an ErrorResponse when a generic exception is thrown', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willThrowException(new \Exception());

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(HostTemplateException::addHostTemplate(new \Exception())->getMessage());
});

it('should present a ForbiddenResponse when a user has insufficient rights', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(false);

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(HostTemplateException::addNotAllowed()->getMessage());
});

it('should present a ConflictResponse when name is already used', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(true);

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
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
    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(false);
    $this->readHostSeverityRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe(HostTemplateException::idDoesNotExist('severityId', $this->request->severityId)->getMessage());
});

it('should present a ConflictResponse when a host timezone ID is not valid', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(false);
    $this->readHostSeverityRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);
    $this->readTimezoneRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe(HostTemplateException::idDoesNotExist('timezoneId', $this->request->timezoneId)->getMessage());
});

it('should present a ConflictResponse when a timeperiod ID is not valid', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(false);
    $this->readHostSeverityRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);
    $this->readTimezoneRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);
    $this->readTimePeriodRepository
        ->expects($this->atMost(2))
        ->method('exists')
        ->willReturnMap(
            [
                [$this->request->checkTimeperiodId, true],
                [$this->request->notificationTimeperiodId, false],
            ]
        );

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe(
            HostTemplateException::idDoesNotExist(
                'notificationTimeperiodId',
                $this->request->notificationTimeperiodId
            )->getMessage()
        );
});

it('should present a ConflictResponse when a command ID is not valid', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(false);
    $this->readHostSeverityRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);
    $this->readTimezoneRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);
    $this->readTimePeriodRepository
        ->expects($this->atMost(2))
        ->method('exists')
        ->willReturnMap(
            [
                [$this->request->checkTimeperiodId, true],
                [$this->request->notificationTimeperiodId, true],
            ]
        );
    $this->readCommandRepository
        ->expects($this->atMost(2))
        ->method('existsByIdAndCommandType')
        ->willReturnMap(
            [
                [$this->request->checkCommandId, CommandType::Check, true],
                [$this->request->eventHandlerCommandId, CommandType::Check, false],
            ]
        );

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe(
            HostTemplateException::idDoesNotExist(
                'eventHandlerCommandId',
                $this->request->eventHandlerCommandId
            )->getMessage()
        );
});

it('should present a ConflictResponse when the host icon ID is not valid', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(false);
    $this->readHostSeverityRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);
    $this->readTimezoneRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);
    $this->readTimePeriodRepository
        ->expects($this->atMost(2))
        ->method('exists')
        ->willReturnMap(
            [
                [$this->request->checkTimeperiodId, true],
                [$this->request->notificationTimeperiodId, true],
            ]
        );
    $this->readCommandRepository
        ->expects($this->atMost(2))
        ->method('existsByIdAndCommandType')
        ->willReturnMap(
            [
                [$this->request->checkCommandId, CommandType::Check, true],
                [$this->request->eventHandlerCommandId, CommandType::Check, true],
            ]
        );
    $this->readViewImgRepository
        ->expects($this->atMost(2))
        ->method('existsOne')
        ->willReturn(false);

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
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
    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(false);
    $this->readHostSeverityRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);
    $this->readTimezoneRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);
    $this->readTimePeriodRepository
        ->expects($this->atMost(2))
        ->method('exists')
        ->willReturnMap(
            [
                [$this->request->checkTimeperiodId, true],
                [$this->request->notificationTimeperiodId, true],
            ]
        );
    $this->readCommandRepository
        ->expects($this->atMost(2))
        ->method('existsByIdAndCommandType')
        ->willReturnMap(
            [
                [$this->request->checkCommandId, CommandType::Check, true],
                [$this->request->eventHandlerCommandId, CommandType::Check, true],
            ]
        );
    $this->readViewImgRepository
        ->expects($this->once())
        ->method('existsOne')
        ->willReturn(true);
    $this->optionService
        ->expects($this->once())
        ->method('findSelectedOptions')
        ->willReturn(['inheritance_mode' => $this->inheritanceModeOption]);

    $this->request->alias = "";

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(InvalidArgumentResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe(AssertionException::notEmptyString('NewHostTemplate::alias')->getMessage());
});

it('should present an ErrorResponse if the newly created host template cannot be retrieved', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(false);
    $this->readHostSeverityRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);
    $this->readTimezoneRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);
    $this->readTimePeriodRepository
        ->expects($this->atMost(2))
        ->method('exists')
        ->willReturnMap(
            [
                [$this->request->checkTimeperiodId, true],
                [$this->request->notificationTimeperiodId, true],
            ]
        );
    $this->readCommandRepository
        ->expects($this->atMost(2))
        ->method('existsByIdAndCommandType')
        ->willReturnMap(
            [
                [$this->request->checkCommandId, CommandType::Check, true],
                [$this->request->eventHandlerCommandId, CommandType::Check, true],
            ]
        );
    $this->readViewImgRepository
        ->expects($this->once())
        ->method('existsOne')
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

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe(HostTemplateException::errorWhileRetrievingObject()->getMessage());
});

it('should return created object on success', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(false);
    $this->readHostSeverityRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);
    $this->readTimezoneRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);
    $this->readTimePeriodRepository
        ->expects($this->atMost(2))
        ->method('exists')
        ->willReturnMap(
            [
                [$this->request->checkTimeperiodId, true],
                [$this->request->notificationTimeperiodId, true],
            ]
        );
    $this->readCommandRepository
        ->expects($this->atMost(2))
        ->method('existsByIdAndCommandType')
        ->willReturnMap(
            [
                [$this->request->checkCommandId, CommandType::Check, true],
                [$this->request->eventHandlerCommandId, CommandType::Check, true],
            ]
        );
    $this->readViewImgRepository
        ->expects($this->once())
        ->method('existsOne')
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
        ->willReturn($this->hostTemplate);

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getPresentedData())->toBeInstanceOf(CreatedResponse::class);
    expect($this->presenter->getPresentedData()->getResourceId())->toBe($this->hostTemplate->getId());

    $payload = $this->presenter->getPresentedData()->getPayload();
    expect($payload->name)
        ->toBe($this->hostTemplate->getName())
        ->and($payload->alias)
        ->toBe($this->hostTemplate->getAlias())
        ->and($payload->snmpVersion)
        ->toBe($this->hostTemplate->getSnmpVersion()->value)
        ->and($payload->snmpCommunity)
        ->toBe($this->hostTemplate->getSnmpCommunity())
        ->and($payload->timezoneId)
        ->toBe($this->hostTemplate->getTimezoneId())
        ->and($payload->severityId)
        ->toBe($this->hostTemplate->getSeverityId())
        ->and($payload->checkCommandId)
        ->toBe($this->hostTemplate->getCheckCommandId())
        ->and($payload->checkCommandArgs)
        ->toBe($this->hostTemplate->getCheckCommandArgs())
        ->and($payload->checkTimeperiodId)
        ->toBe($this->hostTemplate->getCheckTimeperiodId())
        ->and($payload->maxCheckAttempts)
        ->toBe($this->hostTemplate->getMaxCheckAttempts())
        ->and($payload->normalCheckInterval)
        ->toBe($this->hostTemplate->getNormalCheckInterval())
        ->and($payload->retryCheckInterval)
        ->toBe($this->hostTemplate->getRetryCheckInterval())
        ->and($payload->isActiveCheckEnabled)
        ->toBe($this->hostTemplate->isActiveCheckEnabled()->toInt())
        ->and($payload->isPassiveCheckEnabled)
        ->toBe($this->hostTemplate->isPassiveCheckEnabled()->toInt())
        ->and($payload->isNotificationEnabled)
        ->toBe($this->hostTemplate->isNotificationEnabled()->toInt())
        ->and($payload->notificationOptions)
        ->toBe(HostEvent::toBitmask($this->hostTemplate->getNotificationOptions()))
        ->and($payload->notificationInterval)
        ->toBe($this->hostTemplate->getNotificationInterval())
        ->and($payload->notificationTimeperiodId)
        ->toBe($this->hostTemplate->getNotificationTimeperiodId())
        ->and($payload->addInheritedContactGroup)
        ->toBe($this->hostTemplate->addInheritedContactGroup())
        ->and($payload->addInheritedContact)
        ->toBe($this->hostTemplate->addInheritedContact())
        ->and($payload->firstNotificationDelay)
        ->toBe($this->hostTemplate->getFirstNotificationDelay())
        ->and($payload->recoveryNotificationDelay)
        ->toBe($this->hostTemplate->getRecoveryNotificationDelay())
        ->and($payload->acknowledgementTimeout)
        ->toBe($this->hostTemplate->getAcknowledgementTimeout())
        ->and($payload->isFreshnessChecked)
        ->toBe($this->hostTemplate->isFreshnessChecked()->toInt())
        ->and($payload->freshnessThreshold)
        ->toBe($this->hostTemplate->getFreshnessThreshold())
        ->and($payload->isFlapDetectionEnabled)
        ->toBe($this->hostTemplate->isFlapDetectionEnabled()->toInt())
        ->and($payload->lowFlapThreshold)
        ->toBe($this->hostTemplate->getLowFlapThreshold())
        ->and($payload->highFlapThreshold)
        ->toBe($this->hostTemplate->getHighFlapThreshold())
        ->and($payload->isEventHandlerEnabled)
        ->toBe($this->hostTemplate->isEventHandlerEnabled()->toInt())
        ->and($payload->eventHandlerCommandId)
        ->toBe($this->hostTemplate->getEventHandlerCommandId())
        ->and($payload->eventHandlerCommandArgs)
        ->toBe($this->hostTemplate->getEventHandlerCommandArgs())
        ->and($payload->noteUrl)
        ->toBe($this->hostTemplate->getNoteUrl())
        ->and($payload->note)
        ->toBe($this->hostTemplate->getNote())
        ->and($payload->actionUrl)
        ->toBe($this->hostTemplate->getActionUrl())
        ->and($payload->iconId)
        ->toBe($this->hostTemplate->getIconId())
        ->and($payload->iconAlternative)
        ->toBe($this->hostTemplate->getIconAlternative())
        ->and($payload->comment)
        ->toBe($this->hostTemplate->getComment())
        ->and($payload->isActivated)
        ->toBe($this->hostTemplate->isActivated());
});
