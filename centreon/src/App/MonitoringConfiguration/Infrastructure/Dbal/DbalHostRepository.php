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

use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Repository\Criteria\HostCriteria;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\Security\Domain\Aggregate\AccessGroupId;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Domain\Repository\AccessGroupRepository;
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
 * @phpstan-type RowTypeAlias = array{
 *   id: int,
 *   name: string,
 *   alias: string|null,
 *   ip_address: string,
 *   is_activated: string,
 *   poller_id: int,
 *   template_ids: string|null,
 *   group_ids: string|null,
 * }
 */
final readonly class DbalHostRepository extends DbalRepository implements HostRepository
{
    use DbalCriteriaApplierTrait;
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
        $qb = $this->connection->createQueryBuilder();
        $qb->insert(self::TABLE_NAME)
            ->values([
                'host_name' => ':name',
                'host_address' => ':address',
                'host_alias' => ':alias',
                'host_activate' => ':is_activated',
                'host_register' => "'1'",
            ])
            ->setParameter('name', $host->name->value)
            ->setParameter('address', $host->address->value)
            ->setParameter('alias', $host->alias?->value)
            ->setParameter('is_activated', $host->activated ? '1' : '0')
            ->executeStatement();

        $hostId = (int) $this->connection->lastInsertId();
        if ($hostId === 0) {
            throw new \RuntimeException(sprintf('Unable to retrieve last insert ID for "%s".', self::TABLE_NAME));
        }

        $this->setId($host, new HostId($hostId));

        // Every host row has a companion row here, even an entirely empty one: legacy always
        // inserts it (DbWriteHostRepository::addExtendedInformations()), and other parts of the
        // application already assume it exists.
        $this->connection->createQueryBuilder()
            ->insert('extended_host_information')
            ->values(['host_host_id' => ':hostId'])
            ->setParameter('hostId', $hostId)
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
            ->leftJoin('h', 'host_template_relation', 'htpl', 'htpl.host_host_id = h.host_id')
            ->leftJoin('h', 'hostgroup_relation', 'hgr', 'hgr.host_host_id = h.host_id')
            ->andWhere("h.host_register = '1'")
            ->groupBy('h.host_id', 'nsr.nagios_server_id')
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

        if ($criteria?->getPage() === null || $criteria->getItemsPerPage() === null) {
            /** @var array<RowTypeAlias> $rows */
            $rows = $qb->executeQuery()->fetchAllAssociative();

            return new Collection(array_map($this->createHost(...), $rows), Host::class);
        }

        $this->paginate($qb, $criteria);

        // total across all pages: countMatching clones $qb, strips its sort/pagination
        // and resets its GROUP BY (needed here to collapse the template-relation join),
        // so the count is unaffected by the pagination applied above.
        $count = $this->countMatching($qb, 'COUNT(DISTINCT h.host_id)');

        /** @var array<RowTypeAlias> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return new InMemoryPaginator(
            items: new Collection(array_map($this->createHost(...), $rows), Host::class),
            totalItems: $count,
            currentPage: $criteria->getPage() ?? throw new \LogicException('Unexpected null page'),
            itemsPerPage: $criteria->getItemsPerPage() ?? throw new \LogicException('Unexpected null items per page'),
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
            'GROUP_CONCAT(DISTINCT htpl.host_tpl_id) AS template_ids',
            'GROUP_CONCAT(DISTINCT hgr.hostgroup_hg_id) AS group_ids',
        ];
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
        if ($criteria->getPage() === null || $criteria->getItemsPerPage() === null) {
            return;
        }

        $qb->setFirstResult(($criteria->getPage() - 1) * $criteria->getItemsPerPage())
            ->setMaxResults($criteria->getItemsPerPage());
    }

    /**
     * @param RowTypeAlias $row
     */
    private function createHost(array $row): Host
    {
        return $this->transformer->transform($row);
    }
}
