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

namespace Tests\Core\Notification\Application\UseCase\FindNotifiableRule;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Contact\Application\Repository\ReadContactRepositoryInterface;
use Core\Contact\Domain\Model\ContactGroup;
use Core\Notification\Application\Exception\NotificationException;
use Core\Notification\Application\Repository\ReadNotificationRepositoryInterface;
use Core\Notification\Application\UseCase\FindNotifiableRule\FindNotifiableRule;
use Core\Notification\Application\UseCase\FindNotifiableRule\FindNotifiableRuleResponse;
use Core\Notification\Domain\Model\Contact as NotificationContact;
use Core\Notification\Domain\Model\TimePeriod;
use Core\Notification\Domain\Model\Notification;
use Core\Notification\Domain\Model\Channel;
use Core\Notification\Domain\Model\Message;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

beforeEach(function (): void {
    $this->presenter = new FindNotifiableRulePresenterStub();
    $this->useCase = new FindNotifiableRule(
        $this->notificationRepository = $this->createMock(ReadNotificationRepositoryInterface::class),
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->readContactRepository = $this->createMock(ReadContactRepositoryInterface::class),
        $this->contact = $this->createMock(ContactInterface::class),
    );
});

it(
    'should present an error response when the user is not admin and doesn\'t have sufficient ACLs',
    function (): void {
        $this->contact->expects($this->atLeastOnce())
            ->method('getId')->willReturn(1);
        $this->contact->expects($this->atLeastOnce())
            ->method('hasTopologyRole')
            ->willReturnMap(
                [[Contact::ROLE_CONFIGURATION_NOTIFICATIONS_READ_WRITE, false]]
            );

        ($this->useCase)(1, $this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(ForbiddenResponse::class)
            ->and($this->presenter->data?->getMessage())
            ->toBe(NotificationException::listOneNotAllowed()->getMessage());
    }
);

it(
    'should present a not found response when the notification does not exist',
    function (): void {
        $this->contact->expects($this->atLeastOnce())
            ->method('hasTopologyRole')
            ->willReturnMap(
                [[Contact::ROLE_CONFIGURATION_NOTIFICATIONS_READ_WRITE, true]]
            );

        $this->notificationRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        ($this->useCase)(1, $this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(NotFoundResponse::class);
    }
);

it(
    'should present an error response when something unhandled occurs',
    function (): void {
        $this->contact->expects($this->atLeastOnce())
            ->method('hasTopologyRole')
            ->willReturnMap(
                [[Contact::ROLE_CONFIGURATION_NOTIFICATIONS_READ_WRITE, true]]
            );

        $this->notificationRepository
            ->expects($this->once())
            ->method('findById')
            ->willThrowException(new \Exception());

        ($this->useCase)(1, $this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->data?->getMessage())
            ->toBe(NotificationException::errorWhileRetrievingObject()->getMessage());
    }
);

it(
    'should get the rule with ACL calculation when the user is not admin',
    function (): void {
        $this->contact->expects($this->atLeastOnce())
            ->method('hasTopologyRole')
            ->willReturnMap(
                [[Contact::ROLE_CONFIGURATION_NOTIFICATIONS_READ_WRITE, true]]
            );

        $notification = new Notification(
            1,
            'notification',
            new TimePeriod(1, '24x7'),
            false
        );
        $notificationMessage = new Message(
            Channel::from('Slack'),
            'Message subject',
            'Message content',
            '<p>Message content</p>'
        );
        $notificationUser = new NotificationContact(3, 'test-user', 'test-email');

        $this->notificationRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($notification);

        $this->notificationRepository
            ->expects($this->once())
            ->method('findMessagesByNotificationId')
            ->willReturn([$notificationMessage]);

        $this->contact
            ->expects($this->atLeastOnce())
            ->method('isAdmin')
            ->willReturn(false);
        $this->notificationRepository
            ->expects($this->once())
            ->method('findUsersByNotificationIdAndAccessGroups')
            ->willReturn([$notificationUser]);

        $this->notificationRepository
            ->expects($this->once())
            ->method('findContactGroupsByNotificationIdAndAccessGroups')
            ->willReturn([]);

        ($this->useCase)(1, $this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(FindNotifiableRuleResponse::class);
    }
);

it(
    'should present a FindNotifiableRuleResponse when everything is OK',
    function (): void {
        $this->contact->expects($this->atLeastOnce())
            ->method('isAdmin')->willReturn(true);
        $this->contact->expects($this->atLeastOnce())
            ->method('hasTopologyRole')
            ->willReturnMap(
                [[Contact::ROLE_CONFIGURATION_NOTIFICATIONS_READ_WRITE, true]]
            );

        $contactGroups = [
            new ContactGroup(1, 'contactgroup_1', 'contactgroup_1'),
            new ContactGroup(2, 'contactgroup_2', 'contactgroup_2'),
        ];

        $notification = new Notification(1, 'notification', new TimePeriod(1, '24x7'), false);
        $notificationMessage = new Message(
            Channel::from('Email'),
            'Message subject',
            'Message content',
            '<p>Message content</p>'
        );
        $notificationUser = new NotificationContact(3, 'test-user', 'test-email');

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

        $this->notificationRepository
            ->expects($this->once())
            ->method('findContactGroupsByNotificationId')
            ->willReturn($contactGroups);

        ($this->useCase)(1, $this->presenter);

        expect($this->presenter->data)->toBeInstanceOf(FindNotifiableRuleResponse::class)
            ->and($this->presenter->data->notificationId)->toBe(1)
            ->and($this->presenter->data->channels->slack)->toBe(null)
            ->and($this->presenter->data->channels->sms)->toBe(null)
            ->and($this->presenter->data->channels->email?->subject)->toBe('Message subject')
            ->and($this->presenter->data->channels->email?->formattedMessage)->toBe('<p>Message content</p>')
            ->and($this->presenter->data->channels->email?->contacts[0]->fullName)->toBe('test-user')
            ->and($this->presenter->data->channels->email?->contacts[0]->emailAddress)->toBe('test-email');
    }
);
