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

use App\MonitoringConfiguration\Domain\Aggregate\Connector\Connector;
use App\MonitoringConfiguration\Domain\Aggregate\Connector\ConnectorId;
use App\MonitoringConfiguration\Domain\Exception\ConnectorNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\ConnectorRepository;
use App\Shared\Infrastructure\Dbal\DbalRepository;
use App\Shared\Infrastructure\TransformerInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @phpstan-type RowTypeAlias = array{
 *   id: int,
 *   name: string,
 * }
 */
final readonly class DbalConnectorRepository extends DbalRepository implements ConnectorRepository
{
    public const TABLE_NAME = 'connector';

    /**
     * @param TransformerInterface<RowTypeAlias, Connector> $transformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,

        #[Autowire(service: DbalConnectorTransformer::class)]
        private TransformerInterface $transformer,
    ) {
    }

    public function findById(ConnectorId $id): ?Connector
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select(
            'id',
            'name',
        )
            ->from(self::TABLE_NAME)
            ->where('id = :id')
            ->setParameter('id', $id->value)
            ->setMaxResults(1);

        /** @var RowTypeAlias $row */
        $row = $qb->executeQuery()->fetchAssociative();

        if ($row === false) {

            return null;
        }

        return $this->transformer->transform($row);
    }

    public function get(ConnectorId $id): Connector
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select(
            'id',
            'name',
        )
            ->from(self::TABLE_NAME)
            ->where('id = :id')
            ->setParameter('id', $id->value)
            ->setMaxResults(1);

        /** @var RowTypeAlias $row */
        $row = $qb->executeQuery()->fetchAssociative();

        if ($row === false) {
            throw new ConnectorNotFoundException(['id' => $id->value]);
        }

        return $this->transformer->transform($row);
    }
}
