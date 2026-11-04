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

use App\MonitoringConfiguration\Domain\Aggregate\ServiceGroup\ServiceGroup;
use App\MonitoringConfiguration\Domain\Repository\Criteria\ServiceGroupCriteria;
use App\MonitoringConfiguration\Domain\Repository\ServiceGroupRepository;
use App\Shared\Domain\Collection;

final class FakeServiceGroupRepository implements ServiceGroupRepository
{
    /** the criteria received by the last findAll() call */
    public ?ServiceGroupCriteria $receivedCriteria = null;

    /** @var \IteratorAggregate<int, ServiceGroup>&\Countable */
    public \IteratorAggregate&\Countable $result;

    public function __construct()
    {
        $this->result = new Collection([], ServiceGroup::class);
    }

    public function findAll(ServiceGroupCriteria $criteria): \IteratorAggregate&\Countable
    {
        $this->receivedCriteria = $criteria;

        return $this->result;
    }
}
