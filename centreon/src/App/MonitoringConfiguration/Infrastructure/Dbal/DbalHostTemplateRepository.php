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

use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
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

        if ($criteria?->getPage() === null || $criteria->getItemsPerPage() === null) {
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
            currentPage: $criteria->getPage() ?? throw new \LogicException('Unexpected null page'),
            itemsPerPage: $criteria->getItemsPerPage() ?? throw new \LogicException('Unexpected null items per page'),
        );
    }

    private function filterByCriteria(QueryBuilder $qb, HostTemplateCriteria $criteria): void
    {
        if (($name = $criteria->getName()) !== null) {
            $qb->andWhere($qb->expr()->like('h.host_name', $qb->createNamedParameter('%' . $name . '%')));
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
        if ($criteria->getPage() === null || $criteria->getItemsPerPage() === null) {
            return;
        }

        $qb->setFirstResult(($criteria->getPage() - 1) * $criteria->getItemsPerPage())
            ->setMaxResults($criteria->getItemsPerPage());
    }
}
