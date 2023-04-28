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

namespace Core\Notification\Application\UseCase\AddNotification;

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Contact\Interfaces\ContactRepositoryInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\CreatedResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\Common\Domain\NotificationServiceEvent;
use Core\Common\Domain\TrimmedString;
use Core\Notification\Application\Exception\NotificationException;
use Core\Notification\Application\Repository\NotificationResourceRepositoryProviderInterface;
use Core\Notification\Application\Repository\ReadNotificationRepositoryInterface;
use Core\Notification\Application\Repository\WriteNotificationRepositoryInterface;
use Core\Notification\Domain\Model\NewNotification;
use Core\Notification\Domain\Model\Notification;
use Core\Notification\Domain\Model\NotificationChannel;
use Core\Notification\Domain\Model\NotificationGenericObject;
use Core\Notification\Domain\Model\NotificationMessage;
use Core\Notification\Domain\Model\NotificationResource;
use Core\Notification\Infrastructure\API\AddNotification\AddNotificationPresenter;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\TimePeriod\Application\Repository\ReadTimePeriodRepositoryInterface;
use Core\TimePeriod\Domain\Model\TimePeriod;

final class AddNotification
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadNotificationRepositoryInterface $readNotificationRepository,
        private readonly WriteNotificationRepositoryInterface $writeNotificationRepository,
        private readonly ReadTimePeriodRepositoryInterface $readTimePeriodRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ContactRepositoryInterface $contactRepository,
        private readonly NotificationResourceRepositoryProviderInterface $resourceRepositoryProvider,
        private readonly DataStorageEngineInterface $dataStorageEngine,
        private readonly ContactInterface $user,
    ) {
    }

    /**
     * @param AddNotificationRequest $request
     * @param AddNotificationPresenter $presenter
     */
    public function __invoke(
        AddNotificationRequest $request,
        PresenterInterface $presenter
    ): void {
        try {
            // TODO topology role created in front code,
            // TODO how to handle feature flag in role const definition ?
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_NOTIFICATION_READ_WRITE))
            {
                $this->error(
                    "User doesn't have sufficient rights to add notifications",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(NotificationException::addNotAllowed())
                );

                return;
            }
            $this->info('Add notification', ['request' => $request]);

            $newNotification = $this->createMainObject($request);

            $newMessages = $this->createMessageObjects($request);

            $newResources = $this->createResourceObjects($request);

            $this->assertUsersValidity($request);

            try {
                $this->dataStorageEngine->startTransaction();

                $newNotificationId = $this->writeNotificationRepository->add($newNotification);

                $this->writeNotificationRepository->addMessages($newNotificationId, $newMessages);
                $this->writeNotificationRepository->addUsers($newNotificationId, $request->users);
                $this->addResources($newNotificationId, $newResources);

                $this->dataStorageEngine->commitTransaction();
            } catch (\Throwable $ex) {
                $this->error("Rollback of 'Add Notification' transaction.");
                $this->dataStorageEngine->rollbackTransaction();

                throw $ex;
            }

            $notification = $this->readNotificationRepository->findById($newNotificationId)
                ?? throw NotificationException::errorWhileRetrievingObject();
            $messages = $this->readNotificationRepository->findMessagesByNotificationId($newNotificationId);
            $users = $this->readNotificationRepository->findUsersByNotificationId($newNotificationId);
            $resources = $this->findResourcesByNotificationId($newNotificationId);

            $presenter->present($this->createResponse($notification, $users, $resources, $messages));

        } catch (AssertionFailedException|\ValueError $ex) {
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (NotificationException $ex) {
            $presenter->setResponseStatus(
                match ($ex->getCode()) {
                    NotificationException::CODE_CONFLICT => new ConflictResponse($ex),
                    default => new ErrorResponse($ex),
                }
            );
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(new ErrorResponse(NotificationException::addNotification()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @param AddNotificationRequest $request
     *
     * @throws NotificationException
     * @throws \Throwable
     */
    private function assertNameDoesNotAlreadyExists(AddNotificationRequest $request): void
    {
        if ($this->readNotificationRepository->existsByName(new TrimmedString($request->name))) {
            $this->error('Notification name already exists', ['name' => $request->name]);

            throw NotificationException::nameAlreadyExists();
        }
    }

    /**
     * @param AddNotificationRequest $request
     *
     * @throws NotificationException
     * @throws \Throwable
     */
    private function assertTimePeriodExist(AddNotificationRequest $request): void
    {
        if (! $this->readTimePeriodRepository->exists($request->timeperiodId)) {
            $this->error(
                'Invalid ID provided',
                ['propertieName' => 'timeperiodId', 'propertieValue' => $request->timeperiodId]
            );

            throw NotificationException::invalidId('timeperiod');
        }
    }

    /**
     * @param AddNotificationRequest $request
     *
     * @throws NotificationException
     * @throws \Throwable
     */
    private function assertUsersValidity(AddNotificationRequest $request): void
    {
        $request->users = array_unique($request->users);
        if ($request->users === []) {
            throw NotificationException::emptyArrayNotAllowed('user');
        }

        // TODO change for BasicDifference (MON-18366) when merged
        $existingUsers = $this->contactRepository->exist($request->users);
        $missingUsers = array_diff($request->users, $existingUsers);

        if ([] !== $missingUsers) {
            $this->error(
                'Invalid ID(s) provided',
                ['propertieName' => 'users', 'propertieValues' => array_values($missingUsers)]
            );

            throw NotificationException::invalidId('users');
        }
    }

    /**
     * @param AddNotificationRequest $request
     *
     * @throws \Throwable
     *
     * @return NewNotification
     */
    private function createMainObject(AddNotificationRequest $request): NewNotification
    {
        $this->assertNameDoesNotAlreadyExists($request);

        // NOTE: Timeperiod is forced to '24x7' for the moment
        $request->timeperiodId = 1;
        $this->assertTimePeriodExist($request);

        return new NewNotification(
            $request->name,
            new NotificationGenericObject($request->timeperiodId, ''),
            $request->isActivated
        );
    }

    /**
     * @param AddNotificationRequest $request
     *
     * @throws \Throwable
     *
     * @return NotificationMessage[]
     */
    private function createMessageObjects(AddNotificationRequest $request): array
    {
        if ($request->messages === []) {
            throw NotificationException::emptyArrayNotAllowed('message');
        }

        $newMessages = [];
        foreach ($request->messages as $message) {
            $messageType = NotificationChannel::from($message['channel']);

            // If multiple message with same type are defined, only the last one of each type is kept
            $newMessages[$messageType->value] = new NotificationMessage(
                $messageType,
                $message['subject'],
                $message['message']
            );
        }

        return $newMessages;
    }

    /**
     * @param AddNotificationRequest $request
     *
     * @throws \Throwable
     *
     * @return NotificationResource[]
     */
    private function createResourceObjects(AddNotificationRequest $request): array
    {
        if ($request->resources === []) {
            throw NotificationException::emptyArrayNotAllowed('resource');
        }

        $newResources = [];
        /** @var array{
         *      type:string,
         *      events:int,
         *      ids:int[],
         *      includeServiceEvents:int
         * } $resourceData
         */
        foreach ($request->resources as $resourceData) {
            $resourceIds = array_unique($resourceData['ids']);

            if (count($resourceIds) === 0) {
                continue;
            }

            $repository = $this->resourceRepositoryProvider->getRepository($resourceData['type']);

            if ($this->user->isAdmin()) {
                // Assert IDs validity without ACLs
                $existingResources = $repository->exist($resourceIds);
            } else {
                // Assert IDs validity with ACLs
                $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
                $existingResources = $repository->existByAccessGroups($resourceIds, $accessGroups);
            }

            // TODO change for BasicDifference (MON-18366) when merged
            $missingResources = array_diff($resourceIds, $existingResources);
            if ([] !== $missingResources) {
                $this->error(
                    'Invalid ID(s) provided',
                    ['propertieName' => 'resources', 'propertieValues' => array_values($missingResources)]
                );

                throw NotificationException::invalidId('resource.ids');
            }

            // If multiple resources with same type are defined, only the last one of each type is kept
            $newResources[$repository->resourceType()] = new NotificationResource(
                $repository->resourceType(),
                $repository->eventEnum(),
                array_map((fn($resourceId) => new NotificationGenericObject($resourceId, '')), $resourceIds),
                ($repository->eventEnum())::fromBitmask($resourceData['events']),
                $resourceData['includeServiceEvents']
                    ? NotificationServiceEvent::fromBitmask($resourceData['includeServiceEvents'])
                    : []
            );
        }

        $totalResources = 0;
        foreach ($newResources as $newResource) {
            $totalResources += $newResource->getResourcesCount();
        }
        if ($totalResources <= 0) {
            throw NotificationException::emptyArrayNotAllowed('resource.ids');
        }

        return $newResources;
    }

    /**
     * @param int $notificationId
     * @param NotificationResource[] $newResources
     */
    private function addResources(int $notificationId, array $newResources): void
    {
        foreach ($newResources as $resourceType => $newResource) {
            $repository = $this->resourceRepositoryProvider->getRepository($resourceType);

            $repository->add($notificationId, $newResource);
        }
    }

    /**
     * @param int $notificationId
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
                $resources[$resource->getType()] = $resource;
            }
        }

        return $resources;
    }

    /**
     * @param Notification $notification
     * @param NotificationGenericObject[] $users
     * @param NotificationResource[] $resources
     * @param NotificationMessage[] $messages
     *
     * @return CreatedResponse<int,AddNotificationResponse>
     */
    private function createResponse(
        Notification $notification,
        array $users,
        array $resources,
        array $messages,
    ): CreatedResponse
    {
        $response = new AddNotificationResponse();

        $response->id = $notification->getId();
        $response->name = $notification->getName();
        $response->timeperiod = [
            'id' => $notification->getTimePeriod()->getId(),
            'name' => $notification->getTimePeriod()->getName(),
        ];
        $response->isActivated = $notification->isActivated();

        $response->messages = array_map(
            (fn($message) => [
                'channel' => $message->getChannel()->value,
                'subject' => $message->getSubject(),
                'message' => $message->getMessage(),
            ]),
            $messages
        );

        $response->users = array_map(
            (fn($user) => ['id' => $user->getId(), 'name' => $user->getName()]),
            $users
        );

        foreach ($resources as $resource) {
            $eventEnum = $this->resourceRepositoryProvider->getRepository($resource->getType())->eventEnum();
            $responseResource = [
                'type' => $resource->getType(),
                'events' => $eventEnum::toBitmask($resource->getEvents()),
                'ids' => array_map(
                    (fn($resource) => ['id' => $resource->getId(), 'name' => $resource->getName()]),
                    $resource->getResources()
                ),
            ];
            if ($resource->getServiceEvents() !== 0)
            {
                $responseResource['extra'] = [
                    'event_services' => NotificationServiceEvent::toBitmask($resource->getServiceEvents())
                ];
            }
            $response->resources[] = $responseResource;
        }

        return new CreatedResponse($response->id, $response);
    }
}
