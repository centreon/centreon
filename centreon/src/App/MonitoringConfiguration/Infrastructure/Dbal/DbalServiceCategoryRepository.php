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

use App\MonitoringConfiguration\Domain\Aggregate\ServiceCategory\ServiceCategory;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceCategory\ServiceCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceCategory\ServiceCategoryName;
use App\MonitoringConfiguration\Domain\Repository\ServiceCategoryRepository;
use App\Shared\Infrastructure\Dbal\DbalRepository;
use App\Shared\Infrastructure\TransformerInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @phpstan-type RowTypeAlias = array{sc_id: int, sc_name: string, sc_description: string, sc_activate: '0'|'1'}
 */
final readonly class DbalServiceCategoryRepository extends DbalRepository implements ServiceCategoryRepository
{
    private const TABLE_NAME = 'service_categories';

    /**
     * @param TransformerInterface<RowTypeAlias, ServiceCategory> $transformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
        #[Autowire(service: DbalServiceCategoryTransformer::class)]
        private TransformerInterface $transformer,
    ) {
    }

    public function add(ServiceCategory $serviceCategory): void
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->insert(self::TABLE_NAME)
            ->values([
                'sc_name' => ':name',
                'sc_description' => ':alias',
                'sc_activate' => ':activated',
            ])
            ->setParameter('name', $serviceCategory->name->value)
            ->setParameter('alias', $serviceCategory->alias->value)
            ->setParameter('activated', $serviceCategory->activated ? '1' : '0')
            ->executeStatement();

        $id = (int) $this->connection->lastInsertId();

        if ($id === 0) {
            throw new \RuntimeException(\sprintf('Unable to retrieve last insert ID for "%s".', self::TABLE_NAME));
        }

        $this->setId($serviceCategory, new ServiceCategoryId($id));
    }

    public function findOneByName(ServiceCategoryName $name): ?ServiceCategory
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select('sc_id', 'sc_name', 'sc_description', 'sc_activate')
            ->from(self::TABLE_NAME)
            ->where('sc_name = :name')
            ->setParameter('name', $name->value)
            ->setMaxResults(1);

        /**
         * @var RowTypeAlias|false $row
         */
        $row = $qb->executeQuery()->fetchAssociative();

        if (! $row) {
            return null;
        }

        return $this->transformer->transform($row);
    }
}
