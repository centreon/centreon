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

use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Repository\PollerNameResolver;
use App\Shared\Domain\Collection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class DbalPollerNameResolver implements PollerNameResolver
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
    ) {
    }

    public function resolveNames(Collection $ids): array
    {
        $idValues = array_map(static fn (PollerId $id): int => $id->value, iterator_to_array($ids));
        if ($idValues === []) {
            return [];
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->select('id', 'name')
            ->from('nagios_server')
            ->where($qb->expr()->in('id', $qb->createNamedParameter($idValues, ArrayParameterType::INTEGER)));

        /** @var list<array{id: int|string, name: string}> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        $names = [];
        foreach ($rows as $row) {
            $names[(int) $row['id']] = $row['name'];
        }

        return $names;
    }
}
