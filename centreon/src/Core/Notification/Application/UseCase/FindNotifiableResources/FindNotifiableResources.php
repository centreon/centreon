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

namespace Core\Notification\Application\UseCase\FindNotifiableResources;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\{ErrorResponse, ForbiddenResponse, NotModifiedResponse};
use Core\Notification\Application\Converter\{NotificationHostEventConverter, NotificationServiceEventConverter};
use Core\Notification\Application\Exception\NotificationException;
use Core\Notification\Application\Repository\ReadNotifiableResourceRepositoryInterface;
use Core\Notification\Domain\Model\NotifiableResource;

final class FindNotifiableResources
{
    use LoggerTrait;

    /**
     * @param ContactInterface $contact
     * @param ReadNotifiableResourceRepositoryInterface $readRepository
     */
    public function __construct(
        private readonly ContactInterface $contact,
        private readonly ReadNotifiableResourceRepositoryInterface $readRepository
    ) {
    }

    /**
     * @param FindNotifiableResourcesPresenterInterface $presenter
     * @param string $requestUid
     *
     * @throws \Throwable
     */
    public function __invoke(FindNotifiableResourcesPresenterInterface $presenter, string $requestUid): void
    {
        try {
            if ($this->contact->isAdmin()) {
                $this->info('Retrieving all notifiable resources');
                $responseDto = $this->createResponseDto($this->readRepository->findAllForActivatedNotifications());
                $responseJson = \json_encode($responseDto, JSON_THROW_ON_ERROR);
                $calculatedUid = \hash('md5', $responseJson);
                if ($calculatedUid === $requestUid) {
                    $response = new NotModifiedResponse();
                } else {
                    $responseDto->uid = $calculatedUid;
                    $response = $responseDto;
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
     * @param NotifiableResource[] $notifiableResources
     *
     * @return FindNotifiableResourcesResponse
     */
    private function createResponseDto(iterable $notifiableResources): FindNotifiableResourcesResponse
    {
        $responseDto = new FindNotifiableResourcesResponse();
        foreach ($notifiableResources as $notifiableResource) {
            $notifiableResourceDto = new NotifiableResourceDto();
            $notifiableResourceDto->notificationId = $notifiableResource->getNotificationId();
            foreach ($notifiableResource->getHosts() as $notificationHost) {
                $notificationHostDto = new NotifiableHostDto();
                $notificationHostDto->id = $notificationHost->getId();
                $notificationHostDto->name = $notificationHost->getName();
                $notificationHostDto->alias = $notificationHost->getAlias();
                if ([] !== $notificationHost->getEvents()) {
                    $notificationHostDto->events = NotificationHostEventConverter::toBitFlags(
                        $notificationHost->getEvents()
                    );
                }
                foreach ($notificationHost->getServices() as $notificationService) {
                    $notificationServiceDto = new NotifiableServiceDto();
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
