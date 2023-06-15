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

namespace Tests\Core\Notification\Application\UseCase\FindNotification;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\AssertionException;
use Centreon\Domain\Contact\Contact;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Notification\Application\Converter\NotificationHostEventConverter;
use Core\Notification\Application\Exception\NotificationException;
use Core\Notification\Application\Repository\NotificationResourceRepositoryInterface;
use Core\Notification\Application\Repository\NotificationResourceRepositoryProviderInterface;
use Core\Notification\Application\Repository\ReadNotificationRepositoryInterface;
use Core\Notification\Application\UseCase\FindNotification\FindNotification;
use Core\Notification\Application\UseCase\FindNotification\FindNotificationResponse;
use Core\Notification\Domain\Model\ConfigurationResource;
use Core\Notification\Domain\Model\ConfigurationTimePeriod;
use Core\Notification\Domain\Model\ConfigurationUser;
use Core\Notification\Domain\Model\Notification;
use Core\Notification\Domain\Model\NotificationChannel;
use Core\Notification\Domain\Model\NotificationHostEvent;
use Core\Notification\Domain\Model\NotificationMessage;
use Core\Notification\Domain\Model\NotificationResource;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Tests\Core\Notification\Infrastructure\API\FindNotification\FindNotificationPresenterStub;

beforeEach(function (): void {
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->presenter = new FindNotificationPresenterStub($this->presenterFormatter);
    $this->notificationRepository = $this->createMock(ReadNotificationRepositoryInterface::class);
    $this->repositoryProvider = $this->createMock(NotificationResourceRepositoryProviderInterface::class);
    $this->resourceRepository = $this->createMock(NotificationResourceRepositoryInterface::class);
    $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
});

it('should present an error response when the user is not admin and doesn\'t have sufficient ACLs', function (): void {
    $contact = (new Contact())->setAdmin(false)->setId(1);

    (new FindNotification(
        $this->notificationRepository,
        $contact,
        $this->repositoryProvider,
        $this->readAccessGroupRepository,
    ))(1, $this->presenter);

    expect($this->presenter->responseStatus)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->responseStatus?->getMessage())
        ->toBe(NotificationException::listOneNotAllowed()->getMessage());
});

it('should present a not found response when the notification does not exist', function (): void {
    $contact = (new Contact())->setAdmin(true)->setId(1)->setTopologyRules(
        [Contact::ROLE_CONFIGURATION_NOTIFICATIONS_READ_WRITE]
    );

    $this->notificationRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn(null);

    (new FindNotification(
        $this->notificationRepository,
        $contact,
        $this->repositoryProvider,
        $this->readAccessGroupRepository,
    ))(1, $this->presenter);

    expect($this->presenter->responseStatus)
        ->toBeInstanceOf(NotFoundResponse::class);
});

it('should present an error response when something unhandled occurs', function (): void {
    $contact = (new Contact())->setAdmin(true)->setId(1)->setTopologyRules(
        [Contact::ROLE_CONFIGURATION_NOTIFICATIONS_READ_WRITE]
    );

    $this->notificationRepository
        ->expects($this->once())
        ->method('findById')
        ->willThrowException(new \Exception());

    (new FindNotification(
        $this->notificationRepository,
        $contact,
        $this->repositoryProvider,
        $this->readAccessGroupRepository,
    ))(1, $this->presenter);

    expect($this->presenter->responseStatus)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->responseStatus?->getMessage())
        ->toBe(NotificationException::errorWhileRetrievingObject()->getMessage());
});

