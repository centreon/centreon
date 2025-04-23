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

namespace Core\Notification\Application\UseCase\FindNotification;

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
use Core\Notification\Application\Repository\NotificationResourceRepositoryProviderInterface;
use Core\Notification\Application\Repository\ReadNotificationRepositoryInterface;
use Core\Notification\Domain\Model\Contact as NotificationContact;
use Core\Notification\Domain\Model\Message;
use Core\Notification\Domain\Model\Notification;
use Core\Notification\Domain\Model\NotificationResource;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

final class FindNotification
{
    use LoggerTrait;

    /**
     * @param ReadNotificationRepositoryInterface $notificationRepository
     * @param ContactInterface $user
     * @param NotificationResourceRepositoryProviderInterface $resourceRepositoryProvider
     * @param ReadAccessGroupRepositoryInterface $readAccessGroupRepository
     */
    public function __construct(
        private readonly ReadNotificationRepositoryInterface $notificationRepository,
        private readonly ContactInterface $user,
        private readonly NotificationResourceRepositoryProviderInterface $resourceRepositoryProvider,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
    ) {
    }

    /**
     * @param int $notificationId
     * @param FindNotificationPresenterInterface $presenter
     */
    public function __invoke(int $notificationId, FindNotificationPresenterInterface $presenter): void
    {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_NOTIFICATIONS_READ_WRITE)) {
                $this->error(
                    "User doesn't have sufficient rights to add notifications",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->presentResponse(
                    new ForbiddenResponse(NotificationException::listOneNotAllowed())
                );

                return;
            }
            $this->info('Retrieving details for notification', [
                'notification_id' => $notificationId,
            ]);

            if (($notification = $this->notificationRepository->findById($notificationId)) === null) {
                $this->info('Notification not found', [
                    'notification_id' => $notificationId,
                ]);
                $presenter->presentResponse(new NotFoundResponse(_('Notification')));
            } else {
                $this->info('Get all notification messages for notification with ID #' . $notificationId);
                $notificationMessages = $this->notificationRepository->findMessagesByNotificationId($notificationId);
                $this->info('Get all notification users for notification with ID #' . $notificationId);
                $notifiedUsers = $this->findNotificationUsers($notificationId);
                $notifiedContactGroups = $this->findContactGroupsByNotificationId($notificationId);
                $notificationResources = $this->findResourcesByNotificationId($notificationId);

                $presenter->presentResponse($this->createResponse(
                    $notification,
                    $notificationMessages,
                    $notifiedUsers,
                    $notifiedContactGroups,
                    $notificationResources
                ));
            }
        } catch (AssertionFailedException $ex) {
            $this->error('An error occurred while retrieving the details of the notification',[
                'notification_id' => $notificationId,
                'trace' => (string) $ex,
            ]);
            $presenter->presentResponse(new InvalidArgumentResponse($ex->getMessage()));
        } catch (\Throwable $ex) {
            $this->error('Unable to retrieve the details of the notification',[
                'notification_id' => $notificationId,
                'trace' => (string) $ex,
            ]);
            $presenter->presentResponse(new ErrorResponse(NotificationException::errorWhileRetrievingObject()));
        }
    }

    /**
     * @param int $notificationId
     *
     * @throws \Throwable
     *
     * @return NotificationContact[]
     */
    private function findNotificationUsers(int $notificationId): array
    {
        if ($this->user->isAdmin()) {
            $notifiedUsers = array_values($this->notificationRepository->findUsersByNotificationId($notificationId));
        } else {
            $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
            $notifiedUsers = array_values($this->notificationRepository->findUsersByNotificationIdUserAndAccessGroups(
                $notificationId,
                $this->user,
                $accessGroups
            ));
        }

        return $notifiedUsers;
    }

    /**
     * @param int $notificationId
     *
     * @throws \Throwable
     *
     * @return NotificationResource[]
     */
    private function findResourcesByNotificationId(int $notificationId): array
    {
        $resources = [];
        foreach ($this->resourceRepositoryProvider->getRepositories() as $repository) {
            if ($this->user->isAdmin()) {
                $resource = $repository->findByNotificationId($notificationId);
            } else {
                $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
                $resource = $repository->findByNotificationIdAndAccessGroups($notificationId, $accessGroups);
            }

            if ($resource !== null) {
                $resources[] = $resource;
            }
        }

        return $resources;
    }

    /**
     * Retrieve notification contact groups based on ACL.
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
        $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);

        return $this->notificationRepository->findContactGroupsByNotificationIdAndAccessGroups(
            $notificationId,
            $this->user,
            $accessGroups
        );
    }

    /**
     * create FindNotificationResponse Dto.
     *
     * @param Notification $notification
     * @param Message[] $notificationMessages
     * @param NotificationContact[] $notifiedUsers
     * @param ContactGroup[] $notifiedContactGroups
     * @param NotificationResource[] $notificationResources
     *
     * @return FindNotificationResponse
     */
    private function createResponse(
        Notification $notification,
        array $notificationMessages,
        array $notifiedUsers,
        array $notifiedContactGroups,
        array $notificationResources
    ): FindNotificationResponse {
        $response = new FindNotificationResponse();

        $response->id = $notification->getId();
        $response->name = $notification->getName();
        $response->timeperiodId = $notification->getTimePeriod()->getId();
        $response->timeperiodName = $notification->getTimePeriod()->getName();
        $response->isActivated = $notification->isActivated();

        $response->messages = array_map(
            static fn(Message $message): array => [
                'channel' => $message->getChannel()->value,
                'subject' => $message->getSubject(),
                'message' => $message->getRawMessage(),
                'formatted_message' => $message->getFormattedMessage(),
            ],
            $notificationMessages
        );

        $response->users = array_map(
            static fn(NotificationContact $user): array => ['id' => $user->getId(), 'name' => $user->getName()],
            $notifiedUsers
        );

        $response->contactGroups = array_map(
            static fn(ContactGroup $contactGroup): array => [
                'id' => $contactGroup->getId(),
                'name' => $contactGroup->getName(),
            ],
            $notifiedContactGroups
        );

        foreach ($notificationResources as $resource) {
            $responseResource = [
                'type' => $resource->getType(),
                'events' => $resource->getEvents(),
                'ids' => array_map(
                    static fn ($resource): array => ['id' => $resource->getId(), 'name' => $resource->getName()],
                    $resource->getResources()
                ),
            ];
            if (
                $resource->getType() === NotificationResource::TYPE_HOST_GROUP
                && ! empty($resource->getServiceEvents())
            ) {
                $responseResource['extra'] = [
                    'event_services' => $resource->getServiceEvents(),
                ];
            }
            $response->resources[] = $responseResource;
        }

        return $response;
    }
}
