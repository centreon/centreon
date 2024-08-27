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

use Centreon\Domain\Common\Assertion\AssertionException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Contact\Interfaces\ContactRepositoryInterface;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\CreatedResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Contact\Domain\Model\ContactGroup;
use Core\Infrastructure\Common\Api\DefaultPresenter;
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
use Core\Notification\Application\UseCase\AddNotification\Validator\NotificationValidator;
use Core\Notification\Domain\Model\Notification;
use Core\Notification\Domain\Model\NotificationChannel;
use Core\Notification\Domain\Model\NotificationHostEvent;
use Core\Notification\Domain\Model\NotificationMessage;
use Core\Notification\Domain\Model\NotificationResource;
use Core\Notification\Domain\Model\ConfigurationResource;
use Core\Notification\Domain\Model\ConfigurationTimePeriod;
use Core\Notification\Domain\Model\ConfigurationUser;
use Core\Notification\Infrastructure\API\AddNotification\AddNotificationPresenter;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\TimePeriod\Domain\Model\TimePeriod;
use Core\TimePeriod\Application\Repository\ReadTimePeriodRepositoryInterface;

beforeEach(function (): void {
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->presenter = new AddNotificationPresenter($this->presenterFormatter);

    $this->request = new AddNotificationRequest();
    $this->request->name = 'notification-name';
    $this->request->timeperiodId = 2;
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
        $this->contactRepository = $this->createMock(ContactRepositoryInterface::class),
        $this->contactGroupRepository = $this->createMock(ReadContactGroupRepositoryInterface::class),
        $this->resourceRepositoryProvider = $this->createMock(NotificationResourceRepositoryProviderInterface::class),
        $this->dataStorageEngine = $this->createMock(DataStorageEngineInterface::class),
        $this->user = $this->createMock(ContactInterface::class),
        $this->readTimeperiodRepository = $this->createMock(ReadTimePeriodRepositoryInterface::class),
        $this->validator = $this->createMock(NotificationValidator::class)
    );

    $this->resourceRepository = $this->createMock(NotificationResourceRepositoryInterface::class);

    $this->timeperiod = new TimePeriod($this->request->timeperiodId, 'timeperiod-name', 'timeperiod-alias');
    $this->notification = new Notification(
        1,
        $this->request->name,
        $this->timeperiodLight = new ConfigurationTimePeriod($this->request->timeperiodId, 'timeperiod-name'),
        $this->request->isActivated
    );
    $this->messages = [
        new NotificationMessage(
            NotificationChannel::from($this->request->messages[0]['channel']),
            $this->request->messages[0]['subject'],
            $this->request->messages[0]['message'],
            $this->request->messages[0]['formatted_message'],
        ),
    ];
    $this->resources = [
        $this->hostgroupResource = new NotificationResource(
            'hostgroup',
            NotificationHostEvent::class,
            array_map(
                (fn($resourceId) => new ConfigurationResource($resourceId, "resource-name-{$resourceId}")),
                $this->request->resources[0]['ids']
            ),
            NotificationHostEventConverter::fromBitFlags($this->request->resources[0]['events']),
            NotificationServiceEventConverter::fromBitFlags($this->request->resources[0]['includeServiceEvents']),
        ),
    ];
    $this->users = array_map(
        (fn($userId) => new ConfigurationUser($userId, "user_name_{$userId}", "email_{$userId}@centreon.com")),
        $this->request->users
    );

    $this->contactGroups = array_map(
        (fn($contactGroupIds) => new ContactGroup($contactGroupIds, "user_name_{$contactGroupIds}")),
        $this->request->contactGroups
    );
});

it('should present an ErrorResponse when a generic exception is thrown', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readNotificationRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willThrowException(new \Exception());
    $this->readTimeperiodRepository
        ->expects($this->exactly(0))
        ->method('exists');

    $this->validator
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
    $this->readNotificationRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(true);

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(InvalidArgumentResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe(NotificationException::nameAlreadyExists()->getMessage());
});

it('should present an InvalidArgumentResponse when a field assert failed', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readNotificationRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(false);

    $this->request->name = '';
    $expectedException = AssertionException::notEmptyString('NewNotification::name');

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(InvalidArgumentResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe($expectedException->getMessage());
});

