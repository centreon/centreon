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

namespace Tests\Core\Notification\Application\UseCase\AddNotification\Validator;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Contact\Application\Repository\ReadContactRepositoryInterface;
use Core\Notification\Application\Exception\NotificationException;
use Core\Notification\Application\UseCase\AddNotification\Validator\NotificationValidator;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\TimePeriod\Application\Repository\ReadTimePeriodRepositoryInterface;

beforeEach(function (): void {
    $this->user = $this->createMock(ContactInterface::class);
    $this->contactRepository = $this->createMock(ReadContactRepositoryInterface::class);
    $this->contactGroupRepository = $this->createMock(ReadContactGroupRepositoryInterface::class);
    $this->accessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
    $this->readTimePeriodRepository = $this->createMock(ReadTimePeriodRepositoryInterface::class);
    $this->validator = new NotificationValidator(
        $this->contactRepository,
        $this->contactGroupRepository,
        $this->accessGroupRepository,
        $this->readTimePeriodRepository,
    );
});

it('should throw a NotificationException if users and contact groups are empty', function (): void {
    $this->validator->validateUsersAndContactGroups([], [], $this->user);

})->throws(NotificationException::class)
    ->expectExceptionMessage(NotificationException::emptyArrayNotAllowed('users, contact groups')->getMessage());

it('should throw a NotificationException if at least one of the user IDs does not exist', function (): void {
    $requestUsers = [20, 21];
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->contactRepository
        ->expects($this->once())
        ->method('retrieveExistingContactIds')
        ->willReturn([$requestUsers[0]]);

    $this->validator->validateUsersAndContactGroups($requestUsers, [], $this->user);

})->throws(NotificationException::class)
    ->expectExceptionMessage(NotificationException::invalidId('users')->getMessage());
