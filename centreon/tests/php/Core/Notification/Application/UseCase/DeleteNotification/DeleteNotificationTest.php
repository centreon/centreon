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

namespace Tests\Core\Notification\Application\UseCase\DeleteNotification;

use Centreon\Domain\Contact\Contact;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Notification\Application\Exception\NotificationException;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Notification\Application\UseCase\DeleteNotification\DeleteNotification;
use Core\Notification\Application\Repository\WriteNotificationRepositoryInterface;
use Tests\Core\Notification\Application\UseCase\DeleteNotification\DeleteNotificationPresenterStub;


beforeEach(function (): void {
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->presenter = new DeleteNotificationPresenterStub();
    $this->writeRepository = $this->createMock(WriteNotificationRepositoryInterface::class);
});

it('should present a ForbiddenResponse when the user doesn\'t have access to endpoint', function (): void {
    $contact = (new Contact())->setAdmin(false)->setId(1);
    (new DeleteNotification($contact, $this->writeRepository))(1, $this->presenter);

    expect($this->presenter->data)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->data->getMessage())
        ->toBe(NotificationException::deleteNotAllowed()->getMessage());
});

it('should present a NotFoundResponse when the notification to delete is not found', function (): void {
    $contact = (new Contact())->setAdmin(false)->setId(1)->setTopologyRules(
        [Contact::ROLE_CONFIGURATION_NOTIFICATIONS_READ_WRITE]
    );

    $this->writeRepository
        ->expects($this->once())
        ->method('delete')
        ->willReturn(0);

    (new DeleteNotification($contact, $this->writeRepository))(1, $this->presenter);

    expect($this->presenter->data)
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($this->presenter->data->getMessage())
        ->toBe('Notification not found');
});

it('should present an ErrorResponse when an unhandled error occurs', function (): void {
    $contact = (new Contact())->setAdmin(false)->setId(1)->setTopologyRules(
        [Contact::ROLE_CONFIGURATION_NOTIFICATIONS_READ_WRITE]
    );

    $this->writeRepository
        ->expects($this->once())
        ->method('delete')
        ->willThrowException(new \Exception());

    (new DeleteNotification($contact, $this->writeRepository))(1, $this->presenter);

    expect($this->presenter->data)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->data->getMessage())
        ->toBe(NotificationException::errorWhileDeletingObject()->getMessage());
});

it('should present a NoContentResponse when a notification is deleted', function (): void {
    $contact = (new Contact())->setAdmin(false)->setId(1)->setTopologyRules(
        [Contact::ROLE_CONFIGURATION_NOTIFICATIONS_READ_WRITE]
    );

    $this->writeRepository
        ->expects($this->once())
        ->method('delete')
        ->willReturn(1);

    (new DeleteNotification($contact, $this->writeRepository))(1, $this->presenter);

    expect($this->presenter->data)->toBeInstanceOf(NoContentResponse::class);
});
