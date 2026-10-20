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

use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriod;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Repository\Criteria\TimePeriodCriteria;
use App\MonitoringConfiguration\Domain\Repository\TimePeriodRepository;
use App\Shared\Domain\Collection;

final class FakeTimePeriodRepository implements TimePeriodRepository
{
    /** @var list<int> ids of the time periods that exist */
    public array $existingIds = [];

    public function existsOne(TimePeriodId $id): bool
    {
        return in_array($id->value, $this->existingIds, true);
    }

    public function findNamesByIds(Collection $ids): Collection
    {
        return new Collection([], \App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodName::class);
    }

    public function findAll(?TimePeriodCriteria $criteria = null): \IteratorAggregate&\Countable
    {
        return new Collection([], TimePeriod::class);
    }
}
