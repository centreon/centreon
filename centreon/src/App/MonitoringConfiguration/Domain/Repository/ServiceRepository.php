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

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\Shared\Domain\Collection;

interface ServiceRepository
{
    /**
     * Services directly attached to this host (`host_service_relation.host_host_id`) that have
     * exactly one relation row in total — mirrors legacy `findServiceIdsExclusivelyLinkedToHostId()`:
     * a service also reachable through a hostgroup or another host is left alone, only one that
     * would be orphaned by deleting this host is cascade-deleted with it.
     *
     * Returns hydrated `Service` aggregates, not just ids: the caller needs the name (activity log)
     * and macros (vault purge) for each one.
     *
     * @return Collection<Service>
     */
    public function findExclusivelyLinkedToHostId(HostId $hostId): Collection;

    public function remove(Service $service): void;
}
