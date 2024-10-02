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

namespace Tests\Core\ServiceTemplate\Application\UseCase\FindServiceTemplates;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Common\Domain\YesNoDefault;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\ServiceTemplate\Application\Exception\ServiceTemplateException;
use Core\ServiceTemplate\Application\Repository\ReadServiceTemplateRepositoryInterface;
use Core\ServiceTemplate\Application\UseCase\FindServiceTemplates\FindServiceTemplateResponse;
use Core\ServiceTemplate\Application\UseCase\FindServiceTemplates\FindServiceTemplates;
use Core\ServiceTemplate\Application\UseCase\FindServiceTemplates\ServiceTemplateDto;
use Core\ServiceTemplate\Domain\Model\NotificationType;
use Core\ServiceTemplate\Domain\Model\ServiceTemplate;
use Tests\Core\ServiceTemplate\Infrastructure\API\FindServiceTemplates\FindServiceTemplatesPresenterStub;

beforeEach(closure: function (): void {
    $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
    $this->repository = $this->createMock(ReadServiceTemplateRepositoryInterface::class);
    $this->user = $this->createMock(ContactInterface::class);
    $this->presenter = new FindServiceTemplatesPresenterStub($this->createMock(PresenterFormatterInterface::class));

    $this->useCase = new FindServiceTemplates(
        $this->readAccessGroupRepository,
        $this->repository,
        $this->createMock(RequestParametersInterface::class),
        $this->user
    );

    $this->serviceTemplateFound = new ServiceTemplate(
        1,
        'fake_name',
        'fake_alias',
        ['a', 'b'],
        ['c', 'd'],
        [NotificationType::Unknown],
        [2, 3],
        true,
        true,
        true,
        YesNoDefault::Yes,
        YesNoDefault::No,
        YesNoDefault::Default,
        YesNoDefault::Yes,
        YesNoDefault::No,
        YesNoDefault::Default,
        YesNoDefault::Yes,
        'comment',
        'note',
        'note_url',
        'action_url',
        'icon_aternative_text',
        2,
        3,
        4,
        5,
        6,
        7,
        8,
        9,
        5,
        1,
        3,
        1,
        10,
        99,
        5,
        0,
        0,
        0
    );

    $this->serviceTemplateDto = new ServiceTemplateDto();
    $this->serviceTemplateDto->id = $this->serviceTemplateFound->getId();
    $this->serviceTemplateDto->name = $this->serviceTemplateFound->getName();
    $this->serviceTemplateDto->alias = $this->serviceTemplateFound->getAlias();
    $this->serviceTemplateDto->comment = $this->serviceTemplateFound->getComment();
    $this->serviceTemplateDto->serviceTemplateId = $this->serviceTemplateFound->getServiceTemplateParentId();
    $this->serviceTemplateDto->commandId = $this->serviceTemplateFound->getCommandId();
    $this->serviceTemplateDto->commandArguments = $this->serviceTemplateFound->getCommandArguments();
    $this->serviceTemplateDto->checkTimePeriodId = $this->serviceTemplateFound->getCheckTimePeriodId();
    $this->serviceTemplateDto->maxCheckAttempts = $this->serviceTemplateFound->getMaxCheckAttempts();
    $this->serviceTemplateDto->normalCheckInterval = $this->serviceTemplateFound->getNormalCheckInterval();
    $this->serviceTemplateDto->retryCheckInterval = $this->serviceTemplateFound->getRetryCheckInterval();
    $this->serviceTemplateDto->activeChecks = $this->serviceTemplateFound->getActiveChecks();
    $this->serviceTemplateDto->passiveCheck = $this->serviceTemplateFound->getPassiveCheck();
    $this->serviceTemplateDto->volatility = $this->serviceTemplateFound->getVolatility();
    $this->serviceTemplateDto->notificationsEnabled = $this->serviceTemplateFound->getNotificationsEnabled();
    $this->serviceTemplateDto->isContactAdditiveInheritance
        = $this->serviceTemplateFound->isContactAdditiveInheritance();
    $this->serviceTemplateDto->isContactGroupAdditiveInheritance
        = $this->serviceTemplateFound->isContactGroupAdditiveInheritance();
    $this->serviceTemplateDto->notificationInterval = $this->serviceTemplateFound->getNotificationInterval();
    $this->serviceTemplateDto->notificationTimePeriodId = $this->serviceTemplateFound->getNotificationTimePeriodId();
    $this->serviceTemplateDto->notificationTypes = $this->serviceTemplateFound->getNotificationTypes();
    $this->serviceTemplateDto->firstNotificationDelay = $this->serviceTemplateFound->getFirstNotificationDelay();
    $this->serviceTemplateDto->recoveryNotificationDelay = $this->serviceTemplateFound->getRecoveryNotificationDelay();
    $this->serviceTemplateDto->acknowledgementTimeout = $this->serviceTemplateFound->getAcknowledgementTimeout();
    $this->serviceTemplateDto->checkFreshness = $this->serviceTemplateFound->getCheckFreshness();
    $this->serviceTemplateDto->freshnessThreshold = $this->serviceTemplateFound->getFreshnessThreshold();
    $this->serviceTemplateDto->flapDetectionEnabled = $this->serviceTemplateFound->getFlapDetectionEnabled();
    $this->serviceTemplateDto->lowFlapThreshold = $this->serviceTemplateFound->getLowFlapThreshold();
    $this->serviceTemplateDto->highFlapThreshold = $this->serviceTemplateFound->getHighFlapThreshold();
    $this->serviceTemplateDto->eventHandlerEnabled = $this->serviceTemplateFound->getEventHandlerEnabled();
    $this->serviceTemplateDto->eventHandlerId = $this->serviceTemplateFound->getEventHandlerId();
    $this->serviceTemplateDto->eventHandlerArguments = $this->serviceTemplateFound->getEventHandlerArguments();
    $this->serviceTemplateDto->graphTemplateId = $this->serviceTemplateFound->getGraphTemplateId();
    $this->serviceTemplateDto->note = $this->serviceTemplateFound->getNote();
    $this->serviceTemplateDto->noteUrl = $this->serviceTemplateFound->getNoteUrl();
    $this->serviceTemplateDto->actionUrl = $this->serviceTemplateFound->getActionUrl();
    $this->serviceTemplateDto->iconId = $this->serviceTemplateFound->getIconId();
    $this->serviceTemplateDto->iconAlternativeText = $this->serviceTemplateFound->getIconAlternativeText();
    $this->serviceTemplateDto->severityId = $this->serviceTemplateFound->getSeverityId();
    $this->serviceTemplateDto->isLocked = $this->serviceTemplateFound->isLocked();
    $this->serviceTemplateDto->hostTemplateIds = $this->serviceTemplateFound->getHostTemplateIds();
});

