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
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Repository\Criteria\HostCriteria;
use App\Security\Domain\Aggregate\UserId;
use App\Shared\Domain\Collection;

interface HostRepository
{
    public function add(Host $host): void;

    /**
     * Never returns a host template, though both share the `host` table. Returns the `Host` fully
     * hydrated — every field, unlike `findAll()`, which deliberately stays partial (see
     * `Host::$checkOptions` docblock): a listing never needs the full object, this is the one read
     * path that does (a future single-host consumer, e.g. an update handler, plugs straight into it).
     *
     * @param ?UserId $viewerId null means the caller is unrestricted (admin); a non-null value
     *                          scopes the lookup to what that user can access via ACL — a host
     *                          that exists but is outside the viewer's scope returns null, the
     *                          same as a host that does not exist at all, so as not to leak
     *                          existence (mirrors `CreateHostCommandHandler`'s poller/host-group checks)
     */
    public function findOne(HostId $id, ?UserId $viewerId = null): ?Host;

    public function remove(Host $host): void;

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

    /**
     * Never returns a host template, though both share the `host` table.
     *
     * @param Collection<HostId> $ids
     *
     * @return Collection<HostName> indexed by id
     */
    public function findNamesByIds(Collection $ids): Collection;

    /**
     * Includes $ids themselves, minus any that is not a host.
     *
     * @param Collection<HostId> $ids
     *
     * @return Collection<HostId>
     */
    public function findAncestorIds(Collection $ids): Collection;
}
