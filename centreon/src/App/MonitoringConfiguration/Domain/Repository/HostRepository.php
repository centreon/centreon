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
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Repository\Criteria\HostCriteria;
use App\Security\Domain\Aggregate\UserId;
use App\Shared\Domain\Collection;

interface HostRepository
{
    public function add(Host $host): void;

    /**
     * Full replace of an existing host and all its relations (PUT semantics): every column is
     * rewritten and every relation table (poller, host groups, categories/severity, templates,
     * parents/children, macros, contacts/contact groups) is cleared and re-inserted from $host.
     *
     * Precondition: $host carries the id of an existing *real* host — in practice one just loaded
     * via {@see findOne()} (which never returns a template) in the same transaction. The host-row
     * UPDATE is additionally guarded so it never rewrites a template row, but the relation replace
     * addresses the id directly, so honoring the precondition is what keeps a template untouched.
     */
    public function update(Host $host): void;

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

    /**
     * The host's own (direct) macros as stored, with their ids, in storage order — the narrow read
     * for a caller that only needs the ids assigned on insertion, without hydrating the whole host.
     * Not ACL-scoped: the caller has already resolved the host.
     *
     * @return list<HostMacro>
     */
    public function findMacros(HostId $id): array;

    /**
     * Also removes the dependencies left without a parent or child host by this deletion.
     */
    public function remove(Host $host): void;

    /**
     * Bounded UPDATE of the activation flag; never touches a host template. Precondition: the caller
     * confirmed the host exists (typically {@see findOne()} in the same transaction) — it does not
     * assert a matched row, so it is a silent no-op on an unknown or template id.
     */
    public function updateActivationStatus(HostId $id, bool $activated): void;

    /**
     * Looked up across hosts AND host templates (both share the same `host` table and the
     * same name uniqueness constraint in legacy) — never scope this to real hosts only.
     *
     * A plain existence check, not `findOneByName(): ?Host`: a matching row can be a host
     * template, which has no poller relation and therefore cannot be hydrated into a valid
     * `Host` (poller is a required, non-nullable field on the aggregate).
     *
     * @param ?HostId $excludingHostId on an update, the host being edited keeping its own name is
     *                                 not a conflict; pass its id to exclude that row from the check
     */
    public function isNameUsedByHostOrTemplate(HostName $name, ?HostId $excludingHostId = null): bool;

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
     * @param ?HostId $excludingHostId on an update, the edited host's own parent/child edges are
     *                                 about to be replaced, so they must not contribute phantom
     *                                 ancestors to the circular-inheritance check; pass its id to
     *                                 drop every `host_hostparent_relation` row touching it from
     *                                 the traversal
     *
     * @return Collection<HostId>
     */
    public function findAncestorIds(Collection $ids, ?HostId $excludingHostId = null): Collection;
}
