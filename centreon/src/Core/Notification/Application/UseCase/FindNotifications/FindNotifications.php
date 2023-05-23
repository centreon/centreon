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
 * For more information : user@centreon.com
 *
 */

declare(strict_types=1);

namespace Core\Notification\Application\UseCase\FindNotifications;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Log\LoggerTrait;
use Core\Notification\Domain\Model\Notification;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Notification\Domain\Model\NotificationChannel;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Notification\Domain\Model\NotificationResource;
use Core\Notification\Application\Exception\NotificationException;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Notification\Application\UseCase\FindNotifications\NotificationCounts;
use Core\Notification\Application\Repository\ReadNotificationRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Notification\Application\Repository\NotificationResourceRepositoryProviderInterface;

final class FindNotifications
{
    use LoggerTrait;

    public function __construct(
        private readonly ContactInterface $user,
        private readonly ReadNotificationRepositoryInterface $notificationRepository,
        private readonly NotificationResourceRepositoryProviderInterface $repositoryProvider,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly RequestParametersInterface $requestParameters,
    ) {
    }

    public function __invoke(FindNotificationsPresenterInterface $presenter): void
    {
        if (
            ! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_NOTIFICATIONS_READ_WRITE)
        ) {
            $this->error(
                "User doesn't have sufficient rights to list notifications",
                ['user_id' => $this->user->getId()]
            );
            $presenter->presentResponse(
                new ForbiddenResponse(NotificationException::listNotAllowed())
            );

            return;
        }

        try {
            $this->info('Search for Notifications');
            $notifications = $this->notificationRepository->findAll($this->requestParameters);
            $notificationsIds = [];
            foreach ($notifications as $notification) {
                $notificationsIds[] = $notification->getId();
            }

            if (!empty ($notificationsIds)) {
                $this->info('Retrieving Notification channels for notifications', ["notifications" => implode(", ", $notificationsIds)]);
                $notificationChannelByNotifications = $this->notificationRepository
                    ->findNotificationChannelsByNotificationIds(
                        $notificationsIds
                    );
                $notificationCounts = $this->getCountByNotifications($notificationsIds);
                $presenter->presentResponse(
                    $this->createResponse($notifications, $notificationCounts, $notificationChannelByNotifications)
                );

                return;
            }

            $presenter->presentResponse(
                $this->createResponse([], new NotificationCounts([],[],[]), [])
            );
        } catch (\Throwable | NotificationException $ex) {
            $this->error('An error occured while retrieving the notifications listing', ["trace" => (string) $ex]);
            $presenter->presentResponse(
                new ErrorResponse(_('An error occured while retrieving the notifications listing'))
            );
        }
    }

    /**
     * Create Response Object
     *
     * @param Notification[] $notifications
     * @param NotificationCounts $notificationCounts
     * @param array<int, NotificationChannel[]> $notificationChannelByNotifications
     * @return FindNotificationsResponse
     */
    private function createResponse(
        array $notifications,
        NotificationCounts $notificationCounts,
        array $notificationChannelByNotifications
    ): FindNotificationsResponse {
        $response = new FindNotificationsResponse();

        $notificationDtos = [];
        foreach($notifications as $notification) {
            $notificationDto = new NotificationDto();
            $notificationDto->id = $notification->getId();
            $notificationDto->name = $notification->getName();
            $notificationDto->usersCount = $notificationCounts->getUsersCountByNotificationId($notification->getId());
            if ($notificationCounts->getHostgroupResourcesCountByNotificationId($notification->getId()) !== null) {
                $notificationDto->resources[] = [
                    'type' => NotificationResource::HOSTGROUP_RESOURCE_TYPE,
                    'count' => $notificationCounts->getHostgroupResourcesCountByNotificationId($notification->getId())
                ];
            }
            if ($notificationCounts->getServicegroupResourcesCountByNotificationId($notification->getId()) !== null) {
                $notificationDto->resources[] = [
                    'type' => NotificationResource::SERVICEGROUP_RESOURCE_TYPE,
                    'count' => $notificationCounts->getServicegroupResourcesCountByNotificationId(
                        $notification->getId()
                    )
                ];
            }
            $notificationDto->timeperiod = [
                'id' => $notification->getTimePeriod()->getId(),
                'name' => $notification->getTimePeriod()->getName()
            ];
            $notificationDto->notificationChannels = $notificationChannelByNotifications[$notification->getId()];

            $notificationDtos[] = $notificationDto;
        }

        $response->notifications = $notificationDtos;

        return $response;
    }

    /**
     * Retrieve the count of users, hostgroup resources, servicegroup resources for the listed notifications.
     *
     * @param non-empty-array<int> $notificationsIds
     * @return NotificationCounts
     */
    private function getCountByNotifications(array $notificationsIds): NotificationCounts
    {
        $this->info('Retrieving user counts for notifications', ['notification' => implode(', ', $notificationsIds)]);
        $notificationsUsersCount = $this->notificationRepository->findUsersCountByNotificationIds(
            $notificationsIds
        );
        if(($notificationWithEmptyUser = array_search(0, $notificationsUsersCount)) !== false) {
            $this->error('No users found for a notification', ['notification' => $notificationWithEmptyUser]);
            throw NotificationException::invalidUsers();
        }
        $hostGroupResourceRepository = $this->repositoryProvider->getRepository(
            NotificationResource::HOSTGROUP_RESOURCE_TYPE
        );
        $serviceGroupResourceRepository = $this->repositoryProvider->getRepository(
            NotificationResource::SERVICEGROUP_RESOURCE_TYPE
        );
        if(! $this->user->isAdmin()) {
            $this->info('Retrieving ACLs for user', ['user' => $this->user->getId()]);
            $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);

            $this->info('Retrieving hostgroup resource counts for an user with ACL', ["user" => $this->user->getId()]);
            $hostgroupResourcesCount = $hostGroupResourceRepository->findResourcesCountByNotificationIdsAndAccessGroups(
                $notificationsIds,
                $accessGroups
            );

            $this->info(
                'Retrieving servicegroup resource counts for an user with ACL',
                ["user" => $this->user->getId()]
            );
            $servicegroupResourcesCount = $serviceGroupResourceRepository
                ->findResourcesCountByNotificationIdsAndAccessGroups(
                    $notificationsIds,
                    $accessGroups
                );
        } else {
            $this->info('Retrieving hostgroup resource counts for an admin user', ["user" => $this->user->getId()]);
            $hostgroupResourcesCount = $hostGroupResourceRepository->findResourcesCountByNotificationIds(
                $notificationsIds
            );

            $this->info('Retrieving servicegroup resource counts for an admin user', ["user" => $this->user->getId()]);
            $servicegroupResourcesCount = $serviceGroupResourceRepository
                ->findResourcesCountByNotificationIds(
                    $notificationsIds
                );
        }

        $notificationCounts = new NotificationCounts(
            $notificationsUsersCount,
            $hostgroupResourcesCount,
            $servicegroupResourcesCount
        );

        return $notificationCounts;
    }
}
