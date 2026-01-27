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

namespace Tests\Core\ServiceTemplate\Application\UseCase\GetServiceTemplate;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\ServiceTemplate\Application\Exception\ServiceTemplateException;
use Core\ServiceTemplate\Application\Repository\ReadServiceTemplateRepositoryInterface;
use Core\ServiceTemplate\Application\UseCase\GetServiceTemplate\GetServiceTemplate;
use Core\ServiceTemplate\Application\UseCase\GetServiceTemplate\GetServiceTemplateResponse;
use Core\ServiceTemplate\Domain\Model\ServiceTemplate;
use Core\ServiceCategory\Application\Repository\ReadServiceCategoryRepositoryInterface;
use Core\ServiceGroup\Application\Repository\ReadServiceGroupRepositoryInterface;
use Core\ServiceGroup\Domain\Model\ServiceGroupRelation;
use Tests\Core\ServiceTemplate\Infrastructure\API\GetServiceTemplate\GetServiceTemplatePresenterStub;
use Core\Macro\Application\Repository\ReadServiceMacroRepositoryInterface;
use Core\Common\Domain\YesNoDefault;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\ServiceTemplate\Domain\Model\NotificationType;
use Core\ServiceCategory\Domain\Model\ServiceCategory;
use Core\Macro\Domain\Model\Macro;
use Core\ServiceGroup\Domain\Model\ServiceGroup;


beforeEach(function (): void {
    $this->usecase = new GetServiceTemplate(
        $this->readHostTemplateRepository = $this->createMock(ReadHostTemplateRepositoryInterface::class),
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->readServiceTemplateRepository = $this->createMock(ReadServiceTemplateRepositoryInterface::class),
        $this->readServiceCategoryRepository = $this->createMock(ReadServiceCategoryRepositoryInterface::class),
        $this->readServiceGroupRepository = $this->createMock(ReadServiceGroupRepositoryInterface::class),
        $this->user = $this->createMock(ContactInterface::class),
        $this->readServiceMacroRepository = $this->createMock(ReadServiceMacroRepositoryInterface::class),

    );
    $this->presenter = new GetServiceTemplatePresenterStub(
        $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class)
    );

    $this->serviceTemplate = new ServiceTemplate(
        id: 99,
        name: 'fake_name',
        alias: 'fake_alias',
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
        graphTemplateId: 1,
        serviceTemplateParentId: 10,
        commandId: 1,
        eventHandlerId: 12,
        notificationTimePeriodId: 6,
        checkTimePeriodId: 13,
        iconId: 15,
        severityId: 1,
        maxCheckAttempts: 5,
        normalCheckInterval: 1,
        retryCheckInterval: 3,
        freshnessThreshold: 1,
        lowFlapThreshold: 10,
        highFlapThreshold: 99,
        notificationInterval: 14,
        recoveryNotificationDelay: 0,
        firstNotificationDelay: 0,
        acknowledgementTimeout: 0,
    );

    $this->categories = [
        $this->categoryA = new ServiceCategory(12, 'cat-name-A', 'cat-alias-A'),
        $this->categoryB = new ServiceCategory(13, 'cat-name-B', 'cat-alias-B'),
    ];

    $this->macros = [
        new Macro(null, $this->serviceTemplate->getId(), 'MACROA', 'A'),
        new Macro(null, $this->serviceTemplate->getId(), 'MACROB', 'B'),
    ];

    $this->serviceGroup = new ServiceGroup(15, 'SG-name', 'SG-alias', null, '', true);
    $this->serviceGroupRelation = new ServiceGroupRelation(
        serviceGroupId: $this->serviceGroup->getId(),
        serviceId: $this->serviceTemplate->getId(),
        hostId: 2,
    );

   

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

    $this->readServiceTemplateRepository
        ->expects($this->once())
        ->method('findById')
        ->willThrowException(new \Exception());

    ($this->usecase)($this->presenter, $this->serviceTemplate->getId());

    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(ServiceTemplateException::errorWhileSearching(new \Exception())->getMessage());
});

