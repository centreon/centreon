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

namespace Tests\Core\Notification\Application\UseCase\AddNotification;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\CreatedResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Contact\Domain\Model\ContactGroup;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Notification\Application\Converter\NotificationHostEventConverter;
use Core\Notification\Application\Converter\NotificationServiceEventConverter;
use Core\Notification\Application\Exception\NotificationException;
use Core\Notification\Application\Repository\NotificationResourceRepositoryInterface;
use Core\Notification\Application\Repository\NotificationResourceRepositoryProviderInterface;
use Core\Notification\Application\Repository\ReadNotificationRepositoryInterface;
use Core\Notification\Application\Repository\WriteNotificationRepositoryInterface;
use Core\Notification\Application\UseCase\AddNotification\AddNotification;
use Core\Notification\Application\UseCase\AddNotification\AddNotificationRequest;
use Core\Notification\Application\UseCase\AddNotification\Factory\NewNotificationFactory;
use Core\Notification\Application\UseCase\AddNotification\Factory\NotificationResourceFactory;
use Core\Notification\Application\UseCase\AddNotification\Validator\NotificationValidator;
use Core\Notification\Domain\Model\NewNotification;
use Core\Notification\Domain\Model\Notification;
use Core\Notification\Domain\Model\Channel;
use Core\Notification\Domain\Model\HostEvent;
use Core\Notification\Domain\Model\Message;
use Core\Notification\Domain\Model\NotificationResource;
use Core\Notification\Domain\Model\ConfigurationResource;
use Core\Notification\Domain\Model\TimePeriod;
use Core\Notification\Domain\Model\Contact;
use Core\Notification\Infrastructure\API\AddNotification\AddNotificationPresenter;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

beforeEach(function (): void {
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->presenter = new AddNotificationPresenter($this->presenterFormatter);

    $this->request = new AddNotificationRequest();
    $this->request->name = 'notification-name';
    $this->request->timePeriodId = 2;
    $this->request->users = [20, 21];
    $this->request->contactGroups = [5,6];
    $this->request->resources = [
        ['type' => 'hostgroup', 'ids' => [12, 25], 'events' => 5, 'includeServiceEvents' => 1],
    ];
    $this->request->messages = [
        [
            'channel' => 'Slack',
            'subject' => 'some subject',
            'message' => 'some message',
            'formatted_message' => '<h1> A Test Message </h1>'
        ],
    ];
    $this->request->isActivated = true;

    $this->useCase = new AddNotification(
        $this->readNotificationRepository = $this->createMock(ReadNotificationRepositoryInterface::class),
        $this->writeNotificationRepository = $this->createMock(WriteNotificationRepositoryInterface::class),
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->resourceRepositoryProvider = $this->createMock(NotificationResourceRepositoryProviderInterface::class),
        $this->dataStorageEngine = $this->createMock(DataStorageEngineInterface::class),
        $this->newNotificationFactory = $this->createMock(NewNotificationFactory::class),
        $this->notificationResourceFactory = $this->createMock(NotificationResourceFactory::class),
        $this->notificationValidator = $this->createMock(NotificationValidator::class),
        $this->user = $this->createMock(ContactInterface::class),
    );

    $this->resourceRepository = $this->createMock(NotificationResourceRepositoryInterface::class);

    $this->notification = new Notification(
        1,
        $this->request->name,
        $this->timeperiodLight = new TimePeriod($this->request->timePeriodId, 'timeperiod-name'),
        $this->request->isActivated
    );
    $this->messages = [
        new Message(
            Channel::from($this->request->messages[0]['channel']),
            $this->request->messages[0]['subject'],
            $this->request->messages[0]['message'],
            $this->request->messages[0]['formatted_message'],
        ),
    ];
    $this->resources = [
        $this->hostgroupResource = new NotificationResource(
            'hostgroup',
            HostEvent::class,
            array_map(
                (fn($resourceId) => new ConfigurationResource($resourceId, "resource-name-{$resourceId}")),
                $this->request->resources[0]['ids']
            ),
            NotificationHostEventConverter::fromBitFlags($this->request->resources[0]['events']),
            NotificationServiceEventConverter::fromBitFlags($this->request->resources[0]['includeServiceEvents']),
        ),
    ];
    $this->users = array_map(
        (fn($userId) => new Contact($userId, "user_name_{$userId}", "email_{$userId}@centreon.com")),
        $this->request->users
    );

    $this->contactGroups = array_map(
        (fn($contactGroupIds) => new ContactGroup($contactGroupIds, "user_name_{$contactGroupIds}", "alias_{$contactGroupIds}")),
        $this->request->contactGroups
    );
});

