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

namespace Core\Notification\Application\UseCase\UpdateNotification;

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Contact\Domain\Model\ContactGroup;
use Core\Notification\Application\Exception\NotificationException;
use Core\Notification\Application\Repository\NotificationResourceRepositoryInterface;
use Core\Notification\Application\Repository\NotificationResourceRepositoryProviderInterface;
use Core\Notification\Application\Repository\ReadNotificationRepositoryInterface;
use Core\Notification\Application\Repository\WriteNotificationRepositoryInterface;
use Core\Notification\Application\UseCase\UpdateNotification\Factory\NotificationFactory;
use Core\Notification\Application\UseCase\UpdateNotification\Factory\NotificationMessageFactory;
use Core\Notification\Application\UseCase\UpdateNotification\Factory\NotificationResourceFactory;
use Core\Notification\Application\UseCase\UpdateNotification\Validator\NotificationValidator;
use Core\Notification\Domain\Model\Message;
use Core\Notification\Domain\Model\Notification;
use Core\Notification\Domain\Model\NotificationResource;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

final class UpdateNotification
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadNotificationRepositoryInterface $readNotificationRepository,
        private readonly WriteNotificationRepositoryInterface $writeNotificationRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly NotificationResourceRepositoryProviderInterface $resourceRepositoryProvider,
        private readonly DataStorageEngineInterface $dataStorageEngine,
        private readonly NotificationValidator $notificationValidator,
        private readonly NotificationFactory $notificationFactory,
        private readonly NotificationResourceFactory $notificationResourceFactory,
        private readonly ContactInterface $user,
    ) {
    }

    public function __invoke(UpdateNotificationRequest $request, UpdateNotificationPresenterInterface $presenter): void
    {
        if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_NOTIFICATIONS_READ_WRITE)) {
            $this->error(
                "User doesn't have sufficient rights to add notifications",
                ['user_id' => $this->user->getId()]
            );
            $presenter->presentResponse(new ForbiddenResponse(NotificationException::updateNotAllowed()));

            return;
        }
        try {
            $this->info('Update notification', ['request' => $request]);
            if ($this->readNotificationRepository->exists($request->id) === false) {
                $presenter->presentResponse(new NotFoundResponse('Notification'));

                return;
            }

            $notification = $this->notificationFactory->create($request);
            $messages = NotificationMessageFactory::createMultipleMessage($request->messages);

            $resources = $this->notificationResourceFactory->createMultipleResource($request->resources);

            $this->notificationValidator->validateUsersAndContactGroups(
                $request->users,
                $request->contactGroups,
                $this->user
            );

            $this->updateNotificationConfiguration(
                $notification,
                $messages,
                $request->users,
                $request->contactGroups,
                $resources
            );
            $presenter->presentResponse(new NoContentResponse());
        } catch (NotificationException|AssertionFailedException|\ValueError $ex) {
            $this->error('Unable to update notification configuration', ['trace' => (string) $ex]);
            $presenter->presentResponse(
                new InvalidArgumentResponse($ex->getMessage())
            );
        } catch (\Throwable $ex) {
            $this->error('Unable to update notification configuration', ['trace' => (string) $ex]);
            $presenter->presentResponse(
                new ErrorResponse(_('Error while updating a notification configuration'))
            );
        }
    }

    /**
     * @param Notification $notification
     * @param Message[] $messages
     * @param int[] $users
     * @param int[] $contactGroups
     * @param NotificationResource[] $resources
     *
     * @throws \Throwable
     */
    private function updateNotificationConfiguration(
        Notification $notification,
        array $messages,
        array $users,
        array $contactGroups,
        array $resources
    ): void {
        try {
            $this->dataStorageEngine->startTransaction();

            $this->writeNotificationRepository->updateNotification($notification);
            $this->writeNotificationRepository->deleteNotificationMessages($notification->getId());
            $this->writeNotificationRepository->addMessagesToNotification($notification->getId(), $messages);
            $this->writeNotificationRepository->deleteUsersFromNotification($notification->getId());
            $this->writeNotificationRepository->addUsersToNotification($notification->getId(), $users);
            $this->updateResources($notification->getId(), $resources);
            $this->updateContactGroups($notification->getId(), $contactGroups);

            $this->dataStorageEngine->commitTransaction();
        } catch (\Throwable $ex) {
            $this->error("Rollback of 'Update Notification' transaction.");
            $this->dataStorageEngine->rollbackTransaction();

            throw $ex;
        }
    }

    /**
     * @param int $notificationId
     * @param NotificationResource[] $resources
     *
     * @throws \Throwable
     */
    private function updateResources(int $notificationId, array $resources): void
    {
        foreach ($this->resourceRepositoryProvider->getRepositories() as $repository) {
            if (! $this->user->isAdmin()) {
                $this->deleteResourcesForUserWithACL($repository, $notificationId);
            } else {
                $repository->deleteAllByNotification($notificationId);
            }
        }

        foreach ($resources as $resourceType => $resource) {
            $repository = $this->resourceRepositoryProvider->getRepository($resourceType);
            $repository->add($notificationId, $resource);
        }
    }

    /**
     * Update the Notification's Contact Groups with ACL Calculation.
     *
     * @param int $notificationId
     * @param int[] $contactGroups
     *
     * @throws \Throwable
     */
    private function updateContactGroups(int $notificationId, array $contactGroups): void
    {
        if (! $this->user->isAdmin()) {
            $this->deleteContactGroupsForUserWithACL($notificationId);
        } else {
            $this->writeNotificationRepository->deleteContactGroupsFromNotification($notificationId);
        }
        $this->writeNotificationRepository->addContactGroupsToNotification($notificationId, $contactGroups);
    }

    /**
     * Delete Resources for a user with ACL.
     *
     * @param NotificationResourceRepositoryInterface $repository
     * @param int $notificationId
     *
     * @throws \Throwable
     */
    private function deleteResourcesForUserWithACL(
        NotificationResourceRepositoryInterface $repository,
        int $notificationId
    ): void {
        $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
        $existingResources = $repository->findByNotificationIdAndAccessGroups($notificationId, $accessGroups);
        if ($existingResources !== null && ! empty($existingResources->getResources())) {
            $existingResourcesIds = [];
            foreach ($existingResources->getResources() as $existingResource) {
                $existingResourcesIds[] = $existingResource->getId();
            }
            $repository->deleteByNotificationIdAndResourcesId($notificationId, $existingResourcesIds);
        }
    }

    /**
     * Delete Contact Groups for a user with ACL.
     *
     * @param int $notificationId
     *
     * @throws \Throwable
     */
    private function deleteContactGroupsForUserWithACL(int $notificationId): void
    {
        $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
        $contactGroups = $this->readNotificationRepository->findContactGroupsByNotificationIdAndAccessGroups(
            $notificationId,
            $this->user,
            $accessGroups
        );
        if ($contactGroups !== []) {
            $contactGroupsIds = array_map(
                fn (ContactGroup $contactGroup): int => $contactGroup->getId(), $contactGroups
            );
            $this->writeNotificationRepository->deleteContactGroupsByNotificationAndContactGroupIds(
                $notificationId,
                $contactGroupsIds
            );
        }
    }
}
