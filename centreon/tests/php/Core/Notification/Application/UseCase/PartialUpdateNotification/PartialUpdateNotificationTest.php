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
 * For more information : user@centreon.com
 *
 */

declare(strict_types=1);

namespace Tests\Core\Notification\Application\UseCase\PartialUpdateNotification;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\{ErrorResponse, ForbiddenResponse, NoContentResponse, NotFoundResponse};
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Notification\Application\Exception\NotificationException;
use Core\Notification\Application\Repository\{
    ReadNotificationRepositoryInterface,
    WriteNotificationRepositoryInterface
};
use Core\Notification\Application\UseCase\PartialUpdateNotification\{
    PartialUpdateNotification,
    PartialUpdateNotificationRequest
};
use Core\Notification\Domain\Model\TimePeriod;
use Core\Notification\Domain\Model\Notification;

beforeEach(function () {
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->presenter = new PartialUpdateNotificationPresenterStub($this->presenterFormatter);
    $this->dataStorage = $this->createMock(DataStorageEngineInterface::class);
    $this->readRepository = $this->createMock(ReadNotificationRepositoryInterface::class);
    $this->writeRepository = $this->createMock(WriteNotificationRepositoryInterface::class);
    $this->notificationId = 1;
});

it('should present a Forbidden Response when user doesn\'t have access to endpoint', function () {
    $contact = (new Contact())->setAdmin(false)->setId(1);
    $request = new PartialUpdateNotificationRequest();
    $request->isActivated = true;

    $useCase = (new PartialUpdateNotification(
        $contact,
        $this->readRepository,
        $this->writeRepository,
        $this->dataStorage
    ));
    $useCase($request, $this->presenter, $this->notificationId);

    expect($this->presenter->response)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(NotificationException::partialUpdateNotAllowed()->getMessage());
});

it('should present a Not Found Response when notification ID doesn\'t exist', function () {
    $contact = (new Contact())
        ->setAdmin(false)
        ->setTopologyRules([Contact::ROLE_CONFIGURATION_NOTIFICATIONS_READ_WRITE]);
    $request = new PartialUpdateNotificationRequest();
    $request->isActivated = true;
    $this->readRepository
        ->expects($this->once())
        ->method('findById')
        ->with($this->notificationId)
        ->willReturn(null);

    $useCase = (new PartialUpdateNotification(
        $contact,
        $this->readRepository,
        $this->writeRepository,
        $this->dataStorage
    ));
    $useCase($request, $this->presenter, $this->notificationId);

    expect($this->presenter->response)
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe('Notification not found');
});

it('should present an Error Response when an unhandled error occurs', function () {
    $contact = (new Contact())
        ->setAdmin(false)
        ->setTopologyRules([Contact::ROLE_CONFIGURATION_NOTIFICATIONS_READ_WRITE]);
    $request = new PartialUpdateNotificationRequest();
    $request->isActivated = false;
    $this->readRepository
        ->expects($this->once())
        ->method('findById')
        ->with($this->notificationId)
        ->willThrowException(new \Exception());

    $useCase = (new PartialUpdateNotification(
        $contact,
        $this->readRepository,
        $this->writeRepository,
        $this->dataStorage
    ));
    $useCase($request, $this->presenter, $this->notificationId);

    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(NotificationException::errorWhilePartiallyUpdatingObject()->getMessage());
});

it('should present a No Content Response when a notification definition has been partially updated', function () {
    $contact = (new Contact())
        ->setAdmin(false)
        ->setTopologyRules([Contact::ROLE_CONFIGURATION_NOTIFICATIONS_READ_WRITE]);
    $request = new PartialUpdateNotificationRequest();
    $request->isActivated = false;
    $notification = new Notification(1, 'myNotification', new TimePeriod(1, '24x7'), true);
    $this->readRepository
        ->expects($this->once())
        ->method('findById')
        ->with($this->notificationId)
        ->willReturn($notification);

    $useCase = (new PartialUpdateNotification(
        $contact,
        $this->readRepository,
        $this->writeRepository,
        $this->dataStorage
    ));
    $useCase($request, $this->presenter, $this->notificationId);

    expect($this->presenter->response)->toBeInstanceOf(NoContentResponse::class);
});
