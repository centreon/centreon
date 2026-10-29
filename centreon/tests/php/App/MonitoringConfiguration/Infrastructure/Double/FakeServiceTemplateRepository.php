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

use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceCheckIntervals;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateName;
use App\MonitoringConfiguration\Domain\Repository\Criteria\ServiceTemplateCriteria;
use App\MonitoringConfiguration\Domain\Repository\ServiceTemplateRepository;
use App\Shared\Domain\Collection;

final class FakeServiceTemplateRepository implements ServiceTemplateRepository
{
    /** the criteria received by the last findAll() call */
    public ?ServiceTemplateCriteria $receivedCriteria = null;

    /** @var \IteratorAggregate<int, ServiceTemplate>&\Countable */
    public \IteratorAggregate&\Countable $result;

    /** @var array<int, string> template names indexed by template id */
    public array $names = [];

    /** @var array<int, MediaId> inherited icon ids indexed by service id */
    public array $inheritedIconIds = [];

    /** @var array<int, ServiceCheckIntervals> inherited check intervals indexed by service id */
    public array $inheritedCheckIntervals = [];

    /** @var array<string, list<list<int>>> the ids each lookup was called with, one list per call, by method */
    public array $receivedIds = [];

    public function __construct()
    {
        $this->result = new Collection([], ServiceTemplate::class);
    }

    public function findAll(?ServiceTemplateCriteria $criteria = null): \IteratorAggregate&\Countable
    {
        $this->receivedCriteria = $criteria;

        return $this->result;
    }

    public function findNamesByIds(Collection $ids): Collection
    {
        $names = [];
        foreach ($this->record(__FUNCTION__, $ids) as $id) {
            if (isset($this->names[$id])) {
                $names[$id] = new ServiceTemplateName($this->names[$id]);
            }
        }

        return new Collection($names, ServiceTemplateName::class);
    }

    public function findInheritedIconIds(Collection $serviceIds): Collection
    {
        $ids = $this->record(__FUNCTION__, $serviceIds);

        return new Collection(array_intersect_key($this->inheritedIconIds, array_flip($ids)), MediaId::class);
    }

    public function findInheritedCheckIntervals(Collection $serviceIds): Collection
    {
        $ids = $this->record(__FUNCTION__, $serviceIds);

        return new Collection(array_intersect_key($this->inheritedCheckIntervals, array_flip($ids)), ServiceCheckIntervals::class);
    }

    /**
     * @param Collection<ServiceTemplateId>|Collection<ServiceId> $ids
     *
     * @return list<int>
     */
    private function record(string $lookup, Collection $ids): array
    {
        $values = array_values(array_map(static fn (ServiceTemplateId|ServiceId $id): int => $id->value, $ids->toArray()));
        $this->receivedIds[$lookup][] = $values;

        return $values;
    }
}
