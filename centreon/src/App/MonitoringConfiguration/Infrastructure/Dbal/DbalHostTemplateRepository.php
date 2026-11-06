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

use App\MonitoringConfiguration\Domain\Aggregate\CheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\HostMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\HostMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\Criteria\HostTemplateCriteria;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\ModuleRepository;
use App\Security\Domain\Aggregate\AccessScope;
use App\Security\Domain\Service\AccessScopeResolver;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\Dbal\DbalCriteriaApplierTrait;
use App\Shared\Infrastructure\Dbal\DbalRepository;
use App\Shared\Infrastructure\InMemory\InMemoryPaginator;
use App\Shared\Infrastructure\TransformerInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @phpstan-type DbRowTypeAlias = array{
 *   host_id: int,
 *   host_name: string,
 *   host_alias: ?string,
 *   host_locked: int|string|null,
 *   icon_id: int|string|null,
 * }
 * @phpstan-type RowTypeAlias = array{
 *   host_id: int,
 *   host_name: string,
 *   host_alias: ?string,
 *   host_locked: int|string|null,
 *   icon_id: int|string|null,
 *   parent_template_ids: list<int>,
 * }
 * @phpstan-type FindOneRowTypeAlias = array{
 *   host_id: int,
 *   host_name: string,
 *   host_alias: ?string,
 *   host_locked: int|string|null,
 *   icon_id: int|string|null,
 *   parent_template_ids: list<int>,
 *   snmp_community: ?string,
 *   snmp_version: ?string,
 *   timezone_id: int|string|null,
 *   comment: ?string,
 *   note_url: ?string,
 *   note: ?string,
 *   action_url: ?string,
 *   alt_icon: ?string,
 *   check_timeperiod_id: int|string|null,
 *   max_check_attempts: int|string|null,
 *   normal_check_interval: int|string|null,
 *   retry_check_interval: int|string|null,
 *   active_check_enabled: ?string,
 *   passive_check_enabled: ?string,
 *   acknowledgement_timeout: int|string|null,
 *   check_freshness: ?string,
 *   freshness_threshold: int|string|null,
 *   flap_detection_enabled: ?string,
 *   low_flap_threshold: int|string|null,
 *   high_flap_threshold: int|string|null,
 *   event_handler_enabled: ?string,
 *   event_handler_command_id: int|string|null,
 *   event_handler_args: ?string,
 *   check_command_id: int|string|null,
 *   check_command_args: ?string,
 *   notifications_enabled: ?string,
 *   notification_options: ?string,
 *   notification_interval: int|string|null,
 *   notification_period_id: int|string|null,
 *   first_notification_delay: int|string|null,
 *   recovery_notification_delay: int|string|null,
 *   contact_additive_inheritance: int|string|null,
 *   cg_additive_inheritance: int|string|null,
 *   macros: list<HostMacro>,
 *   category_ids: list<int>,
 *   severity_id: ?int,
 *   contact_ids: list<int>,
 *   contact_group_ids: list<int>,
 *   linked_service_template_ids: list<int>,
 *   service_template_check_command_ids: list<int>,
 * }
 */
