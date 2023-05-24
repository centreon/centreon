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

namespace Tests\Core\Notification\Application\UseCase\FindNotifications;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Notification\Application\Exception\NotificationException;
use Core\Notification\Application\Repository\NotificationResourceRepositoryInterface;
use Core\Notification\Application\Repository\NotificationResourceRepositoryProviderInterface;
use Core\Notification\Application\Repository\ReadNotificationRepositoryInterface;
use Core\Notification\Application\UseCase\FindNotifications\FindNotifications;
use Core\Notification\Application\UseCase\FindNotifications\FindNotificationsResponse;
use Core\Notification\Application\UseCase\FindNotifications\NotificationDto;
use Core\Notification\Domain\Model\Notification;
use Core\Notification\Domain\Model\NotificationChannel;
use Core\Notification\Domain\Model\NotificationGenericObject;
use Core\Notification\Domain\Model\NotificationResource;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Tests\Core\Notification\Infrastructure\API\FindNotifications\FindNotificationsPresenterStub;

beforeEach(function (): void {
    $this->requestParameters = $this->createMock(RequestParametersInterface::class);
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->presenter = new FindNotificationsPresenterStub($this->presenterFormatter);
    $this->notificationRepository = $this->createMock(ReadNotificationRepositoryInterface::class);
    $this->repositoryProvider = $this->createMock(NotificationResourceRepositoryProviderInterface::class);
    $this->resourceRepository = $this->createMock(NotificationResourceRepositoryInterface::class);
    $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
});

it('should present an error response when the user is not admin and doesn\'t have sufficient ACLs', function (): void {
    $contact = (new Contact())->setAdmin(false)->setId(1);

    (new FindNotifications(
        $contact,
        $this->notificationRepository,
        $this->repositoryProvider,
        $this->readAccessGroupRepository,
        $this->requestParameters
    ))($this->presenter);

    expect($this->presenter->responseStatus)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->responseStatus?->getMessage())
        ->toBe(NotificationException::listNotAllowed()->getMessage());
});

