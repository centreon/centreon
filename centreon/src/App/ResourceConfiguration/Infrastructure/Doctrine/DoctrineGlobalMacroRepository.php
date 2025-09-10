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

namespace App\ResourceConfiguration\Infrastructure\Doctrine;

use App\ResourceConfiguration\Domain\Repository\GlobalMacroCriteria;
use App\ResourceConfiguration\Domain\Repository\GlobalMacroRepository;
use App\Shared\Domain\Repository\Paginator;
use App\Shared\Infrastructure\Doctrine\DoctrineRepository;
use App\Shared\Infrastructure\InMemory\InMemoryPaginator;
use App\Shared\Infrastructure\TransformerInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @phpstan-type RowTypeAlias = array{
 *  resource_id: int,
 *  resource_name: string,
 *  resource_line: string,
 *  resource_comment: string|null,
 *  resource_activate: '0'|'1',
 *  is_password: 0|1
 * }
 */
final readonly class DoctrineGlobalMacroRepository extends DoctrineRepository implements GlobalMacroRepository
{
    private const TABLE_NAME = 'cfg_resource';

    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
        #[Autowire(service: GlobalMacroTransformer::class)]
        private TransformerInterface $transformer,
    ) {
    }

    public function findAll(?GlobalMacroCriteria $criteria = null): Paginator|array
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select('resource_id', 'resource_name', 'resource_line', 'resource_comment', 'resource_activate', 'is_password')
           ->from(self::TABLE_NAME);
        if ($nameCriteria = $criteria?->getNames()) {
            $paramIndex = 0;

            foreach ($nameCriteria as $operator => $names) {
                if ($operator === 'lk') {
                    $likeParts = [];
                    foreach ($names as $name) {
                        $paramName = 'name' . $paramIndex++;
                        $likeParts[] = 'resource_name LIKE :' . $paramName;
                        $qb->setParameter($paramName, '%' . $name . '%');
                    }
                    if ($likeParts) {
                        $qb->andWhere('(' . implode(' OR ', $likeParts) . ')');
                    }
                } elseif ($operator === 'eq') {
                    $placeholders = [];
                    foreach ($names as $name) {
                        $paramName = 'name' . $paramIndex++;
                        $placeholders[] = ':' . $paramName;
                        $qb->setParameter($paramName, $name);
                    }
                    if ($placeholders) {
                        $qb->andWhere(
                            'resource_name IN (' . implode(',', $placeholders) . ')'
                        );
                    }
                }
            }
        }
        $qbCount = clone $qb;
        if ($criteria?->getPage() !== null) {
            $qb->setFirstResult(($criteria->getPage() - 1) * $criteria->getItemsPerPage())
                ->setMaxResults($criteria->getItemsPerPage());
        }

        /**
         * @var RowTypeAlias[] $rows
         */
        $rows = $qb->executeQuery()->fetchAllAssociative();
        $globalMacros = array_map($this->transformer->transform(...), $rows);
        if ($criteria?->getPage() === null) {
            return $globalMacros;
        }

        /** @var int $count */
        $count = $qbCount->select('count(1)')->executeQuery()->fetchOne();

        return new InMemoryPaginator(
            items: $globalMacros,
            totalItems: $count,
            currentPage: $criteria->getPage(),
            itemsPerPage: $criteria->getItemsPerPage() ?? 0
        );
    }
}