it('should throw an InvalidArgumentResponse if at least one of the resource IDs does not exist', function (): void {
    $this->user
        ->method('isAdmin')
        ->willReturn(true);
    $this->resourceRepositoryProvider
        ->method('getRepository')
        ->willReturn($this->resourceRepository);
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readNotificationRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(false);
    $this->resourceRepository
        ->expects($this->atMost(2))
        ->method('exist')
        ->willReturn([$this->request->resources[0]['ids'][0]]);

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(InvalidArgumentResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe(NotificationException::invalidId('resource.ids')->getMessage());
});

it('should throw an InvalidArgumentResponse if at least one resource ID is not provided', function (): void {
    $this->request->resources[0]['ids'] = [];

    $this->user
        ->expects($this->exactly(0))
        ->method('isAdmin')
        ->willReturn(true);
    $this->resourceRepositoryProvider
        ->expects($this->exactly(0))
        ->method('getRepository')
        ->willReturn($this->resourceRepository);
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readNotificationRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(false);

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(InvalidArgumentResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe(NotificationException::emptyArrayNotAllowed('resource.ids')->getMessage());
});

it('should throw an InvalidArgumentResponse if at least one of the user IDs does not exist', function (): void {

    $this->request->users = [10,12];

    $this->validator
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
        ->willReturn(NotificationHostEvent::class);
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
    $this->contactRepository
        ->expects($this->never())
        ->method('exist')
        ->willReturn([$this->request->users[0]]);

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(InvalidArgumentResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe(NotificationException::invalidId('users')->getMessage());
});

it('should throw an InvalidArgumentResponse if at least one of the user IDs is not provided', function (): void {

    $this->validator
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
        ->willReturn(NotificationHostEvent::class);
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
    $this->validator
        ->expects($this->once())
        ->method('validateUsersAndContactGroups');
    
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->resourceRepositoryProvider
        ->expects($this->atLeast(1))
        ->method('getRepository')
        ->willReturn($this->resourceRepository);
    $this->resourceRepository
        ->expects($this->atLeast(1))
        ->method('eventEnum')
        ->willReturn(NotificationHostEvent::class);
    $this->resourceRepository
        ->expects($this->atLeast(1))
        ->method('eventEnumConverter')
        ->willReturn(NotificationHostEventConverter::class);
    $this->resourceRepository
        ->expects($this->atLeast(1))
        ->method('resourceType')
        ->willReturn('hostgroup');

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->readNotificationRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(false);

    $this->resourceRepository
        ->expects($this->once())
        ->method('exist')
        ->willReturn($this->request->resources[0]['ids']);
    $this->contactRepository
        ->expects($this->never())
        ->method('exist')
        ->willReturn($this->request->users);
    $this->contactGroupRepository
        ->expects($this->never())
        ->method('findByIds')
        ->willReturn(
            [
                new ContactGroup($this->request->contactGroups[0], 'contactgroup'),
                new ContactGroup($this->request->contactGroups[1], 'contactgroup_1')
            ]
        );
    $this->writeNotificationRepository
        ->expects($this->once())
        ->method('add')
        ->willReturn(1);
    $this->writeNotificationRepository
        ->expects($this->once())
        ->method('addMessages');
    $this->writeNotificationRepository
        ->expects($this->once())
        ->method('addUsers');
    $this->resourceRepository
        ->expects($this->once())
        ->method('add');

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

    $this->validator
        ->expects($this->once())
        ->method('validateUsersAndContactGroups');

    $this->user
        ->expects($this->atLeast(2))
        ->method('isAdmin')
        ->willReturn(true);
    $this->resourceRepositoryProvider
        ->expects($this->atLeast(2))
        ->method('getRepository')
        ->willReturn($this->resourceRepository);
    $this->resourceRepository
        ->expects($this->atLeast(1))
        ->method('eventEnum')
        ->willReturn(NotificationHostEvent::class);
    $this->resourceRepository
        ->expects($this->atLeast(1))
        ->method('eventEnumConverter')
        ->willReturn(NotificationHostEventConverter::class);
    $this->resourceRepository
        ->expects($this->atLeast(1))
        ->method('resourceType')
        ->willReturn('hostgroup');

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readNotificationRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(false);
    $this->resourceRepository
        ->expects($this->once())
        ->method('exist')
        ->willReturn($this->request->resources[0]['ids']);
    $this->contactRepository
        ->expects($this->never())
        ->method('exist')
        ->willReturn($this->request->users);
    $this->contactGroupRepository
        ->expects($this->never())
        ->method('findByIds')
        ->willReturn(
            [
                new ContactGroup($this->request->contactGroups[0], 'contactgroup'),
                new ContactGroup($this->request->contactGroups[1], 'contactgroup_1')
            ]
        );
    $this->writeNotificationRepository
        ->expects($this->once())
        ->method('add')
        ->willReturn(1);
    $this->writeNotificationRepository
        ->expects($this->once())
        ->method('addMessages');
    $this->writeNotificationRepository
        ->expects($this->once())
        ->method('addUsers');
    $this->writeNotificationRepository
        ->expects($this->once())
        ->method('addContactGroups');
    $this->resourceRepository
        ->expects($this->once())
        ->method('add');
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
