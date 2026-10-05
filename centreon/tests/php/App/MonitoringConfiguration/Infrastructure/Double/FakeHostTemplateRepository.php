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

use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Repository\Criteria\HostTemplateCriteria;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\Shared\Domain\Collection;

final class FakeHostTemplateRepository implements HostTemplateRepository
{
    /** @var array<int, HostTemplate> */
    public array $hostTemplates = [];

    /** @var array<int, MediaId> inherited icon ids indexed by host id */
    public array $inheritedIconIds = [];

    public function findNamesByIds(Collection $ids): Collection
    {
        $names = [];
        foreach ($ids as $id) {
            if (isset($this->hostTemplates[$id->value])) {
                $names[$id->value] = $this->hostTemplates[$id->value]->name;
            }
        }

        return new Collection($names, HostTemplateName::class);
    }

    public function findInheritedIconIds(Collection $hostIds): Collection
    {
        $iconIds = [];
        foreach ($hostIds as $hostId) {
            if (isset($this->inheritedIconIds[$hostId->value])) {
                $iconIds[$hostId->value] = $this->inheritedIconIds[$hostId->value];
            }
        }

        return new Collection($iconIds, MediaId::class);
    }

    public function findAll(?HostTemplateCriteria $criteria = null): \IteratorAggregate&\Countable
    {
        return new Collection(array_values($this->hostTemplates), HostTemplate::class);
    }
}