it('should present a ForbiddenResponse when user has insufficient rights', function (): void {
    $this->user
        ->expects($this->exactly(2))
        ->method('hasTopologyRole')
        ->willReturn(false);

    ($this->usecase)($this->presenter, $this->serviceTemplate->getId());

    expect($this->presenter->response)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(ServiceTemplateException::accessNotAllowed()->getMessage());
});

it('should present a GetServiceTemplateResponse with non-admin user', function (): void {
    $this->user
        ->expects($this->exactly(2))
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ, false],
                [Contact::ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE, true],
            ]
        );
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

    $this->readAccessGroupRepository
        ->expects($this->once())
        ->method('findByContact')
        ->willReturn([]);

    $this->readServiceTemplateRepository
        ->expects($this->once())
        ->method('findByIdAndAccessGroups')
        ->willReturn($this->serviceTemplate);


    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('findByServiceAndAccessGroups')
        ->willReturn($this->categories);
    
    $this->readServiceGroupRepository
        ->expects($this->once())
        ->method('findByServiceAndAccessGroups')
        ->willReturn([
            ['relation' => $this->serviceGroupRelation, 'serviceGroup' => $this->serviceGroup]
        ]);

    $this->readServiceMacroRepository
        ->expects($this->once())
        ->method('findByServiceIds')
        ->willReturn($this->macros);

    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('findNamesByIds')
        ->willReturn([2 => 'HostTemplateName']);

    ($this->usecase)($this->presenter, $this->serviceTemplate->getId());

    $dto = $this->presenter->response;
    expect($dto)->toBeInstanceOf(GetServiceTemplateResponse::class);
    expect($dto->id)->toBe($this->serviceTemplate->getId());
    expect($dto->name)->toBe($this->serviceTemplate->getName());
    expect($dto->alias)->toBe($this->serviceTemplate->getAlias());
    expect($dto->comment)->toBe($this->serviceTemplate->getComment());
    expect($dto->serviceTemplateId)->toBe($this->serviceTemplate->getServiceTemplateParentId());
    expect($dto->commandId)->toBe($this->serviceTemplate->getCommandId());
    expect($dto->commandArguments)->toBe($this->serviceTemplate->getCommandArguments());
    expect($dto->checkTimePeriodId)->toBe($this->serviceTemplate->getCheckTimePeriodId());
    expect($dto->maxCheckAttempts)->toBe($this->serviceTemplate->getMaxCheckAttempts());
    expect($dto->normalCheckInterval)->toBe($this->serviceTemplate->getNormalCheckInterval());
    expect($dto->retryCheckInterval)->toBe($this->serviceTemplate->getRetryCheckInterval());
    expect($dto->activeChecks)->toBe($this->serviceTemplate->getActiveChecks());
    expect($dto->passiveCheck)->toBe($this->serviceTemplate->getPassiveCheck());
    expect($dto->volatility)->toBe($this->serviceTemplate->getVolatility());
    expect($dto->notificationsEnabled)->toBe($this->serviceTemplate->getNotificationsEnabled());
    expect($dto->isContactAdditiveInheritance)->toBe($this->serviceTemplate->isContactAdditiveInheritance());
    expect($dto->isContactGroupAdditiveInheritance)
        ->toBe($this->serviceTemplate->isContactGroupAdditiveInheritance());
    expect($dto->notificationInterval)->toBe($this->serviceTemplate->getNotificationInterval());
    expect($dto->notificationTimePeriodId)->toBe($this->serviceTemplate->getNotificationTimePeriodId());
    expect($dto->notificationTypes)->toBe($this->serviceTemplate->getNotificationTypes());
    expect($dto->firstNotificationDelay)->toBe($this->serviceTemplate->getFirstNotificationDelay());
    expect($dto->recoveryNotificationDelay)->toBe($this->serviceTemplate->getRecoveryNotificationDelay());
    expect($dto->acknowledgementTimeout)->toBe($this->serviceTemplate->getAcknowledgementTimeout());
    expect($dto->checkFreshness)->toBe($this->serviceTemplate->getCheckFreshness());
    expect($dto->freshnessThreshold)->toBe($this->serviceTemplate->getFreshnessThreshold());
    expect($dto->flapDetectionEnabled)->toBe($this->serviceTemplate->getFlapDetectionEnabled());
    expect($dto->lowFlapThreshold)->toBe($this->serviceTemplate->getLowFlapThreshold());
    expect($dto->highFlapThreshold)->toBe($this->serviceTemplate->getHighFlapThreshold());
    expect($dto->eventHandlerEnabled)->toBe($this->serviceTemplate->getEventHandlerEnabled());
    expect($dto->eventHandlerId)->toBe($this->serviceTemplate->getEventHandlerId());
    expect($dto->eventHandlerArguments)->toBe($this->serviceTemplate->getEventHandlerArguments());
    expect($dto->graphTemplateId)->toBe($this->serviceTemplate->getGraphTemplateId());
    expect($dto->note)->toBe($this->serviceTemplate->getNote());
    expect($dto->noteUrl)->toBe($this->serviceTemplate->getNoteUrl());
    expect($dto->actionUrl)->toBe($this->serviceTemplate->getActionUrl());
    expect($dto->iconId)->toBe($this->serviceTemplate->getIconId());
    expect($dto->iconAlternativeText)->toBe($this->serviceTemplate->getIconAlternativeText());
    expect($dto->severityId)->toBe($this->serviceTemplate->getSeverityId());
    expect($dto->isLocked)->toBe($this->serviceTemplate->isLocked());
    expect($dto->hostTemplateIds)->toBe($this->serviceTemplate->getHostTemplateIds());
    foreach ($dto->macros as $index => $expectedMacro) {
        expect($expectedMacro->name)->toBe($this->macros[$index]->getName())
            ->and($expectedMacro->value)->toBe($this->macros[$index]->getValue())
            ->and($expectedMacro->isPassword)->toBe($this->macros[$index]->isPassword())
            ->and($expectedMacro->description)->toBe('');
    }
    expect($dto->groups)->toBe(
        [[
            'serviceGroupId' => $this->serviceGroup->getId(),
            'serviceGroupName' => $this->serviceGroup->getName(),
            'hostTemplateId' => 2,
            'hostTemplateName' => 'HostTemplateName',
        ]]
    );
});

