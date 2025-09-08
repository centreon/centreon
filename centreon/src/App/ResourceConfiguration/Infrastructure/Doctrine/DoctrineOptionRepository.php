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

use App\ResourceConfiguration\Domain\Aggregate\Option;
use App\ResourceConfiguration\Domain\Aggregate\OptionName;
use App\ResourceConfiguration\Domain\Aggregate\OptionValue;
use App\ResourceConfiguration\Domain\Repository\OptionRepository;
use App\Shared\Infrastructure\Doctrine\DoctrineRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @phpstan-type RowTypeAlias = array{
 *  key: string,
 *  value: string,
 * }
 */
final readonly class DoctrineOptionRepository extends DoctrineRepository implements OptionRepository
{
    private const TABLE_NAME = 'options';

    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
    ) {
    }

    public function findByName(OptionName $name): ?Option
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('`key`', '`value`')
            ->from(self::TABLE_NAME)
            ->where('`key` = :name')
            ->setParameter('name', $name->value)
            ->setMaxResults(1);
        /** @var RowTypeAlias|false $row */
        $row = $qb->executeQuery()->fetchAssociative();
        return $row !== false
            ? $this->createOption($row)
            : null;
    }

    /**
     * @param RowTypeAlias $row
     */
    private function createOption(array $row): Option
    {
        return new Option(
            name: new OptionName($row['key']),
            value: new OptionValue($row['value']),
        );
    }
}
