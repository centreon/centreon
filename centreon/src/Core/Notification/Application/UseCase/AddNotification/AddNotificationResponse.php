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

use Core\Notification\Application\Converter\NotificationHostEventConverter;
use Core\Notification\Application\Converter\NotificationServiceEventConverter;
use Core\Notification\Domain\Model\HostEvent;
use Core\Notification\Domain\Model\ServiceEvent;

final class AddNotificationResponse
{
    public int $id = 0;

    public string $name = '';

    /**
     * @var array{
     *         id:int,
     *         name:string,
     *     } $timeperiod
     */
    public array $timeperiod = ['id' => 0, 'name' => ''];

    public bool $isActivated = true;

    /**
     * @var array<array{
     *         id:int,
     *         name:string,
     *     }> $users
     */
    public array $users = [];

    /**
     * @var array<array{
     *         id:int,
     *         name:string,
     *     }> $contactGroups
     */
    public array $contactGroups = [];

    /**
     * @var array<array{
     *         type:string,
     *         ids:array<array{id:int,name:string}>,
     *         events:int,
     *         extra?:array{events_services?: int}
     *     }> $resources
     */
    public array $resources = [];

    /**
     * @var array<array{
     *         channel:string,
     *         subject:string,
     *         message:string,
     *         formatted_message:string
     *     }> $messages
     */
    public array $messages = [];

    /**
     * @param HostEvent[]|ServiceEvent[] $enums
     *
     * @return int
     */
    public function convertHostEventsToBitFlags(array $enums): int
    {
        /**
         * @var HostEvent[] $enums
         */
        return NotificationHostEventConverter::toBitFlags($enums);
    }

    /**
     * @param ServiceEvent[]|HostEvent[] $enums
     *
     * @return int
     */
    public function convertServiceEventsToBitFlags(array $enums): int
    {
        /**
         * @var ServiceEvent[] $enums
         */
        return NotificationServiceEventConverter::toBitFlags($enums);
    }
}
