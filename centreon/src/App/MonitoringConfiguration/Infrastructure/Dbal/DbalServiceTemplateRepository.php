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

namespace App\MonitoringConfiguration\Infrastructure\Dbal;

use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Repository\Criteria\ServiceTemplateCriteria;
use App\MonitoringConfiguration\Domain\Repository\ModuleRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceTemplateRepository;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Repository\Pagination;
use App\Shared\Infrastructure\Dbal\DbalCriteriaApplierTrait;
use App\Shared\Infrastructure\Dbal\DbalRepository;
use App\Shared\Infrastructure\InMemory\InMemoryPaginator;
use App\Shared\Infrastructure\TransformerInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @phpstan-type RowTypeAlias = array{
 *   service_id: int,
 *   service_description: string,
 * }
 */
final readonly class DbalServiceTemplateRepository extends DbalRepository implements ServiceTemplateRepository
{
    use DbalCriteriaApplierTrait;
    public const TABLE_NAME = 'service';

    // provides the Monitoring Connectors, stored in its own tables
    private const PLUGIN_PACK_MANAGER_MODULE = 'centreon-pp-manager';

    /**
     * @param TransformerInterface<RowTypeAlias, ServiceTemplate> $transformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,

        #[Autowire(service: ServiceTemplateTransformer::class)]
        private TransformerInterface $transformer,

        private ModuleRepository $moduleRepository,
    ) {
    }

    public function findAll(?ServiceTemplateCriteria $criteria = null): \IteratorAggregate&\Countable
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('s.service_id', 's.service_description')
            ->from(self::TABLE_NAME, 's')
            // service_register '0' is a template; '1' a service, '2'/'3' meta and BA services.
            // Locked and disabled templates are kept on purpose: the legacy form offers them too.
            ->where("s.service_register = '0'")
            // service_description is nullable while ServiceTemplateName requires a non-empty value:
            // excluding such rows keeps one malformed row from failing the whole listing.
            ->andWhere("s.service_description IS NOT NULL AND s.service_description != ''")
            ->orderBy('s.service_id'); // the selector order, also required for deterministic pagination

        if (($name = $criteria?->getName()) !== null) {
            $qb->andWhere($qb->expr()->like('s.service_description', $qb->createNamedParameter('%' . $name . '%')));
        }

        if (($excludedIds = $criteria?->getExcludedIds() ?? []) !== []) {
            $qb->andWhere($qb->expr()->notIn('s.service_id', $qb->createNamedParameter($excludedIds, ArrayParameterType::INTEGER)));
        }

        $pagination = $criteria?->getPagination();
        if (! $pagination instanceof Pagination) {
            /** @var array<RowTypeAlias> $rows */
            $rows = $qb->executeQuery()->fetchAllAssociative();

            return $this->createServiceTemplates($rows);
        }

        $this->applyPagination($qb, $pagination);

        // total across all pages: countMatching clones $qb and strips its sort/pagination
        $count = $this->countMatching($qb, 'COUNT(s.service_id)');

        /** @var array<RowTypeAlias> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return new InMemoryPaginator(
            items: $this->createServiceTemplates($rows),
            totalItems: $count,
            currentPage: $pagination->page,
            itemsPerPage: $pagination->itemsPerPage,
        );
    }

    public function findMonitoringConnectorTemplateIds(array $excludedConnectorSlugs): Collection
    {
        if (! $this->moduleRepository->isInstalled(self::PLUGIN_PACK_MANAGER_MODULE)) {
            return new Collection([], ServiceTemplateId::class);
        }

        // The connector templates, then every template inheriting from them. UNION dedupes the
        // reached ids, so a template loop already in the data cannot make the recursion run forever.
        [$excludeClause, $params, $types] = $excludedConnectorSlugs === []
            ? ['', [], []]
            : ['WHERE pp.slug NOT IN (:excludedSlugs)', ['excludedSlugs' => $excludedConnectorSlugs], ['excludedSlugs' => ArrayParameterType::STRING]];
        $sql = <<<SQL
            WITH RECURSIVE connector_templates (id) AS (
                SELECT ps.svc_id
                FROM mod_ppm_pluginpack_service ps
                INNER JOIN mod_ppm_pluginpack pp ON pp.pluginpack_id = ps.pluginpack_id
                {$excludeClause}
                UNION
                SELECT child.service_id
                FROM service child
                INNER JOIN connector_templates ct ON ct.id = child.service_template_model_stm_id
            )
            SELECT s.service_id
            FROM service s
            INNER JOIN connector_templates ct ON ct.id = s.service_id
            WHERE s.service_register = '0'
            ORDER BY s.service_id
            SQL;

        /** @var list<int|string> $ids */
        $ids = $this->connection->executeQuery($sql, $params, $types)->fetchFirstColumn();

        return new Collection(
            array_map(static fn (int|string $id): ServiceTemplateId => new ServiceTemplateId((int) $id), $ids),
            ServiceTemplateId::class,
        );
    }

    /**
     * @param array<RowTypeAlias> $rows
     *
     * @return Collection<ServiceTemplate>
     */
    private function createServiceTemplates(array $rows): Collection
    {
        return new Collection(
            array_map(
                fn (array $row): ServiceTemplate => $this->transformer->transform($row),
                $rows
            ),
            ServiceTemplate::class
        );
    }
}
