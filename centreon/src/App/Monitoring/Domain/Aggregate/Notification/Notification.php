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

namespace App\Monitoring\Domain\Aggregate\Notification;

use App\Monitoring\Domain\Aggregate\HostGroup\HostGroup;
use App\Monitoring\Domain\Aggregate\Notification\Message\Message;
use App\Monitoring\Domain\Aggregate\ServiceGroup\ServiceGroup;
use App\Monitoring\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\Monitoring\Domain\Aggregate\User\UserId;
use App\Monitoring\Domain\Aggregate\UserGroup\UserGroupId;
use App\Shared\Domain\Aggregate\AggregateRoot;

/**
 * @extends AggregateRoot<NotificationId>
 */
final class Notification extends AggregateRoot
{
    /**
     * @param list<UserId> $userIds
     * @param list<UserGroupId> $userGroupIds
     * @param list<Message> $messages
     * @param list<HostEventEnum> $hostGroupEvents
     * @param list<ServiceEventEnum> $serviceGroupEvents
     * @param list<ServiceEventEnum> $serviceEvents
     * @param list<HostGroup> $hostGroups
     * @param list<ServiceGroup> $serviceGroups
     */
    public function __construct(
        ?NotificationId $id,
        public readonly NotificationName $name,
        public readonly bool $isActivated,
        public readonly TimePeriodId $timePeriod,
        public readonly array $userIds,
        public readonly array $messages,
        public readonly array $userGroupIds,
        public readonly array $hostGroupEvents,
        public readonly array $serviceGroupEvents,
        public readonly array $serviceEvents,
        public readonly array $hostGroups,
        public readonly array $serviceGroups,
    ) {
        parent::__construct($id);
    }
}
