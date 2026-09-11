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

use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Repository\Criteria\HostCriteria;

interface HostRepository
{
    public function add(Host $host): void;

    /**
     * Looked up across hosts AND host templates (both share the same `host` table and the
     * same name uniqueness constraint in legacy) — never scope this to real hosts only.
     *
     * A plain existence check, not `findOneByName(): ?Host`: a matching row can be a host
     * template, which has no poller relation and therefore cannot be hydrated into a valid
     * `Host` (poller is a required, non-nullable field on the aggregate).
     */
    public function isNameUsedByHostOrTemplate(HostName $name): bool;

    /**
     * @return \IteratorAggregate<int, Host>&\Countable
     */
    public function findAll(?HostCriteria $criteria = null): \IteratorAggregate&\Countable;
}
