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

namespace Tests\App\MonitoringConfiguration\Infrastructure\Double;

use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContact;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactName;
use App\MonitoringConfiguration\Domain\Repository\Criteria\NotificationContactCriteria;
use App\MonitoringConfiguration\Domain\Repository\NotificationContactRepository;
use App\Shared\Domain\Collection;

final class FakeNotificationContactRepository implements NotificationContactRepository
{
    /** @var array<int, NotificationContactName> indexed by contact id */
    public array $names = [];

    public function findNamesByIds(Collection $ids): Collection
    {
        $names = [];
        foreach ($ids as $id) {
            if (isset($this->names[$id->value])) {
                $names[$id->value] = $this->names[$id->value];
            }
        }

        return new Collection($names, NotificationContactName::class);
    }

    public function findAll(?NotificationContactCriteria $criteria = null): \IteratorAggregate&\Countable
    {
        return new Collection([], NotificationContact::class);
    }
}
