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

use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Repository\Criteria\HostTemplateCriteria;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\Shared\Domain\Collection;

/**
 * Decorates a host template repository to choose the templates of the paid Monitoring Connectors,
 * everything else being read from the decorated repository. No template comes from a paid connector
 * until a test calls {@see restrict()}; a test that does calls {@see reset()} in its tearDown(). The
 * ids are static because the API test client boots a new kernel, so a new instance, for every
 * request. The excluded connector slugs are ignored: the restricted ids are the paid connectors'
 * templates.
 */
final class FakeMonitoringConnectorTemplates implements HostTemplateRepository
{
    /** @var list<int> */
    private static array $ids = [];

    public function __construct(
        private readonly HostTemplateRepository $inner,
    ) {
    }

    public static function restrict(int ...$ids): void
    {
        self::$ids = array_values($ids);
    }

    public static function reset(): void
    {
        self::$ids = [];
    }

    public function findMonitoringConnectorTemplateIds(array $excludedConnectorSlugs): Collection
    {
        return new Collection(
            array_map(static fn (int $id): HostTemplateId => new HostTemplateId($id), self::$ids),
            HostTemplateId::class,
        );
    }

    public function findAll(?HostTemplateCriteria $criteria = null): \IteratorAggregate&\Countable
    {
        return $this->inner->findAll($criteria);
    }

    public function findNamesByIds(Collection $ids): Collection
    {
        return $this->inner->findNamesByIds($ids);
    }

    public function findInheritedIconIds(Collection $hostIds): Collection
    {
        return $this->inner->findInheritedIconIds($hostIds);
    }

    public function findInheritanceLine(Collection $directTemplateIds): Collection
    {
        return $this->inner->findInheritanceLine($directTemplateIds);
    }

    public function findServiceTemplateIds(Collection $templateIds): array
    {
        return $this->inner->findServiceTemplateIds($templateIds);
    }
}
