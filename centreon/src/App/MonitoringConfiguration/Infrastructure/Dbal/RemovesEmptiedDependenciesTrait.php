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

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * The relation tables cascade on the deleted host or service, but the parent `dependency` row has
 * no foreign key back to them and would be left without any member.
 *
 * @property Connection $connection
 */
trait RemovesEmptiedDependenciesTrait
{
    /**
     * Must run before the member is deleted: the cascade wipes the relation rows.
     *
     * @param non-empty-string $relationTable
     * @param non-empty-string $memberColumn
     *
     * @return list<int>
     */
    private function findDependencyIds(string $relationTable, string $memberColumn, int $memberId): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('dependency_dep_id')
            ->from($relationTable)
            ->where($qb->expr()->eq($memberColumn, $qb->createNamedParameter($memberId, ParameterType::INTEGER)));

        /** @var list<int|string> $ids */
        $ids = $qb->executeQuery()->fetchFirstColumn();

        return array_map(static fn (int|string $id): int => (int) $id, $ids);
    }

    /**
     * @param non-empty-string $relationTable
     * @param list<int> $dependencyIds
     */
    private function deleteDependenciesWithoutMember(string $relationTable, array $dependencyIds): void
    {
        if ($dependencyIds === []) {
            return;
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->delete('dependency')
            ->where($qb->expr()->in('dep_id', $qb->createNamedParameter($dependencyIds, ArrayParameterType::INTEGER)))
            ->andWhere(sprintf(
                'NOT EXISTS (SELECT 1 FROM %s WHERE %s.dependency_dep_id = dependency.dep_id)',
                $relationTable,
                $relationTable,
            ))
            ->executeStatement();
    }
}