it('should present an empty response when no notifications are configured', function (): void {
    $contact = (new Contact())->setAdmin(true)->setId(1)->setTopologyRules(
        [Contact::ROLE_CONFIGURATION_NOTIFICATIONS_READ_WRITE]
    );

    $this->notificationRepository
        ->expects($this->once())
        ->method('findAll')
        ->willReturn([]);

    (new FindNotifications(
        $contact,
        $this->notificationRepository,
        $this->repositoryProvider,
        $this->readAccessGroupRepository,
        $this->requestParameters
    ))($this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(FindNotificationsResponse::class)
        ->and($this->presenter->response->notifications)
        ->toBeArray()
        ->toBeEmpty();
});

it('should get the resources count with ACL calculation when the user is not admin', function (): void {
    $contact = (new Contact())->setAdmin(false)->setId(1)->setTopologyRules(
        [Contact::ROLE_CONFIGURATION_NOTIFICATIONS_READ_WRITE]
    );

    $notificationOne = new Notification(1,'notification-one', new NotificationGenericObject(1, '24x7'), true);
    $notificationTwo = new Notification(2,'notification-two', new NotificationGenericObject(1, '24x7'), true);
    $notificationThree = new Notification(3,'notification-three', new NotificationGenericObject(1, '24x7'), true);

    $this->notificationRepository
        ->expects($this->once())
        ->method('findAll')
        ->willReturn([
            $notificationOne,
            $notificationTwo,
            $notificationThree,
        ]);

    $this->notificationRepository
        ->expects($this->once())
        ->method('findUsersCountByNotificationIds')
        ->willReturn([
            1 => 4,
            2 => 3,
        ]);

    $accessGroups = [
        new AccessGroup(1, 'acl-name', 'acl-alias'),
        new AccessGroup(2, 'acl-name-two', 'acl-alias-two'),
    ];

    $this->repositoryProvider
        ->expects($this->exactly(2))
        ->method('getRepository')
        ->willReturnOnConsecutiveCalls(
            $this->resourceRepository,
            $this->resourceRepository
        );

    $this->readAccessGroupRepository
        ->expects($this->once())
        ->method('findByContact')
        ->with($contact)
        ->willReturn($accessGroups);

    $this->resourceRepository
        ->expects($this->exactly(2))
        ->method('findResourcesCountByNotificationIdsAndAccessGroups');

    (new FindNotifications(
        $contact,
        $this->notificationRepository,
        $this->repositoryProvider,
        $this->readAccessGroupRepository,
        $this->requestParameters
    ))($this->presenter);
});

it('should get the resources count without ACL calculation when the user is admin', function (): void {
    $contact = (new Contact())->setAdmin(true)->setId(1)->setTopologyRules(
        [Contact::ROLE_CONFIGURATION_NOTIFICATIONS_READ_WRITE]
    );

    $notificationOne = new Notification(1,'notification-one', new NotificationGenericObject(1, '24x7'), true);
    $notificationTwo = new Notification(2,'notification-two', new NotificationGenericObject(1, '24x7'), true);
    $notificationThree = new Notification(3,'notification-three', new NotificationGenericObject(1, '24x7'), true);

    $this->notificationRepository
        ->expects($this->once())
        ->method('findAll')
        ->willReturn([
            $notificationOne,
            $notificationTwo,
            $notificationThree,
        ]);

    $this->notificationRepository
        ->expects($this->once())
        ->method('findUsersCountByNotificationIds')
        ->willReturn([
            1 => 4,
            2 => 3,
        ]);

    $this->repositoryProvider
        ->expects($this->exactly(2))
        ->method('getRepository')
        ->willReturnOnConsecutiveCalls(
            $this->resourceRepository,
            $this->resourceRepository
        );

    $this->resourceRepository
        ->expects($this->exactly(2))
        ->method('findResourcesCountByNotificationIds');

    (new FindNotifications(
        $contact,
        $this->notificationRepository,
        $this->repositoryProvider,
        $this->readAccessGroupRepository,
        $this->requestParameters
    ))($this->presenter);
});

it('should present a FindNotificationsResponse when the use case is executed correctly', function (): void {
    $contact = (new Contact())->setAdmin(true)->setId(1)->setTopologyRules(
        [Contact::ROLE_CONFIGURATION_NOTIFICATIONS_READ_WRITE]
    );

    $notificationOne = new Notification(1,'notification-one', new NotificationGenericObject(1, '24x7'), true);
    $notificationTwo = new Notification(2,'notification-two', new NotificationGenericObject(1, '24x7'), true);
    $notificationThree = new Notification(3,'notification-three', new NotificationGenericObject(1, '24x7'), true);

    $this->notificationRepository
        ->expects($this->once())
        ->method('findAll')
        ->willReturn([
            $notificationOne,
            $notificationTwo,
            $notificationThree,
        ]);

    $notificationChannelsByNotifications = [
        1 => [NotificationChannel::from('Slack')],
        2 => [NotificationChannel::from('Slack'), NotificationChannel::from('Sms')],
        3 => [
            NotificationChannel::from('Slack'),
            NotificationChannel::from('Sms'),
            NotificationChannel::from('Email'),
        ],
    ];

    $this->notificationRepository
        ->expects($this->once())
        ->method('findNotificationChannelsByNotificationIds')
        ->willReturn($notificationChannelsByNotifications);

    $usersCount = [
        1 => 4,
        2 => 4,
        3 => 2,
    ];

    $this->notificationRepository
        ->expects($this->once())
        ->method('findUsersCountByNotificationIds')
        ->willReturn($usersCount);

    $this->repositoryProvider
        ->expects($this->exactly(2))
        ->method('getRepository')
        ->willReturnOnConsecutiveCalls(
            $this->resourceRepository,
            $this->resourceRepository
        );

    $hostgroupResourcesCount = [1 => 10, 2 => 5, 3 => 6];
    $servicegroupResourcesCount = [1 => 8, 2 => 12, 3 => 3];

    $this->resourceRepository
        ->expects($this->exactly(2))
        ->method('findResourcesCountByNotificationIds')
        ->willReturnOnConsecutiveCalls(
            $hostgroupResourcesCount,
            $servicegroupResourcesCount
        );

    (new FindNotifications(
        $contact,
        $this->notificationRepository,
        $this->repositoryProvider,
        $this->readAccessGroupRepository,
        $this->requestParameters
    ))($this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(FindNotificationsResponse::class)
        ->and($this->presenter->response->notifications)
        ->toBeArray();

    $firstNotification = $this->presenter->response->notifications[0];
    $secondNotification = $this->presenter->response->notifications[1];
    $thirdNotification = $this->presenter->response->notifications[2];

    expect($firstNotification)
        ->toBeInstanceOf(NotificationDto::class)
        ->and($firstNotification->id)->toBe($notificationOne->getId())
        ->and($firstNotification->name)->toBe($notificationOne->getName())
        ->and($firstNotification->usersCount)->toBe($usersCount[$notificationOne->getId()])
        ->and($firstNotification->isActivated)->toBeTrue()
        ->and($firstNotification->notificationChannels)
        ->toBe($notificationChannelsByNotifications[$notificationOne->getId()])
        ->and($firstNotification->resources)->toBe(
            [
                [
                    'type' => NotificationResource::HOSTGROUP_RESOURCE_TYPE,
                    'count' => $hostgroupResourcesCount[$notificationOne->getId()],
                ],
                [
                    'type' => NotificationResource::SERVICEGROUP_RESOURCE_TYPE,
                    'count' => $servicegroupResourcesCount[$notificationOne->getId()],
                ],
            ]
        )
        ->and($firstNotification->timeperiodId)->toBe(1)
        ->and($firstNotification->timeperiodName)->toBe('24x7');

    expect($secondNotification)
        ->toBeInstanceOf(NotificationDto::class)
        ->and($secondNotification->id)->toBe($notificationTwo->getId())
        ->and($secondNotification->name)->toBe($notificationTwo->getName())
        ->and($secondNotification->usersCount)->toBe($usersCount[$notificationTwo->getId()])
        ->and($secondNotification->isActivated)->toBeTrue()
        ->and($secondNotification->notificationChannels)
        ->toBe($notificationChannelsByNotifications[$notificationTwo->getId()])
        ->and($secondNotification->resources)->toBe(
            [
                [
                    'type' => NotificationResource::HOSTGROUP_RESOURCE_TYPE,
                    'count' => $hostgroupResourcesCount[$notificationTwo->getId()],
                ],
                [
                    'type' => NotificationResource::SERVICEGROUP_RESOURCE_TYPE,
                    'count' => $servicegroupResourcesCount[$notificationTwo->getId()],
                ],
            ]
        )
        ->and($secondNotification->timeperiodId)->toBe(1)
        ->and($secondNotification->timeperiodName)->toBe('24x7');

    expect($thirdNotification)
        ->toBeInstanceOf(NotificationDto::class)
        ->and($thirdNotification->id)->toBe($notificationThree->getId())
        ->and($thirdNotification->name)->toBe($notificationThree->getName())
        ->and($thirdNotification->usersCount)->toBe($usersCount[$notificationThree->getId()])
        ->and($thirdNotification->isActivated)->toBeTrue()
        ->and($thirdNotification->notificationChannels)
        ->toBe($notificationChannelsByNotifications[$notificationThree->getId()])
        ->and($thirdNotification->resources)->toBe(
            [
                [
                    'type' => NotificationResource::HOSTGROUP_RESOURCE_TYPE,
                    'count' => $hostgroupResourcesCount[$notificationThree->getId()],
                ],
                [
                    'type' => NotificationResource::SERVICEGROUP_RESOURCE_TYPE,
                    'count' => $servicegroupResourcesCount[$notificationThree->getId()],
                ],
            ]
        )
        ->and($thirdNotification->timeperiodId)->toBe(1)
        ->and($thirdNotification->timeperiodName)->toBe('24x7');
});
