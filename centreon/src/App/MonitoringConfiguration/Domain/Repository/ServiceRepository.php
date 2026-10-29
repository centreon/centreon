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
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Repository\Criteria\ServiceCriteria;
use App\Security\Domain\Aggregate\ActingUser;
use App\Shared\Domain\Collection;

interface ServiceRepository
{
    /**
     * Services directly attached to this host (`host_service_relation.host_host_id`) that have
     * exactly one relation row in total — mirrors legacy `findServiceIdsExclusivelyLinkedToHostId()`:
     * a service also reachable through a hostgroup or another host is left alone, only one that
     * would be orphaned by deleting this host is cascade-deleted with it.
     *
     * Returns hydrated `Service` aggregates, not just ids: the caller needs the name (activity log),
     * macros (vault purge) and the host's activation and poller as they are now (poller flag, once the
     * rows are gone) for each one.
     *
     * @return Collection<Service>
     */
    public function findExclusivelyLinkedToHostId(HostId $hostId): Collection;

    /**
     * The services of this host that come from one of the given service templates, the ones a host
     * gets from its host templates. Like {@see findExclusivelyLinkedToHostId()}, a service also
     * reachable through another host or a hostgroup is left out, and so is never deleted from under
     * them. Hydrated like {@see findExclusivelyLinkedToHostId()}.
     *
     * @param list<int> $serviceTemplateIds
     *
     * @return Collection<Service>
     */
    public function findFromServiceTemplates(HostId $hostId, array $serviceTemplateIds): Collection;

    /**
     * Also removes the dependencies left without a parent service by this deletion.
     */
    public function remove(Service $service): void;

    /**
     * The real services, each with the real host it is directly linked to, the services having no host group
     * link at all. For an actor whose access is restricted, only the (host, service) pairs the real-time ACL
     * cache grants to one of their active Access Groups; a pair granted by several groups is listed and counted
     * once. Each service carries its own values only, not what it inherits from its templates, and comes without
     * its macros, which a listing does not show.
     *
     * @return \IteratorAggregate<int, Service>&\Countable
     */
    public function findAll(ServiceCriteria $criteria): \IteratorAggregate&\Countable;

    /**
     * The service, with its macros, if {@see findAll()} lists it for this actor: a real service directly linked
     * to a real host, with no host group link, and for an actor whose access is restricted, granted to one of
     * their active Access Groups by the real-time ACL cache. Null otherwise.
     */
    public function findOne(ServiceId $id, ActingUser $actor): ?Service;

    /**
     * The number of distinct real hosts the service is directly linked to, whatever the actor's access.
     */
    public function countRealHostLinks(ServiceId $id): int;
}
