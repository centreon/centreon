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
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Repository\Criteria\HostTemplateCriteria;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Domain\Repository\ResourceAccessRepository;
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
 *   host_id: int,
 *   host_name: string,
 * }
 */
final readonly class DbalHostTemplateRepository extends DbalRepository implements HostTemplateRepository
{
    use DbalCriteriaApplierTrait;
    public const TABLE_NAME = 'host';

    // host templates are host rows discriminated by host_register = '0'
    private const HOST_TEMPLATE_REGISTER = '0';

    /**
     * @param TransformerInterface<RowTypeAlias, HostTemplate> $transformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,

        #[Autowire(service: HostTemplateTransformer::class)]
        private TransformerInterface $transformer,

        private ResourceAccessRepository $resourceAccessRepository,
    ) {
    }

    public function findAll(?HostTemplateCriteria $criteria = null): \IteratorAggregate&\Countable
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('h.host_id', 'h.host_name')
            ->from(self::TABLE_NAME, 'h')
            ->where('h.host_register = ' . $qb->createNamedParameter(self::HOST_TEMPLATE_REGISTER))
            ->orderBy('h.host_id'); // required for deterministic pagination

        if ($criteria instanceof HostTemplateCriteria) {
            $this->filterByCriteria($qb, $criteria);
        }

        $pagination = $criteria?->getPagination();
        if (! $pagination instanceof \App\Shared\Domain\Repository\Pagination) {
            /** @var array<RowTypeAlias> $rows */
            $rows = $qb->executeQuery()->fetchAllAssociative();

            return $this->createHostTemplates($rows);
        }

        $this->paginate($qb, $criteria);

        // total across all pages: countMatching clones $qb and strips its sort/pagination,
        // so the count is unaffected by the pagination applied above.
        $count = $this->countMatching($qb, 'COUNT(DISTINCT h.host_id)');

        /** @var array<RowTypeAlias> $rows */
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
        $idValues = array_map(static fn (HostId $id): int => $id->value, $hostIds->toArray());
        if ($idValues === []) {
            return new Collection([], MediaId::class);
        }

        // One query fetches every template relation reachable from the requested hosts, in legacy's
        // `order`; the depth-first walk itself is done in PHP. UNION dedupes reached ids, so a
        // template loop already in the data cannot make the recursion run forever. Like legacy, an
        // icon whose image sits in no folder cannot be displayed and is read as no icon (same joins
        // as DbalMediaRepository::findByIds).
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

    public function findInheritanceLine(Collection $directTemplateIds): Collection
    {
        $directIds = array_values(array_map(static fn (HostTemplateId $id): int => $id->value, $directTemplateIds->toArray()));
        if ($directIds === []) {
            return new Collection([], HostTemplate::class);
        }

        // One query fetches every relation reachable from the direct templates through active
        // templates (legacy getTemplateChain: activated, register = '0'), in `order`; the
        // depth-first walk is done in PHP. UNION dedupes reached ids, so a template loop already in
        // the data cannot make the recursion run forever.
        $sql = <<<'SQL'
            WITH RECURSIVE chain (id) AS (
                SELECT host_id
                FROM host
                WHERE host_id IN (:ids) AND host_register = '0'
                UNION
                SELECT htr.host_tpl_id
                FROM host_template_relation htr
                INNER JOIN chain c ON c.id = htr.host_host_id
                INNER JOIN host t ON t.host_id = htr.host_tpl_id AND t.host_activate = '1' AND t.host_register = '0'
            )
            SELECT htr.host_host_id, htr.host_tpl_id, t.host_name AS tpl_name
            FROM host_template_relation htr
            INNER JOIN chain c ON c.id = htr.host_host_id
            INNER JOIN host t ON t.host_id = htr.host_tpl_id AND t.host_activate = '1' AND t.host_register = '0'
            ORDER BY htr.host_host_id, htr.`order`, htr.host_tpl_id
            SQL;

        /** @var list<array{host_host_id: int|string, host_tpl_id: int|string, tpl_name: string}> $rows */
        $rows = $this->connection->executeQuery(
            $sql,
            ['ids' => $directIds],
            ['ids' => ArrayParameterType::INTEGER],
        )->fetchAllAssociative();

        /** @var array<int, list<int>> $parentIdsById */
        $parentIdsById = [];
        $names = $this->findDirectTemplateNames($directIds);
        foreach ($rows as $row) {
            $parentIdsById[(int) $row['host_host_id']][] = (int) $row['host_tpl_id'];
            $names[(int) $row['host_tpl_id']] = $row['tpl_name'];
        }

        $line = [];
        $visited = [];
        foreach ($directIds as $directId) {
            // A direct id that is not a host template has no name and is skipped.
            if (isset($names[$directId])) {
                $this->walkInheritanceLine($directId, $parentIdsById, $visited, $line);
            }
        }

        $macrosByOwner = $this->findMacrosByOwner($line);

        return new Collection(
            array_map(
                static fn (int $id): HostTemplate => new HostTemplate(
                    new HostTemplateId($id),
                    new HostTemplateName($names[$id]),
                    new Collection($macrosByOwner[$id] ?? [], HostMacro::class),
                ),
                $line,
            ),
            HostTemplate::class,
        );
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
     * @return array<int, string> template name indexed by id, host templates only
     */
    private function findDirectTemplateNames(array $ids): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('host_id', 'host_name')
            ->from(self::TABLE_NAME)
            ->where('host_register = ' . $qb->createNamedParameter(self::HOST_TEMPLATE_REGISTER))
            ->andWhere($qb->expr()->in('host_id', $qb->createNamedParameter($ids, ArrayParameterType::INTEGER)));

        /** @var list<array{host_id: int|string, host_name: string}> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        $names = [];
        foreach ($rows as $row) {
            $names[(int) $row['host_id']] = $row['host_name'];
        }

        return $names;
    }

    /**
     * The custom macros the given templates own, grouped by template, in `macro_order`.
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
            ->orderBy('macro_order')
            ->addOrderBy('host_macro_id');

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

    private function filterByCriteria(QueryBuilder $qb, HostTemplateCriteria $criteria): void
    {
        if (($name = $criteria->getName()) !== null) {
            $qb->andWhere($qb->expr()->like('h.host_name', $qb->createNamedParameter('%' . $name . '%')));
        }

        if ($criteria->excludeLocked()) {
            // A locked host template is a paid plugin-pack template the platform's license doesn't
            // cover, matching legacy's CentreonHost::getLimitedList() exclusion for the host form's
            // "extend template" select.
            $qb->andWhere($qb->expr()->eq('h.host_locked', $qb->createNamedParameter(0, ParameterType::INTEGER)));
        }

        $this->filterByViewer($qb, $criteria);
    }

    private function filterByViewer(QueryBuilder $qb, HostTemplateCriteria $criteria): void
    {
        $viewerId = $criteria->getViewerId();
        if (! $viewerId instanceof UserId) {
            return;
        }

        $accessibleHostSeverities = $this->resourceAccessRepository->findAccessibleHostSeverityIds($viewerId);
        // null means no host-severity restriction applies — the viewer sees every host template.
        if (! $accessibleHostSeverities instanceof Collection) {
            return;
        }

        $accessibleHostSeverityIds = array_map(
            static fn (HostSeverityId $hostSeverityId): int => $hostSeverityId->value,
            $accessibleHostSeverities->toArray()
        );

        // An empty set means the viewer is restricted but grants no accessible severity: they must see
        // nothing (fail-closed), matching legacy, which returns [] for a user with no access group and
        // filters out every severity-less template once a category restriction applies. Forcing an
        // always-false predicate avoids emitting an `IN ()` the driver would reject.
        if ($accessibleHostSeverityIds === []) {
            $qb->andWhere('1 = 0');

            return;
        }

        // Restricted viewer: keep only templates linked to an accessible host severity. This mirrors
        // legacy findByRequestParametersAndAccessGroups, whose "AND hc.hc_id IN (...)" is applied on a
        // join filtered by "hc.level IS NOT NULL", so a template linked only to a regular (levelless)
        // category — or to none — is excluded. The accessible set is already severity-scoped (see
        // ResourceAccessRepository::findAccessibleHostSeverityIds), so matching the relation id
        // directly is enough. EXISTS rather than a JOIN keeps LIMIT/OFFSET pagination correct, as
        // hostcategories_relation carries no per-host uniqueness.
        $qb->andWhere(
            'EXISTS (
                SELECT 1 FROM hostcategories_relation hcr
                WHERE hcr.host_host_id = h.host_id
                    AND hcr.hostcategories_hc_id IN (' . $qb->createNamedParameter(
                $accessibleHostSeverityIds,
                ArrayParameterType::INTEGER
            ) . ')
            )'
        );
    }

    /**
     * @param array<RowTypeAlias> $rows
     *
     * @return Collection<HostTemplate>
     */
    private function createHostTemplates(array $rows): Collection
    {
        return new Collection(
            array_map(
                fn (array $row): HostTemplate => $this->transformer->transform($row),
                $rows
            ),
            HostTemplate::class
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
