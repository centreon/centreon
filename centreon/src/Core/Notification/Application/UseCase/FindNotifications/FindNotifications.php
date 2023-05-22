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
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Notification\Application\Exception\NotificationException;
use Core\Notification\Application\Repository\NotificationResourceRepositoryProviderInterface;
use Core\Notification\Application\Repository\ReadNotificationRepositoryInterface;
use Core\Notification\Domain\Model\Notification;
use Core\Notification\Domain\Model\NotificationResource;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

final class FindNotifications
{
    use LoggerTrait;

    public function __construct(
        private readonly ContactInterface $user,
        private readonly ReadNotificationRepositoryInterface $notificationRepository,
        private readonly NotificationResourceRepositoryProviderInterface $repositoryProvider,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
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
            $notifications = $this->notificationRepository->findAll();
            $notificationsIds = [];
            foreach ($notifications as $notification) {
                $notificationsIds[] = $notification->getId();
            }
            $notificationChannelByNotifications = $this->notificationRepository
                ->findNotificationChannelsByNotificationIds(
                    $notificationsIds
                );
            $countsByNotification = $this->getCountByNotifications($notificationsIds);

            $presenter->presentResponse($this->createResponse($notifications, $countsByNotification, $notificationChannelByNotifications));
        } catch (\Throwable $ex) {

        }
    }

    /**
     * Create Response Object
     *
     * @param Notification[] $notifications
     * @param array<int, array{
     *  users : int
     *  hostgroup ?: int
     *  servicegroup ?: int
     * }> $countsByNotification
     * @return FindNotificationsResponse
     */
    private function createResponse(
        array $notifications,
        array $countsByNotification,
        array $notificationChannelByNotifications
    ): FindNotificationsResponse {
        $response = new FindNotificationsResponse();

        $notificationDtos = [];
        foreach($notifications as $notification) {
            $notificationDto = new NotificationDto();
            $notificationDto->id = $notification->getId();
            $notificationDto->name = $notification->getName();
            $notificationDto->usersCount = $countsByNotification[$notification->getId()]['users'];
            if (array_key_exists(
                NotificationResource::HOSTGROUP_RESOURCE_TYPE,
                $countsByNotification[$notification->getId()]
            )) {
                $notificationDto->resources[] = [
                    'type' => NotificationResource::HOSTGROUP_RESOURCE_TYPE,
                    'count' => $countsByNotification[$notification->getId()][
                        NotificationResource::HOSTGROUP_RESOURCE_TYPE
                    ]
                ];
            }
            if (array_key_exists(
                NotificationResource::SERVICEGROUP_RESOURCE_TYPE,
                $countsByNotification[$notification->getId()]
            )) {
                $notificationDto->resources[] = [
                    'type' => NotificationResource::SERVICEGROUP_RESOURCE_TYPE,
                    'count' => $countsByNotification[$notification->getId()][
                        NotificationResource::SERVICEGROUP_RESOURCE_TYPE
                    ]
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
     * Undocumented function
     *
     * @param array<int> $notificationsIds
     * @return array<int, array{
     *  users : int
     *  hostgroup ?: int
     *  servicegroup ?: int
     * }>
     */
    private function getCountByNotifications(array $notificationsIds): array
    {
        $notificationsUsersCount = $this->notificationRepository->findUsersCountByNotificationIds(
            $notificationsIds
        );

        $hostGroupResourceRepository = $this->repositoryProvider->getRepository(
            NotificationResource::HOSTGROUP_RESOURCE_TYPE
        );
        $serviceGroupResourceRepository = $this->repositoryProvider->getRepository(
            NotificationResource::SERVICEGROUP_RESOURCE_TYPE
        );
        $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
        $hostgroupResourcesCount = $hostGroupResourceRepository->findResourcesCountByNotificationIdsAndAccessGroups(
            $notificationsIds,
            $accessGroups
        );
        $servicegroupResourcesCount = $serviceGroupResourceRepository
            ->findResourcesCountByNotificationIdsAndAccessGroups(
                $notificationsIds,
                $accessGroups
            );

        $countsByNotification = [];
        // TODO: Create an Object to handle those arrays
        foreach ($notificationsIds as $notificationId) {
            if (array_key_exists($notificationId, $notificationsUsersCount)) {
                $countsByNotification[$notificationId]['users'] = $notificationsUsersCount[
                    $notificationId
                ];
            }
            if (array_key_exists($notificationId, $hostgroupResourcesCount)) {
                $countsByNotification[$notificationId]['hostgroup'] = $hostgroupResourcesCount[
                    $notificationId
                ];
            }
            if (array_key_exists($notificationId, $servicegroupResourcesCount)) {
                $countsByNotification[$notificationId]['servicegroup'] = $servicegroupResourcesCount[
                    $notificationId
                ];
            }
        }

        return $countsByNotification;
    }
}
