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

namespace Tests\Core\Notification\Application\UseCase\UpdateNotification;

use Centreon\Domain\Common\Assertion\AssertionException;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactRepositoryInterface;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Notification\Application\Converter\NotificationHostEventConverter;
use Core\Notification\Application\Exception\NotificationException;
use Core\Notification\Application\Repository\NotificationResourceRepositoryInterface;
use Core\Notification\Application\Repository\NotificationResourceRepositoryProviderInterface;
use Core\Notification\Application\Repository\ReadNotificationRepositoryInterface;
use Core\Notification\Application\Repository\WriteNotificationRepositoryInterface;
use Core\Notification\Application\UseCase\UpdateNotification\UpdateNotification;
use Core\Notification\Application\UseCase\UpdateNotification\UpdateNotificationRequest;
use Core\Notification\Domain\Model\ConfigurationTimePeriod;
use Core\Notification\Domain\Model\Notification;
use Core\Notification\Domain\Model\NotificationHostEvent;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Tests\Core\Notification\Infrastructure\API\UpdateNotification\UpdateNotificationPresenterStub;

beforeEach(function():void {
    $this->readNotificationRepository = $this->createMock(ReadNotificationRepositoryInterface::class);
    $this->writeNotificationRepository = $this->createMock(WriteNotificationRepositoryInterface::class);
    $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
    $this->contactRepository = $this->createMock(ContactRepositoryInterface::class);
    $this->resourceRepositoryProvider = $this->createMock(NotificationResourceRepositoryProviderInterface::class);
    $this->resourceRepository = $this->createMock(NotificationResourceRepositoryInterface::class);
    $this->dataStorageEngine = $this->createMock(DataStorageEngineInterface::class);
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->presenter = new UpdateNotificationPresenterStub($this->presenterFormatter);
});

it('should present an forbidden response when the user is not admin and does not have sufficient ACLs', function (): void {
    $contact = (new Contact())->setAdmin(false)->setId(1);
    $request= new UpdateNotificationRequest();
    (new UpdateNotification(
        $this->readNotificationRepository,
        $this->writeNotificationRepository,
        $this->readAccessGroupRepository,
        $this->contactRepository,
        $this->resourceRepositoryProvider,
        $this->dataStorageEngine,
        $contact
    ))($request, $this->presenter);

    expect($this->presenter->responseStatus)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->responseStatus?->getMessage())
        ->toBe(NotificationException::updateNotAllowed()->getMessage());
});

it('should present a not found response when the does not exists', function (): void {
    $contact = (new Contact())->setAdmin(true)->setId(1)->setTopologyRules(
        [Contact::ROLE_CONFIGURATION_NOTIFICATIONS_READ_WRITE]
    );
    $request= new UpdateNotificationRequest();
    $request->id = 2;

    $this->readNotificationRepository
        ->expects($this->once())
        ->method('exists')
        ->with($request->id)
        ->willReturn(false);

    (new UpdateNotification(
        $this->readNotificationRepository,
        $this->writeNotificationRepository,
        $this->readAccessGroupRepository,
        $this->contactRepository,
        $this->resourceRepositoryProvider,
        $this->dataStorageEngine,
        $contact
    ))($request, $this->presenter);

    expect($this->presenter->responseStatus)->toBeInstanceOf(NotFoundResponse::class);
});

