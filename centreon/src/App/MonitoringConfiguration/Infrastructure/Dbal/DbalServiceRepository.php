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
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Repository\Criteria\ServiceCriteria;
use App\MonitoringConfiguration\Domain\Repository\ServiceRepository;
use App\Security\Domain\Aggregate\AccessGroupId;
use App\Security\Domain\Aggregate\AccessScope;
use App\Security\Domain\Service\AccessScopeResolver;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Repository\Pagination;
use App\Shared\Domain\Repository\SortDirectionEnum;
use App\Shared\Infrastructure\Dbal\DbalCriteriaApplierTrait;
use App\Shared\Infrastructure\Dbal\DbalRepository;
use App\Shared\Infrastructure\InMemory\InMemoryPaginator;
use App\Shared\Infrastructure\TransformerInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webmozart\Assert\Assert;

/**
 * @phpstan-import-type RowTypeAlias from ServiceTransformer
 * @phpstan-import-type MacroRowTypeAlias from ServiceTransformer
 *
 * @phpstan-type OwnRowTypeAlias array{
 *   service_id: int|string,
 *   service_description: string,
 *   service_activate: string,
 *   template_id: int|string|null,
 *   icon_id: int|string|null,
 *   normal_check_interval: int|string|null,
 *   retry_check_interval: int|string|null,
 * }
 * @phpstan-type ListedRowTypeAlias array{
 *   service_id: int|string,
 *   service_description: string,
 *   service_activate: string,
 *   template_id: int|string|null,
 *   icon_id: int|string|null,
 *   normal_check_interval: int|string|null,
 *   retry_check_interval: int|string|null,
 *   host_id: int|string,
 * }
 */
