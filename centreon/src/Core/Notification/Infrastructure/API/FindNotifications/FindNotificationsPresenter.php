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

use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Notification\Application\UseCase\FindNotifications\FindNotificationsResponse;
use Core\Notification\Application\UseCase\FindNotifications\FindNotificationsPresenterInterface;

class FindNotificationsPresenter extends AbstractPresenter implements FindNotificationsPresenterInterface
{
    public function __construct(
        private readonly RequestParametersInterface $requestParameters,
        protected PresenterFormatterInterface $presenterFormatter
    ) {
        parent::__construct($presenterFormatter);
    }

    public function presentResponse(FindNotificationsResponse|ResponseStatusInterface $response): void
    {
        if ($response instanceof ResponseStatusInterface) {
            $this->setResponseStatus($response);
        } else {
            $this->present(
                [
                    "result" => array_map(static function ($notificationDto) {
                        return [
                            "id" => $notificationDto->id,
                            "name" => $notificationDto->name,
                            "users" => $notificationDto->usersCount,
                            "is_activated" => $notificationDto->isActivated,
                            "channels" => self::convertNotificationChannelToString(
                                $notificationDto->notificationChannels
                            ),
                            "resources" => $notificationDto->resources,
                            "timeperiod" => $notificationDto->timeperiod
                        ];
                    }, $response->notifications),
                    "meta" => $this->requestParameters->toArray()
                ]
            );
        }
    }

    /**
     * Convert NotificationChannel Enum values to string values
     *
     * @param NotificationChannel[] $notificationChannels
     * @return string
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
