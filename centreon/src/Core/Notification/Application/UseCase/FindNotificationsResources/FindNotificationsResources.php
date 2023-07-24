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

namespace Core\Notification\Application\UseCase\FindNotificationsResources;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\NotModifiedResponse;
use Core\Notification\Application\Converter\NotificationHostEventConverter;
use Core\Notification\Application\Converter\NotificationServiceEventConverter;
use Core\Notification\Application\Exception\NotificationException;
use Core\Notification\Application\Repository\ReadNotifiableResourceRepositoryInterface;
use Core\Notification\Application\Repository\ReadNotificationRepositoryInterface;

final class FindNotificationsResources
{
    use LoggerTrait;

    /**
     * @param ContactInterface $contact
     * @param ReadNotificationRepositoryInterface $notificationRepository
     */
    public function __construct(
        private readonly ContactInterface $contact,
        private readonly ReadNotifiableResourceRepositoryInterface $readRepository
        // private readonly ReadNotificationRepositoryInterface $notificationRepository
    ) {
    }

    /**
     * @param FindNotificationsResourcesPresenterInterface $presenter
     * @param string $requestUID
     *
     * @throws \Throwable
     */
    public function __invoke(FindNotificationsResourcesPresenterInterface $presenter, string $requestUid): void
    {
        try {
            if ($this->contact->isAdmin()) {
                // $notifiableResources = $this->notificationRepository
                //     ->findNotifiableResourcesForActivatedNotifications();

                $notifiableResources = $this->readRepository->findAllForActivatedNotifications();

                if ([] === $notifiableResources) {
                    $response = new NotFoundResponse('Notifiable resources');
                } else {
                    $responseJson = \json_encode($notifiableResources, JSON_THROW_ON_ERROR);
                    $calculatedUid = \hash('md5', $responseJson);
                    if ($calculatedUid === $requestUid) {
                        $response = new NotModifiedResponse();
                    } else {
                        $response = $this->createResponseDto($notifiableResources, $calculatedUid);
                    }
                }
            } else {
                $this->error(
                    "User doesn't have sufficient rights to list notification resources",
                    ['user_id' => $this->contact->getId()]
                );
                $response = new ForbiddenResponse(NotificationException::listResourcesNotAllowed());
            }
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $response = new ErrorResponse(NotificationException::errorWhileListingResources());
        }

        $presenter->presentResponse($response);
    }

    /**
     * @param array $notifiableResources
     * @param string $calculatedUid
     *
     * @return FindNotificationsResourcesResponse
     */
    private function createResponseDto(
        array $notifiableResources,
        string $calculatedUid
    ): FindNotificationsResourcesResponse {
        $responseDto = new FindNotificationsResourcesResponse();
        $responseDto->uid = $calculatedUid;
        foreach ($notifiableResources as $notifiableResource) {
            $notifiableResourceDto = new NotifiableResourceDto();
            $notifiableResourceDto->notificationId = $notifiableResource->getNotificationId();
            foreach ($notifiableResource->getHosts() as $notificationHost) {
                $notificationHostDto = new NotificationHostDto();
                $notificationHostDto->id = $notificationHost->getId();
                $notificationHostDto->name = $notificationHost->getName();
                $notificationHostDto->alias = $notificationHost->getAlias();
                if ([] !== $notificationHost->getEvents()) {
                    $notificationHostDto->events = NotificationHostEventConverter::toBitFlags(
                        $notificationHost->getEvents()
                    );
                }
                foreach ($notificationHost->getServices() as $notificationService) {
                    $notificationServiceDto = new NotificationServiceDto();
                    $notificationServiceDto->id = $notificationService->getId();
                    $notificationServiceDto->name = $notificationService->getName();
                    $notificationServiceDto->alias = $notificationService->getAlias();
                    if ([] !== $notificationService->getEvents()) {
                        $notificationServiceDto->events = NotificationServiceEventConverter::toBitFlags(
                            $notificationService->getEvents()
                        );
                    }
                    $notificationHostDto->services[] = $notificationServiceDto;
                }
                $notifiableResourceDto->hosts[] = $notificationHostDto;
            }
            $responseDto->notifiableResources[] = $notifiableResourceDto;
        }

        return $responseDto;
    }
}
