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

namespace Core\Notification\Application\UseCase\FindNotifications;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Notification\Application\Exception\NotificationException;
use Core\Notification\Application\Repository\NotificationResourceRepositoryInterface;
use Core\Notification\Application\Repository\NotificationResourceRepositoryProviderInterface;
use Core\Notification\Application\Repository\ReadNotificationRepositoryInterface;
use Core\Notification\Domain\Model\Channel;
use Core\Notification\Domain\Model\Notification;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

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
        $this->info('Search for notifications', ['request_parameter' => $this->requestParameters->toArray()]);
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
            $notifications = $this->notificationRepository->findAll($this->requestParameters);
            if (empty($notifications)) {
                $presenter->presentResponse(new FindNotificationsResponse());

                return;
            }

            $notificationsIds = array_map(fn (Notification $notification) => $notification->getId(), $notifications);
            $this->info(
                'Retrieving notification channels for notifications',
                ['notifications' => implode(', ', $notificationsIds)]
            );
            $notificationChannelByNotifications = $this->notificationRepository
                ->findNotificationChannelsByNotificationIds($notificationsIds);

            $notificationCounts = $this->countUsersAndResourcesPerNotification($notificationsIds);

            $presenter->presentResponse(
                $this->createResponse($notifications, $notificationCounts, $notificationChannelByNotifications)
            );
        } catch (RequestParametersTranslatorException $ex) {
            $presenter->presentResponse(new ErrorResponse($ex->getMessage()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $this->error('An error occurred while retrieving the notifications listing', ['trace' => (string) $ex]);
            $presenter->presentResponse(
                new ErrorResponse(_('An error occurred while retrieving the notifications listing'))
            );
        }
    }

    /**
     * Counts the number of users, host groups and service groups for given notifications.
     *
     * @param non-empty-array<int> $notificationsIds
     *
     * @throws \Throwable $ex
     *
     * @return NotificationCounts
     */
    private function countUsersAndResourcesPerNotification(array $notificationsIds): NotificationCounts
    {
        $this->debug(
            'Retrieving user counts for notifications',
            ['notification' => implode(', ', $notificationsIds)]
        );
        if ($this->user->isAdmin()) {
            $numberOfUsers = $this->notificationRepository->countContactsByNotificationIds($notificationsIds);
        } else {
            $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
            $numberOfUsers = $this->notificationRepository->countContactsByNotificationIdsAndAccessGroup(
                $notificationsIds,
                $accessGroups
            );
        }
        $this->debug(sprintf('Found %d users for notifications', count($numberOfUsers)));

        $repositories = $this->repositoryProvider->getRepositories();

        $this->debug(
            'Retrieving resource counts for notifications',
            ['notification' => implode(', ', $notificationsIds)]
        );
        if ($this->user->isAdmin()) {
            $numberOfResources = $this->countResourcesForAdmin($repositories, $notificationsIds);
        } else {
            $numberOfResources = $this->countResourcesWithACL($repositories, $notificationsIds);
        }
        $this->debug(sprintf('Found %d resources for notifications', count($numberOfResources)));

        return new NotificationCounts($numberOfUsers, $numberOfResources);
    }

    /**
     * Get count of resources by listed notifications id and ACL.
     *
     * @param NotificationResourceRepositoryInterface[] $repositories
     * @param non-empty-array<int> $notificationsIds
     *
     * @throws \Throwable $ex
     *
     * @return array<string, array<int,int>>
     */
    private function countResourcesWithACL(array $repositories, array $notificationsIds): array
    {
        $this->info(
            'Retrieving host group resource counts for a non-admin user',
            ['user' => $this->user->getId()]
        );
        $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);

        $resourcesCount = [];
        foreach ($repositories as $repository) {
            $count = $repository->countResourcesByNotificationIdsAndAccessGroups($notificationsIds, $accessGroups);
            $resourcesCount[$repository->resourceType()] = $count;
        }

        return $resourcesCount;
    }

    /**
     * Get count of resources by listed notifications id.
     *
     * @param NotificationResourceRepositoryInterface[] $repositories
     * @param non-empty-array<int> $notificationsIds
     *
     * @throws \Throwable $ex
     *
     * @return array<string, array<int,int>>
     */
    private function countResourcesForAdmin(array $repositories, array $notificationsIds): array
    {
        $this->info('Retrieving host group resource counts for an admin user', ['user' => $this->user->getId()]);
        $resourcesCount = [];
        foreach ($repositories as $repository) {
            $count = $repository->countResourcesByNotificationIds($notificationsIds);
            $resourcesCount[$repository->resourceType()] = $count;
        }

        return $resourcesCount;
    }

    /**
     * Create Response Object.
     *
     * @param Notification[] $notifications
     * @param NotificationCounts $notificationCounts
     * @param array<int, Channel[]> $notificationChannelByNotifications
     *
     * @return FindNotificationsResponse
     */
    private function createResponse(
        array $notifications,
        NotificationCounts $notificationCounts,
        array $notificationChannelByNotifications
    ): FindNotificationsResponse {
        $response = new FindNotificationsResponse();

        $notificationDtos = [];
        foreach ($notifications as $notification) {
            $notificationDto = new NotificationDto();
            $notificationDto->id = $notification->getId();
            $notificationDto->name = $notification->getName();
            $notificationDto->isActivated = $notification->isActivated();
            if (($usersCount = $notificationCounts->getUsersCountByNotificationId($notification->getId())) !== 0) {
                $notificationDto->usersCount = $usersCount;
            }
            $resourcesCounts = $notificationCounts->getResourcesCount();
            foreach ($resourcesCounts as $type => $resourcesCount) {
                $count = $resourcesCount[$notification->getId()] ?? 0;
                if ($count !== 0) {
                    $notificationDto->resources[] = [
                        'type' => $type,
                        'count' => $count,
                    ];
                }
            }
            $notificationDto->timeperiodId = $notification->getTimePeriod()->getId();
            $notificationDto->timeperiodName = $notification->getTimePeriod()->getName();
            $notificationDto->notificationChannels = $notificationChannelByNotifications[$notification->getId()];

            $notificationDtos[] = $notificationDto;
        }

        $response->notifications = $notificationDtos;

        return $response;
    }
}
