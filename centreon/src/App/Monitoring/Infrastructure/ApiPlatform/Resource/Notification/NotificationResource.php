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

namespace App\Monitoring\Infrastructure\ApiPlatform\Resource\Notification;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Monitoring\Domain\Security\NotificationPermissionEnum;
use App\Monitoring\Infrastructure\ApiPlatform\State\FindNotificationProvider;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    shortName: 'Notification',
    operations: [
        new Get(
            uriTemplate: '/configuration/notifications/{id}',
            security: "is_granted('" . NotificationPermissionEnum::CanReadAndWriteNotifications->value . "')",
            securityMessage: "User doesn't have sufficient rights to get notification",
            provider: FindNotificationProvider::class,
        ),
    ]
)]
final class NotificationResource
{
    /**
     * @param list<MessageDto> $messages
     * @param list<UserDto> $users
     * @param list<ContactGroupDto> $contactGroups
     * @param list<ResourceDto> $resources
     */
    public function __construct(
        public int $id,
        public string $name,
        #[SerializedName('timeperiod')]
        #[ApiProperty(genId: false)]
        public TimePeriodDto $timePeriod,
        public bool $isActivated,
        public array $messages,
        public array $users,
        #[SerializedName('contactgroups')]
        public array $contactGroups,
        public array $resources,
    ) {
    }
}
