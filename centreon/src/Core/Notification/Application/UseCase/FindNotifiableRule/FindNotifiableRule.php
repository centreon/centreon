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

namespace Core\Notification\Application\UseCase\FindNotifiableRule;

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Contact\Application\Repository\ReadContactRepositoryInterface;
use Core\Contact\Domain\Model\BasicContact;
use Core\Contact\Domain\Model\ContactGroup;
use Core\Notification\Application\Exception\NotificationException;
use Core\Notification\Application\Repository\ReadNotificationRepositoryInterface;
use Core\Notification\Application\UseCase\FindNotifiableRule\Response\ContactDto;
use Core\Notification\Application\UseCase\FindNotifiableRule\Response\EmailDto;
use Core\Notification\Domain\Model\Channel;
use Core\Notification\Domain\Model\Contact as NotificationContact;
use Core\Notification\Domain\Model\Message;
use Core\Notification\Domain\Model\Notification;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

final class FindNotifiableRule
{
    use LoggerTrait;

    /** @var AccessGroup[]|null */
    private ?array $accessGroups = null;

    public function __construct(
        private readonly ReadNotificationRepositoryInterface $notificationRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ReadContactRepositoryInterface $readContactRepository,
        private readonly ContactInterface $user,
    ) {
    }

    public function __invoke(
        int $notificationId,
        FindNotifiableRulePresenterInterface $presenter,
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
                        contacts: $this->findUsersByNotificationId($notificationId),
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
  
            return $this->notificationRepository->findContactGroupsByNotificationIdAndAccessGroups(
                $notificationId,
                $this->findAccessGroupsOfNonAdminUser()
            );
        
    }

    /**
     * @param int $notificationId
     *
     * @throws \Throwable
     *
     * @return NotificationContact[]
     */
    private function findUsersByNotificationId(int $notificationId): array
    {
        if ($this->user->isAdmin()) {
            return $this->notificationRepository->findUsersByNotificationId($notificationId);
        }
  
            return $this->notificationRepository->findUsersByNotificationIdAndAccessGroups(
                $notificationId,
                $this->findAccessGroupsOfNonAdminUser()
            );
        
    }

    /**
     * @throws \Throwable
     *
     * @return AccessGroup[]
     */
    private function findAccessGroupsOfNonAdminUser(): array
    {
        if ($this->accessGroups === null) {
            $this->accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
        }

        return $this->accessGroups;
    }

    /**
     * @param Notification $notification
     * @param Message[] $messages
     * @param array<int, NotificationContact> $contacts
     * @param ContactGroup[] $contactGroups
     *
     * @throws \Throwable
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

        // Retrieve contacts from contact groups
        $contactGroupIds = array_map(
            static fn(ContactGroup $contactGroup) => $contactGroup->getId(),
            $contactGroups
        );
        $contactIdsFromContactGroups = $this->readContactRepository->findContactIdsByContactGroups($contactGroupIds);
        $contactsFromContactGroups = $this->readContactRepository->findByIds($contactIdsFromContactGroups);

        $contactDtos = $this->createContactDto([
            ...$contactsFromContactGroups,
            ...$contacts,
        ]);

        foreach ($messages as $message) {
            switch ($message->getChannel()) {
                case Channel::Email:
                    $response->channels->email = new EmailDto(
                        contacts: $contactDtos,
                        subject: $message->getSubject(),
                        formattedMessage: $message->getFormattedMessage(),
                    );
                    break;
                case Channel::Slack:
                case Channel::Sms:
                    // Not implemented yet, 2023-09-07.
                    break;
            }
        }

        return $response;
    }

    /**
     * @param NotificationContact[]|BasicContact[] $contacts
     *
     * @return ContactDto[]
     */
    private function createContactDto (array $contacts): array
    {
        $contactDtos = [];
        $contactIdsAlreadyCreated = [];
        foreach ($contacts as $contact) {
            if (in_array($contact->getId(), $contactIdsAlreadyCreated, true)) {
                continue;
            }
            if ($contact instanceof NotificationContact) {
                $contactDtos[] = new ContactDto(
                    fullName: $contact->getName(),
                    emailAddress: $contact->getEmail(),
                );
                $contactIdsAlreadyCreated[] = $contact->getId();
            } elseif ($contact instanceof BasicContact) {
                $contactDtos[] = new ContactDto(
                    fullName: $contact->getName(),
                    emailAddress: $contact->getEmail(),
                );
                $contactIdsAlreadyCreated[] = $contact->getId();
            }
        }

        return $contactDtos;
    }
}
