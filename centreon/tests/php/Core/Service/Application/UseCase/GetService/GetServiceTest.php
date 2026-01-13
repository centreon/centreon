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

namespace Tests\Core\Service\Application\UseCase\GetService;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Service\Application\Exception\ServiceException;
use Core\Service\Application\Repository\ReadServiceRepositoryInterface;
use Core\Service\Application\UseCase\GetService\GetService;
use Core\Service\Application\UseCase\GetService\GetServiceResponse;
use Core\Service\Domain\Model\Service;
use Core\ServiceCategory\Application\Repository\ReadServiceCategoryRepositoryInterface;
use Core\ServiceGroup\Application\Repository\ReadServiceGroupRepositoryInterface;
use Core\ServiceGroup\Domain\Model\ServiceGroupRelation;
use Tests\Core\Service\Infrastructure\API\GetService\GetServicePresenterStub;
use Core\Macro\Application\Repository\ReadServiceMacroRepositoryInterface;
use Core\Common\Domain\YesNoDefault;
use Core\Service\Domain\Model\NotificationType;
use Core\ServiceCategory\Domain\Model\ServiceCategory;
use Core\Macro\Domain\Model\Macro;
use Core\ServiceGroup\Domain\Model\ServiceGroup;


beforeEach(function (): void {
    $this->usecase = new GetService(
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->readServiceRepository = $this->createMock(ReadServiceRepositoryInterface::class),
        $this->readServiceCategoryRepository = $this->createMock(ReadServiceCategoryRepositoryInterface::class),
        $this->readServiceGroupRepository = $this->createMock(ReadServiceGroupRepositoryInterface::class),
        $this->user = $this->createMock(ContactInterface::class),
        $this->readServiceMacroRepository = $this->createMock(ReadServiceMacroRepositoryInterface::class),

    );
    $this->presenter = new GetServicePresenterStub(
        $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class)
    );

    $this->service = new Service(
        id: 1,
        name: 'my-service-name',
        commandArguments: ['a', 'b'],
        eventHandlerArguments: ['c', 'd'],
        notificationTypes: [NotificationType::Unknown],
        hostId: 1,
        contactAdditiveInheritance: true,
        contactGroupAdditiveInheritance: true,
        isActivated: true,
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
        notificationTimePeriodId: 14,
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
        new Macro(null, $this->service->getId(), 'MACROA', 'A'),
        new Macro(null, $this->service->getId(), 'MACROB', 'B'),
    ];

    $this->serviceGroup = new ServiceGroup(15, 'SG-name', 'SG-alias', null, '', true);
    $this->serviceGroupRelation = new ServiceGroupRelation(
        serviceGroupId: $this->serviceGroup->getId(),
        serviceId: $this->service->getId(),
        hostId: $this->service->getHostId(),
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

    $this->readServiceRepository
        ->expects($this->once())
        ->method('findById')
        ->willThrowException(new \Exception());

    ($this->usecase)($this->presenter, $this->service->getId());

    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(ServiceException::errorWhileSearching(new \Exception())->getMessage());
});

it('should present a ForbiddenResponse when user has insufficient rights', function (): void {
    $this->user
        ->expects($this->exactly(2))
        ->method('hasTopologyRole')
        ->willReturn(false);

    ($this->usecase)($this->presenter, $this->service->getId());

    expect($this->presenter->response)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(ServiceException::accessNotAllowed()->getMessage());
});

