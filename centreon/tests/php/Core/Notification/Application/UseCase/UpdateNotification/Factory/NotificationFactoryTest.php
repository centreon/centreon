<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

declare(strict_types = 1);

namespace Tests\Core\Notification\Application\UseCase\UpdateNotification\Factory;

use Core\Notification\Application\Exception\NotificationException;
use Core\Notification\Application\Repository\ReadNotificationRepositoryInterface;
use Core\Notification\Application\UseCase\UpdateNotification\Factory\NotificationFactory;
use Core\Notification\Application\UseCase\UpdateNotification\UpdateNotificationRequest;
use Core\Notification\Domain\Model\Notification;
use Core\Notification\Domain\Model\TimePeriod;

beforeEach(function(): void {
    $this->repository = $this->createMock(ReadNotificationRepositoryInterface::class);
    $this->factory = new NotificationFactory($this->repository);
});

it('should throw a NotificationException when a different notification with the same name exists', function ():
void {
    $request = new UpdateNotificationRequest();
    $request->id = 1;
    $request->name = 'notification';

    $existingNotification = new Notification(
        2,
        'notification',
        new TimePeriod(1, TimePeriod::ALL_TIME_PERIOD)
    );

    $this->repository
        ->expects($this->once())
        ->method('findByName')
        ->with($request->name)
        ->willReturn($existingNotification);

    $this->factory->create($request);
})->throws(NotificationException::class)
    ->expectExceptionMessage(NotificationException::nameAlreadyExists()->getMessage());
