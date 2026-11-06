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

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Repository\Criteria\HostTemplateCriteria;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Collection;

final class FakeHostTemplateRepository implements HostTemplateRepository
{
    /** @var array<int, HostTemplate> */
    public array $hostTemplates = [];

    /** @var array<int, list<int>> the service templates a template provides, its ancestors' included, indexed by template id */
    public array $serviceTemplateIdsByTemplate = [];

    /** @var array<int, MediaId> inherited icon ids indexed by host id */
    public array $inheritedIconIds = [];

    /** @var array<int, MediaId> inherited icon ids indexed by host template id */
    public array $templatesInheritedIconIds = [];

    /** @var array<int, int> linked service template counts indexed by host template id */
    public array $linkedServiceTemplateCounts = [];

    /** @var list<list<int>> the host ids asked to {@see findInheritedIconIds()}, one list per call */
    public array $receivedInheritedIconHostIds = [];

    /** @var list<int> the template ids of the last findInheritanceLine() call, in order */
    public array $receivedLineTemplateIds = [];

    /** @var list<int> the templates of the paid Monitoring Connectors, the excluded connector slugs being ignored */
    public array $monitoringConnectorTemplateIds = [];

    /** @var array<int, list<int>> the templates inheriting from each template, directly or not, indexed by template id */
    public array $descendantIds = [];

    public ?HostTemplateCriteria $receivedCriteria = null;

    /** @var list<string> names used by hosts, templates excluded */
    public array $hostNames = [];

    /** @var array<int, list<int>> poller ids of the hosts inheriting each template, indexed by template id */
    public array $inheritingHostPollerIds = [];

    /** @var array<int, int> source template id indexed by copy id */
    public array $copiedFrom = [];

    /** @var list<int> */
    public array $removedIds = [];

    /** @var list<int> the ids given by add(), in order */
    public array $addedIds = [];

    public ?\Throwable $addThrows = null;

    public ?\Throwable $addCopyThrows = null;

    private int $nextId = 1000;

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

    public function findServiceTemplateIds(Collection $templateIds): array
    {
        $serviceTemplateIds = [];
        foreach ($templateIds as $id) {
            array_push($serviceTemplateIds, ...($this->serviceTemplateIdsByTemplate[$id->value] ?? []));
        }

        return array_values(array_unique($serviceTemplateIds));
    }

    public function findInheritedIconIds(Collection $hostIds): Collection
    {
        $this->receivedInheritedIconHostIds[] = array_values(array_map(static fn (HostId $id): int => $id->value, $hostIds->toArray()));
        $iconIds = [];
        foreach ($hostIds as $hostId) {
            if (isset($this->inheritedIconIds[$hostId->value])) {
                $iconIds[$hostId->value] = $this->inheritedIconIds[$hostId->value];
            }
        }

        return new Collection($iconIds, MediaId::class);
    }

    public function findTemplatesInheritedIconIds(Collection $templateIds): Collection
    {
        $iconIds = [];
        foreach ($templateIds as $templateId) {
            if (isset($this->templatesInheritedIconIds[$templateId->value])) {
                $iconIds[$templateId->value] = $this->templatesInheritedIconIds[$templateId->value];
            }
        }

        return new Collection($iconIds, MediaId::class);
    }

    public function countLinkedServiceTemplates(Collection $templateIds): array
    {
        $counts = [];
        foreach ($templateIds as $templateId) {
            $counts[$templateId->value] = $this->linkedServiceTemplateCounts[$templateId->value] ?? 0;
        }

        return $counts;
    }

    public function findAll(HostTemplateCriteria $criteria): \IteratorAggregate&\Countable
    {
        $this->receivedCriteria = $criteria;

        return new Collection(array_values($this->hostTemplates), HostTemplate::class);
    }

    /**
     * Returns the known templates among the requested ones, in order: the fake has no relations, so
     * a test describing a multi-level line passes the whole line as direct templates.
     */
    public function findInheritanceLine(Collection $directTemplateIds): Collection
    {
        $this->receivedLineTemplateIds = [];
        $line = [];
        foreach ($directTemplateIds as $id) {
            $this->receivedLineTemplateIds[] = $id->value;
            if (isset($this->hostTemplates[$id->value])) {
                $line[] = $this->hostTemplates[$id->value];
            }
        }

        return new Collection($line, HostTemplate::class);
    }

    public function findMonitoringConnectorTemplateIds(array $excludedConnectorSlugs): Collection
    {
        return new Collection(
            array_map(static fn (int $id): HostTemplateId => new HostTemplateId($id), $this->monitoringConnectorTemplateIds),
            HostTemplateId::class,
        );
    }

    public function findDescendantIds(HostTemplateId $id): Collection
    {
        return new Collection(
            array_map(static fn (int $descendantId): HostTemplateId => new HostTemplateId($descendantId), $this->descendantIds[$id->value] ?? []),
            HostTemplateId::class,
        );
    }

    public function findOne(HostTemplateId $id): ?HostTemplate
    {
        return $this->hostTemplates[$id->value] ?? null;
    }

    public function isNameUsedByHostOrTemplate(HostTemplateName $name): bool
    {
        if (in_array($name->value, $this->hostNames, true)) {
            return true;
        }

        return array_any($this->hostTemplates, static fn (HostTemplate $template): bool => $template->name->value === $name->value);
    }

    public function add(HostTemplate $hostTemplate): void
    {
        if ($this->addThrows instanceof \Throwable) {
            throw $this->addThrows;
        }

        $id = $this->nextId++;
        (new \ReflectionProperty(AggregateRoot::class, 'id'))->setValue($hostTemplate, new HostTemplateId($id));
        $this->hostTemplates[$id] = $hostTemplate;
        $this->addedIds[] = $id;
    }

    public function addCopyOf(HostTemplate $copy, HostTemplateId $sourceId): void
    {
        if ($this->addCopyThrows instanceof \Throwable) {
            throw $this->addCopyThrows;
        }

        $id = $this->nextId++;
        (new \ReflectionProperty(AggregateRoot::class, 'id'))->setValue($copy, new HostTemplateId($id));
        $this->hostTemplates[$id] = $copy;
        $this->copiedFrom[$id] = $sourceId->value;
    }

    public function findPollerIdsOfInheritingHosts(HostTemplateId $id): Collection
    {
        return new Collection(
            array_map(static fn (int $pollerId): PollerId => new PollerId($pollerId), $this->inheritingHostPollerIds[$id->value] ?? []),
            PollerId::class,
        );
    }

    public function remove(HostTemplate $hostTemplate): void
    {
        $id = $hostTemplate->id()->value;
        unset($this->hostTemplates[$id]);
        $this->removedIds[] = $id;
    }
}
