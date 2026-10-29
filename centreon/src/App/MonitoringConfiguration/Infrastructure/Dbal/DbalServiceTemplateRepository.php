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

use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceCheckIntervals;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateName;
use App\MonitoringConfiguration\Domain\Repository\Criteria\ServiceTemplateCriteria;
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
 * @phpstan-type InheritedValuesTypeAlias array{icon_id: int|null, normal_check_interval: int|null, retry_check_interval: int|null}
 */
final readonly class DbalServiceTemplateRepository extends DbalRepository implements ServiceTemplateRepository
{
    use DbalCriteriaApplierTrait;
    public const TABLE_NAME = 'service';

    /**
     * @param TransformerInterface<RowTypeAlias, ServiceTemplate> $transformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,

        #[Autowire(service: ServiceTemplateTransformer::class)]
        private TransformerInterface $transformer,
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

    public function findNamesByIds(Collection $ids): Collection
    {
        $idValues = array_values(array_map(static fn (ServiceTemplateId $id): int => $id->value, $ids->toArray()));
        if ($idValues === []) {
            return new Collection([], ServiceTemplateName::class);
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->select('s.service_id', 's.service_description')
            ->from(self::TABLE_NAME, 's')
            ->where("s.service_register = '0'")
            ->andWhere($qb->expr()->in('s.service_id', $qb->createNamedParameter($idValues, ArrayParameterType::INTEGER)));

        /** @var list<array{service_id: int|string, service_description: string}> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        $names = [];
        foreach ($rows as $row) {
            $names[(int) $row['service_id']] = new ServiceTemplateName($row['service_description']);
        }

        return new Collection($names, ServiceTemplateName::class);
    }

    public function findInheritedIconIds(Collection $serviceIds): Collection
    {
        $iconIds = [];
        foreach ($this->findInheritedValues($serviceIds) as $serviceId => $inherited) {
            if ($inherited['icon_id'] !== null) {
                $iconIds[$serviceId] = new MediaId($inherited['icon_id']);
            }
        }

        return new Collection($iconIds, MediaId::class);
    }

    public function findInheritedCheckIntervals(Collection $serviceIds): Collection
    {
        $intervals = [];
        foreach ($this->findInheritedValues($serviceIds) as $serviceId => $inherited) {
            if ($inherited['normal_check_interval'] !== null || $inherited['retry_check_interval'] !== null) {
                $intervals[$serviceId] = new ServiceCheckIntervals($inherited['normal_check_interval'], $inherited['retry_check_interval']);
            }
        }

        return new Collection($intervals, ServiceCheckIntervals::class);
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

    /**
     * What each service inherits from its template chain, its own level excluded: the first icon whose image sits
     * in a folder (a level without extended information or without such an image being skipped) and, field by
     * field, the first check interval of at least 1.
     *
     * One query fetches every level reachable from the given services; the chain itself is walked in PHP. UNION
     * dedupes the reached ids, and the walk stops on an id already seen, so a template loop in the data ends both.
     *
     * @param Collection<ServiceId> $serviceIds
     *
     * @return array<int, InheritedValuesTypeAlias> indexed by service id
     */
    private function findInheritedValues(Collection $serviceIds): array
    {
        $idValues = array_values(array_unique(array_map(static fn (ServiceId $id): int => $id->value, $serviceIds->toArray())));
        if ($idValues === []) {
            return [];
        }

        $sql = <<<'SQL'
            WITH RECURSIVE chain (id) AS (
                SELECT service_id
                FROM service
                WHERE service_id IN (:ids)
                UNION
                SELECT s.service_template_model_stm_id
                FROM service s
                INNER JOIN chain c ON c.id = s.service_id
                WHERE s.service_template_model_stm_id IS NOT NULL
            )
            SELECT
                s.service_id,
                s.service_template_model_stm_id AS template_id,
                s.service_normal_check_interval AS normal_check_interval,
                s.service_retry_check_interval AS retry_check_interval,
                (
                    SELECT MIN(esi.esi_icon_image)
                    FROM extended_service_information esi
                    WHERE esi.service_service_id = s.service_id
                        AND EXISTS (
                            SELECT 1
                            FROM view_img_dir_relation vidr
                            INNER JOIN view_img_dir vid ON vid.dir_id = vidr.dir_dir_parent_id
                            WHERE vidr.img_img_id = esi.esi_icon_image
                        )
                ) AS icon_id
            FROM service s
            INNER JOIN chain c ON c.id = s.service_id
            SQL;

        /** @var list<array{service_id: int|string, template_id: int|string|null, normal_check_interval: int|string|null, retry_check_interval: int|string|null, icon_id: int|string|null}> $rows */
        $rows = $this->connection->executeQuery(
            $sql,
            ['ids' => $idValues],
            ['ids' => ArrayParameterType::INTEGER],
        )->fetchAllAssociative();

        $levels = [];
        foreach ($rows as $row) {
            $levels[(int) $row['service_id']] = [
                'template_id' => $row['template_id'] !== null ? (int) $row['template_id'] : null,
                'icon_id' => $row['icon_id'] !== null ? (int) $row['icon_id'] : null,
                'normal_check_interval' => $this->storedInterval($row['normal_check_interval']),
                'retry_check_interval' => $this->storedInterval($row['retry_check_interval']),
            ];
        }

        $inherited = [];
        foreach ($idValues as $serviceId) {
            $inherited[$serviceId] = $this->walkTemplateChain($serviceId, $levels);
        }

        return $inherited;
    }

    /**
     * Walks up from the service's template (the service's own level is skipped), keeping the first value found
     * for each field.
     *
     * @param array<int, array{template_id: int|null, icon_id: int|null, normal_check_interval: int|null, retry_check_interval: int|null}> $levels
     *
     * @return InheritedValuesTypeAlias
     */
    private function walkTemplateChain(int $serviceId, array $levels): array
    {
        $values = ['icon_id' => null, 'normal_check_interval' => null, 'retry_check_interval' => null];
        $visited = [$serviceId => true];
        $current = $levels[$serviceId]['template_id'] ?? null;
        while ($current !== null && ! isset($visited[$current]) && isset($levels[$current])) {
            $visited[$current] = true;
            foreach ($values as $key => $value) {
                $values[$key] = $value ?? $levels[$current][$key];
            }
            $current = $levels[$current]['template_id'];
        }

        return $values;
    }

    /**
     * A stored interval below 1 (0 or negative) counts as unset.
     */
    private function storedInterval(int|string|null $value): ?int
    {
        return $value === null || (int) $value < 1 ? null : (int) $value;
    }
}