final readonly class DbalServiceRepository extends DbalRepository implements ServiceRepository
{
    use RemovesEmptiedDependenciesTrait;
    use DbalCriteriaApplierTrait;
    private const TABLE_NAME = 'service';

    /** The service's own values, its own icon (as stored, whatever its folder) included. */
    private const OWN_COLUMNS = [
        's.service_id',
        's.service_description',
        's.service_activate',
        's.service_template_model_stm_id AS template_id',
        's.service_normal_check_interval AS normal_check_interval',
        's.service_retry_check_interval AS retry_check_interval',
        '(SELECT MIN(esi.esi_icon_image) FROM extended_service_information esi WHERE esi.service_service_id = s.service_id) AS icon_id',
    ];

    /**
     * @param TransformerInterface<RowTypeAlias, Service> $transformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
        #[Autowire(service: ServiceTransformer::class)]
        private TransformerInterface $transformer,
        private AccessScopeResolver $accessScopes,
        // The real-time ACL cache is read across databases, from the configuration connection.
        #[Autowire(env: 'dbcstg')]
        private string $realTimeDatabaseName,
    ) {
        Assert::stringNotEmpty($realTimeDatabaseName);
    }

    /**
     * One query selects, filters, sorts and pages the (host, service) pairs, whatever the number of services, plus
     * one to count them.
     */
    public function findAll(ServiceCriteria $criteria): \IteratorAggregate&\Countable
    {
        $scope = $criteria->accessScope($this->accessScopes->serviceAccessGroups(...), AccessGroupId::class);
        if ($scope->allowsNothing()) {
            return $this->emptyResult($criteria, Service::class);
        }

        $qb = $this->createPairsQuery($scope);
        $this->filter($qb, $criteria);

        $pagination = $criteria->getPagination();
        if (! $pagination instanceof Pagination) {
            $this->selectAndSort($qb, $criteria);

            return new Collection($this->createListedServices($qb), Service::class);
        }

        // A pair may come from several link rows: it counts once.
        $count = $this->countMatching($qb, 'COUNT(DISTINCT h.host_id, s.service_id)');
        $this->selectAndSort($qb, $criteria);
        $this->applyPagination($qb, $pagination);

        return new InMemoryPaginator(
            items: new Collection($this->createListedServices($qb), Service::class),
            totalItems: $count,
            currentPage: $pagination->page,
            itemsPerPage: $pagination->itemsPerPage,
        );
    }

    public function findExclusivelyLinkedToHostId(HostId $hostId): Collection
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select(...self::OWN_COLUMNS)
            ->from(self::TABLE_NAME, 's')
            ->innerJoin('s', 'host_service_relation', 'hsr', 'hsr.service_service_id = s.service_id')
            ->innerJoin('hsr', 'host', 'h', 'h.host_id = hsr.host_host_id')
            ->where($qb->expr()->eq('hsr.host_host_id', $qb->createNamedParameter($hostId->value, ParameterType::INTEGER)))
            ->andWhere("h.host_register = '1'");
        $this->keepOnlyServicesWithASingleRelation($qb);

        /** @var list<OwnRowTypeAlias> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return $this->hydrate($hostId, $rows);
    }

    public function findFromServiceTemplates(HostId $hostId, array $serviceTemplateIds): Collection
    {
        if ($serviceTemplateIds === []) {
            return new Collection([], Service::class);
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->select(...self::OWN_COLUMNS)
            ->from(self::TABLE_NAME, 's')
            ->innerJoin('s', 'host_service_relation', 'hsr', 'hsr.service_service_id = s.service_id')
            ->where($qb->expr()->eq('hsr.host_host_id', $qb->createNamedParameter($hostId->value, ParameterType::INTEGER)))
            ->andWhere("s.service_register = '1'")
            ->andWhere($qb->expr()->in('s.service_template_model_stm_id', $qb->createNamedParameter($serviceTemplateIds, ArrayParameterType::INTEGER)));
        $this->keepOnlyServicesWithASingleRelation($qb);

        /** @var list<OwnRowTypeAlias> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return $this->hydrate($hostId, $rows);
    }

    public function remove(Service $service): void
    {
        $serviceId = $service->id()->value;
        $parentDependencyIds = $this->findDependencyIds('dependency_serviceParent_relation', 'service_service_id', $serviceId);

        $qb = $this->connection->createQueryBuilder();
        $qb->delete(self::TABLE_NAME)
            ->where($qb->expr()->eq('service_id', $qb->createNamedParameter($serviceId, ParameterType::INTEGER)))
            ->andWhere("service_register = '1'")
            ->executeStatement();

        $this->deleteDependenciesWithoutMember('dependency_serviceParent_relation', $parentDependencyIds);
    }

    /**
     * A listable pair: a real service directly linked to a real host, the service having no host group link at
     * all (those are not handled here, even when also linked to hosts directly), and, for a restricted actor,
     * granted to one of their groups by the real-time ACL cache.
     *
     * @param AccessScope<AccessGroupId> $scope
     */
    private function createPairsQuery(AccessScope $scope): QueryBuilder
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->from('host_service_relation', 'hsr')
            ->innerJoin('hsr', 'host', 'h', "h.host_id = hsr.host_host_id AND h.host_register = '1'")
            ->innerJoin('hsr', self::TABLE_NAME, 's', "s.service_id = hsr.service_service_id AND s.service_register = '1'")
            ->where(
                'NOT EXISTS (
                    SELECT 1 FROM host_service_relation hsrg
                    WHERE hsrg.service_service_id = s.service_id AND hsrg.hostgroup_hg_id IS NOT NULL
                )'
            )
            // A link row may be duplicated: one row per pair.
            ->groupBy('h.host_id', 's.service_id');

        if (! $scope->isUnrestricted()) {
            $qb->andWhere(sprintf(
                'EXISTS (
                    SELECT 1 FROM %s.centreon_acl acl
                    WHERE acl.group_id IN (%s) AND acl.host_id = h.host_id AND acl.service_id = s.service_id
                )',
                $this->connection->quoteSingleIdentifier($this->realTimeDatabaseName),
                $qb->createNamedParameter($scope->allowedIdValues(), ArrayParameterType::INTEGER),
            ));
        }

        return $qb;
    }

    private function filter(QueryBuilder $qb, ServiceCriteria $criteria): void
    {
        if (($hostName = $criteria->getHostName()) !== null) {
            $qb->andWhere($qb->expr()->like('h.host_name', $qb->createNamedParameter('%' . $hostName . '%')));
        }

        if (($name = $criteria->getName()) !== null) {
            $qb->andWhere($qb->expr()->like('s.service_description', $qb->createNamedParameter('%' . $name . '%')));
        }

        if (($templateId = $criteria->getTemplateId()) !== null) {
            $qb->andWhere($qb->expr()->eq('s.service_template_model_stm_id', $qb->createNamedParameter($templateId, ParameterType::INTEGER)));
        }

        if (($activated = $criteria->getActivated()) !== null) {
            $qb->andWhere($qb->expr()->eq('s.service_activate', $qb->createNamedParameter($activated ? '1' : '0')));
        }

        if (($hostActivated = $criteria->getHostActivated()) !== null) {
            $qb->andWhere($qb->expr()->eq('h.host_activate', $qb->createNamedParameter($hostActivated ? '1' : '0')));
        }
    }

    /**
     * The client sort replaces the default one (host name, then service name). The host id, then the service
     * id, always come last, so a page never depends on the order the database happens to return ties in.
     */
    private function selectAndSort(QueryBuilder $qb, ServiceCriteria $criteria): void
    {
        $qb->select(...[...self::OWN_COLUMNS, 'h.host_id']);

        $mapping = $criteria->getFieldMapping();
        $sorted = false;
        foreach ($criteria->getSort() as $field => $direction) {
            if (isset($mapping[$field])) {
                $qb->addOrderBy($mapping[$field], $direction === SortDirectionEnum::DESC ? 'DESC' : 'ASC');
                $sorted = true;
            }
        }

        if (! $sorted) {
            $qb->addOrderBy('h.host_name', 'ASC')->addOrderBy('s.service_description', 'ASC');
        }

        $qb->addOrderBy('h.host_id', 'ASC')->addOrderBy('s.service_id', 'ASC');
    }

    /**
     * @return list<Service>
     */
    private function createListedServices(QueryBuilder $qb): array
    {
        /** @var list<ListedRowTypeAlias> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return array_map(fn (array $row): Service => $this->transformer->transform([...$row, 'macros' => []]), $rows);
    }

    /**
     * A service reachable through more than one relation row (another host, or a shared hostgroup)
     * is not exclusively one host's, and is left alone — mirrors legacy
     * `findServiceIdsExclusivelyLinkedToHostId()`.
     */
    private function keepOnlyServicesWithASingleRelation(QueryBuilder $qb): void
    {
        $qb->innerJoin(
            's',
            '(SELECT service_service_id FROM host_service_relation GROUP BY service_service_id HAVING COUNT(*) = 1)',
            'uniq',
            'uniq.service_service_id = s.service_id',
        );
    }

    /**
     * @param list<OwnRowTypeAlias> $rows
     *
     * @return Collection<Service>
     */
    private function hydrate(HostId $hostId, array $rows): Collection
    {
        if ($rows === []) {
            return new Collection([], Service::class);
        }

        $serviceIds = array_map(static fn (array $row): int => (int) $row['service_id'], $rows);
        $macrosByServiceId = $this->findMacroRowsByServiceIds($serviceIds);

        $services = array_map(
            fn (array $row): Service => $this->transformer->transform([
                ...$row,
                'host_id' => $hostId->value,
                'macros' => $macrosByServiceId[(int) $row['service_id']] ?? [],
            ]),
            $rows,
        );

        return new Collection($services, Service::class);
    }

    /**
     * @param list<int> $serviceIds
     *
     * @return array<int, list<MacroRowTypeAlias>>
     */
    private function findMacroRowsByServiceIds(array $serviceIds): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('svc_svc_id', 'svc_macro_name AS name', 'svc_macro_value AS value', 'is_password', 'description')
            ->from('on_demand_macro_service')
            ->where($qb->expr()->in('svc_svc_id', $qb->createNamedParameter($serviceIds, ArrayParameterType::INTEGER)))
            ->orderBy('macro_order');

        /** @var list<array{svc_svc_id: int|string, name: string, value: string, is_password: string|int, description: string|null}> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['svc_svc_id']][] = [
                'name' => $row['name'],
                'value' => $row['value'],
                'is_password' => $row['is_password'],
                'description' => $row['description'],
            ];
        }

        return $grouped;
    }
}
