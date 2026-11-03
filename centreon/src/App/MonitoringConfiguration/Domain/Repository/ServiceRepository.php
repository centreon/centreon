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
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceSeverity\ServiceSeverityId;
use App\MonitoringConfiguration\Domain\Repository\Criteria\ServiceCriteria;
use App\Security\Domain\Aggregate\ActingUser;
use App\Shared\Domain\Collection;

interface ServiceRepository
{
    public function add(Service $service): void;

    /**
     * Rewrites the form columns of a stored real service (its alias and the columns the form does not expose are
     * left as they are) and its extended information: every `extended_service_information` row of the service is
     * updated, and one is written when it has none. Precondition: the caller loaded the service (typically
     * {@see findOne()} in the same transaction); a service template is never touched.
     */
    public function update(Service $service): void;

    /**
     * Rewrites the relations of a stored real service and its own macros:
     * - its host link only when its host changed;
     * - its service groups, with its current host, only when they or its host changed: every service-group row
     *   of the service is then replaced, those of another host or of a host group included;
     * - its levelless categories and its severity, each in its own half of the shared category table, every
     *   levelled row being replaced by the one severity;
     * - its contacts, contact groups and traps;
     * - its own macros in place: a stored macro keeps its row (id, description, order), a new one is inserted, one
     *   no longer held is deleted.
     * Escalations, dependencies and downtimes stay on the (host, service) pair they name.
     */
    public function replaceRelationsAndMacros(Service $service): void;

    /**
     * The levelled categories (severities) the service is linked to, lowest id first, whatever the actor may see:
     * {@see findOne()} reads only the one the actor may see, so a write preserving a severity hidden from the
     * actor needs this unscoped read.
     *
     * @return Collection<ServiceSeverityId>
     */
    public function findSeverityIds(ServiceId $id): Collection;

    /**
     * Compared as legacy does: case-insensitive, whatever the actor's scope or the service's activation. A service
     * reaching the host only through a host group is not counted.
     */
    public function isNameUsedOnHost(ServiceName $name, HostId $hostId, ?ServiceId $excludingServiceId = null): bool;

    /**
     * Services directly attached to this host (`host_service_relation.host_host_id`) that have
     * exactly one relation row in total — mirrors legacy `findServiceIdsExclusivelyLinkedToHostId()`:
     * a service also reachable through a hostgroup or another host is left alone, only one that
     * would be orphaned by deleting this host is cascade-deleted with it.
     *
     * Returns hydrated `Service` aggregates: name (activity log), macros (vault purge), host state (poller flag).
     *
     * @return Collection<Service>
     */
    public function findExclusivelyLinkedToHostId(HostId $hostId): Collection;

    /**
     * The ids of the services of this host that come from one of the given service templates, the ones a host
     * gets from its host templates. Like {@see findExclusivelyLinkedToHostId()}, a service also reachable through
     * another host or a hostgroup is left out, and so is never deleted from under them. Nothing is hydrated, so
     * one service with malformed stored data cannot fail the whole listing.
     *
     * @param list<int> $serviceTemplateIds
     *
     * @return Collection<ServiceId>
     */
    public function findIdsFromServiceTemplates(HostId $hostId, array $serviceTemplateIds): Collection;

    /**
     * The real service directly linked to that host, hydrated as {@see findOne()}, not scoped by any actor.
     * Null when it is not a real service directly linked to that host.
     */
    public function findOneOnHost(ServiceId $id, HostId $hostId): ?Service;

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
     * The service, wholly hydrated, if {@see findAll()} lists it for this actor: a real service directly linked
     * to a real host, with no host group link, and for an actor whose access is restricted, granted to one of
     * their active Access Groups by the real-time ACL cache. Null otherwise.
     *
     * Hydrates every own column and relation, unfiltered by the actor's scope; out-of-bound stored numbers read
     * as unset.
     */
    public function findOne(ServiceId $id, ActingUser $actor): ?Service;

    /**
     * The service's own macros as stored, with their ids, in storage order: the narrow read for a caller that only
     * needs the ids assigned on insertion, without hydrating the whole service. Not ACL-scoped: the caller has
     * already resolved the service.
     *
     * @return Collection<ServiceMacro>
     */
    public function findMacros(ServiceId $id): Collection;

    /**
     * Bounded UPDATE of the activation flag; never touches a service template, nor the links of the service.
     * Precondition: the caller confirmed the service exists (typically {@see findOne()} in the same transaction)
     * — it does not assert a matched row, so it is a silent no-op on an unknown or template id.
     */
    public function updateActivationStatus(ServiceId $id, bool $activated): void;
}
