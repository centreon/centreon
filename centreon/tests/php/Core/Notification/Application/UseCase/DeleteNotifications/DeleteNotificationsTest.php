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

namespace Tests\Core\Notification\Application\UseCase\DeleteNotifications;

use Centreon\Domain\Contact\Contact;
use Core\Application\Common\UseCase\{ForbiddenResponse, MultiStatusResponse};
use Core\Notification\Application\Exception\NotificationException;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Notification\Application\Repository\WriteNotificationRepositoryInterface;
use Core\Notification\Application\UseCase\DeleteNotifications\{DeleteNotifications, DeleteNotificationsRequest};

beforeEach(function () {
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->presenter = new DeleteNotificationsPresenterStub($this->presenterFormatter);
    $this->writeRepository = $this->createMock(WriteNotificationRepositoryInterface::class);
});

it('should present a ForbiddenResponse when the user doesn\'t have access to endpoint', function () {
    $contact = (new Contact())->setAdmin(false)->setId(1);
    $request = new DeleteNotificationsRequest();
    $request->ids = [1, 2];
    (new DeleteNotifications($contact, $this->writeRepository)) ($request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(NotificationException::deleteNotAllowed()->getMessage());
});

it('should present a Multi-Status Response when a bulk delete action is executed', function () {
    $contact = (new Contact())->setAdmin(false)->setId(1)->setTopologyRules(
        [Contact::ROLE_CONFIGURATION_NOTIFICATIONS_READ_WRITE]
    );

    $request = new DeleteNotificationsRequest();
    $request->ids = [1, 2, 3];

    $this->writeRepository
        ->expects($this->exactly(3))
        ->method('deleteNotification')
        ->will($this->onConsecutiveCalls(1, 0, $this->throwException(new \Exception())));

    (new DeleteNotifications($contact, $this->writeRepository)) ($request, $this->presenter);

    $expectedResult = [
        'results' => [
            [
                'href' => 'centreon/api/latest/configuration/notifications/1',
                'status' => 204,
                'message' => null
            ],
            [
                'href' => 'centreon/api/latest/configuration/notifications/2',
                'status' => 404,
                'message' => 'Notification not found'
            ],
            [
                'href' => 'centreon/api/latest/configuration/notifications/3',
                'status' => 500,
                'message' => "Error while deleting a notification configuration"
            ]
        ]
    ];

    expect($this->presenter->response)
        ->toBeInstanceOf(MultiStatusResponse::class)
        ->and($this->presenter->response->getPayload())
        ->toBeArray()
        ->and($this->presenter->response->getPayload())
        ->toBe($expectedResult);
});