it('should present an ErrorResponse when a generic exception is thrown', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->newNotificationFactory
        ->expects($this->once())
        ->method('create')
        ->willThrowException(new \Exception());
    $this->notificationValidator
        ->expects($this->once())
        ->method('validateUsersAndContactGroups');

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(NotificationException::addNotification()->getMessage());
});

it('should present a ForbiddenResponse when a user has insufficient rights', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(false);

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(NotificationException::addNotAllowed()->getMessage());
});

it('should present an InvalidArgumentResponse when name is already used', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->newNotificationFactory
        ->expects($this->once())
        ->method('create')
        ->willThrowException(NotificationException::nameAlreadyExists());

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(InvalidArgumentResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe(NotificationException::nameAlreadyExists()->getMessage());
});

it('should present an InvalidArgumentResponse if an error is generated when creating a notification resource.', function
():
void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $newNotification = new NewNotification(
        $this->request->name,
        new TimePeriod(1, ''),
        $this->request->isActivated
    );

    $this->newNotificationFactory
        ->method('create')
        ->willReturn($newNotification);

    $this->notificationResourceFactory
        ->expects($this->once())
        ->method('createNotificationResources')
        ->willThrowException(NotificationException::invalidId('resource.ids'));

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(InvalidArgumentResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe(NotificationException::invalidId('resource.ids')->getMessage());
});