it('should present a GetServiceResponse with non-admin user', function (): void {
    $this->user
        ->expects($this->exactly(2))
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_SERVICES_READ, false],
                [Contact::ROLE_CONFIGURATION_SERVICES_WRITE, true],
            ]
        );
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

    $this->readAccessGroupRepository
        ->expects($this->once())
        ->method('findByContact');

    $this->readServiceRepository
        ->expects($this->once())
        ->method('existsByAccessGroups')
        ->willReturn(true);

    $this->readServiceRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->service);

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

    ($this->usecase)($this->presenter, $this->service->getId());

    $dto = $this->presenter->response;
    expect($dto)->toBeInstanceOf(GetServiceResponse::class);
    expect($dto->id)->toBe($this->service->getId());
    expect($dto->name)->toBe($this->service->getName());
    expect($dto->comment)->toBe($this->service->getComment());
    expect($dto->hostId)->toBe($this->service->getHostId());
    expect($dto->serviceTemplateId)->toBe($this->service->getServiceTemplateParentId());
    expect($dto->commandId)->toBe($this->service->getCommandId());
    expect($dto->commandArguments)->toBe($this->service->getCommandArguments());
    expect($dto->checkTimePeriodId)->toBe($this->service->getCheckTimePeriodId());
    expect($dto->maxCheckAttempts)->toBe($this->service->getMaxCheckAttempts());
    expect($dto->normalCheckInterval)->toBe($this->service->getNormalCheckInterval());
    expect($dto->retryCheckInterval)->toBe($this->service->getRetryCheckInterval());
    expect($dto->activeChecks)->toBe($this->service->getActiveChecks());
    expect($dto->passiveCheck)->toBe($this->service->getPassiveCheck());
    expect($dto->volatility)->toBe($this->service->getVolatility());
    expect($dto->notificationsEnabled)->toBe($this->service->getNotificationsEnabled());
    expect($dto->isContactAdditiveInheritance)->toBe($this->service->isContactAdditiveInheritance());
    expect($dto->isContactGroupAdditiveInheritance)
        ->toBe($this->service->isContactGroupAdditiveInheritance());
    expect($dto->notificationInterval)->toBe($this->service->getNotificationInterval());
    expect($dto->notificationTimePeriodId)->toBe($this->service->getNotificationTimePeriodId());
    expect($dto->notificationTypes)->toBe($this->service->getNotificationTypes());
    expect($dto->firstNotificationDelay)->toBe($this->service->getFirstNotificationDelay());
    expect($dto->recoveryNotificationDelay)->toBe($this->service->getRecoveryNotificationDelay());
    expect($dto->acknowledgementTimeout)->toBe($this->service->getAcknowledgementTimeout());
    expect($dto->checkFreshness)->toBe($this->service->getCheckFreshness());
    expect($dto->freshnessThreshold)->toBe($this->service->getFreshnessThreshold());
    expect($dto->flapDetectionEnabled)->toBe($this->service->getFlapDetectionEnabled());
    expect($dto->lowFlapThreshold)->toBe($this->service->getLowFlapThreshold());
    expect($dto->highFlapThreshold)->toBe($this->service->getHighFlapThreshold());
    expect($dto->eventHandlerEnabled)->toBe($this->service->getEventHandlerEnabled());
    expect($dto->eventHandlerId)->toBe($this->service->getEventHandlerId());
    expect($dto->eventHandlerArguments)->toBe($this->service->getEventHandlerArguments());
    expect($dto->graphTemplateId)->toBe($this->service->getGraphTemplateId());
    expect($dto->note)->toBe($this->service->getNote());
    expect($dto->noteUrl)->toBe($this->service->getNoteUrl());
    expect($dto->actionUrl)->toBe($this->service->getActionUrl());
    expect($dto->iconId)->toBe($this->service->getIconId());
    expect($dto->iconAlternativeText)->toBe($this->service->getIconAlternativeText());
    expect($dto->severityId)->toBe($this->service->getSeverityId());
    expect($dto->isActivated)->toBe($this->service->isActivated());
    foreach ($dto->macros as $index => $expectedMacro) {
        expect($expectedMacro->name)->toBe($this->macros[$index]->getName())
            ->and($expectedMacro->value)->toBe($this->macros[$index]->getValue())
            ->and($expectedMacro->isPassword)->toBe($this->macros[$index]->isPassword())
            ->and($expectedMacro->description)->toBe('');
    }
    expect($dto->groups)->toBe(
        [['id' => $this->serviceGroup->getId(), 'name' => $this->serviceGroup->getName()]]
    );
    expect($dto->categories)->toBe(
        [
            ['id' => $this->categoryA->getId(), 'name' => $this->categoryA->getName()],
            ['id' => $this->categoryB->getId(), 'name' => $this->categoryB->getName()],
        ]
    );
});

