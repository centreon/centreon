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

namespace Tests\Core\HostTemplate\Application\UseCase\FindHostTemplates;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Common\Domain\HostEvent;
use Core\Common\Domain\SnmpVersion;
use Core\Common\Domain\YesNoDefault;
use Core\HostTemplate\Application\Exception\HostTemplateException;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\HostTemplate\Application\UseCase\FindHostTemplates\FindHostTemplates;
use Core\HostTemplate\Application\UseCase\FindHostTemplates\FindHostTemplatesResponse;
use Core\HostTemplate\Domain\Model\HostTemplate;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;

beforeEach(function (): void {
    $this->readHostTemplateRepository = $this->createMock(ReadHostTemplateRepositoryInterface::class);
    $this->user = $this->createMock(ContactInterface::class);

    $this->presenter = new DefaultPresenter($this->createMock(PresenterFormatterInterface::class));
    $this->useCase = new FindHostTemplates(
        $this->readHostTemplateRepository,
        $this->createMock(RequestParametersInterface::class),
        $this->user
    );

    $this->testedHostTemplate = new HostTemplate(
        1,
        'host-template-name',
        'host-template-alias',
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
        1,
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
        1,
        'eventHandlerCommandArgs-value',
        'noteUrl-value',
        'note-value',
        'actionUrl-value',
        1,
        'iconAlternative-value',
        'comment-value',
        false,
        true,
    );
    $this->testedHostTemplateArray = [
        'id' => 1,
        'name' => 'host-template-name',
        'alias' => 'host-template-alias',
        'snmpVersion' => SnmpVersion::Two->value,
        'snmpCommunity' => 'snmpCommunity-value',
        'timezoneId' => 1,
        'severityId' => 1,
        'checkCommandId' => 1,
        'checkCommandArgs' => 'checkCommandArgs-value',
        'checkTimeperiodId' => 1,
        'maxCheckAttempts' => 5,
        'normalCheckInterval' => 5,
        'retryCheckInterval' => 5,
        'isActiveCheckEnabled' => YesNoDefault::Yes->toInt(),
        'isPassiveCheckEnabled' => YesNoDefault::Yes->toInt(),
        'isNotificationEnabled' => YesNoDefault::Yes->toInt(),
        'notificationOptions' => HostEvent::toBitmask([HostEvent::Down, HostEvent::Unreachable]),
        'notificationInterval' => 5,
        'notificationTimeperiodId' => 1,
        'addInheritedContactGroup' => true,
        'addInheritedContact' => true,
        'firstNotificationDelay' => 5,
        'recoveryNotificationDelay' => 5,
        'acknowledgementTimeout' => 5,
        'isFreshnessChecked' => YesNoDefault::Yes->toInt(),
        'freshnessThreshold' => 5,
        'isFlapDetectionEnabled' => YesNoDefault::Yes->toInt(),
        'lowFlapThreshold' => 5,
        'highFlapThreshold' => 5,
        'isEventHandlerEnabled' => YesNoDefault::Yes->toInt(),
        'eventHandlerCommandId' => 1,
        'eventHandlerCommandArgs' => 'eventHandlerCommandArgs-value',
        'noteUrl' => 'noteUrl-value',
        'note' => 'note-value',
        'actionUrl' => 'actionUrl-value',
        'iconId' => 1,
        'iconAlternative' => 'iconAlternative-value',
        'comment' => 'comment-value',
        'isActivated' => false,
        'isLocked' => true,
    ];
});

it(
    'should present an ErrorResponse when an exception is thrown',
    function (): void {
        $this->user
            ->expects($this->atMost(2))
            ->method('hasTopologyRole')
            ->willReturn(true);
        $this->readHostTemplateRepository
            ->expects($this->once())
            ->method('findByRequestParameter')
            ->willThrowException(new \Exception());

        ($this->useCase)($this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->getResponseStatus()?->getMessage())
            ->toBe(HostTemplateException::findHostTemplates(new \Exception())->getMessage());
    }
);

it(
    'should present a ForbiddenResponse when the user insufficent rights',
    function (): void {
        $this->user
            ->expects($this->atMost(2))
            ->method('hasTopologyRole')
            ->willReturn(false);

        ($this->useCase)($this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(ForbiddenResponse::class)
            ->and($this->presenter->getResponseStatus()?->getMessage())
            ->toBe(HostTemplateException::accessNotAllowed()->getMessage());
    }
);

it(
    'should present a FindHostTemplatesResponse as user with read only rights',
    function (): void {
        $this->user
            ->expects($this->atMost(2))
            ->method('hasTopologyRole')
            ->willReturnMap(
                [
                    [Contact::ROLE_CONFIGURATION_HOSTS_TEMPLATES_READ, true],
                    [Contact::ROLE_CONFIGURATION_HOSTS_TEMPLATES_READ_WRITE, false],
                ]
            );
        $this->readHostTemplateRepository
            ->expects($this->once())
            ->method('findByRequestParameter')
            ->willReturn([$this->testedHostTemplate]);

        ($this->useCase)($this->presenter);

        expect($this->presenter->getPresentedData())
            ->toBeInstanceOf(FindHostTemplatesResponse::class)
            ->and($this->presenter->getPresentedData()->hostTemplates[0])
            ->toBe($this->testedHostTemplateArray);
    }
);

it(
    'should present a FindHostTemplatesResponse as user with read-wite rights',
    function (): void {
        $this->user
            ->expects($this->atMost(2))
            ->method('hasTopologyRole')
            ->willReturnMap(
                [
                    [Contact::ROLE_CONFIGURATION_HOSTS_TEMPLATES_READ, false],
                    [Contact::ROLE_CONFIGURATION_HOSTS_TEMPLATES_READ_WRITE, true],
                ]
            );
        $this->readHostTemplateRepository
            ->expects($this->once())
            ->method('findByRequestParameter')
            ->willReturn([$this->testedHostTemplate]);

        ($this->useCase)($this->presenter);

        expect($this->presenter->getPresentedData())
            ->toBeInstanceOf(FindHostTemplatesResponse::class)
            ->and($this->presenter->getPresentedData()->hostTemplates[0])
            ->toBe($this->testedHostTemplateArray);
    }
);
