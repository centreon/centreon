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

namespace Core\Notification\Infrastructure\API\FindNotifications;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Notification\Application\UseCase\FindNotifications\FindNotificationsPresenterInterface;
use Core\Notification\Application\UseCase\FindNotifications\FindNotificationsResponse;
use Core\Notification\Domain\Model\NotificationChannel;

class FindNotificationsPresenter extends AbstractPresenter implements FindNotificationsPresenterInterface
{
    public function __construct(
        private readonly RequestParametersInterface $requestParameters,
        protected PresenterFormatterInterface $presenterFormatter
    ) {
        parent::__construct($presenterFormatter);
    }

    /**
     * @param FindNotificationsResponse|ResponseStatusInterface $response
     */
    public function presentResponse(FindNotificationsResponse|ResponseStatusInterface $response): void
    {
        if ($response instanceof ResponseStatusInterface) {
            $this->setResponseStatus($response);
        } else {
            $this->present(
                [
                    'result' => array_map(static fn($notificationDto) => [
                        'id' => $notificationDto->id,
                        'is_activated' => $notificationDto->isActivated,
                        'name' => $notificationDto->name,
                        'user_count' => $notificationDto->usersCount,
                        'channels' => self::convertNotificationChannelToString(
                            $notificationDto->notificationChannels
                        ),
                        'resources' => $notificationDto->resources,
                        'timeperiod' => [
                            'id' => $notificationDto->timeperiodId,
                            'name' => $notificationDto->timeperiodName,
                        ],
                    ], $response->notifications),
                    'meta' => $this->requestParameters->toArray(),
                ]
            );
        }
    }

    /**
     * Convert NotificationChannel Enum values to string values.
     *
     * @param NotificationChannel[] $notificationChannels
     *
     * @return string[]
     */
    private static function convertNotificationChannelToString(array $notificationChannels): array
    {
        $notificationChannelsToString = [];
        foreach ($notificationChannels as $notificationChannel) {
            $notificationChannelsToString[] = $notificationChannel->value;
        }

        return $notificationChannelsToString;
    }
}
