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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service;

use ApiPlatform\Metadata\ApiProperty;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\EnumResolver\ServiceNotificationOptionEnumResolver;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\ContactGroup\ContactGroupResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\NotificationContact\NotificationContactResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\TimePeriod\TimePeriodResource;
use App\Shared\Domain\Aggregate\TriStateEnum;

/**
 * The `notifications` sub-object of a service. Left out on a Cloud platform.
 */
final readonly class ServiceNotificationsOutput
{
    /**
     * @param list<NotificationContactResource> $contacts
     * @param list<ContactGroupResource> $contactGroups
     * @param list<string> $options
     */
    public function __construct(
        #[ApiProperty(openapiContext: ['enum' => [TriStateEnum::False->value, TriStateEnum::True->value, TriStateEnum::UseDefault->value]])]
        public string $enabled,
        public bool $useOnlyContactsFromHost,
        public bool $contactAdditiveInheritance,
        public bool $contactGroupAdditiveInheritance,
        public array $contacts,
        public array $contactGroups,
        public ?int $interval,
        #[ApiProperty(readableLink: true)]
        public ?TimePeriodResource $timeperiod,
        #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ServiceNotificationOptionEnumResolver::API_VALUES]])]
        public array $options,
        public ?int $firstDelay,
        public ?int $recoveryDelay,
    ) {
    }
}
