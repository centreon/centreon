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
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\CreatedResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Command\Application\Repository\ReadCommandRepositoryInterface;
use Core\Common\Application\Converter\YesNoDefaultConverter;
use Core\Common\Domain\CommandType;
use Core\Common\Domain\YesNoDefault;
use Core\Host\Application\Converter\HostEventConverter;
use Core\Host\Domain\Model\HostEvent;
use Core\Host\Domain\Model\SnmpVersion;
use Core\HostSeverity\Application\Repository\ReadHostSeverityRepositoryInterface;
use Core\HostTemplate\Application\Exception\HostTemplateException;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\HostTemplate\Application\Repository\WriteHostTemplateRepositoryInterface;
use Core\HostTemplate\Application\UseCase\AddHostTemplate\AddHostTemplate;
use Core\HostTemplate\Application\UseCase\AddHostTemplate\AddHostTemplateRequest;
use Core\HostTemplate\Application\UseCase\AddHostTemplate\AddHostTemplateResponse;
use Core\HostTemplate\Domain\Model\HostTemplate;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\TimePeriod\Application\Repository\ReadTimePeriodRepositoryInterface;
use Core\Timezone\Application\Repository\ReadTimezoneRepositoryInterface;
use Core\ViewImg\Application\Repository\ReadViewImgRepositoryInterface;
use Tests\Core\HostTemplate\Infrastructure\API\AddHostTemplate\AddHostTemplatePresenterStub;

beforeEach(function (): void {
    $this->request = new AddHostTemplateRequest();
    $this->request->name = 'host template name';
    $this->request->alias = 'host-template-alias';
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
    $this->request->activeCheckEnabled = '1';
    $this->request->passiveCheckEnabled = '1';
    $this->request->notificationEnabled = '1';
    $this->request->notificationOptions = HostEventConverter::toBitmask([HostEvent::Down, HostEvent::Unreachable]);
    $this->request->notificationInterval = 5;
    $this->request->notificationTimeperiodId = 2;
    $this->request->addInheritedContactGroup = true;
    $this->request->addInheritedContact = true;
    $this->request->firstNotificationDelay = 5;
    $this->request->recoveryNotificationDelay = 5;
    $this->request->acknowledgementTimeout = 5;
    $this->request->freshnessChecked = '1';
    $this->request->freshnessThreshold = 5;
    $this->request->flapDetectionEnabled = '1';
    $this->request->lowFlapThreshold = 5;
    $this->request->highFlapThreshold = 5;
    $this->request->eventHandlerEnabled = '1';
    $this->request->eventHandlerCommandId = 2;
    $this->request->eventHandlerCommandArgs = ["arg\n3", "  arg4"];
    $this->request->noteUrl = 'noteUrl-value';
    $this->request->note = 'note-value';
    $this->request->actionUrl = 'actionUrl-value';
    $this->request->iconId = 1;
    $this->request->iconAlternative = 'iconAlternative-value';
    $this->request->comment = 'comment-value';
    $this->request->isActivated = false;

    $this->presenter = new AddHostTemplatePresenterStub(
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
        ['arg1', 'test2'],
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
        ["arg\n3", 'arg4'],
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

    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(HostTemplateException::addHostTemplate(new \Exception())->getMessage());
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
    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(true);

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
    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(false);
    $this->readHostSeverityRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

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

    expect($this->presenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->response->getMessage())
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

    expect($this->presenter->response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->response->getMessage())
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

    expect($this->presenter->response)
        ->toBeInstanceOf(InvalidArgumentResponse::class)
        ->and($this->presenter->response->getMessage())
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

    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->response->getMessage())
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

    expect($this->presenter->response)->toBeInstanceOf(AddHostTemplateResponse::class);
    expect($this->presenter->response->id)->toBe($this->hostTemplate->getId());

    $response = $this->presenter->response;
    expect($response->name)
        ->toBe($this->hostTemplate->getName())
        ->and($response->alias)
        ->toBe($this->hostTemplate->getAlias())
        ->and($response->snmpVersion)
        ->toBe($this->hostTemplate->getSnmpVersion()->value)
        ->and($response->snmpCommunity)
        ->toBe($this->hostTemplate->getSnmpCommunity())
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
        ->toBe(HostEventConverter::toBitmask($this->hostTemplate->getNotificationOptions()))
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
        ->and($response->isActivated)
        ->toBe($this->hostTemplate->isActivated());
});
