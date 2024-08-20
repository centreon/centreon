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

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Notification\Application\Exception\NotificationException;
use Core\Notification\Application\Repository\NotificationResourceRepositoryInterface;
use Core\Notification\Application\Repository\NotificationResourceRepositoryProviderInterface;
use Core\Notification\Application\UseCase\AddNotification\Factory\NotificationResourceFactory;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

beforeEach(function (): void {
    $this->notificationResourceRepositoryProvider = $this->createMock(NotificationResourceRepositoryProviderInterface::class);
    $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
    $this->user = $this->createMock(ContactInterface::class);
    $this->notificationResouceFactory = new NotificationResourceFactory(
        $this->notificationResourceRepositoryProvider,
        $this->readAccessGroupRepository,
        $this->user,
    );
    $this->resourceRepository = $this->createMock(NotificationResourceRepositoryInterface::class);
    $this->requestResources =  [
        ['type' => 'hostgroup', 'ids' => [12, 25], 'events' => 5, 'includeServiceEvents' => 1],
    ];
});

it('should throw a NotificationException if at least one of the resource IDs does not exist', function (): void {
    $this->notificationResourceRepositoryProvider
        ->method('getRepository')
        ->willReturn($this->resourceRepository);

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->resourceRepository
        ->expects($this->atMost(2))
        ->method('exist')
        ->willReturn([$this->requestResources[0]['ids'][0]]);

    $this->notificationResouceFactory->createNotificationResources($this->requestResources);
})->expectException(NotificationException::class)
    ->expectExceptionMessage(NotificationException::invalidId('resource.ids')->getMessage());

it('should throw a NotificationException if at least one resource ID is not provided', function (): void {
    $this->notificationResourceRepositoryProvider
        ->method('getRepository')
        ->willReturn($this->resourceRepository);

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->notificationResouceFactory->createNotificationResources($this->requestResources);
})->expectException(NotificationException::class)
    ->expectExceptionMessage(NotificationException::invalidId('resource.ids')->getMessage());
