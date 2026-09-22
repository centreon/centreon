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

use App\MonitoringConfiguration\Domain\Aggregate\Host\GeoCoordinates;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Repository\Criteria\HostCriteria;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\MonitoringConfiguration\Infrastructure\Service\CheckCommandArgumentsFormatter;
use App\Security\Domain\Aggregate\AccessGroupId;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Domain\Repository\AccessGroupRepository;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\Dbal\DbalCriteriaApplierTrait;
use App\Shared\Infrastructure\Dbal\DbalRepository;
use App\Shared\Infrastructure\Dbal\TriStateColumnTrait;
use App\Shared\Infrastructure\InMemory\InMemoryPaginator;
use App\Shared\Infrastructure\TransformerInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @phpstan-type RowTypeAlias = array{
 *   id: int,
 *   name: string,
 *   alias: string|null,
 *   ip_address: string,
 *   is_activated: string,
 *   poller_id: int,
 *   template_ids: string|null,
 *   group_ids: string|null,
 *   icon_id: int|null,
 * }
 */
final readonly class DbalHostRepository extends DbalRepository implements HostRepository
{
    use DbalCriteriaApplierTrait;
    use TriStateColumnTrait;
    public const TABLE_NAME = 'host';

    /**
     * @param TransformerInterface<RowTypeAlias, Host> $transformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
        #[Autowire(service: 'doctrine.dbal.realtime_connection')]
        private Connection $realTimeConnection,
        #[Autowire(service: DbalHostTransformer::class)]
        private TransformerInterface $transformer,
        private AccessGroupRepository $accessGroupRepository,
    ) {
    }

    public function add(Host $host): void
    {
        $dataProcessing = $host->dataProcessing;
        $extendedInformations = $host->extendedInformations;
        $schedulingOptions = $host->schedulingOptions;

        $qb = $this->connection->createQueryBuilder();
        $qb->insert(self::TABLE_NAME)
            ->values([
                'host_name' => ':name',
                'host_address' => ':address',
                'host_alias' => ':alias',
                'host_activate' => ':is_activated',
                'host_register' => "'1'",
                'host_acknowledgement_timeout' => ':ackTimeout',
                'host_check_freshness' => ':checkFreshness',
                'host_freshness_threshold' => ':freshnessThreshold',
                'host_flap_detection_enabled' => ':flapDetectionEnabled',
                'host_low_flap_threshold' => ':lowFlapThreshold',
                'host_high_flap_threshold' => ':highFlapThreshold',
                'host_event_handler_enabled' => ':eventHandlerEnabled',
                'command_command_id2' => ':eventHandlerCommandId',
                'command_command_id_arg2' => ':eventHandlerArgs',
                'host_snmp_version' => ':snmpVersion',
                'host_snmp_community' => ':snmpCommunity',
                'host_location' => ':timezoneId', // the timezone, despite the legacy column name
                'geo_coords' => ':geoCoords',
                'host_comment' => ':comment',
                'timeperiod_tp_id' => ':checkTimeperiodId',
                'host_max_check_attempts' => ':maxCheckAttempts',
                'host_check_interval' => ':normalCheckInterval',
                'host_retry_check_interval' => ':retryCheckInterval',
                'host_active_checks_enabled' => ':activeCheckEnabled',
                'host_passive_checks_enabled' => ':passiveCheckEnabled',
                'command_command_id' => ':check_command_id',
                'command_command_id_arg1' => ':check_command_args',
            ])
            ->setParameter('name', $host->name->value)
            ->setParameter('address', $host->address->value)
            // NULL where legacy stores '': config generation skips both identically.
            ->setParameter('alias', $host->alias?->value)
            ->setParameter('is_activated', $host->activated ? '1' : '0')
            ->setParameter('ackTimeout', $dataProcessing->acknowledgmentTimeout, ParameterType::INTEGER)
            ->setParameter('checkFreshness', $this->triStateToColumn($dataProcessing->checkFreshness))
            ->setParameter('freshnessThreshold', $dataProcessing->freshnessThreshold, ParameterType::INTEGER)
            ->setParameter('flapDetectionEnabled', $this->triStateToColumn($dataProcessing->flapDetectionEnabled))
            ->setParameter('lowFlapThreshold', $dataProcessing->lowFlapThreshold, ParameterType::INTEGER)
            ->setParameter('highFlapThreshold', $dataProcessing->highFlapThreshold, ParameterType::INTEGER)
            ->setParameter('eventHandlerEnabled', $this->triStateToColumn($dataProcessing->eventHandlerEnabled))
            ->setParameter('eventHandlerCommandId', $dataProcessing->eventHandlerCommandId?->value, ParameterType::INTEGER)
            ->setParameter('eventHandlerArgs', $this->joinCommandArgsForLegacyColumn($dataProcessing->eventHandlerArgs))
            ->setParameter('snmpVersion', $host->snmpVersion?->value)
            ->setParameter('snmpCommunity', $host->snmpCommunity?->value)
            ->setParameter('timezoneId', $host->timezoneId?->value)
            ->setParameter('geoCoords', $extendedInformations?->geoCoordinates instanceof GeoCoordinates ? (string) $extendedInformations->geoCoordinates : null)
            ->setParameter('comment', $extendedInformations?->comment)
            ->setParameter('checkTimeperiodId', $schedulingOptions->checkTimeperiodId?->value, ParameterType::INTEGER)
            ->setParameter('maxCheckAttempts', $schedulingOptions->maxCheckAttempts, ParameterType::INTEGER)
            ->setParameter('normalCheckInterval', $schedulingOptions->normalCheckInterval, ParameterType::INTEGER)
            ->setParameter('retryCheckInterval', $schedulingOptions->retryCheckInterval, ParameterType::INTEGER)
            ->setParameter('activeCheckEnabled', $this->triStateToColumn($schedulingOptions->activeCheckEnabled))
            ->setParameter('passiveCheckEnabled', $this->triStateToColumn($schedulingOptions->passiveCheckEnabled))
            ->setParameter('check_command_id', $host->checkOptions->checkCommandId?->value)
            ->setParameter('check_command_args', CheckCommandArgumentsFormatter::format($host->checkOptions->args))
            ->executeStatement();

        $hostId = (int) $this->connection->lastInsertId();
        if ($hostId === 0) {
            throw new \RuntimeException(sprintf('Unable to retrieve last insert ID for "%s".', self::TABLE_NAME));
        }

        $this->setId($host, new HostId($hostId));

        // Every host row has a companion row here, even when Extended Informations were left
        // empty: legacy always inserts it (DbWriteHostRepository::addExtendedInformations()), and
        // other parts of the application already assume it exists.
        $this->connection->createQueryBuilder()
            ->insert('extended_host_information')
            ->values([
                'host_host_id' => ':hostId',
                'ehi_notes_url' => ':noteUrl',
                'ehi_notes' => ':note',
                'ehi_action_url' => ':actionUrl',
                'ehi_icon_image' => ':iconId',
                'ehi_icon_image_alt' => ':iconAlternative',
            ])
            ->setParameter('hostId', $hostId)
            ->setParameter('noteUrl', $extendedInformations?->noteUrl)
            ->setParameter('note', $extendedInformations?->note)
            ->setParameter('actionUrl', $extendedInformations?->actionUrl)
            ->setParameter('iconId', $extendedInformations?->iconId?->value)
            ->setParameter('iconAlternative', $extendedInformations?->altIcon)
            ->executeStatement();

        $this->connection->createQueryBuilder()
            ->insert('ns_host_relation')
            ->values(['host_host_id' => ':hostId', 'nagios_server_id' => ':pollerId'])
            ->setParameter('hostId', $hostId)
            ->setParameter('pollerId', $host->pollerId->value)
            ->executeStatement();

        foreach ($host->hostGroupIds as $hostGroupId) {
            $this->connection->createQueryBuilder()
                ->insert('hostgroup_relation')
                ->values(['hostgroup_hg_id' => ':groupId', 'host_host_id' => ':hostId'])
                ->setParameter('groupId', $hostGroupId->value)
                ->setParameter('hostId', $hostId)
                ->executeStatement();
        }

        // Categories and the severity share this table, told apart only by `hostcategories.level`.
        foreach ($host->categoryIds as $categoryId) {
            $this->linkToHostCategory($hostId, $categoryId->value);
        }

        if ($host->severityId instanceof HostSeverityId) {
            $this->linkToHostCategory($hostId, $host->severityId->value);
        }

        // Contiguous, unlike AddHost, which leaves gaps from the array_unique() keys.
        $order = 0;
        foreach ($host->templateIds as $templateId) {
            $this->connection->createQueryBuilder()
                ->insert('host_template_relation')
                ->values(['host_tpl_id' => ':templateId', 'host_host_id' => ':hostId', '`order`' => ':order'])
                ->setParameter('templateId', $templateId->value)
                ->setParameter('hostId', $hostId)
                ->setParameter('order', $order++)
                ->executeStatement();
        }

        foreach ($host->parentHostIds as $parentHostId) {
            $this->insertParentRelation(parentId: $parentHostId->value, childId: $hostId);
        }

        foreach ($host->childHostIds as $childHostId) {
            $this->insertParentRelation(parentId: $hostId, childId: $childHostId->value);
        }
    }

    /**
     * No unique index backs `host_name` at the DB level (verified against the live schema) —
     * legacy has the same gap, this is a pre-existing, accepted race window, not something
     * introduced here. This is the only safeguard against a duplicate name.
     */
    public function isNameUsedByHostOrTemplate(HostName $name): bool
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('1')
            ->from(self::TABLE_NAME)
            ->where($qb->expr()->eq('host_name', $qb->createNamedParameter($name->value)))
            ->setMaxResults(1);

        return (bool) $qb->executeQuery()->fetchOne();
    }

    public function findAll(?HostCriteria $criteria = null): \IteratorAggregate&\Countable
    {
        $accessibleHostIds = null;
        if (($viewerId = $criteria?->getViewerId()) instanceof UserId) {
            $accessibleHostIds = $this->findAccessibleHostIds($viewerId);
            if ($accessibleHostIds === []) {
                return new Collection([], Host::class);
            }
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->select(...self::getSelectColumns())
            ->from(self::TABLE_NAME, 'h')
            ->leftJoin('h', 'ns_host_relation', 'nsr', 'nsr.host_host_id = h.host_id')
            ->innerJoin('nsr', 'nagios_server', 'ns', 'ns.id = nsr.nagios_server_id')
            ->leftJoin('h', 'hostgroup_relation', 'hgr', 'hgr.host_host_id = h.host_id')
            ->leftJoin('h', 'extended_host_information', 'ehi', 'ehi.host_host_id = h.host_id')
            ->andWhere("h.host_register = '1'")
            ->groupBy('h.host_id', 'nsr.nagios_server_id', 'ehi.ehi_icon_image')
            ->orderBy('h.host_id'); // required for deterministic pagination

        if ($accessibleHostIds !== null) {
            $qb->andWhere($qb->expr()->in(
                'h.host_id',
                $qb->createNamedParameter($accessibleHostIds, ArrayParameterType::INTEGER)
            ));
        }

        if ($criteria instanceof HostCriteria) {
            $this->filterByHostCriteria($qb, $criteria);
        }

        $pagination = $criteria?->getPagination();
        if (! $pagination instanceof \App\Shared\Domain\Repository\Pagination) {
            /** @var array<RowTypeAlias> $rows */
            $rows = $qb->executeQuery()->fetchAllAssociative();

            return new Collection(array_map($this->createHost(...), $rows), Host::class);
        }

        $this->paginate($qb, $criteria);

        // total across all pages: countMatching clones $qb, strips its sort/pagination
        // and resets its GROUP BY (needed here to collapse the host-group join),
        // so the count is unaffected by the pagination applied above.
        $count = $this->countMatching($qb, 'COUNT(DISTINCT h.host_id)');

        /** @var array<RowTypeAlias> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return new InMemoryPaginator(
            items: new Collection(array_map($this->createHost(...), $rows), Host::class),
            totalItems: $count,
            currentPage: $pagination->page,
            itemsPerPage: $pagination->itemsPerPage,
        );
    }

    /**
     * @return array<string>
     */
    public static function getSelectColumns(string $alias = 'h'): array
    {
        return [
            "{$alias}.host_id AS id",
            "{$alias}.host_name AS name",
            "{$alias}.host_alias AS alias",
            "{$alias}.host_address AS ip_address",
            "{$alias}.host_activate AS is_activated",
            'nsr.nagios_server_id AS poller_id',
            // A correlated subquery, not a GROUP_CONCAT over the join, because the position in
            // this list is the inheritance order the aggregate promises.
            "(SELECT GROUP_CONCAT(htr.host_tpl_id ORDER BY htr.`order`)
                FROM host_template_relation htr
                WHERE htr.host_host_id = {$alias}.host_id) AS template_ids",
            'GROUP_CONCAT(DISTINCT hgr.hostgroup_hg_id) AS group_ids',
            'ehi.ehi_icon_image AS icon_id',
        ];
    }

    public function findNamesByIds(Collection $ids): Collection
    {
        $idValues = array_map(static fn (HostId $id): int => $id->value, $ids->toArray());
        if ($idValues === []) {
            return new Collection([], HostName::class);
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->select('host_id', 'host_name')
            ->from(self::TABLE_NAME)
            ->where("host_register = '1'")
            ->andWhere($qb->expr()->in('host_id', $qb->createNamedParameter($idValues, ArrayParameterType::INTEGER)));

        /** @var list<array{host_id: int|string, host_name: string}> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        // `host_name` is nullable; a nameless row reads as "not found".
        $names = [];
        foreach ($rows as $row) {
            if (($name = (string) $row['host_name']) !== '') {
                $names[(int) $row['host_id']] = new HostName($name);
            }
        }

        return new Collection($names, HostName::class);
    }

    public function findAncestorIds(Collection $ids): Collection
    {
        $idValues = array_map(static fn (HostId $id): int => $id->value, $ids->toArray());
        if ($idValues === []) {
            return new Collection([], HostId::class);
        }

        // UNION, not UNION ALL: a loop already in the data cannot hang the query.
        $sql = <<<'SQL'
            WITH RECURSIVE ancestors (host_id) AS (
                SELECT h.host_id
                FROM host h
                WHERE h.host_id IN (:ids)
                UNION
                SELECT hhr.host_parent_hp_id
                FROM host_hostparent_relation hhr
                INNER JOIN ancestors a ON a.host_id = hhr.host_host_id
                WHERE hhr.host_parent_hp_id IS NOT NULL
            )
            SELECT host_id FROM ancestors
            SQL;

        /** @var list<array{host_id: int|string}> $rows */
        $rows = $this->connection->executeQuery(
            $sql,
            ['ids' => $idValues],
            ['ids' => ArrayParameterType::INTEGER],
        )->fetchAllAssociative();

        return new Collection(
            array_map(static fn (array $row): HostId => new HostId((int) $row['host_id']), $rows),
            HostId::class,
        );
    }

    /**
     * Store the event-handler command arguments the legacy way: each argument prefixed with "!" and
     * concatenated; an empty list stores NULL. Arguments are validated upstream to contain no "!"
     * delimiter (nor \n\t\r), so no escaping is needed here.
     *
     * @param list<string> $args
     */
    private function joinCommandArgsForLegacyColumn(array $args): ?string
    {
        if ($args === []) {
            return null;
        }

        // Arguments are validated upstream (DataProcessingInput / DataProcessing) to contain neither
        // the '!' delimiter nor the \n\t\r characters the legacy codec would encode, so a plain
        // '!'-prefixed join is unambiguous and round-trips through the reader.
        return '!' . implode('!', $args);
    }

    /**
     * Host-level ACL is a real-time cache (`centreon_acl`, on a separate connection than the
     * `host` table) mapping accessible host ids per Access Group — mirrors legacy exactly.
     * Kept as two bounded queries (Access Group ids, then host ids), never a per-host lookup:
     * the query count stays constant regardless of how many hosts exist or are returned.
     *
     * @return list<int>
     */
    private function findAccessibleHostIds(UserId $userId): array
    {
        $groupIds = array_map(
            static fn (AccessGroupId $id): int => $id->value,
            iterator_to_array($this->accessGroupRepository->findActiveGroupIdsForUser($userId)),
        );

        if ($groupIds === []) {
            return [];
        }

        $qb = $this->realTimeConnection->createQueryBuilder();
        $qb->select('DISTINCT host_id')
            ->from('centreon_acl')
            ->where('service_id IS NULL')
            ->andWhere($qb->expr()->in('group_id', $qb->createNamedParameter($groupIds, ArrayParameterType::INTEGER)));

        /** @var list<array{host_id: int|string}> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return array_map(static fn (array $row): int => (int) $row['host_id'], $rows);
    }

    private function filterByHostCriteria(QueryBuilder $qb, HostCriteria $criteria): void
    {
        if (($name = $criteria->getName()) !== null) {
            $qb->andWhere($qb->expr()->like('h.host_name', $qb->createNamedParameter('%' . $name . '%')));
        }

        if (($templateId = $criteria->getTemplateId()) !== null) {
            $qb->andWhere(sprintf(
                'EXISTS (
                    SELECT 1 FROM host_template_relation htplf
                    WHERE htplf.host_host_id = h.host_id AND htplf.host_tpl_id = %s
                )',
                $qb->createNamedParameter($templateId, ParameterType::INTEGER)
            ));
        }

        if (($groupId = $criteria->getGroupId()) !== null) {
            $qb->andWhere(sprintf(
                'EXISTS (
                    SELECT 1 FROM hostgroup_relation hgrf
                    WHERE hgrf.host_host_id = h.host_id AND hgrf.hostgroup_hg_id = %s
                )',
                $qb->createNamedParameter($groupId, ParameterType::INTEGER)
            ));
        }

        if (($pollerId = $criteria->getPollerId()) !== null) {
            $qb->andWhere($qb->expr()->eq('nsr.nagios_server_id', $qb->createNamedParameter($pollerId, ParameterType::INTEGER)));
        }

        if (($activated = $criteria->getActivated()) !== null) {
            $qb->andWhere($qb->expr()->eq('h.host_activate', $qb->createNamedParameter($activated ? '1' : '0')));
        }
    }

    private function paginate(QueryBuilder $qb, HostCriteria $criteria): void
    {
        $pagination = $criteria->getPagination();
        if (! $pagination instanceof \App\Shared\Domain\Repository\Pagination) {
            return;
        }

        $qb->setFirstResult($pagination->getOffset())
            ->setMaxResults($pagination->itemsPerPage);
    }

    /**
     * @param RowTypeAlias $row
     */
    private function createHost(array $row): Host
    {
        return $this->transformer->transform($row);
    }

    private function linkToHostCategory(int $hostId, int $hostCategoryId): void
    {
        $this->connection->createQueryBuilder()
            ->insert('hostcategories_relation')
            ->values(['hostcategories_hc_id' => ':categoryId', 'host_host_id' => ':hostId'])
            ->setParameter('categoryId', $hostCategoryId)
            ->setParameter('hostId', $hostId)
            ->executeStatement();
    }

    private function insertParentRelation(int $parentId, int $childId): void
    {
        $this->connection->createQueryBuilder()
            ->insert('host_hostparent_relation')
            ->values(['host_parent_hp_id' => ':parentId', 'host_host_id' => ':childId'])
            ->setParameter('parentId', $parentId)
            ->setParameter('childId', $childId)
            ->executeStatement();
    }
}
