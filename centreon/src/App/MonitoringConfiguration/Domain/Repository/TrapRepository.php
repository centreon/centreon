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

use App\MonitoringConfiguration\Domain\Aggregate\Trap\Trap;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapId;
use App\MonitoringConfiguration\Domain\Repository\Criteria\TrapCriteria;
use App\Shared\Domain\Collection;

interface TrapRepository
{
    /**
     * Only traps attached to a vendor, ordered by id. The name criterion applies to the trap name, not the vendor.
     *
     * @return \IteratorAggregate<int, Trap>&\Countable
     */
    public function findAll(?TrapCriteria $criteria = null): \IteratorAggregate&\Countable;

    /**
     * The requested traps with their vendor. Like {@see findAll()}, a trap without a vendor or a name is
     * absent, as is an id that does not exist.
     *
     * @param Collection<TrapId> $ids
     *
     * @return Collection<Trap> indexed by trap id
     */
    public function findByIds(Collection $ids): Collection;
}
