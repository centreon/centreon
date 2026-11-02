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

namespace App\ActivityLogging\Infrastructure\Dbal;

use App\ActivityLogging\Domain\Repository\ActorNameRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class DbalActorNameRepository implements ActorNameRepository
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
    ) {
    }

    public function findNames(int ...$actorIds): array
    {
        if ($actorIds === []) {
            return [];
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->select('c.contact_id', 'c.contact_name')
            ->from('contact', 'c')
            ->where('c.contact_id IN (:ids)')
            ->setParameter('ids', array_values(array_unique($actorIds)), ArrayParameterType::INTEGER);

        $names = [];
        /** @var array{contact_id: int|string, contact_name: string|null} $row */
        foreach ($qb->executeQuery()->iterateAssociative() as $row) {
            $names[(int) $row['contact_id']] = (string) $row['contact_name'];
        }

        return $names;
    }
}
