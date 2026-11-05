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

namespace App\MonitoringConfiguration\Domain\Aggregate\HostTemplate;

use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Collection;

final readonly class HostTemplateNotifications
{
    /** @var list<HostTemplateNotificationOptionEnum> */
    public array $options;

    /**
     * @param Collection<NotificationContactId> $contactIds
     * @param Collection<ContactGroupId> $contactGroupIds
     * @param array<int, HostTemplateNotificationOptionEnum> $options
     */
    public function __construct(
        public TriStateEnum $enabled = TriStateEnum::UseDefault,
        public Collection $contactIds = new Collection([], NotificationContactId::class),
        public Collection $contactGroupIds = new Collection([], ContactGroupId::class),
        array $options = [],
        public ?int $interval = null,
        public ?TimePeriodId $periodId = null,
        public ?int $firstDelay = null,
        public ?int $recoveryDelay = null,
        public bool $contactAdditiveInheritance = false,
        public bool $contactGroupAdditiveInheritance = false,
    ) {
        $this->options = array_values($options);
    }
}
