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

namespace App\MonitoringConfiguration\Domain\Repository;

use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroup;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupName;
use App\MonitoringConfiguration\Domain\Repository\Criteria\HostGroupCriteria;
use App\Shared\Domain\Collection;

interface HostGroupRepository
{
    /**
     * Every requested id's name, for bulk display purposes and existence checks. An id
     * absent from the result no longer exists.
     *
     * @param Collection<HostGroupId> $ids
     *
     * @return Collection<HostGroupName> indexed by host group id
     */
    public function findNamesByIds(Collection $ids): Collection;

    /**
     * @return \IteratorAggregate<int, HostGroup>&\Countable
     */
    public function findAll(?HostGroupCriteria $criteria = null): \IteratorAggregate&\Countable;
}