final readonly class DbalHostTemplateRepository extends DbalRepository implements HostTemplateRepository
{
    use DbalCriteriaApplierTrait;
    use RemovesEmptiedDependenciesTrait;
    public const TABLE_NAME = 'host';

    // host templates are host rows discriminated by host_register = '0'
    private const HOST_TEMPLATE_REGISTER = '0';

    // provides the Monitoring Connectors, stored in its own tables
    private const PLUGIN_PACK_MANAGER_MODULE = 'centreon-pp-manager';

    /**
     * @param TransformerInterface<RowTypeAlias|FindOneRowTypeAlias, HostTemplate> $transformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,

        #[Autowire(service: HostTemplateTransformer::class)]
        private TransformerInterface $transformer,

        private AccessScopeResolver $accessScopes,

        private ModuleRepository $moduleRepository,
    ) {
    }

    public function findAll(HostTemplateCriteria $criteria): \IteratorAggregate&\Countable
    {
        // An empty scope means the actor is restricted but grants no accessible severity: they must see
        // nothing (fail-closed), matching legacy, which returns [] for a user with no access group and
        // filters out every severity-less template once a category restriction applies.
        $scope = $criteria->accessScope($this->accessScopes->hostSeverities(...), HostSeverityId::class);
        if ($scope->allowsNothing()) {
            return $this->emptyResult($criteria, HostTemplate::class);
        }

        // extended_host_information is unique per host, so the join cannot duplicate a template
        $qb = $this->connection->createQueryBuilder();
        $qb->select('h.host_id', 'h.host_name', 'h.host_alias', 'h.host_locked', 'ehi.ehi_icon_image AS icon_id')
            ->from(self::TABLE_NAME, 'h')
            ->leftJoin('h', 'extended_host_information', 'ehi', 'ehi.host_host_id = h.host_id')
            ->where('h.host_register = ' . $qb->createNamedParameter(self::HOST_TEMPLATE_REGISTER));

        $this->filterByCriteria($qb, $criteria, $scope);
        $this->applySort($qb, 'h', $criteria);
        // id order when unsorted (choices list), else tie-breaker for deterministic pagination
        $qb->addOrderBy('h.host_id');

        $pagination = $criteria->getPagination();
        if (! $pagination instanceof \App\Shared\Domain\Repository\Pagination) {
            /** @var array<DbRowTypeAlias> $rows */
            $rows = $qb->executeQuery()->fetchAllAssociative();

            return $this->createHostTemplates($rows);
        }

        $this->paginate($qb, $criteria);

        // total across all pages: countMatching clones $qb and strips its sort/pagination,
        // so the count is unaffected by the pagination applied above.
        $count = $this->countMatching($qb, 'COUNT(DISTINCT h.host_id)');

        /** @var array<DbRowTypeAlias> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return new InMemoryPaginator(
            items: $this->createHostTemplates($rows),
            totalItems: $count,
            currentPage: $pagination->page,
            itemsPerPage: $pagination->itemsPerPage,
        );
    }

    public function findNamesByIds(Collection $ids): Collection
    {
        $idValues = array_map(static fn (HostTemplateId $id): int => $id->value, $ids->toArray());
        if ($idValues === []) {
            return new Collection([], HostTemplateName::class);
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->select('host_id', 'host_name')
            ->from(self::TABLE_NAME)
            ->where('host_register = ' . $qb->createNamedParameter(self::HOST_TEMPLATE_REGISTER))
            ->andWhere($qb->expr()->in('host_id', $qb->createNamedParameter($idValues, ArrayParameterType::INTEGER)));

        /** @var list<array{host_id: int|string, host_name: string}> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        $names = [];
        foreach ($rows as $row) {
            $names[(int) $row['host_id']] = new HostTemplateName($row['host_name']);
        }

        return new Collection($names, HostTemplateName::class);
    }

    public function findInheritedIconIds(Collection $hostIds): Collection
    {
        return $this->findInheritedIconIdsOf(array_values(array_map(static fn (HostId $id): int => $id->value, $hostIds->toArray())));
    }

    public function findTemplatesInheritedIconIds(Collection $templateIds): Collection
    {
        return $this->findInheritedIconIdsOf(
            array_values(array_map(static fn (HostTemplateId $id): int => $id->value, $templateIds->toArray()))
        );
    }

    public function countLinkedServiceTemplates(Collection $templateIds): array
    {
        $idValues = array_values(array_map(static fn (HostTemplateId $id): int => $id->value, $templateIds->toArray()));
        if ($idValues === []) {
            return [];
        }

        // Direct links only (no host-group links); the service FK cascades, so no join on service.
        $qb = $this->connection->createQueryBuilder();
        $qb->select('hsr.host_host_id', 'COUNT(DISTINCT hsr.service_service_id) AS linked_count')
            ->from('host_service_relation', 'hsr')
            ->where($qb->expr()->in('hsr.host_host_id', $qb->createNamedParameter($idValues, ArrayParameterType::INTEGER)))
            ->andWhere('hsr.service_service_id IS NOT NULL')
            ->groupBy('hsr.host_host_id');

        /** @var list<array{host_host_id: int|string, linked_count: int|string}> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        $counts = array_fill_keys($idValues, 0);
        foreach ($rows as $row) {
            $counts[(int) $row['host_host_id']] = (int) $row['linked_count'];
        }

        return $counts;
    }

    public function findServiceTemplateIds(Collection $templateIds): array
    {
        $ids = array_values(array_map(static fn (HostTemplateId $id): int => $id->value, $templateIds->toArray()));
        if ($ids === []) {
            return [];
        }

        // UNION dedupes the reached ids, so a template loop already in the data cannot make the
        // recursion run forever.
        $sql = <<<'SQL'
            WITH RECURSIVE chain (id) AS (
                SELECT host_id
                FROM host
                WHERE host_id IN (:ids)
                UNION
                SELECT htr.host_tpl_id
                FROM host_template_relation htr
                INNER JOIN chain c ON c.id = htr.host_host_id
            )
            SELECT DISTINCT hsr.service_service_id
            FROM host_service_relation hsr
            INNER JOIN chain c ON c.id = hsr.host_host_id
            INNER JOIN service s ON s.service_id = hsr.service_service_id AND s.service_register = '0'
            SQL;

        /** @var list<int|string> $serviceTemplateIds */
        $serviceTemplateIds = $this->connection->executeQuery(
            $sql,
            ['ids' => $ids],
            ['ids' => ArrayParameterType::INTEGER],
        )->fetchFirstColumn();

        return array_map(intval(...), $serviceTemplateIds);
    }

    public function findMonitoringConnectorTemplateIds(array $excludedConnectorSlugs): Collection
    {
        if (! $this->moduleRepository->isInstalled(self::PLUGIN_PACK_MANAGER_MODULE)) {
            return new Collection([], HostTemplateId::class);
        }

        // The connector templates, then every template inheriting from them (legacy getHostChain()).
        // UNION dedupes the reached ids, so a template loop already in the data cannot make the
        // recursion run forever.
        [$excludeClause, $params, $types] = $excludedConnectorSlugs === []
            ? ['', [], []]
            : ['WHERE pp.slug NOT IN (:excludedSlugs)', ['excludedSlugs' => $excludedConnectorSlugs], ['excludedSlugs' => ArrayParameterType::STRING]];
        $sql = <<<SQL
            WITH RECURSIVE connector_templates (id) AS (
                SELECT ph.host_id
                FROM mod_ppm_pluginpack_host ph
                INNER JOIN mod_ppm_pluginpack pp ON pp.pluginpack_id = ph.pluginpack_id
                {$excludeClause}
                UNION
                SELECT htr.host_host_id
                FROM host_template_relation htr
                INNER JOIN connector_templates ct ON ct.id = htr.host_tpl_id
            )
            SELECT h.host_id
            FROM host h
            INNER JOIN connector_templates ct ON ct.id = h.host_id
            WHERE h.host_register = '0'
            ORDER BY h.host_id
            SQL;

        /** @var list<int|string> $ids */
        $ids = $this->connection->executeQuery($sql, $params, $types)->fetchFirstColumn();

        return new Collection(
            array_map(static fn (int|string $id): HostTemplateId => new HostTemplateId((int) $id), $ids),
            HostTemplateId::class,
        );
    }

    public function findDescendantIds(HostTemplateId $id): Collection
    {
        // UNION dedupes the reached ids, so a template loop already in the data cannot make the
        // recursion run forever.
        $sql = <<<'SQL'
            WITH RECURSIVE descendants (id) AS (
                SELECT htr.host_host_id
                FROM host_template_relation htr
                INNER JOIN host h ON h.host_id = htr.host_host_id AND h.host_register = '0'
                WHERE htr.host_tpl_id = :id
                UNION
                SELECT htr.host_host_id
                FROM host_template_relation htr
                INNER JOIN descendants d ON d.id = htr.host_tpl_id
                INNER JOIN host h ON h.host_id = htr.host_host_id AND h.host_register = '0'
            )
            SELECT id FROM descendants ORDER BY id
            SQL;

        /** @var list<int|string> $ids */
        $ids = $this->connection->executeQuery($sql, ['id' => $id->value], ['id' => ParameterType::INTEGER])->fetchFirstColumn();

        return new Collection(
            array_map(static fn (int|string $descendantId): HostTemplateId => new HostTemplateId((int) $descendantId), $ids),
            HostTemplateId::class,
        );
    }

    public function findOne(HostTemplateId $id): ?HostTemplate
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select(
            'h.host_id',
            'h.host_name',
            'h.host_alias',
            'h.host_locked',
            'h.host_snmp_community AS snmp_community',
            'h.host_snmp_version AS snmp_version',
            'h.host_location AS timezone_id',
            'h.host_comment AS comment',
            'ehi.ehi_notes_url AS note_url',
            'ehi.ehi_notes AS note',
            'ehi.ehi_action_url AS action_url',
            'ehi.ehi_icon_image AS icon_id',
            'ehi.ehi_icon_image_alt AS alt_icon',
            'h.timeperiod_tp_id AS check_timeperiod_id',
            'h.host_max_check_attempts AS max_check_attempts',
            'h.host_check_interval AS normal_check_interval',
            'h.host_retry_check_interval AS retry_check_interval',
            'h.host_active_checks_enabled AS active_check_enabled',
            'h.host_passive_checks_enabled AS passive_check_enabled',
            'h.host_acknowledgement_timeout AS acknowledgement_timeout',
            'h.host_check_freshness AS check_freshness',
            'h.host_freshness_threshold AS freshness_threshold',
            'h.host_flap_detection_enabled AS flap_detection_enabled',
            'h.host_low_flap_threshold AS low_flap_threshold',
            'h.host_high_flap_threshold AS high_flap_threshold',
            'h.host_event_handler_enabled AS event_handler_enabled',
            'h.command_command_id2 AS event_handler_command_id',
            'h.command_command_id_arg2 AS event_handler_args',
            'h.command_command_id AS check_command_id',
            'h.command_command_id_arg1 AS check_command_args',
            'h.host_notifications_enabled AS notifications_enabled',
            'h.host_notification_options AS notification_options',
            'h.host_notification_interval AS notification_interval',
            'h.timeperiod_tp_id2 AS notification_period_id',
            'h.host_first_notification_delay AS first_notification_delay',
            'h.host_recovery_notification_delay AS recovery_notification_delay',
            'h.contact_additive_inheritance',
            'h.cg_additive_inheritance',
        )
            ->from(self::TABLE_NAME, 'h')
            ->leftJoin('h', 'extended_host_information', 'ehi', 'ehi.host_host_id = h.host_id')
            ->where($qb->expr()->eq('h.host_id', $qb->createNamedParameter($id->value, ParameterType::INTEGER)))
            ->andWhere('h.host_register = ' . $qb->createNamedParameter(self::HOST_TEMPLATE_REGISTER));

        $row = $qb->executeQuery()->fetchAssociative();
        if ($row === false) {
            return null;
        }

        [$categoryIds, $severityId] = $this->findCategoryAndSeverityIds($id->value);

        /** @var FindOneRowTypeAlias $fullRow */
        $fullRow = $row + [
            'parent_template_ids' => $this->findParentTemplateIdValues([$id->value])[$id->value] ?? [],
            'macros' => $this->findMacrosByOwner([$id->value])[$id->value] ?? [],
            'category_ids' => $categoryIds,
            'severity_id' => $severityId,
            'contact_ids' => $this->findRelatedIds('contact_host_relation', 'contact_id', $id->value),
            'contact_group_ids' => $this->findRelatedIds('contactgroup_host_relation', 'contactgroup_cg_id', $id->value),
            'linked_service_template_ids' => $this->findLinkedServiceTemplateIds($id->value),
            'service_template_check_command_ids' => $this->findServiceTemplateCheckCommandIds([$id->value])[$id->value] ?? [],
        ];

        return $this->transformer->transform($fullRow);
    }

    public function isNameUsedByHostOrTemplate(HostTemplateName $name): bool
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('1')
            ->from(self::TABLE_NAME)
            ->where($qb->expr()->eq('host_name', $qb->createNamedParameter($name->value)))
            ->setMaxResults(1);

        return (bool) $qb->executeQuery()->fetchOne();
    }

    public function addCopyOf(HostTemplate $copy, HostTemplateId $sourceId): void
    {
        $source = $sourceId->value;

        $inserted = $this->copyRow(
            self::TABLE_NAME,
            'host_id',
            $source,
            ['host_name' => $copy->name->value, 'host_snmp_community' => $copy->snmpCommunity?->value],
            ['host_register' => self::HOST_TEMPLATE_REGISTER],
        );
        // A source that is no longer a template copies no row.
        if ($inserted !== 1) {
            throw new HostTemplateNotFoundException([$source], 'id');
        }

        $copyId = (int) $this->connection->lastInsertId();
        if ($copyId === 0) {
            throw new \RuntimeException(sprintf('Unable to retrieve last insert ID for "%s".', self::TABLE_NAME));
        }

        $this->setId($copy, new HostTemplateId($copyId));

        $this->copyRow('extended_host_information', 'host_host_id', $source, ['host_host_id' => $copyId]);
        $this->copyRow('on_demand_macro_host', 'host_host_id', $source, ['host_host_id' => $copyId]);
        foreach (['host_template_relation', 'hostcategories_relation', 'contact_host_relation', 'contactgroup_host_relation', 'ns_host_relation'] as $table) {
            $this->copyRow($table, 'host_host_id', $source, ['host_host_id' => $copyId]);
        }

        // Links to the same service templates, never copies of them.
        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO host_service_relation (host_host_id, service_service_id)
                SELECT DISTINCT :copyId, service_service_id
                FROM host_service_relation
                WHERE host_host_id = :sourceId AND service_service_id IS NOT NULL
                SQL,
            ['copyId' => $copyId, 'sourceId' => $source],
            ['copyId' => ParameterType::INTEGER, 'sourceId' => ParameterType::INTEGER],
        );

        // Re-minted secrets replace the copied vault references.
        foreach ($copy->checkOptions->macros as $macro) {
            if (! $macro->isPassword) {
                continue;
            }

            $this->connection->update(
                'on_demand_macro_host',
                ['host_macro_value' => $macro->value],
                ['host_host_id' => $copyId, 'host_macro_name' => $macro->name->toStorageName()],
            );
        }
    }

    public function findPollerIdsOfInheritingHosts(HostTemplateId $id): Collection
    {
        // UNION dedupes, so a template loop cannot recurse forever.
        $sql = <<<'SQL'
            WITH RECURSIVE inheritors (id) AS (
                SELECT host_host_id
                FROM host_template_relation
                WHERE host_tpl_id = :id
                UNION
                SELECT htr.host_host_id
                FROM host_template_relation htr
                INNER JOIN inheritors i ON i.id = htr.host_tpl_id
            )
            SELECT DISTINCT nhr.nagios_server_id
            FROM inheritors i
            INNER JOIN host h ON h.host_id = i.id AND h.host_register = '1' AND h.host_activate = '1'
            INNER JOIN ns_host_relation nhr ON nhr.host_host_id = h.host_id
            ORDER BY nhr.nagios_server_id
            SQL;

        /** @var list<int|string> $pollerIds */
        $pollerIds = $this->connection->executeQuery($sql, ['id' => $id->value], ['id' => ParameterType::INTEGER])
            ->fetchFirstColumn();

        return new Collection(
            array_map(static fn (int|string $pollerId): PollerId => new PollerId((int) $pollerId), $pollerIds),
            PollerId::class,
        );
    }

    public function remove(HostTemplate $hostTemplate): void
    {
        $id = $hostTemplate->id()->value;
        $parentDependencyIds = $this->findDependencyIds('dependency_hostParent_relation', 'host_host_id', $id);
        $childDependencyIds = $this->findDependencyIds('dependency_hostChild_relation', 'host_host_id', $id);

        $qb = $this->connection->createQueryBuilder();
        $qb->delete(self::TABLE_NAME)
            ->where($qb->expr()->eq('host_id', $qb->createNamedParameter($id, ParameterType::INTEGER)))
            ->andWhere('host_register = ' . $qb->createNamedParameter(self::HOST_TEMPLATE_REGISTER))
            ->executeStatement();

        $this->deleteDependenciesWithoutMember('dependency_hostParent_relation', $parentDependencyIds);
        $this->deleteDependenciesWithoutMember('dependency_hostChild_relation', $childDependencyIds);
    }

    public function findInheritanceLine(Collection $directTemplateIds): Collection
    {
        $directIds = array_values(array_map(static fn (HostTemplateId $id): int => $id->value, $directTemplateIds->toArray()));
        if ($directIds === []) {
            return new Collection([], HostTemplate::class);
        }

        // One query fetches every relation reachable from the direct templates through active
        // templates (legacy getTemplateChain: activated, register = '0', at every level, the direct
        // templates included), in `order`; the depth-first walk is done in PHP. UNION dedupes
        // reached ids, so a template loop already in the data cannot make the recursion run forever.
        $sql = <<<'SQL'
            WITH RECURSIVE chain (id) AS (
                SELECT host_id
                FROM host
                WHERE host_id IN (:ids) AND host_activate = '1' AND host_register = '0'
                UNION
                SELECT htr.host_tpl_id
                FROM host_template_relation htr
                INNER JOIN chain c ON c.id = htr.host_host_id
                INNER JOIN host t ON t.host_id = htr.host_tpl_id AND t.host_activate = '1' AND t.host_register = '0'
            )
            SELECT htr.host_host_id, htr.host_tpl_id, t.host_name AS tpl_name, t.command_command_id AS tpl_command_id
            FROM host_template_relation htr
            INNER JOIN chain c ON c.id = htr.host_host_id
            INNER JOIN host t ON t.host_id = htr.host_tpl_id AND t.host_activate = '1' AND t.host_register = '0'
            ORDER BY htr.host_host_id, htr.`order`, htr.host_tpl_id
            SQL;

        /** @var list<array{host_host_id: int|string, host_tpl_id: int|string, tpl_name: string, tpl_command_id: int|string|null}> $rows */
        $rows = $this->connection->executeQuery(
            $sql,
            ['ids' => $directIds],
            ['ids' => ArrayParameterType::INTEGER],
        )->fetchAllAssociative();

        /** @var array<int, list<int>> $parentIdsById */
        $parentIdsById = [];
        $templates = $this->findDirectTemplates($directIds);
        foreach ($rows as $row) {
            $parentIdsById[(int) $row['host_host_id']][] = (int) $row['host_tpl_id'];
            $templates[(int) $row['host_tpl_id']] = [
                'name' => $row['tpl_name'],
                'command_id' => $row['tpl_command_id'] !== null ? (int) $row['tpl_command_id'] : null,
            ];
        }

        $line = [];
        $visited = [];
        foreach ($directIds as $directId) {
            // A direct id that is not an active host template is skipped.
            if (isset($templates[$directId])) {
                $this->walkInheritanceLine($directId, $parentIdsById, $visited, $line);
            }
        }

        $macrosByOwner = $this->findMacrosByOwner($line);
        $serviceTemplateCommandIds = $this->findServiceTemplateCheckCommandIds($line);
        // every stored parent template, active or not, as in findAll(): $parentIdsById has active ones only
        $storedParentIdsById = $this->findParentTemplateIdValues($line);

        return new Collection(
            array_map(
                static fn (int $id): HostTemplate => new HostTemplate(
                    new HostTemplateId($id),
                    new HostTemplateName($templates[$id]['name']),
                    new CheckOptions(
                        $templates[$id]['command_id'] !== null ? new CommandId($templates[$id]['command_id']) : null,
                        macros: $macrosByOwner[$id] ?? [],
                    ),
                    new Collection(
                        array_map(static fn (int $commandId): CommandId => new CommandId($commandId), $serviceTemplateCommandIds[$id] ?? []),
                        CommandId::class,
                    ),
                    parentTemplateIds: new Collection(
                        array_map(static fn (int $parentId): HostTemplateId => new HostTemplateId($parentId), $storedParentIdsById[$id] ?? []),
                        HostTemplateId::class,
                    ),
                ),
                $line,
            ),
            HostTemplate::class,
        );
    }

    /**
     * @param list<int> $idValues host or host template ids
     *
     * @return Collection<MediaId> indexed by the requested id
     */
    private function findInheritedIconIdsOf(array $idValues): Collection
    {
        if ($idValues === []) {
            return new Collection([], MediaId::class);
        }

        // Relations reachable from the requested ids, walked depth-first in PHP; UNION stops loops.
        // An icon whose image sits in no folder is read as no icon, like legacy.
        $sql = <<<'SQL'
            WITH RECURSIVE chain (id) AS (
                SELECT host_host_id
                FROM host_template_relation
                WHERE host_host_id IN (:ids)
                UNION
                SELECT htr.host_tpl_id
                FROM host_template_relation htr
                INNER JOIN chain c ON c.id = htr.host_host_id
            )
            SELECT htr.host_host_id, htr.host_tpl_id, ehi.ehi_icon_image AS icon_id
            FROM host_template_relation htr
            INNER JOIN chain c ON c.id = htr.host_host_id
            LEFT JOIN extended_host_information ehi
                ON ehi.host_host_id = htr.host_tpl_id
                AND EXISTS (
                    SELECT 1
                    FROM view_img_dir_relation vidr
                    INNER JOIN view_img_dir vid ON vid.dir_id = vidr.dir_dir_parent_id
                    WHERE vidr.img_img_id = ehi.ehi_icon_image
                )
            ORDER BY htr.host_host_id, htr.`order`, htr.host_tpl_id
            SQL;

        /** @var list<array{host_host_id: int|string, host_tpl_id: int|string, icon_id: int|string|null}> $rows */
        $rows = $this->connection->executeQuery(
            $sql,
            ['ids' => $idValues],
            ['ids' => ArrayParameterType::INTEGER],
        )->fetchAllAssociative();

        /** @var array<int, list<array{int, ?int}>> $templatesById ordered [template id, icon id] pairs per host or template */
        $templatesById = [];
        foreach ($rows as $row) {
            $templatesById[(int) $row['host_host_id']][] = [
                (int) $row['host_tpl_id'],
                $row['icon_id'] !== null ? (int) $row['icon_id'] : null,
            ];
        }

        $iconIds = [];
        foreach ($idValues as $hostId) {
            $visited = [$hostId => true];
            $iconId = $this->findFirstIconId($hostId, $templatesById, $visited);
            if ($iconId !== null) {
                $iconIds[$hostId] = new MediaId($iconId);
            }
        }

        return new Collection($iconIds, MediaId::class);
    }

    /**
     * The check commands of the service templates linked to each template, like legacy
     * CentreonHost::getServicesTemplates(): each linked service template (register = '0', in
     * relation order) followed by its own templates, nearest first, keeping those with a command.
     *
     * @param list<int> $templateIds
     *
     * @return array<int, list<int>> command ids indexed by host template id
     */
    private function findServiceTemplateCheckCommandIds(array $templateIds): array
    {
        if ($templateIds === []) {
            return [];
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->select('hsr.host_host_id', 's.service_id')
            ->from('host_service_relation', 'hsr')
            ->innerJoin('hsr', 'service', 's', "s.service_id = hsr.service_service_id AND s.service_register = '0'")
            ->where($qb->expr()->in('hsr.host_host_id', $qb->createNamedParameter($templateIds, ArrayParameterType::INTEGER)))
            ->orderBy('hsr.hsr_id');

        /** @var list<array{host_host_id: int|string, service_id: int|string}> $links */
        $links = $qb->executeQuery()->fetchAllAssociative();
        if ($links === []) {
            return [];
        }

        $services = $this->findServiceTemplateChains(array_map(static fn (array $link): int => (int) $link['service_id'], $links));

        $commandIds = [];
        foreach ($links as $link) {
            $serviceId = (int) $link['service_id'];
            $visited = [];
            while ($serviceId !== null && isset($services[$serviceId]) && ! isset($visited[$serviceId])) {
                $visited[$serviceId] = true;
                if ($services[$serviceId]['command_id'] !== null) {
                    $commandIds[(int) $link['host_host_id']][] = $services[$serviceId]['command_id'];
                }
                $serviceId = $services[$serviceId]['parent_id'];
            }
        }

        return $commandIds;
    }

    /**
     * The given services and every template they inherit from, through service_template_model_stm_id.
     *
     * @param list<int> $serviceIds
     *
     * @return array<int, array{command_id: ?int, parent_id: ?int}> indexed by service id
     */
    private function findServiceTemplateChains(array $serviceIds): array
    {
        $services = [];
        $toFetch = array_values(array_unique($serviceIds));
        while ($toFetch !== []) {
            $qb = $this->connection->createQueryBuilder();
            $qb->select('service_id', 'command_command_id', 'service_template_model_stm_id')
                ->from('service')
                ->where($qb->expr()->in('service_id', $qb->createNamedParameter($toFetch, ArrayParameterType::INTEGER)));

            /** @var list<array{service_id: int|string, command_command_id: int|string|null, service_template_model_stm_id: int|string|null}> $rows */
            $rows = $qb->executeQuery()->fetchAllAssociative();

            $toFetch = [];
            foreach ($rows as $row) {
                $parentId = $row['service_template_model_stm_id'] !== null ? (int) $row['service_template_model_stm_id'] : null;
                $services[(int) $row['service_id']] = [
                    'command_id' => $row['command_command_id'] !== null ? (int) $row['command_command_id'] : null,
                    'parent_id' => $parentId,
                ];
                if ($parentId !== null && ! isset($services[$parentId])) {
                    $toFetch[] = $parentId;
                }
            }
            $toFetch = array_values(array_diff(array_unique($toFetch), array_keys($services)));
        }

        return $services;
    }

    /**
     * Legacy CentreonHost::getTemplateChain() walk: the template, then each of its own templates in
     * order, depth-first; a template already walked keeps its nearest position.
     *
     * @param array<int, list<int>> $parentIdsById
     * @param array<int, true> $visited
     * @param list<int> $line
     */
    private function walkInheritanceLine(int $id, array $parentIdsById, array &$visited, array &$line): void
    {
        if (isset($visited[$id])) {
            return;
        }
        $visited[$id] = true;
        $line[] = $id;

        foreach ($parentIdsById[$id] ?? [] as $parentId) {
            $this->walkInheritanceLine($parentId, $parentIdsById, $visited, $line);
        }
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, array{name: string, command_id: ?int}> indexed by id, active host templates only
     */
    private function findDirectTemplates(array $ids): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('host_id', 'host_name', 'command_command_id')
            ->from(self::TABLE_NAME)
            ->where('host_register = ' . $qb->createNamedParameter(self::HOST_TEMPLATE_REGISTER))
            ->andWhere("host_activate = '1'")
            ->andWhere($qb->expr()->in('host_id', $qb->createNamedParameter($ids, ArrayParameterType::INTEGER)));

        /** @var list<array{host_id: int|string, host_name: string, command_command_id: int|string|null}> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        $templates = [];
        foreach ($rows as $row) {
            $templates[(int) $row['host_id']] = [
                'name' => $row['host_name'],
                'command_id' => $row['command_command_id'] !== null ? (int) $row['command_command_id'] : null,
            ];
        }

        return $templates;
    }

    /**
     * The custom macros the given templates own, grouped by template, in insertion order.
     *
     * @param list<int> $ownerIds
     *
     * @return array<int, list<HostMacro>>
     */
    private function findMacrosByOwner(array $ownerIds): array
    {
        if ($ownerIds === []) {
            return [];
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->select('host_macro_id', 'host_host_id', 'host_macro_name', 'host_macro_value', 'is_password')
            ->from('on_demand_macro_host')
            ->where($qb->expr()->in('host_host_id', $qb->createNamedParameter($ownerIds, ArrayParameterType::INTEGER)))
            ->orderBy('host_macro_id');

        /** @var list<array{host_macro_id: int|string, host_host_id: int|string, host_macro_name: string, host_macro_value: string, is_password: int|string|null}> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        $byOwner = [];
        foreach ($rows as $row) {
            // Stored as the engine form $_HOST<NAME>$; a row not in that form is not a host macro.
            if (preg_match('/^\$_HOST(.+)\$$/', $row['host_macro_name'], $matches) !== 1) {
                continue;
            }

            $byOwner[(int) $row['host_host_id']][] = new HostMacro(
                new HostMacroName($matches[1]),
                $row['host_macro_value'],
                isPassword: (bool) (int) ($row['is_password'] ?? 0),
                id: new HostMacroId((int) $row['host_macro_id']),
            );
        }

        return $byOwner;
    }

    /**
     * Legacy getMyHostExtendedInfoImage() walk: for each template in order, its own icon wins,
     * otherwise its own templates are searched before moving on to the next one.
     *
     * @param array<int, list<array{int, ?int}>> $templatesById
     * @param array<int, true> $visited guards against template loops
     */
    private function findFirstIconId(int $id, array $templatesById, array &$visited): ?int
    {
        foreach ($templatesById[$id] ?? [] as [$templateId, $iconId]) {
            if (isset($visited[$templateId])) {
                continue;
            }
            $visited[$templateId] = true;

            $iconId ??= $this->findFirstIconId($templateId, $templatesById, $visited);
            if ($iconId !== null) {
                return $iconId;
            }
        }

        return null;
    }

    /**
     * @param AccessScope<HostSeverityId> $scope
     */
    private function filterByCriteria(QueryBuilder $qb, HostTemplateCriteria $criteria, AccessScope $scope): void
    {
        if (($id = $criteria->getId()) instanceof HostTemplateId) {
            $qb->andWhere($qb->expr()->eq('h.host_id', $qb->createNamedParameter($id->value, ParameterType::INTEGER)));
        }

        if (($name = $criteria->getName()) !== null) {
            $qb->andWhere($qb->expr()->like('h.host_name', $qb->createNamedParameter('%' . $name . '%')));
        }

        if (($nameOrAlias = $criteria->getNameOrAlias()) !== null) {
            $pattern = $qb->createNamedParameter('%' . $nameOrAlias . '%');
            $qb->andWhere($qb->expr()->or(
                $qb->expr()->like('h.host_name', $pattern),
                $qb->expr()->like('h.host_alias', $pattern),
            ));
        }

        if ($criteria->isLockedExcluded()) {
            // like legacy's "AND host_locked = 0", which also leaves out a NULL host_locked
            $qb->andWhere('h.host_locked = 0');
        }

        if (($excludedIds = $criteria->getExcludedIds()) !== []) {
            $qb->andWhere($qb->expr()->notIn('h.host_id', $qb->createNamedParameter($excludedIds, ArrayParameterType::INTEGER)));
        }

        $this->filterByAccessScope($qb, $scope);
    }

    /**
     * @param AccessScope<HostSeverityId> $scope
     */
    private function filterByAccessScope(QueryBuilder $qb, AccessScope $scope): void
    {
        if ($scope->isUnrestricted()) {
            return;
        }

        // Restricted actor: keep only templates linked to an accessible host severity. This mirrors
        // legacy findByRequestParametersAndAccessGroups, whose "AND hc.hc_id IN (...)" is applied on a
        // join filtered by "hc.level IS NOT NULL", so a template linked only to a regular (levelless)
        // category — or to none — is excluded. The accessible set is already severity-scoped (see
        // ResourceAccessRepository::findHostSeverityAccessScope), so matching the relation id
        // directly is enough. EXISTS rather than a JOIN keeps LIMIT/OFFSET pagination correct, as
        // hostcategories_relation carries no per-host uniqueness.
        $qb->andWhere(
            'EXISTS (
                SELECT 1 FROM hostcategories_relation hcr
                WHERE hcr.host_host_id = h.host_id
                    AND hcr.hostcategories_hc_id IN (' . $qb->createNamedParameter(
                $scope->allowedIdValues(),
                ArrayParameterType::INTEGER
            ) . ')
            )'
        );
    }

    /**
     * @param array<DbRowTypeAlias> $rows
     *
     * @return Collection<HostTemplate>
     */
    private function createHostTemplates(array $rows): Collection
    {
        $parentIdsById = $this->findParentTemplateIdValues(
            array_values(array_map(static fn (array $row): int => $row['host_id'], $rows))
        );

        return new Collection(
            array_map(
                fn (array $row): HostTemplate => $this->transformer->transform(
                    $row + ['parent_template_ids' => $parentIdsById[$row['host_id']] ?? []]
                ),
                $rows
            ),
            HostTemplate::class
        );
    }

    /**
     * The levelless categories, in id order, and the severity: the lowest level, then the lowest id.
     *
     * @return array{list<int>, ?int}
     */
    private function findCategoryAndSeverityIds(int $templateId): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('hcr.hostcategories_hc_id AS id', 'hc.level')
            ->from('hostcategories_relation', 'hcr')
            ->innerJoin('hcr', 'hostcategories', 'hc', 'hc.hc_id = hcr.hostcategories_hc_id')
            ->where($qb->expr()->eq('hcr.host_host_id', $qb->createNamedParameter($templateId, ParameterType::INTEGER)))
            ->orderBy('hc.level')
            ->addOrderBy('hcr.hostcategories_hc_id');

        /** @var list<array{id: int|string, level: int|string|null}> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        $categoryIds = [];
        $severityId = null;
        foreach ($rows as $row) {
            if ($row['level'] === null) {
                $categoryIds[(int) $row['id']] = (int) $row['id'];
            } else {
                $severityId ??= (int) $row['id'];
            }
        }

        return [array_values($categoryIds), $severityId];
    }

    /**
     * @param non-empty-string $table a relation table keyed by `host_host_id`
     * @param non-empty-string $column the related id column
     *
     * @return list<int> in id order, each once
     */
    private function findRelatedIds(string $table, string $column, int $templateId): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('DISTINCT ' . $column)
            ->from($table)
            ->where($qb->expr()->eq('host_host_id', $qb->createNamedParameter($templateId, ParameterType::INTEGER)))
            ->orderBy($column);

        /** @var list<int|string> $ids */
        $ids = $qb->executeQuery()->fetchFirstColumn();

        return array_map(intval(...), $ids);
    }

    /**
     * The service templates linked to the template, in relation order, each once at its first position.
     *
     * @return list<int>
     */
    private function findLinkedServiceTemplateIds(int $templateId): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('hsr.service_service_id')
            ->from('host_service_relation', 'hsr')
            ->innerJoin('hsr', 'service', 's', "s.service_id = hsr.service_service_id AND s.service_register = '0'")
            ->where($qb->expr()->eq('hsr.host_host_id', $qb->createNamedParameter($templateId, ParameterType::INTEGER)))
            ->orderBy('hsr.hsr_id');

        /** @var list<int|string> $ids */
        $ids = $qb->executeQuery()->fetchFirstColumn();

        return array_values(array_unique(array_map(intval(...), $ids)));
    }

    /**
     * The direct parent templates of the given templates, in relation `order`, in one query.
     *
     * @param list<int> $templateIds
     *
     * @return array<int, list<int>> parent template ids indexed by template id
     */
    private function findParentTemplateIdValues(array $templateIds): array
    {
        if ($templateIds === []) {
            return [];
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->select('htr.host_host_id', 'htr.host_tpl_id')
            ->from('host_template_relation', 'htr')
            ->innerJoin('htr', self::TABLE_NAME, 'p', 'p.host_id = htr.host_tpl_id AND p.host_register = ' . $qb->createNamedParameter(self::HOST_TEMPLATE_REGISTER))
            ->where($qb->expr()->in('htr.host_host_id', $qb->createNamedParameter($templateIds, ArrayParameterType::INTEGER)))
            ->orderBy('htr.host_host_id')
            ->addOrderBy('htr.`order`')
            ->addOrderBy('htr.host_tpl_id');

        /** @var list<array{host_host_id: int|string, host_tpl_id: int|string}> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        $parentIdsById = [];
        foreach ($rows as $row) {
            $parentIdsById[(int) $row['host_host_id']][] = (int) $row['host_tpl_id'];
        }

        return $parentIdsById;
    }

    /**
     * Copies the $table rows of $sourceId, all columns but auto-increment, generated and $overrides ones.
     *
     * @param non-empty-string $table
     * @param non-empty-string $ownerColumn
     * @param array<string, int|string|null> $overrides
     * @param array<string, string> $conditions extra column = value filters on the source rows
     *
     * @return int the number of rows copied
     */
    private function copyRow(
        string $table,
        string $ownerColumn,
        int $sourceId,
        array $overrides,
        array $conditions = [],
    ): int {
        /** @var list<string> $columns */
        $columns = $this->connection->fetchFirstColumn(
            <<<'SQL'
                SELECT COLUMN_NAME
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table
                  AND EXTRA NOT LIKE '%auto_increment%'
                  AND EXTRA NOT LIKE '% GENERATED'
                ORDER BY ORDINAL_POSITION
                SQL,
            ['table' => $table],
        );

        $selected = [];
        $parameters = ['sourceId' => $sourceId];
        $types = ['sourceId' => ParameterType::INTEGER];
        foreach ($columns as $index => $column) {
            if (array_key_exists($column, $overrides)) {
                $selected[] = ':override' . $index;
                $parameters['override' . $index] = $overrides[$column];
                $types['override' . $index] = is_int($overrides[$column]) ? ParameterType::INTEGER : ParameterType::STRING;
            } else {
                $selected[] = $this->connection->quoteSingleIdentifier($column);
            }
        }

        $where = [$this->connection->quoteSingleIdentifier($ownerColumn) . ' = :sourceId'];
        foreach (array_keys($conditions) as $index => $column) {
            $where[] = $this->connection->quoteSingleIdentifier($column) . ' = :condition' . $index;
            $parameters['condition' . $index] = $conditions[$column];
            $types['condition' . $index] = ParameterType::STRING;
        }

        return (int) $this->connection->executeStatement(
            sprintf(
                'INSERT INTO %1$s (%2$s) SELECT %3$s FROM %1$s WHERE %4$s',
                $this->connection->quoteSingleIdentifier($table),
                implode(', ', array_map($this->connection->quoteSingleIdentifier(...), $columns)),
                implode(', ', $selected),
                implode(' AND ', $where),
            ),
            $parameters,
            $types,
        );
    }

    private function paginate(QueryBuilder $qb, HostTemplateCriteria $criteria): void
    {
        $pagination = $criteria->getPagination();
        if (! $pagination instanceof \App\Shared\Domain\Repository\Pagination) {
            return;
        }

        $qb->setFirstResult($pagination->getOffset())
            ->setMaxResults($pagination->itemsPerPage);
    }
}
