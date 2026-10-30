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

use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceCheckIntervals;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateName;
use App\MonitoringConfiguration\Domain\Repository\Criteria\ServiceTemplateCriteria;
use App\Shared\Domain\Collection;

interface ServiceTemplateRepository
{
    /**
     * Service templates only (never services), ordered by id.
     *
     * @return \IteratorAggregate<int, ServiceTemplate>&\Countable
     */
    public function findAll(?ServiceTemplateCriteria $criteria = null): \IteratorAggregate&\Countable;

    /**
     * Service templates only. A missing id, such as a service's dangling template reference, is absent.
     *
     * @param Collection<ServiceTemplateId> $ids
     *
     * @return Collection<ServiceTemplateName> indexed by template id
     */
    public function findNamesByIds(Collection $ids): Collection;

    /**
     * Each service's icon inherited from its template chain: the first icon found walking up the whole chain,
     * a level without extended information or whose image sits in no folder being skipped. The service's own
     * icon is ignored. A service with no inherited icon is absent from the result.
     *
     * @param Collection<ServiceId> $serviceIds
     *
     * @return Collection<MediaId> indexed by service id
     */
    public function findInheritedIconIds(Collection $serviceIds): Collection;

    /**
     * Each service's check intervals inherited from its template chain, field by field: the first stored value
     * of at least 1 walking up the chain (below 1 counts as unset). The service's own values are ignored.
     *
     * @param Collection<ServiceId> $serviceIds
     *
     * @return Collection<ServiceCheckIntervals> indexed by service id
     */
    public function findInheritedCheckIntervals(Collection $serviceIds): Collection;
}
