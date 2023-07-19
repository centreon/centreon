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
use Core\Notification\Application\Exception\NotificationException;
use Core\Notification\Application\Repository\ReadNotificationRepositoryInterface;

final class FindNotificationsResources
{
    use LoggerTrait;

    public function __construct(
        private readonly ContactInterface $contact,
        private readonly ReadNotificationRepositoryInterface $notificationRepository
    ) {
    }

    public function __invoke(FindNotificationsResourcesPresenterInterface $presenter, string $requestUID): void
    {
        try {
            if ($this->contact->isAdmin()) {
                $notifiableResources = $this->notificationRepository->findNotifiableResourcesForActivatedNotifications();
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
}
