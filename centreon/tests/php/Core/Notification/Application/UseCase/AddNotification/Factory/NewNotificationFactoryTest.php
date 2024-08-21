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

declare(strict_types = 1);

namespace Tests\Core\Notification\Application\UseCase\AddNotification\Factory;

use Centreon\Domain\Common\Assertion\AssertionException;
use Core\Notification\Application\Exception\NotificationException;
use Core\Notification\Application\Repository\ReadNotificationRepositoryInterface;
use Core\Notification\Application\UseCase\AddNotification\Factory\NewNotificationFactory;

beforeEach(function (): void {
    $this->notificationRepository = $this->createMock(ReadNotificationRepositoryInterface::class);
    $this->factory = new NewNotificationFactory($this->notificationRepository);
});

it('should throws an InvalidArgumentResponse when a field assert failed', function (): void {
    $this->notificationRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(false);

    $this->factory->create('', true, 1);
})->throws(AssertionException::class)
    ->expectExceptionMessage(AssertionException::notEmptyString('NewNotification::name')->getMessage());

it('should present an InvalidArgumentResponse when name is already used', function (): void {
    $this->notificationRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(true);

    $this->factory->create('name', true, 1);
})->throws(NotificationException::class)
    ->expectExceptionMessage(NotificationException::nameAlreadyExists()->getMessage());
