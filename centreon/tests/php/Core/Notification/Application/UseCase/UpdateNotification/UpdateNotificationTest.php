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
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Notification\Application\Converter\NotificationHostEventConverter;
use Core\Notification\Application\Exception\NotificationException;
use Core\Notification\Application\Repository\NotificationResourceRepositoryProviderInterface;
use Core\Notification\Application\Repository\ReadNotificationRepositoryInterface;
use Core\Notification\Application\Repository\WriteNotificationRepositoryInterface;
use Core\Notification\Application\UseCase\UpdateNotification\Factory\NotificationFactory;
use Core\Notification\Application\UseCase\UpdateNotification\Factory\NotificationResourceFactory;
use Core\Notification\Application\UseCase\UpdateNotification\UpdateNotification;
use Core\Notification\Application\UseCase\UpdateNotification\UpdateNotificationRequest;
use Core\Notification\Application\UseCase\UpdateNotification\Validator\NotificationValidator;
use Core\Notification\Domain\Model\TimePeriod;
use Core\Notification\Domain\Model\Notification;
use Core\Notification\Domain\Model\HostEvent;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Tests\Core\Notification\Infrastructure\API\UpdateNotification\UpdateNotificationPresenterStub;

beforeEach(function():void {
    $this->readNotificationRepository = $this->createMock(ReadNotificationRepositoryInterface::class);
    $this->writeNotificationRepository = $this->createMock(WriteNotificationRepositoryInterface::class);
    $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
    $this->resourceRepositoryProvider = $this->createMock(NotificationResourceRepositoryProviderInterface::class);
    $this->dataStorageEngine = $this->createMock(DataStorageEngineInterface::class);
    $this->notificationValidator = $this->createMock(NotificationValidator::class);
    $this->notificationFactory = $this->createMock(NotificationFactory::class);
    $this->notificationResourceFactory = $this->createMock(NotificationResourceFactory::class);
    $this->contact = $this->createMock(ContactInterface::class);

    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->presenter = new UpdateNotificationPresenterStub($this->presenterFormatter);

    $this->useCase = new UpdateNotification(
        $this->readNotificationRepository,
        $this->writeNotificationRepository,
        $this->readAccessGroupRepository,
        $this->resourceRepositoryProvider,
        $this->dataStorageEngine,
        $this->notificationValidator,
        $this->notificationFactory,
        $this->notificationResourceFactory,
        $this->contact,
    );
});

it('should present a forbidden response when the user is not admin and does not have sufficient ACLs', function (): void {
    $this->contact
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(false);

    $request= new UpdateNotificationRequest();
    $this->useCase->__invoke($request, $this->presenter);

    expect($this->presenter->responseStatus)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->responseStatus?->getMessage())
        ->toBe(NotificationException::updateNotAllowed()->getMessage());
});

it('should present a not found response when the notification does not exists', function (): void {
    $this->contact
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $request= new UpdateNotificationRequest();
    $request->id = 2;

    $this->readNotificationRepository
        ->expects($this->once())
        ->method('exists')
        ->with($request->id)
        ->willReturn(false);

    $this->useCase->__invoke($request, $this->presenter);

    expect($this->presenter->responseStatus)->toBeInstanceOf(NotFoundResponse::class);
});

it('should present a no content response when everything is ok', function (): void {
    $this->contact
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $request = new UpdateNotificationRequest();
    $request->id = 1;
    $request->name = 'notification';
    $request->messages = [
        [
            "channel" => "Email",
            "subject" => "Subject",
            "message" => "This is my message",
            "formatted_message" => "<h1>This is my message</h1>",
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
        ->willReturn(true);

    $this->useCase->__invoke($request, $this->presenter);

    expect($this->presenter->responseStatus)->toBeInstanceOf(NoContentResponse::class);
});