it('should get the resources with ACL Calculation when the user is not admin', function (): void {
    $contact = (new Contact())->setAdmin(false)->setId(1)->setTopologyRules(
        [Contact::ROLE_CONFIGURATION_NOTIFICATIONS_READ_WRITE]
    );

    $notification = new Notification(1, 'notification', new ConfigurationTimePeriod(1, '24x7'), false);
    $notificationMessage = new NotificationMessage(NotificationChannel::from('Slack'), 'Message subject', 'Message content');
    $notificationUser = new ConfigurationUser(3, 'test-user');

    $this->notificationRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($notification);

    $this->notificationRepository
        ->expects($this->once())
        ->method('findMessagesByNotificationId')
        ->willReturn([$notificationMessage]);

    $this->notificationRepository
        ->expects($this->once())
        ->method('findUsersByNotificationId')
        ->willReturn([$notificationUser]);

    $this->repositoryProvider
        ->expects($this->once())
        ->method('getRepositories')
        ->willReturn(
            [$this->resourceRepository]
        );

    $this->resourceRepository
        ->expects($this->once())
        ->method('findByNotificationIdAndAccessGroups');

    (new FindNotification(
        $this->notificationRepository,
        $contact,
        $this->repositoryProvider,
        $this->readAccessGroupRepository,
    ))(1, $this->presenter);
});

it('should present a FindNotificationResponse when everything is ok', function (): void {
    $contact = (new Contact())->setAdmin(true)->setId(1)->setTopologyRules(
        [Contact::ROLE_CONFIGURATION_NOTIFICATIONS_READ_WRITE]
    );

    $notification = new Notification(1, 'notification', new ConfigurationTimePeriod(1, '24x7'), false);
    $notificationMessage = new NotificationMessage(
        NotificationChannel::from('Slack'),
        'Message subject',
        'Message content'
    );
    $notificationUser = new ConfigurationUser(3, 'test-user');
    $notificationResource = new NotificationResource(
        NotificationResource::HOSTGROUP_RESOURCE_TYPE,
        NotificationHostEvent::class,
        [new ConfigurationResource(1, 'hostgroup-resource')],
        NotificationHostEventConverter::fromBitFlags(4)
    );

    $this->notificationRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($notification);

    $this->notificationRepository
        ->expects($this->once())
        ->method('findMessagesByNotificationId')
        ->willReturn([$notificationMessage]);

    $this->notificationRepository
        ->expects($this->once())
        ->method('findUsersByNotificationId')
        ->willReturn([$notificationUser]);

    $this->repositoryProvider
        ->expects($this->once())
        ->method('getRepositories')
        ->willReturn(
            [$this->resourceRepository]
        );

    $this->resourceRepository
        ->expects($this->once())
        ->method('findByNotificationId')
        ->willReturn($notificationResource);

    (new FindNotification(
        $this->notificationRepository,
        $contact,
        $this->repositoryProvider,
        $this->readAccessGroupRepository,
    ))(1, $this->presenter);

    var_dump($this->presenter->response);

    expect($this->presenter->response)
        ->toBeInstanceOf(FindNotificationResponse::class)
        ->and($this->presenter->response->id)->toBe(1)
        ->and($this->presenter->response->name)->toBe('notification')
        ->and($this->presenter->response->timeperiodId)->toBe(1)
        ->and($this->presenter->response->timeperiodName)->toBe('24x7')
        ->and($this->presenter->response->isActivated)->toBe(false)
        ->and($this->presenter->response->messages)->toBeArray()
        ->and($this->presenter->response->messages[0]['channel'])->toBe('Slack')
        ->and($this->presenter->response->messages[0]['subject'])->toBe('Message subject')
        ->and($this->presenter->response->messages[0]['message'])->toBe('Message content')
        ->and($this->presenter->response->users)->toBeArray()
        ->and($this->presenter->response->users[0]['id'])->toBe(3)
        ->and($this->presenter->response->users[0]['name'])->toBe('test-user')
        ->and($this->presenter->response->resources)->toBeArray()
        ->and($this->presenter->response->resources[0]['type'])->toBe(NotificationResource::HOSTGROUP_RESOURCE_TYPE)
        ->and($this->presenter->response->resources[0]['events'])->toBe([NotificationHostEvent::Unreachable])
        ->and($this->presenter->response->resources[0]['ids'][0]['id'])->toBe(1)
        ->and($this->presenter->response->resources[0]['ids'][0]['name'])->toBe('hostgroup-resource');
});
