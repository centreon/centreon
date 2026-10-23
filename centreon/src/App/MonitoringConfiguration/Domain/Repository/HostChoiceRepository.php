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

use App\MonitoringConfiguration\Domain\Model\HostChoice;
use App\MonitoringConfiguration\Domain\Repository\Criteria\HostChoiceCriteria;

interface HostChoiceRepository
{
    /**
     * Hosts only (never host templates), with or without a poller, ordered by id. When the criteria
     * carry a viewer, only the hosts that viewer can access are listed.
     *
     * @return \IteratorAggregate<int, HostChoice>&\Countable
     */
    public function findAll(?HostChoiceCriteria $criteria = null): \IteratorAggregate&\Countable;
}
