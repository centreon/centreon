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

use App\MonitoringConfiguration\Domain\Aggregate\PlatformMetadata\PlatformMetadata;
use App\MonitoringConfiguration\Domain\Aggregate\PlatformMetadata\PlatformMetadataName;
use App\MonitoringConfiguration\Domain\Exception\PlatformMetadataNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\PlatformMetadataRepository;
use App\Shared\Infrastructure\Dbal\DbalRepository;
use App\Shared\Infrastructure\TransformerInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @phpstan-type RowTypeAlias = array{
 *  option_name: string,
 *  option_value: string,
 * }
 */
final readonly class DbalPlatformMetadataRepository extends DbalRepository implements PlatformMetadataRepository
{
    private const TABLE_NAME = 'informations';

    /**
     * @param TransformerInterface<RowTypeAlias, PlatformMetadata> $transformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,

        #[Autowire(service: DbalPlatformMetadataTransformer::class)]
        private TransformerInterface $transformer,
    ) {
    }

    public function getByName(PlatformMetadataName $name): PlatformMetadata
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('`key` AS option_name', '`value` AS option_value')
            ->from(self::TABLE_NAME)
            ->where('`key` = :name')
            ->setParameter('name', $name->value)
            ->setMaxResults(1);
        /** @var RowTypeAlias|false $row */
        $row = $qb->executeQuery()->fetchAssociative();
        if ($row === false) {
            throw new PlatformMetadataNotFoundException(['option_name' => $name->value]);
        }

        return $this->transformer->transform($row);
    }
}