it('should present an InvalidArgumentResponse when a different notification with same name exists', function (): void {
    $contact = (new Contact())->setAdmin(true)->setId(1)->setTopologyRules(
        [Contact::ROLE_CONFIGURATION_NOTIFICATIONS_READ_WRITE]
    );
    $request = new UpdateNotificationRequest();
    $request->id = 1;
    $request->name = 'notification';

    $existingNotification = new Notification(
        2,
        'notification',
        new ConfigurationTimePeriod(1, ConfigurationTimePeriod::ALL_TIME_PERIOD)
    );

    $this->readNotificationRepository
        ->expects($this->once())
        ->method('exists')
        ->with($request->id)
        ->willReturn(true);

    $this->readNotificationRepository
        ->expects($this->once())
        ->method('findByName')
        ->with($request->name)
        ->willReturn($existingNotification);

    (new UpdateNotification(
        $this->readNotificationRepository,
        $this->writeNotificationRepository,
        $this->readAccessGroupRepository,
        $this->contactRepository,
        $this->resourceRepositoryProvider,
        $this->dataStorageEngine,
        $contact
    ))($request, $this->presenter);

    expect($this->presenter->responseStatus)->toBeInstanceOf(InvalidArgumentResponse::class)
        ->and($this->presenter->responseStatus?->getMessage())
        ->toBe(NotificationException::nameAlreadyExists()->getMessage());
});


it('should present an InvalidArgumentResponse when a message has an empty subject', function (): void {
    $contact = (new Contact())->setAdmin(true)->setId(1)->setTopologyRules(
        [Contact::ROLE_CONFIGURATION_NOTIFICATIONS_READ_WRITE]
    );
    $request = new UpdateNotificationRequest();
    $request->id = 1;
    $request->name = 'notification';
    $request->messages = [
        [
            "channel" => "Email",
            "subject" => "",
            "message" => "This is my message"
        ]
    ];

    $this->readNotificationRepository
        ->expects($this->once())
        ->method('exists')
        ->with($request->id)
        ->willReturn(true);

    (new UpdateNotification(
        $this->readNotificationRepository,
        $this->writeNotificationRepository,
        $this->readAccessGroupRepository,
        $this->contactRepository,
        $this->resourceRepositoryProvider,
        $this->dataStorageEngine,
        $contact
    ))($request, $this->presenter);

    expect($this->presenter->responseStatus)->toBeInstanceOf(InvalidArgumentResponse::class)
        ->and($this->presenter->responseStatus?->getMessage())
        ->toBe(AssertionException::notEmptyString('NotificationMessage::subject')->getMessage());
});

it('should present a no content response when everything is ok', function (): void {
    $contact = (new Contact())->setAdmin(true)->setId(1)->setTopologyRules(
        [Contact::ROLE_CONFIGURATION_NOTIFICATIONS_READ_WRITE]
    );
    $request = new UpdateNotificationRequest();
    $request->id = 1;
    $request->name = 'notification';
    $request->messages = [
        [
            "channel" => "Email",
            "subject" => "Subject",
            "message" => "This is my message"
        ]
    ];
    $request->resources = [
        [
            "type" => "hostgroup",
            "events" => 3,
            "ids" => [1,2,3],
            "includeServiceEvents" => 0
        ]
    ];
    $request->users = [1];

    $this->readNotificationRepository
        ->expects($this->once())
        ->method('exists')
        ->with($request->id)
        ->willReturn(true);

    $this->resourceRepositoryProvider
        ->expects($this->atLeast(2))
        ->method('getRepository')
        ->willReturn($this->resourceRepository);
    $this->resourceRepository
        ->expects($this->atLeast(1))
        ->method('eventEnum')
        ->willReturn(NotificationHostEvent::class);
    $this->resourceRepository
        ->expects($this->atLeast(1))
        ->method('eventEnumConverter')
        ->willReturn(NotificationHostEventConverter::class);
    $this->resourceRepository
        ->expects($this->atLeast(1))
        ->method('resourceType')
        ->willReturn('hostgroup');
    $this->resourceRepository
        ->expects($this->once())
        ->method('exist')
        ->willReturn([1,2,3]);
    $this->contactRepository
        ->expects($this->once())
        ->method('exist')
        ->willReturn([1]);

    (new UpdateNotification(
        $this->readNotificationRepository,
        $this->writeNotificationRepository,
        $this->readAccessGroupRepository,
        $this->contactRepository,
        $this->resourceRepositoryProvider,
        $this->dataStorageEngine,
        $contact
    ))($request, $this->presenter);

    expect($this->presenter->responseStatus)->toBeInstanceOf(NoContentResponse::class);
});