it('should present a GetServiceTemplateResponse with admin user', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    
    $this->readServiceTemplateRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->serviceTemplate);

    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('findByService')
        ->willReturn($this->categories);
    
    $this->readServiceGroupRepository
        ->expects($this->once())
        ->method('findByService')
        ->willReturn([
            ['relation' => $this->serviceGroupRelation, 'serviceGroup' => $this->serviceGroup]
        ]);

    $this->readServiceMacroRepository
        ->expects($this->once())
        ->method('findByServiceIds')
        ->willReturn($this->macros);

    $this->readHostTemplateRepository
        ->expects($this->once())
        ->method('findNamesByIds')
        ->willReturn([2 => 'HostTemplateName']);

    

    ($this->usecase)($this->presenter, $this->serviceTemplate->getId());


    $dto = $this->presenter->response;
    expect($dto)->toBeInstanceOf(GetServiceTemplateResponse::class);
    expect($dto->id)->toBe($this->serviceTemplate->getId());
    expect($dto->name)->toBe($this->serviceTemplate->getName());
    expect($dto->alias)->toBe($this->serviceTemplate->getAlias());
    expect($dto->comment)->toBe($this->serviceTemplate->getComment());
    expect($dto->serviceTemplateId)->toBe($this->serviceTemplate->getServiceTemplateParentId());
    expect($dto->commandId)->toBe($this->serviceTemplate->getCommandId());
    expect($dto->commandArguments)->toBe($this->serviceTemplate->getCommandArguments());
    expect($dto->checkTimePeriodId)->toBe($this->serviceTemplate->getCheckTimePeriodId());
    expect($dto->maxCheckAttempts)->toBe($this->serviceTemplate->getMaxCheckAttempts());
    expect($dto->normalCheckInterval)->toBe($this->serviceTemplate->getNormalCheckInterval());
    expect($dto->retryCheckInterval)->toBe($this->serviceTemplate->getRetryCheckInterval());
    expect($dto->activeChecks)->toBe($this->serviceTemplate->getActiveChecks());
    expect($dto->passiveCheck)->toBe($this->serviceTemplate->getPassiveCheck());
    expect($dto->volatility)->toBe($this->serviceTemplate->getVolatility());
    expect($dto->notificationsEnabled)->toBe($this->serviceTemplate->getNotificationsEnabled());
    expect($dto->isContactAdditiveInheritance)->toBe($this->serviceTemplate->isContactAdditiveInheritance());
    expect($dto->isContactGroupAdditiveInheritance)
        ->toBe($this->serviceTemplate->isContactGroupAdditiveInheritance());
    expect($dto->notificationInterval)->toBe($this->serviceTemplate->getNotificationInterval());
    expect($dto->notificationTimePeriodId)->toBe($this->serviceTemplate->getNotificationTimePeriodId());
    expect($dto->notificationTypes)->toBe($this->serviceTemplate->getNotificationTypes());
    expect($dto->firstNotificationDelay)->toBe($this->serviceTemplate->getFirstNotificationDelay());
    expect($dto->recoveryNotificationDelay)->toBe($this->serviceTemplate->getRecoveryNotificationDelay());
    expect($dto->acknowledgementTimeout)->toBe($this->serviceTemplate->getAcknowledgementTimeout());
    expect($dto->checkFreshness)->toBe($this->serviceTemplate->getCheckFreshness());
    expect($dto->freshnessThreshold)->toBe($this->serviceTemplate->getFreshnessThreshold());
    expect($dto->flapDetectionEnabled)->toBe($this->serviceTemplate->getFlapDetectionEnabled());
    expect($dto->lowFlapThreshold)->toBe($this->serviceTemplate->getLowFlapThreshold());
    expect($dto->highFlapThreshold)->toBe($this->serviceTemplate->getHighFlapThreshold());
    expect($dto->eventHandlerEnabled)->toBe($this->serviceTemplate->getEventHandlerEnabled());
    expect($dto->eventHandlerId)->toBe($this->serviceTemplate->getEventHandlerId());
    expect($dto->eventHandlerArguments)->toBe($this->serviceTemplate->getEventHandlerArguments());
    expect($dto->graphTemplateId)->toBe($this->serviceTemplate->getGraphTemplateId());
    expect($dto->note)->toBe($this->serviceTemplate->getNote());
    expect($dto->noteUrl)->toBe($this->serviceTemplate->getNoteUrl());
    expect($dto->actionUrl)->toBe($this->serviceTemplate->getActionUrl());
    expect($dto->iconId)->toBe($this->serviceTemplate->getIconId());
    expect($dto->iconAlternativeText)->toBe($this->serviceTemplate->getIconAlternativeText());
    expect($dto->severityId)->toBe($this->serviceTemplate->getSeverityId());
    expect($dto->isLocked)->toBe($this->serviceTemplate->isLocked());
    expect($dto->hostTemplateIds)->toBe($this->serviceTemplate->getHostTemplateIds());
    foreach ($dto->macros as $index => $expectedMacro) {
        expect($expectedMacro->name)->toBe($this->macros[$index]->getName())
            ->and($expectedMacro->value)->toBe($this->macros[$index]->getValue())
            ->and($expectedMacro->isPassword)->toBe($this->macros[$index]->isPassword())
            ->and($expectedMacro->description)->toBe('');
    }
    expect($dto->groups)->toBe(
        [[
            'serviceGroupId' => $this->serviceGroup->getId(),
            'serviceGroupName' => $this->serviceGroup->getName(),
            'hostTemplateId' => 2,
            'hostTemplateName' => 'HostTemplateName',
        ]]
    );
});