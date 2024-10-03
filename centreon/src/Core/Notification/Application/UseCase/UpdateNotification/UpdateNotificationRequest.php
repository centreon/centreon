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

namespace Core\Notification\Application\UseCase\UpdateNotification;

final class UpdateNotificationRequest
{
    public int $id = 0;

    public string $name = '';

    public int $timeperiodId = 1;

    /**
     * User IDs.
     *
     * @var int[]
     */
    public array $users = [];

    /**
     * Contact Groups IDs.
     *
     * @var int[]
     */
    public array $contactGroups = [];

    /**
     * @var array<array{
     *    type:string,
     *    ids:int[],
     *    events:int,
     *    includeServiceEvents:int
     * }> $resources
     */
    public array $resources = [];

    /** @var array<array{
     *     channel:string,
     *     subject:string,
     *     message:string,
     *     formatted_message:string
     * }> $messages
     */
    public array $messages = [];

    public bool $isActivated = true;
}