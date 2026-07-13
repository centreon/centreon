<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace App\Monitoring\Infrastructure\ApiPlatform\State;

use App\Monitoring\Domain\Aggregate\HostGroup\HostGroup;
use App\Monitoring\Domain\Aggregate\Notification\Notification;
use App\Monitoring\Domain\Aggregate\ServiceGroup\ServiceGroup;
use App\Monitoring\Domain\Aggregate\User\User;
use Core\Domain\Configuration\UserGroup\Model\UserGroup;

/**
 * Aggregates every domain object required to build a {@see NotificationResource}.
 *
 * New aggregates (resources, contact groups, ...) are added as extra typed
 * properties here, without changing the {@see TransformerInterface} signature.
 */
final readonly class NotificationResourceInput
{
    /**
     * @param list<User> $users
     * @param list<UserGroup> $userGroups
     * @param list<HostGroup> $hostGroups
     * @param list<ServiceGroup> $serviceGroups
     */
    public function __construct(
        public Notification $notification,
        public array $users,
        public array $userGroups,
        public array $hostGroups,
        public array $serviceGroups,
    ) {
    }
}