it('should present a GetServiceResponse with admin user', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    
    $this->readServiceRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->service);

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

    

    ($this->usecase)($this->presenter, $this->service->getId());


    $dto = $this->presenter->response;
    expect($dto)->toBeInstanceOf(GetServiceResponse::class);
    expect($dto->id)->toBe($this->service->getId());
    expect($dto->name)->toBe($this->service->getName());
    expect($dto->comment)->toBe($this->service->getComment());
    expect($dto->hostId)->toBe($this->service->getHostId());
    expect($dto->serviceTemplateId)->toBe($this->service->getServiceTemplateParentId());
    expect($dto->commandId)->toBe($this->service->getCommandId());
    expect($dto->commandArguments)->toBe($this->service->getCommandArguments());
    expect($dto->checkTimePeriodId)->toBe($this->service->getCheckTimePeriodId());
    expect($dto->maxCheckAttempts)->toBe($this->service->getMaxCheckAttempts());
    expect($dto->normalCheckInterval)->toBe($this->service->getNormalCheckInterval());
    expect($dto->retryCheckInterval)->toBe($this->service->getRetryCheckInterval());
    expect($dto->activeChecks)->toBe($this->service->getActiveChecks());
    expect($dto->passiveCheck)->toBe($this->service->getPassiveCheck());
    expect($dto->volatility)->toBe($this->service->getVolatility());
    expect($dto->notificationsEnabled)->toBe($this->service->getNotificationsEnabled());
    expect($dto->isContactAdditiveInheritance)->toBe($this->service->isContactAdditiveInheritance());
    expect($dto->isContactGroupAdditiveInheritance)
        ->toBe($this->service->isContactGroupAdditiveInheritance());
    expect($dto->notificationInterval)->toBe($this->service->getNotificationInterval());
    expect($dto->notificationTimePeriodId)->toBe($this->service->getNotificationTimePeriodId());
    expect($dto->notificationTypes)->toBe($this->service->getNotificationTypes());
    expect($dto->firstNotificationDelay)->toBe($this->service->getFirstNotificationDelay());
    expect($dto->recoveryNotificationDelay)->toBe($this->service->getRecoveryNotificationDelay());
    expect($dto->acknowledgementTimeout)->toBe($this->service->getAcknowledgementTimeout());
    expect($dto->checkFreshness)->toBe($this->service->getCheckFreshness());
    expect($dto->freshnessThreshold)->toBe($this->service->getFreshnessThreshold());
    expect($dto->flapDetectionEnabled)->toBe($this->service->getFlapDetectionEnabled());
    expect($dto->lowFlapThreshold)->toBe($this->service->getLowFlapThreshold());
    expect($dto->highFlapThreshold)->toBe($this->service->getHighFlapThreshold());
    expect($dto->eventHandlerEnabled)->toBe($this->service->getEventHandlerEnabled());
    expect($dto->eventHandlerId)->toBe($this->service->getEventHandlerId());
    expect($dto->eventHandlerArguments)->toBe($this->service->getEventHandlerArguments());
    expect($dto->graphTemplateId)->toBe($this->service->getGraphTemplateId());
    expect($dto->note)->toBe($this->service->getNote());
    expect($dto->noteUrl)->toBe($this->service->getNoteUrl());
    expect($dto->actionUrl)->toBe($this->service->getActionUrl());
    expect($dto->iconId)->toBe($this->service->getIconId());
    expect($dto->iconAlternativeText)->toBe($this->service->getIconAlternativeText());
    expect($dto->severityId)->toBe($this->service->getSeverityId());
    expect($dto->isActivated)->toBe($this->service->isActivated());
    foreach ($dto->macros as $index => $expectedMacro) {
        expect($expectedMacro->name)->toBe($this->macros[$index]->getName())
            ->and($expectedMacro->value)->toBe($this->macros[$index]->getValue())
            ->and($expectedMacro->isPassword)->toBe($this->macros[$index]->isPassword())
            ->and($expectedMacro->description)->toBe('');
    }
    expect($dto->groups)->toBe(
        [['id' => $this->serviceGroup->getId(), 'name' => $this->serviceGroup->getName()]]
    );
    expect($dto->categories)->toBe(
        [
            ['id' => $this->categoryA->getId(), 'name' => $this->categoryA->getName()],
            ['id' => $this->categoryB->getId(), 'name' => $this->categoryB->getName()],
        ]
    );
});