it('should present an ErrorResponse when an exception is thrown', function (): void {
    $this->user
        ->expects($this->atMost(2))
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->user
        ->expects($this->any())
        ->method('isAdmin')
        ->willReturn(true);

    $this->repository
        ->expects($this->once())
        ->method('findByRequestParameter')
        ->willThrowException(new \Exception());

    ($this->useCase)($this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(ServiceTemplateException::errorWhileSearching(new \Exception())->getMessage());
});

it('should present a ForbiddenResponse when the user has insufficient rights', function (): void {
    $this->user
        ->expects($this->atMost(2))
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ, false],
                [Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, false],
            ]
        );

    ($this->useCase)($this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(ServiceTemplateException::accessNotAllowed()->getMessage());
});

it('should present a FindServiceTemplatesResponse when user has read-only rights', closure: function (): void {
    $this->user
        ->expects($this->atMost(2))
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ, true],
                [Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, false],
            ]
        );

    $this->user
        ->expects($this->any())
        ->method('isAdmin')
        ->willReturn(false);

    $this->repository
        ->expects($this->once())
        ->method('findByRequestParametersAndAccessGroups')
        ->willReturn([$this->serviceTemplateFound]);

    ($this->useCase)($this->presenter);

    expect($this->presenter->response)->toBeInstanceOf(FindServiceTemplateResponse::class);
});

it('should present a FindHostTemplatesResponse when user has read-write rights', function (): void {
    $this->user
        ->expects($this->atMost(2))
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ, false],
                [Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, true],
            ]
        );

    $this->user
        ->expects($this->any())
        ->method('isAdmin')
        ->willReturn(false);

    $this->repository
        ->expects($this->once())
        ->method('findByRequestParametersAndAccessGroups')
        ->willReturn([$this->serviceTemplateFound]);

    ($this->useCase)($this->presenter);

    expect($this->presenter->response)->toBeInstanceOf(FindServiceTemplateResponse::class);
});

it('should present a FindHostTemplatesResponse when user has read or write rights', function (): void {
    $this->user
        ->expects($this->atMost(2))
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ, true],
                [Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, true],
            ]
        );

    $this->user
        ->expects($this->any())
        ->method('isAdmin')
        ->willReturn(false);

    $this->repository
        ->expects($this->once())
        ->method('findByRequestParametersAndAccessGroups')
        ->willReturn([$this->serviceTemplateFound]);

    ($this->useCase)($this->presenter);

    expect($this->presenter->response)->toBeInstanceOf(FindServiceTemplateResponse::class);
    $dto = $this->presenter->response->serviceTemplates[0];
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
});
