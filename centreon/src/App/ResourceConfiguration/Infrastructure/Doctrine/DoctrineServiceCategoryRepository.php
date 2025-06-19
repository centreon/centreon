<?php

declare(strict_types=1);

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
 */

namespace App\ResourceConfiguration\Infrastructure\Doctrine;

use App\ResourceConfiguration\Domain\Aggregate\ServiceCategory;
use App\ResourceConfiguration\Domain\Aggregate\ServiceCategoryId;
use App\ResourceConfiguration\Domain\Aggregate\ServiceCategoryName;
use App\ResourceConfiguration\Domain\Repository\ServiceCategoryRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class DoctrineServiceCategoryRepository implements ServiceCategoryRepository
{
    private const TABLE_NAME = 'service_categories';

    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
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
            ->setParameter('name', $serviceCategory->name()->value)
            ->setParameter('alias', $serviceCategory->alias()->value)
            ->setParameter('activated', $serviceCategory->isActivated() ? '1' : '0')
            ->executeStatement();

        $id = (int) $this->connection->lastInsertId();

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
         * @var array{sc_id: int, sc_name: string, sc_description: string, sc_activate: '0'|'1'}|false $row
         */
        $row = $qb->fetchAssociative();

        if (!$row) {
            return null;
        }

        $serviceCategory = new ServiceCategory(
            name: new ServiceCategoryName($row['sc_name']),
            alias: new ServiceCategoryName($row['sc_description']),
            activated: '1' === $row['sc_activate'] ? true : false,
        );

        $this->setId($serviceCategory, new ServiceCategoryId($row['sc_id']));

        return $serviceCategory;
    }

    private function setId(ServiceCategory $serviceCategory, ServiceCategoryId $id): void
    {
        $r = new \ReflectionProperty($serviceCategory, 'id');

        $r->setAccessible(true);
        $r->setValue($serviceCategory, $id);
    }
}
