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
use App\MonitoringConfiguration\Domain\Aggregate\NotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\Shared\Domain\Aggregate\AggregateRootId;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Collection;

final readonly class HostTemplateNotifications
{
    /** @var list<NotificationOptionEnum> */
    public array $options;

    /**
     * @param Collection<NotificationContactId> $contactIds
     * @param Collection<ContactGroupId> $contactGroupIds
     * @param array<int, NotificationOptionEnum> $options
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

    /**
     * The contacts and contact groups compared as sets.
     */
    public function equals(self $other): bool
    {
        return $this->enabled === $other->enabled
            && $this->options === $other->options
            && $this->interval === $other->interval
            && $this->periodId?->value === $other->periodId?->value
            && $this->firstDelay === $other->firstDelay
            && $this->recoveryDelay === $other->recoveryDelay
            && $this->contactAdditiveInheritance === $other->contactAdditiveInheritance
            && $this->contactGroupAdditiveInheritance === $other->contactGroupAdditiveInheritance
            && $this->sortedIds($this->contactIds) === $this->sortedIds($other->contactIds)
            && $this->sortedIds($this->contactGroupIds) === $this->sortedIds($other->contactGroupIds);
    }

    /**
     * Both additive-inheritance flags off, every other field untouched.
     */
    public function withoutAdditiveInheritance(): self
    {
        return new self(
            enabled: $this->enabled,
            contactIds: $this->contactIds,
            contactGroupIds: $this->contactGroupIds,
            options: $this->options,
            interval: $this->interval,
            periodId: $this->periodId,
            firstDelay: $this->firstDelay,
            recoveryDelay: $this->recoveryDelay,
        );
    }

    /**
     * @template T of AggregateRootId
     *
     * @param Collection<T> $ids
     *
     * @return list<int>
     */
    private function sortedIds(Collection $ids): array
    {
        $values = array_values(array_map(static fn (AggregateRootId $id): int => $id->value, $ids->toArray()));
        sort($values);

        return $values;
    }
}
