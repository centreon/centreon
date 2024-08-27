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

namespace Core\Notification\Application\UseCase\AddNotification;

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Contact\Interfaces\ContactRepositoryInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\CreatedResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Contact\Domain\Model\ContactGroup;
use Core\Notification\Application\Exception\NotificationException;
use Core\Notification\Application\Repository\NotificationResourceRepositoryProviderInterface;
use Core\Notification\Application\Repository\ReadNotificationRepositoryInterface;
use Core\Notification\Application\Repository\WriteNotificationRepositoryInterface;
use Core\Notification\Application\UseCase\AddNotification\Factory\NewNotificationFactory;
use Core\Notification\Application\UseCase\AddNotification\Factory\NotificationMessageFactory;
use Core\Notification\Application\UseCase\AddNotification\Factory\NotificationResourceFactory;
use Core\Notification\Application\UseCase\AddNotification\Validator\NotificationValidator;
use Core\Notification\Domain\Model\ConfigurationUser;
use Core\Notification\Domain\Model\Notification;
use Core\Notification\Domain\Model\NotificationMessage;
use Core\Notification\Domain\Model\NotificationResource;
use Core\Notification\Infrastructure\API\AddNotification\AddNotificationPresenter;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\TimePeriod\Application\Repository\ReadTimePeriodRepositoryInterface;

final class AddNotification
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadNotificationRepositoryInterface $readNotificationRepository,
        private readonly WriteNotificationRepositoryInterface $writeNotificationRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ContactRepositoryInterface $contactRepository,
        private readonly ReadContactGroupRepositoryInterface $contactGroupRepository,
        private readonly NotificationResourceRepositoryProviderInterface $resourceRepositoryProvider,
        private readonly DataStorageEngineInterface $dataStorageEngine,
        private readonly ContactInterface $user,
        private readonly ReadTimePeriodRepositoryInterface $readTimePeriodRepository,
        private readonly NotificationValidator $validator
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
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_NOTIFICATIONS_READ_WRITE)) {
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

            $this->validator->validateUsersAndContactGroups(
                $request->users,
                $request->contactGroups,
                $this->contactRepository,
                $this->contactGroupRepository,
                $this->user,
            );

            $this->validator->validateTimePeriod($request->timeperiodId, $this->readTimePeriodRepository);

            $notificationFactory = new NewNotificationFactory($this->readNotificationRepository);
            $newNotification = $notificationFactory->create(
                $request->name,
                $request->isActivated,
                $request->timeperiodId
            );

            $newMessages = NotificationMessageFactory::createMultipleMessage($request->messages);

            $notificationResourceFactory = new NotificationResourceFactory(
                $this->resourceRepositoryProvider,
                $this->readAccessGroupRepository,
                $this->user
            );
            $newResources = $notificationResourceFactory->createMultipleResource($request->resources);

            try {
                $this->dataStorageEngine->startTransaction();

                $newNotificationId = $this->writeNotificationRepository->add($newNotification);

                $this->writeNotificationRepository->addMessages($newNotificationId, $newMessages);
                $this->writeNotificationRepository->addUsers($newNotificationId, $request->users);
                $this->writeNotificationRepository->addContactGroups($newNotificationId, $request->contactGroups);
                $this->addResources($newNotificationId, $newResources);

                $this->dataStorageEngine->commitTransaction();
            } catch (\Throwable $ex) {
                $this->error("Rollback of 'Add Notification' transaction.");
                $this->dataStorageEngine->rollbackTransaction();

                throw $ex;
            }

            $createdNotificationInformation = $this->findCreatedNotificationInformation($newNotificationId);

            $presenter->present($this->createResponse(
                $createdNotificationInformation['notification'],
                $createdNotificationInformation['users'],
                $createdNotificationInformation['contactgroups'],
                $createdNotificationInformation['resources'],
                $createdNotificationInformation['messages']
            ));
        } catch (AssertionFailedException|\ValueError $ex) {
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (NotificationException $ex) {
            $presenter->setResponseStatus(
                match ($ex->getCode()) {
                        NotificationException::CODE_CONFLICT => new InvalidArgumentResponse($ex),
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
     * @param int $notificationId
     * @param NotificationResource[] $newResources
     *
     * @throws \Throwable
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
                $resources[$resource->getType()] = $resource;
            }
        }

        return $resources;
    }

    /**
     * @param Notification $notification
     * @param ConfigurationUser[] $users
     * @param ContactGroup[] $contactGroups
     * @param NotificationResource[] $resources
     * @param NotificationMessage[] $messages
     *
     * @return CreatedResponse<int,AddNotificationResponse>
     */
    private function createResponse(
        Notification $notification,
        array $users,
        array $contactGroups,
        array $resources,
        array $messages,
    ): CreatedResponse {
        $response = new AddNotificationResponse();

        $response->id = $notification->getId();
        $response->name = $notification->getName();
        $response->timeperiod = [
            'id' => $notification->getTimePeriod()->getId(),
            'name' => $notification->getTimePeriod()->getName(),
        ];
        $response->isActivated = $notification->isActivated();

        $response->messages = array_map(
            static fn(NotificationMessage $message): array => [
                'channel' => $message->getChannel()->value,
                'subject' => $message->getSubject(),
                'message' => $message->getRawMessage(),
                'formatted_message' => $message->getFormattedMessage(),
            ],
            $messages
        );

        $response->users = array_map(
            static fn(ConfigurationUser $user): array => ['id' => $user->getId(), 'name' => $user->getName()],
            $users
        );

        $response->contactGroups = array_map(
            static fn(ContactGroup $contactGroup): array => [
                'id' => $contactGroup->getId(),
                'name' => $contactGroup->getName(),
            ],
            $contactGroups
        );

        foreach ($resources as $resource) {
            $responseResource = [
                'type' => $resource->getType(),
                'events' => $resource->getType() === NotificationResource::HOSTGROUP_RESOURCE_TYPE
                    ? $response->convertHostEventsToBitFlags($resource->getEvents())
                    : $response->convertServiceEventsToBitFlags($resource->getEvents()),
                'ids' => array_map(
                    static fn($resource): array => ['id' => $resource->getId(), 'name' => $resource->getName()],
                    $resource->getResources()
                ),
            ];
            if (
                $resource->getType() === NotificationResource::HOSTGROUP_RESOURCE_TYPE
                && ! empty($resource->getServiceEvents())
            ) {
                $responseResource['extra'] = [
                    'event_services' => $response->convertServiceEventsToBitFlags($resource->getServiceEvents()),
                ];
            }
            $response->resources[] = $responseResource;
        }

        return new CreatedResponse($response->id, $response);
    }

    /**
     * Find freshly created notification information.
     *
     * @param int $newNotificationId
     *
     * @throws \Throwable|NotificationException
     *
     * @return array{
     *  notification: Notification,
     *  messages: NotificationMessage[],
     *  users: ConfigurationUser[],
     *  contactgroups: ContactGroup[],
     *  resources: NotificationResource[]
     * }
     */
    private function findCreatedNotificationInformation(int $newNotificationId): array
    {
        return [
            'notification' => $this->readNotificationRepository->findById($newNotificationId)
                ?? throw NotificationException::errorWhileRetrievingObject(),
            'messages' => $this->readNotificationRepository->findMessagesByNotificationId($newNotificationId),
            'users' => array_values($this->readNotificationRepository->findUsersByNotificationId($newNotificationId)),
            'contactgroups' => $this->readNotificationRepository->findContactGroupsByNotificationId($newNotificationId),
            'resources' => $this->findResourcesByNotificationId($newNotificationId),
        ];
    }
}
