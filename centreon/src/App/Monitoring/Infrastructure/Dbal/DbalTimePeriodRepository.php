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

namespace App\Monitoring\Infrastructure\Dbal;

use App\Monitoring\Domain\Aggregate\TimePeriod\TimePeriod;
use App\Monitoring\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\Monitoring\Domain\Exception\TimePeriodNotFoundException;
use App\Monitoring\Domain\Repository\TimePeriodRepository;
use App\Shared\Infrastructure\Dbal\DbalRepository;
use App\Shared\Infrastructure\TransformerInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @phpstan-type RowTypeAlias = array{
 *    timeperiod_id: int,
 *    timeperiod_name: string,
 * }
 */
final readonly class DbalTimePeriodRepository extends DbalRepository implements TimePeriodRepository
{
    public const string TABLE_NAME = 'timeperiod';

    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
        #[Autowire(service: DbalTimePeriodTransformer::class)]
        private TransformerInterface $transformer,
    )
    {
    }

    /**
     * @return array<string>
     */
    public static function getSelectColumns(string $alias = 't'): array
    {
        return [
            "{$alias}.tp_id AS timeperiod_id",
            "{$alias}.tp_name AS timeperiod_name",
        ];
    }

    public function get(TimePeriodId $id): TimePeriod
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select(...self::getSelectColumns('t'))
            ->from(self::TABLE_NAME, 't')
            ->where('t.tp_id = :id')
            ->setParameter('id', $id->value);

        /** @var RowTypeAlias|false $row */
        $row = $qb->executeQuery()->fetchAssociative();

        if ($row === false) {
            throw new TimePeriodNotFoundException(
                ['id' => $id->value],
                sprintf('TimePeriod #%d not found', $id->value)
            );
        }

        return $this->transformer->transform($row);
    }
}
