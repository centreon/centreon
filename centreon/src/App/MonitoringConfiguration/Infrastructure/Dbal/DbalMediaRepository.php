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

use App\MonitoringConfiguration\Domain\Aggregate\Media\ImageFolderId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\Media;
use App\MonitoringConfiguration\Domain\Repository\Criteria\MediaCriteria;
use App\MonitoringConfiguration\Domain\Repository\MediaRepository;
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
 *   id: int,
 *   name: string,
 *   directory: string,
 * }
 */
final readonly class DbalMediaRepository extends DbalRepository implements MediaRepository
{
    use DbalCriteriaApplierTrait;
    public const TABLE_NAME = 'view_img';
    public const DIR_RELATION_TABLE_NAME = 'view_img_dir_relation';
    public const DIR_TABLE_NAME = 'view_img_dir';

    /**
     * @param TransformerInterface<RowTypeAlias, Media> $transformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,

        #[Autowire(service: MediaTransformer::class)]
        private TransformerInterface $transformer,

        private ResourceAccessRepository $resourceAccessRepository,
    ) {
    }

    public function findAll(?MediaCriteria $criteria = null): \IteratorAggregate&\Countable
    {
        $accessibleFolderIds = null;
        if (($viewerId = $criteria?->getViewerId()) instanceof UserId) {
            $accessibleFolderIds = $this->resourceAccessRepository->findAccessibleImageFolderIds($viewerId);
            if ($accessibleFolderIds instanceof Collection && $accessibleFolderIds->count() === 0) {
                return $this->emptyResult($criteria);
            }
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->select('img.img_id AS id', 'img.img_path AS name', 'dir.dir_name AS directory')
            ->from(self::TABLE_NAME, 'img')
            ->innerJoin('img', self::DIR_RELATION_TABLE_NAME, 'rel', 'rel.img_img_id = img.img_id')
            ->innerJoin('rel', self::DIR_TABLE_NAME, 'dir', 'dir.dir_id = rel.dir_dir_parent_id')
            ->orderBy('img.img_path') // alphabetical order for a name-based selector, matches legacy
            ->addOrderBy('img.img_id'); // deterministic pagination (img_path is not unique)

        if ($accessibleFolderIds instanceof Collection) {
            $qb->andWhere($qb->expr()->in(
                'dir.dir_id',
                $qb->createNamedParameter(
                    array_map(static fn (ImageFolderId $id): int => $id->value, iterator_to_array($accessibleFolderIds)),
                    ArrayParameterType::INTEGER
                )
            ));
        }

        if ($criteria instanceof MediaCriteria) {
            $this->filterByCriteria($qb, $criteria);
        }

        $pagination = $criteria?->getPagination();
        if (! $pagination instanceof \App\Shared\Domain\Repository\Pagination) {
            /** @var array<RowTypeAlias> $rows */
            $rows = $qb->executeQuery()->fetchAllAssociative();

            return $this->createMedias($rows);
        }

        $this->paginate($qb, $criteria);

        // total across all pages: countMatching clones $qb and strips its sort/pagination,
        // so it is unaffected by the pagination applied above.
        $count = $this->countMatching($qb, 'COUNT(img.img_id)');

        /** @var array<RowTypeAlias> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return new InMemoryPaginator(
            items: $this->createMedias($rows),
            totalItems: $count,
            currentPage: $pagination->page,
            itemsPerPage: $pagination->itemsPerPage,
        );
    }

    /**
     * A restricted viewer with no accessible image folder short-circuits before any query runs.
     * When pagination was requested, the response must still carry the pagination envelope (page,
     * page size, totalItems: 0) instead of degrading to a bare array — the client can't otherwise
     * tell "zero matches" from "pagination metadata omitted".
     */
    private function emptyResult(?MediaCriteria $criteria): \IteratorAggregate&\Countable
    {
        $pagination = $criteria?->getPagination();
        if (! $pagination instanceof \App\Shared\Domain\Repository\Pagination) {
            return new Collection([], Media::class);
        }

        return new InMemoryPaginator(
            items: new Collection([], Media::class),
            totalItems: 0,
            currentPage: $pagination->page,
            itemsPerPage: $pagination->itemsPerPage,
        );
    }

    private function filterByCriteria(QueryBuilder $qb, MediaCriteria $criteria): void
    {
        if (($name = $criteria->getName()) !== null) {
            $qb->andWhere($qb->expr()->like('img.img_path', $qb->createNamedParameter('%' . $name . '%')));
        }
    }

    /**
     * @param array<RowTypeAlias> $rows
     *
     * @return Collection<Media>
     */
    private function createMedias(array $rows): Collection
    {
        return new Collection(
            array_map(
                fn (array $row): Media => $this->transformer->transform($row),
                $rows
            ),
            Media::class
        );
    }

    private function paginate(QueryBuilder $qb, MediaCriteria $criteria): void
    {
        $pagination = $criteria->getPagination();
        if (! $pagination instanceof \App\Shared\Domain\Repository\Pagination) {
            return;
        }

        $qb->setFirstResult($pagination->getOffset())
            ->setMaxResults($pagination->itemsPerPage);
    }
}
