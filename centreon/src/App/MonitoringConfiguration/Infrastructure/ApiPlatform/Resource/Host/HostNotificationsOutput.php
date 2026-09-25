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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host;

use ApiPlatform\Metadata\ApiProperty;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\ContactGroup\ContactGroupResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\NotificationContact\NotificationContactResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\TimePeriod\TimePeriodResource;

final readonly class HostNotificationsOutput
{
    /**
     * @param list<NotificationContactResource> $contacts
     * @param list<ContactGroupResource> $contactGroups
     * @param list<string> $options {@see \App\MonitoringConfiguration\Domain\Aggregate\Host\NotificationOptionEnum}
     */
    public function __construct(
        public string $enabled,
        public array $contacts,
        public array $contactGroups,
        public array $options,
        public ?int $interval,
        #[ApiProperty(readableLink: true)]
        public ?TimePeriodResource $timeperiod,
        public ?int $firstDelay,
        public ?int $recoveryDelay,
        public bool $contactAdditiveInheritance,
        public bool $contactGroupAdditiveInheritance,
    ) {
    }
}
