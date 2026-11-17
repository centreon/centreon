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

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceCheckIntervals;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceGroup\ServiceGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateName;
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
use Doctrine\DBAL\ParameterType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @phpstan-import-type MacroRowTypeAlias from ServiceTransformer
 *
 * @phpstan-type RowTypeAlias = array{
 *   service_id: int|string,
 *   service_description: string,
 *   parent_id?: int|string|null,
 *   check_command_id?: int|string|null,
 *   event_handler_command_id?: int|string|null,
 *   macros?: list<MacroRowTypeAlias>,
 * }
 * @phpstan-type LineRowTypeAlias = array{
 *   service_id: int|string,
 *   service_description: string,
 *   parent_id: int|string|null,
 *   check_command_id: int|string|null,
 *   event_handler_command_id: int|string|null,
 * }
 * @phpstan-type DeployableRowTypeAlias array{service_id: int|string, service_description: string, service_alias: string|null}
 * @phpstan-type InheritedValuesTypeAlias array{icon_id: int|null, normal_check_interval: int|null, retry_check_interval: int|null}
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
            // A nameless template is not a valid state: this only guards against the nullable schema column,
            // so one malformed row cannot fail the whole listing.
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
            // A nameless template is not a valid state: this only guards against the nullable schema column,
            // so one malformed row cannot fail the whole listing.
            ->andWhere("s.service_description IS NOT NULL AND s.service_description != ''")
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
     * One recursive query, walked in PHP; a loop ends both. Disabled templates are kept, as in legacy.
     */
    public function findInheritanceLine(ServiceTemplateId $templateId): Collection
    {
        $sql = <<<'SQL'
            WITH RECURSIVE chain (id) AS (
                SELECT service_id
                FROM service
                WHERE service_id = :id AND service_register = '0'
                UNION
                SELECT p.service_id
                FROM service s
                INNER JOIN chain c ON c.id = s.service_id
                INNER JOIN service p ON p.service_id = s.service_template_model_stm_id AND p.service_register = '0'
            )
            SELECT
                t.service_id,
                t.service_description,
                t.service_template_model_stm_id AS parent_id,
                t.command_command_id AS check_command_id,
                t.command_command_id2 AS event_handler_command_id
            FROM service t
            INNER JOIN chain c ON c.id = t.service_id
            SQL;

        /** @var list<LineRowTypeAlias> $rows */
        $rows = $this->connection->executeQuery(
            $sql,
            ['id' => $templateId->value],
            ['id' => ParameterType::INTEGER],
        )->fetchAllAssociative();

        $levels = [];
        foreach ($rows as $row) {
            $levels[(int) $row['service_id']] = $row;
        }

        $line = [];
        $currentId = $templateId->value;
        while ($currentId !== null && isset($levels[$currentId]) && ! isset($line[$currentId])) {
            $line[$currentId] = $levels[$currentId];
            $currentId = $levels[$currentId]['parent_id'] !== null ? (int) $levels[$currentId]['parent_id'] : null;
        }

        $macrosByOwner = $this->findMacroRowsByOwner(array_keys($line));

        return new Collection(
            array_values(array_map(
                fn (array $row): ServiceTemplate => $this->transformer->transform([
                    ...$row,
                    'macros' => $macrosByOwner[(int) $row['service_id']] ?? [],
                ]),
                $line,
            )),
            ServiceTemplate::class,
        );
    }

    /**
     * One query fetches every host template relation reachable from the host, enabled or not (as legacy
     * deployment); the depth-first walk is done in PHP. UNION dedupes the reached ids, and the walk stops on an id
     * already visited, the host included, so a loop in the data ends both.
     */
    public function findDeployableOnHost(HostId $hostId): Collection
    {
        $sql = <<<'SQL'
            WITH RECURSIVE chain (id) AS (
                SELECT :hostId
                UNION
                SELECT htr.host_tpl_id
                FROM host_template_relation htr
                INNER JOIN chain c ON c.id = htr.host_host_id
            )
            SELECT htr.host_host_id, htr.host_tpl_id
            FROM host_template_relation htr
            INNER JOIN chain c ON c.id = htr.host_host_id
            ORDER BY htr.host_host_id, htr.`order`, htr.host_tpl_id
            SQL;

        /** @var list<array{host_host_id: int|string, host_tpl_id: int|string}> $relations */
        $relations = $this->connection->executeQuery(
            $sql,
            ['hostId' => $hostId->value],
            ['hostId' => ParameterType::INTEGER],
        )->fetchAllAssociative();

        /** @var array<int, list<int>> $parentIdsById */
        $parentIdsById = [];
        foreach ($relations as $relation) {
            $parentIdsById[(int) $relation['host_host_id']][] = (int) $relation['host_tpl_id'];
        }

        $hostTemplateIds = [];
        $visited = [$hostId->value => true];
        foreach ($parentIdsById[$hostId->value] ?? [] as $directTemplateId) {
            $this->walkHostTemplateChain($directTemplateId, $parentIdsById, $visited, $hostTemplateIds);
        }

        $rowsByHostTemplate = $this->findRowsLinkedToHostTemplates($hostTemplateIds);

        $rows = [];
        foreach ($hostTemplateIds as $hostTemplateId) {
            foreach ($rowsByHostTemplate[$hostTemplateId] ?? [] as $row) {
                $rows[(int) $row['service_id']] ??= $row;
            }
        }

        $groupIdsByTemplate = $this->findServiceGroupIdsByTemplate(array_keys($rows));

        return new Collection(
            array_values(array_map(
                static fn (array $row): ServiceTemplate => new ServiceTemplate(
                    new ServiceTemplateId((int) $row['service_id']),
                    new ServiceTemplateName($row['service_description']),
                    alias: $row['service_alias'],
                    serviceGroupIds: new Collection(
                        array_map(
                            static fn (int $groupId): ServiceGroupId => new ServiceGroupId($groupId),
                            $groupIdsByTemplate[(int) $row['service_id']] ?? [],
                        ),
                        ServiceGroupId::class,
                    ),
                ),
                $rows,
            )),
            ServiceTemplate::class,
        );
    }

    /**
     * @param array<int, list<int>> $parentIdsById
     * @param array<int, true> $visited
     * @param list<int> $line
     */
    private function walkHostTemplateChain(int $id, array $parentIdsById, array &$visited, array &$line): void
    {
        if (isset($visited[$id])) {
            return;
        }
        $visited[$id] = true;
        $line[] = $id;

        foreach ($parentIdsById[$id] ?? [] as $parentId) {
            $this->walkHostTemplateChain($parentId, $parentIdsById, $visited, $line);
        }
    }

    /**
     * @param list<int> $hostTemplateIds
     *
     * @return array<int, list<DeployableRowTypeAlias>> the service templates of each host template, by ascending id
     */
    private function findRowsLinkedToHostTemplates(array $hostTemplateIds): array
    {
        if ($hostTemplateIds === []) {
            return [];
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->select('hsr.host_host_id', 's.service_id', 's.service_description', 's.service_alias')
            ->from('host_service_relation', 'hsr')
            ->innerJoin('hsr', self::TABLE_NAME, 's', "s.service_id = hsr.service_service_id AND s.service_register = '0'")
            ->innerJoin('hsr', 'host', 'h', "h.host_id = hsr.host_host_id AND h.host_register = '0'")
            ->where($qb->expr()->in('hsr.host_host_id', $qb->createNamedParameter($hostTemplateIds, ArrayParameterType::INTEGER)))
            // A nameless template is not a valid state: this only guards against the nullable schema column,
            // so one malformed row cannot fail the whole deployment.
            ->andWhere("s.service_description IS NOT NULL AND s.service_description != ''")
            ->orderBy('hsr.host_host_id')
            ->addOrderBy('s.service_id');

        /** @var list<array{host_host_id: int|string, service_id: int|string, service_description: string, service_alias: string|null}> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        $byHostTemplate = [];
        foreach ($rows as $row) {
            $byHostTemplate[(int) $row['host_host_id']][] = [
                'service_id' => $row['service_id'],
                'service_description' => $row['service_description'],
                'service_alias' => $row['service_alias'],
            ];
        }

        return $byHostTemplate;
    }

    /**
     * Every row of the templates counts, whatever host, host template or host group it names; a row without a group
     * is skipped.
     *
     * @param list<int> $templateIds
     *
     * @return array<int, list<int>> distinct group ids by template id
     */
    private function findServiceGroupIdsByTemplate(array $templateIds): array
    {
        if ($templateIds === []) {
            return [];
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->select('sgr.service_service_id', 'sgr.servicegroup_sg_id')
            ->distinct()
            ->from('servicegroup_relation', 'sgr')
            ->where($qb->expr()->in('sgr.service_service_id', $qb->createNamedParameter($templateIds, ArrayParameterType::INTEGER)))
            ->andWhere('sgr.servicegroup_sg_id IS NOT NULL')
            ->orderBy('sgr.service_service_id')
            ->addOrderBy('sgr.servicegroup_sg_id');

        /** @var list<array{service_service_id: int|string, servicegroup_sg_id: int|string}> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        $byTemplate = [];
        foreach ($rows as $row) {
            $byTemplate[(int) $row['service_service_id']][] = (int) $row['servicegroup_sg_id'];
        }

        return $byTemplate;
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
     * The custom macros the given templates own, grouped by template, in insertion order. A row not in the
     * engine form `$_SERVICE<NAME>$` is not a service macro and is left out.
     *
     * @param list<int> $ownerIds
     *
     * @return array<int, list<MacroRowTypeAlias>>
     */
    private function findMacroRowsByOwner(array $ownerIds): array
    {
        if ($ownerIds === []) {
            return [];
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->select('svc_macro_id AS id', 'svc_svc_id', 'svc_macro_name AS name', 'svc_macro_value AS value', 'is_password', 'description')
            ->from('on_demand_macro_service')
            ->where($qb->expr()->in('svc_svc_id', $qb->createNamedParameter($ownerIds, ArrayParameterType::INTEGER)))
            ->orderBy('svc_macro_id');

        /** @var list<array{id: int|string, svc_svc_id: int|string, name: string, value: string, is_password: int|string|null, description: string|null}> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        $byOwner = [];
        foreach ($rows as $row) {
            if (preg_match('/^\$_SERVICE.+\$$/', $row['name']) !== 1) {
                continue;
            }
            $byOwner[(int) $row['svc_svc_id']][] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'value' => $row['value'],
                'is_password' => $row['is_password'],
                'description' => $row['description'],
            ];
        }

        return $byOwner;
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
