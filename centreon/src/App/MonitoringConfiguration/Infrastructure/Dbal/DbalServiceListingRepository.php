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

use App\MonitoringConfiguration\Domain\Model\ServiceListing\ServiceListItem;
use App\MonitoringConfiguration\Domain\Repository\Criteria\ServiceListingCriteria;
use App\MonitoringConfiguration\Domain\Repository\ServiceListingRepository;
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
 * @phpstan-import-type ServiceListItemRowTypeAlias from ServiceListItemTransformer
 *
 * @phpstan-type PairRowTypeAlias array{
 *   service_id: int|string,
 *   name: string,
 *   activated: string,
 *   host_id: int|string,
 *   host_name: string,
 *   host_icon_id: int|string|null,
 *   template_id: int|string|null,
 *   template_name: string|null,
 * }
 * @phpstan-type InheritedTypeAlias array{icon_id: int|null, normal_check_interval: int|null, retry_check_interval: int|null}
 */
final readonly class DbalServiceListingRepository extends DbalRepository implements ServiceListingRepository
{
    use DbalCriteriaApplierTrait;
    use NonBlankNameConditionTrait;

    /**
     * @param TransformerInterface<ServiceListItemRowTypeAlias, ServiceListItem> $transformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
        #[Autowire(service: ServiceListItemTransformer::class)]
        private TransformerInterface $transformer,
        private AccessScopeResolver $accessScopes,
        // The real-time ACL cache is read across databases, from the configuration connection.
        #[Autowire(env: 'dbcstg')]
        private string $realTimeDatabaseName,
    ) {
        Assert::stringNotEmpty($realTimeDatabaseName);
    }

    /**
     * Two queries, whatever the number of services: one selects, filters, sorts, counts and pages the
     * (host, service) pairs, the other resolves what the services of the page inherit from their templates.
     */
    public function findAll(ServiceListingCriteria $criteria): \IteratorAggregate&\Countable
    {
        $scope = $criteria->accessScope($this->accessScopes->serviceAccessGroups(...), AccessGroupId::class);
        if ($scope->allowsNothing()) {
            return $this->emptyResult($criteria, ServiceListItem::class);
        }

        $qb = $this->createPairsQuery($scope);
        $this->filter($qb, $criteria);

        $pagination = $criteria->getPagination();
        if (! $pagination instanceof Pagination) {
            $this->addDisplayColumns($qb);
            $this->sort($qb, $criteria);

            return new Collection($this->createItems($qb), ServiceListItem::class);
        }

        // Counted before the display-only joins are added, as they cannot change the number of pairs. A pair
        // may come from several link rows: it counts once.
        $count = $this->countMatching($qb, 'COUNT(DISTINCT h.host_id, s.service_id)');
        $this->addDisplayColumns($qb);
        $this->sort($qb, $criteria);
        $this->applyPagination($qb, $pagination);

        return new InMemoryPaginator(
            items: new Collection($this->createItems($qb), ServiceListItem::class),
            totalItems: $count,
            currentPage: $pagination->page,
            itemsPerPage: $pagination->itemsPerPage,
        );
    }

    /**
     * A listable pair: a real service directly linked to a real host, neither name NULL nor blank, the service
     * having no host group link at all (those are not handled here, even when also linked to hosts directly),
     * and, for a restricted actor, granted to one of their groups by the real-time ACL cache.
     *
     * @param AccessScope<AccessGroupId> $scope
     */
    private function createPairsQuery(AccessScope $scope): QueryBuilder
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->from('host_service_relation', 'hsr')
            ->innerJoin('hsr', 'host', 'h', "h.host_id = hsr.host_host_id AND h.host_register = '1'")
            ->innerJoin('hsr', 'service', 's', "s.service_id = hsr.service_service_id AND s.service_register = '1'");
        $this->andWhereNameIsNotBlank($qb, 'h.host_name');
        $this->andWhereNameIsNotBlank($qb, 's.service_description');
        $qb->andWhere(
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

    /**
     * What a listed row shows besides the pair: the direct template and the host's own icon.
     */
    private function addDisplayColumns(QueryBuilder $qb): void
    {
        $qb->select(
            's.service_id',
            's.service_description AS name',
            's.service_activate AS activated',
            'h.host_id',
            'h.host_name',
            'ehi.ehi_icon_image AS host_icon_id',
            't.service_id AS template_id',
            't.service_description AS template_name',
        )
            ->leftJoin('s', 'service', 't', "t.service_id = s.service_template_model_stm_id AND t.service_register = '0'")
            ->leftJoin('h', 'extended_host_information', 'ehi', 'ehi.host_host_id = h.host_id');
    }

    private function filter(QueryBuilder $qb, ServiceListingCriteria $criteria): void
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
    private function sort(QueryBuilder $qb, ServiceListingCriteria $criteria): void
    {
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
     * @return list<ServiceListItem>
     */
    private function createItems(QueryBuilder $qb): array
    {
        /** @var list<PairRowTypeAlias> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();
        $inherited = $this->findInheritedValues(array_values(array_unique(array_map(
            static fn (array $row): int => (int) $row['service_id'],
            $rows,
        ))));

        return array_map(
            fn (array $row): ServiceListItem => $this->transformer->transform([...$row, ...$inherited[(int) $row['service_id']]]),
            $rows,
        );
    }

    /**
     * What each service shows from its template chain, its own values first: the check intervals (a value of 0
     * counts as unset, like NULL) and the icon, the first one whose image sits in a folder (an image in no
     * folder cannot be displayed; a level without extended information is skipped).
     *
     * One query fetches every level reachable from the given services; the chain itself is walked in PHP.
     * UNION dedupes the reached ids, and the walk stops on an id already seen, so a template loop in the data
     * ends both.
     *
     * @param list<int> $serviceIds
     *
     * @return array<int, InheritedTypeAlias> indexed by service id
     */
    private function findInheritedValues(array $serviceIds): array
    {
        if ($serviceIds === []) {
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
            ['ids' => $serviceIds],
            ['ids' => ArrayParameterType::INTEGER],
        )->fetchAllAssociative();

        $levels = [];
        foreach ($rows as $row) {
            $levels[(int) $row['service_id']] = [
                'template_id' => $row['template_id'] !== null ? (int) $row['template_id'] : null,
                'icon_id' => $row['icon_id'] !== null ? (int) $row['icon_id'] : null,
                'normal_check_interval' => $this->ownInterval($row['normal_check_interval']),
                'retry_check_interval' => $this->ownInterval($row['retry_check_interval']),
            ];
        }

        $inherited = [];
        foreach ($serviceIds as $serviceId) {
            $inherited[$serviceId] = $this->walkChain($serviceId, $levels);
        }

        return $inherited;
    }

    /**
     * @param array<int, array{template_id: int|null, icon_id: int|null, normal_check_interval: int|null, retry_check_interval: int|null}> $levels
     *
     * @return InheritedTypeAlias
     */
    private function walkChain(int $serviceId, array $levels): array
    {
        $values = ['icon_id' => null, 'normal_check_interval' => null, 'retry_check_interval' => null];
        $visited = [];
        $current = $serviceId;
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
     * A stored interval of 0 counts as unset.
     */
    private function ownInterval(int|string|null $value): ?int
    {
        return $value === null || (int) $value === 0 ? null : (int) $value;
    }
}