it('should throw an InvalidArgumentResponse if at least one of the user IDs does not exist', function (): void {

    $this->request->users = [10,12];

    $this->notificationValidator
        ->expects($this->once())
        ->method('validateUsersAndContactGroups')
        ->willThrowException(NotificationException::invalidId('users'));

    $this->user
        ->expects($this->never())
        ->method('isAdmin')
        ->willReturn(true);
    $this->resourceRepositoryProvider
        ->expects($this->never())
        ->method('getRepository')
        ->willReturn($this->resourceRepository);
    $this->resourceRepository
        ->expects($this->never())
        ->method('eventEnum')
        ->willReturn(HostEvent::class);
    $this->resourceRepository
        ->expects($this->never())
        ->method('eventEnumConverter')
        ->willReturn(NotificationHostEventConverter::class);
    $this->resourceRepository
        ->expects($this->never())
        ->method('resourceType')
        ->willReturn('hostgroup');

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readNotificationRepository
        ->expects($this->never())
        ->method('existsByName')
        ->willReturn(false);
    $this->resourceRepository
        ->expects($this->never())
        ->method('exist')
        ->willReturn($this->request->resources[0]['ids']);

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(InvalidArgumentResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe(NotificationException::invalidId('users')->getMessage());
});

it('should throw an InvalidArgumentResponse if at least one of the user IDs is not provided', function (): void {

    $this->notificationValidator
        ->expects($this->once())
        ->method('validateUsersAndContactGroups')
        ->willThrowException(NotificationException::invalidId('users'));

    $this->user
        ->expects($this->never())
        ->method('isAdmin')
        ->willReturn(true);

    $this->resourceRepositoryProvider
        ->expects($this->never())
        ->method('getRepository')
        ->willReturn($this->resourceRepository);
    $this->resourceRepository
        ->expects($this->never())
        ->method('eventEnum')
        ->willReturn(HostEvent::class);
    $this->resourceRepository
        ->expects($this->never())
        ->method('eventEnumConverter')
        ->willReturn(NotificationHostEventConverter::class);
    $this->resourceRepository
        ->expects($this->never())
        ->method('resourceType')
        ->willReturn('hostgroup');

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readNotificationRepository
        ->expects($this->never())
        ->method('existsByName')
        ->willReturn(false);
    $this->resourceRepository
        ->expects($this->never())
        ->method('exist')
        ->willReturn($this->request->resources[0]['ids']);

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(InvalidArgumentResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe(NotificationException::invalidId('users')->getMessage());
});

it('should present an ErrorResponse if the newly created service severity cannot be retrieved', function (): void {
    $this->notificationValidator
        ->expects($this->once())
        ->method('validateUsersAndContactGroups');
    
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->newNotificationFactory
        ->expects($this->once())
        ->method('create');

    $this->notificationResourceFactory
        ->expects($this->once())
        ->method('createNotificationResources');

    $this->dataStorageEngine
        ->expects($this->once())
        ->method('startTransaction');

    $this->writeNotificationRepository
        ->expects($this->once())
        ->method('addNewNotification');

    $this->writeNotificationRepository
        ->expects($this->once())
        ->method('addMessagesToNotification');

    $this->writeNotificationRepository
        ->expects($this->once())
        ->method('addUsersToNotification');

    $this->writeNotificationRepository
        ->expects($this->once())
        ->method('addContactGroupsToNotification');

    $this->dataStorageEngine
        ->expects($this->once())
        ->method('commitTransaction');

    $this->readNotificationRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn(null);

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe(NotificationException::errorWhileRetrievingObject()->getMessage());
});

it('should return created object on success', function (): void {

    $this->notificationValidator
        ->expects($this->once())
        ->method('validateUsersAndContactGroups');

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readNotificationRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->notification);
    $this->readNotificationRepository
        ->expects($this->once())
        ->method('findMessagesByNotificationId')
        ->willReturn($this->messages);
    $this->readNotificationRepository
        ->expects($this->once())
        ->method('findUsersByNotificationId')
        ->willReturn($this->users);
    $this->readNotificationRepository
        ->expects($this->once())
        ->method('findContactGroupsByNotificationId')
        ->willReturn($this->contactGroups);
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->resourceRepositoryProvider
        ->expects($this->once())
        ->method('getRepositories')
        ->willReturn([$this->resourceRepository]);
    $this->resourceRepository
        ->expects($this->once(1))
        ->method('findByNotificationId')
        ->willReturn(
            $this->hostgroupResource
        );

    ($this->useCase)($this->request, $this->presenter);
    expect($this->presenter->getPresentedData())->toBeInstanceOf(CreatedResponse::class);
    expect($this->presenter->getPresentedData()->getResourceId())->toBe($this->notification->getId());

    $payload = $this->presenter->getPresentedData()->getPayload();
    expect($payload['name'])
        ->toBe($this->notification->getName())
        ->and($payload['timeperiod'])
        ->toBe([
            'id' => $this->notification->getTimePeriod()->getId(),
            'name' => $this->notification->getTimePeriod()->getName(),
        ])
        ->and($payload['is_activated'])
        ->toBe($this->notification->isActivated())
        ->and($payload['users'])
        ->toBe(array_map(
            (fn($user) => [
                'id' => $user->getId(),
                'name' => $user->getName(),
            ]),
            $this->users
        ))
        ->and($payload['contactgroups'])
        ->toBe(array_map(
            (fn($contactgroup) => [
                'id' => $contactgroup->getId(),
                'name' => $contactgroup->getName(),
            ]),
            $this->contactGroups
        ))
        ->and($payload['messages'])
        ->toBe(array_map(
            (fn($message) => [
                'channel' => $message->getChannel()->value,
                'subject' => $message->getSubject(),
                'message' => $message->getRawMessage(),
                'formatted_message' => $message->getFormattedMessage(),
            ]),
            $this->messages
        ))
        ->and($payload['resources'])
        ->toBe(array_map(
            (fn($resource) => [
                'type' => $resource->getType(),
                'events' => NotificationHostEventConverter::toBitFlags($resource->getEvents()),
                'ids' => array_map(
                    (fn($resourceDetail) => [
                        'id' => $resourceDetail->getId(),
                        'name' => $resourceDetail->getName(),
                    ]),
                    $resource->getResources()),
                'extra' => [
                    'event_services' => NotificationServiceEventConverter::toBitFlags($resource->getServiceEvents()),
                ],
            ]),
            $this->resources
        ));
});
