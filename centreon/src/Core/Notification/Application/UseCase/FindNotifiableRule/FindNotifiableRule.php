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

declare(strict_types=1);

namespace Core\Notification\Application\UseCase\FindNotifiableRule;

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Contact\Domain\Model\ContactGroup;
use Core\Notification\Application\Exception\NotificationException;
use Core\Notification\Application\Repository\ReadNotificationRepositoryInterface;
use Core\Notification\Application\UseCase\FindNotifiableRule\Response\ChannelEmailContactResponseDto;
use Core\Notification\Application\UseCase\FindNotifiableRule\Response\ChannelEmailResponseDto;
use Core\Notification\Domain\Model\ConfigurationUser;
use Core\Notification\Domain\Model\Notification;
use Core\Notification\Domain\Model\NotificationChannel;
use Core\Notification\Domain\Model\NotificationMessage;

final class FindNotifiableRule
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadNotificationRepositoryInterface $notificationRepository,
        private readonly ContactInterface $user,
    ) {
    }

    public function __invoke(
        int $notificationId,
        FindNotifiableRulePresenterInterface $presenter
    ): void {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_NOTIFICATIONS_READ_WRITE)) {
                $this->error(
                    "User doesn't have sufficient rights to get notifications rule",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->presentResponse(
                    new ForbiddenResponse(NotificationException::listOneNotAllowed())
                );

                return;
            }
            $this->info(
                'Retrieving details for notification',
                ['notification_id' => $notificationId]
            );

            if ($notification = $this->notificationRepository->findById($notificationId)) {
                $presenter->presentResponse(
                    $this->createResponse(
                        notification: $notification,
                        messages: $this->notificationRepository->findMessagesByNotificationId($notificationId),
                        contacts: $this->notificationRepository->findUsersByNotificationId($notificationId),
                        contactGroups: $this->findContactGroupsByNotificationId($notificationId),
                    )
                );
            } else {
                $this->info('Notification not found', ['notification_id' => $notificationId]);
                $presenter->presentResponse(new NotFoundResponse('Notification'));
            }
        } catch (AssertionFailedException $ex) {
            $this->error(
                'An error occurred while retrieving the details of the notification',
                ['notification_id' => $notificationId, 'trace' => (string) $ex]
            );
            $presenter->presentResponse(new InvalidArgumentResponse($ex->getMessage()));
        } catch (\Throwable $ex) {
            $this->error(
                'Unable to retrieve the details of the notification',
                ['notification_id' => $notificationId, 'trace' => (string) $ex]
            );
            $presenter->presentResponse(new ErrorResponse(NotificationException::errorWhileRetrievingObject()));
        }
    }

    /**
     * Retrieve notification contactgroup with user rights.
     *
     * @param int $notificationId
     *
     * @throws \Throwable
     *
     * @return ContactGroup[]
     */
    private function findContactGroupsByNotificationId(int $notificationId): array
    {
        if ($this->user->isAdmin()) {
            return $this->notificationRepository->findContactGroupsByNotificationId($notificationId);
        }

        return $this->notificationRepository
            ->findContactGroupsByNotificationIdAndUserId($notificationId, $this->user->getId());
    }

    /**
     * @param Notification $notification
     * @param NotificationMessage[] $messages
     * @param array<int, ConfigurationUser> $contacts
     * @param ContactGroup[] $contactGroups
     *
     * @return FindNotifiableRuleResponse
     */
    private function createResponse(
        Notification $notification,
        array $messages,
        array $contacts,
        array $contactGroups,
    ): FindNotifiableRuleResponse {
        $response = new FindNotifiableRuleResponse();
        $response->notificationId = $notification->getId();

        $contactsFromContactGroups = $this->notificationRepository->findUsersByContactGroupIds(
            ...array_map(
                static fn(ContactGroup $contactGroup): int => $contactGroup->getId(),
                $contactGroups
            )
        );
        $allContacts = array_values(array_replace($contacts, $contactsFromContactGroups));

        foreach ($messages as $message) {
            switch ($message->getChannel()) {
                case NotificationChannel::Email:
                    $response->channels->email = new ChannelEmailResponseDto(
                        contacts: array_map(
                            static fn(ConfigurationUser $user) => new ChannelEmailContactResponseDto(
                                fullName: $user->getName(),
                                emailAddress: $user->getEmail(),
                            ),
                            $allContacts
                        ),
                        subject: $message->getSubject(),
                        formattedMessage: $message->getFormattedMessage(),
                    );
                    break;
                case NotificationChannel::Slack:
                case NotificationChannel::Sms:
                    // Not implemented yet, 2023-09-07.
                    break;
            }
        }

        return $response;
    }
}
