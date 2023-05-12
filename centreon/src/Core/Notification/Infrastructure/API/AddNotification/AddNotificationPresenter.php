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

namespace Core\Notification\Infrastructure\API\AddNotification;

use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\CreatedResponse;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Notification\Application\Converter\NotificationHostEventConverter;
use Core\Notification\Application\Converter\NotificationServiceEventConverter;
use Core\Notification\Application\UseCase\AddNotification\AddNotificationResponse;
use Core\Notification\Domain\Model\NotificationResource;

class AddNotificationPresenter extends AbstractPresenter
{
    use LoggerTrait;

    public function __construct(
        PresenterFormatterInterface $presenterFormatter,
    ) {
        parent::__construct($presenterFormatter);
    }

    /**
     * @inheritDoc
     */
    public function present(mixed $data): void
    {
        if (
            $data instanceof CreatedResponse
            && $data->getPayload() instanceof AddNotificationResponse
        ) {
            $payload = $data->getPayload();
            $resources = [];
            foreach ($payload->resources as $index => $resource) {
                $eventEnumConverter = $resource['type'] === NotificationResource::HOSTGROUP_RESOURCE_TYPE
                    ? NotificationHostEventConverter::class
                    : NotificationServiceEventConverter::class;
                $resources[$index]['type'] = $resource['type'];
                $resources[$index]['events'] = $eventEnumConverter::toBitFlag($resource['events']);
                $resources[$index]['ids'] = $resource['ids'];
                if (! empty($resource['extra']['event_services'])
                ) {
                    $resources[$index]['extra']['event_services'] = NotificationServiceEventConverter::toBitFlag(
                        $resource['extra']['event_services']
                    );
                }
            }
            $data->setPayload([
                'id' => $payload->id,
                'name' => $payload->name,
                'timeperiod' => $payload->timeperiod,
                'users' => $payload->users,
                'resources' => $resources,
                'messages' => $payload->messages,
                'is_activated' => $payload->isActivated,
            ]);
        }
        parent::present($data);
    }
}
