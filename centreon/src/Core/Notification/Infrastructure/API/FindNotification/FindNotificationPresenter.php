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

namespace Core\Notification\Infrastructure\API\FindNotification;

use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Notification\Application\Converter\NotificationHostEventConverter;
use Core\Notification\Application\Converter\NotificationServiceEventConverter;
use Core\Notification\Application\UseCase\FindNotification\FindNotificationPresenterInterface;
use Core\Notification\Application\UseCase\FindNotification\FindNotificationResponse;
use Core\Notification\Domain\Model\NotificationResource;

class FindNotificationPresenter extends AbstractPresenter implements FindNotificationPresenterInterface
{
    public function __construct(
        protected PresenterFormatterInterface $presenterFormatter
    ) {
        parent::__construct($presenterFormatter);
    }

    public function presentResponse(FindNotificationResponse|ResponseStatusInterface $response): void
    {
        if ($response instanceof ResponseStatusInterface) {
            $this->setResponseStatus($response);
        } else {
            $this->present([
                'id' => $response->id,
                'name' => $response->name,
                'timeperiod' => [
                    'id' => $response->timeperiodId,
                    'name' => $response->timeperiodName,
                ],
                'is_activated' => $response->isActivated,
                'messages' => $response->messages,
                'users' => $response->users,
                'resources' => $this->formatResource($response->resources),
            ]);
        }
    }

    private function formatResource(array $resources) {
        foreach ($resources as $index => $resource) {
            $resources[$index]['events'] = $resource["type"] === NotificationResource::HOSTGROUP_RESOURCE_TYPE
                ? NotificationHostEventConverter::toBitFlags($resource['events'])
                : NotificationServiceEventConverter::toBitFlags($resource['events']);

            if (array_key_exists('extra', $resource)) {
                $resource[$index]['extra']['service_events'] = NotificationServiceEventConverter::toBitFlags(
                    $resource['extra']['service_events']
                );
            }

        }

        return $resources;
    }
}
