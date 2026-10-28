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

use App\MonitoringConfiguration\Domain\Model\ServiceListing\ServiceListItem;
use App\MonitoringConfiguration\Domain\Repository\Criteria\ServiceListingCriteria;

interface ServiceListingRepository
{
    /**
     * One item per listable (host, service) pair: a real service directly linked to a real host, neither name
     * NULL nor blank, the service having no host group link at all, and, for an actor whose access is
     * restricted, the pair granted to one of their active Access Groups by the real-time ACL cache. A pair
     * granted by several groups is listed and counted once.
     *
     * @return \IteratorAggregate<int, ServiceListItem>&\Countable
     */
    public function findAll(ServiceListingCriteria $criteria): \IteratorAggregate&\Countable;
}
