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

namespace Tests\Core\Service\Infrastructure\Repository;

use Adaptation\Database\Connection\Model\ConnectionConfig;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Service\Domain\Model\ServiceInheritance;
use Core\Service\Infrastructure\Repository\DbReadServiceRepository;

beforeEach(function (): void {
    $this->db = $this->createMock(DatabaseConnection::class);
    $this->db->method('getConnectionConfig')->willReturn(
        new ConnectionConfig('localhost', 'user', 'password', 'centreon', 'centreon_storage')
    );
    $this->repository = new DbReadServiceRepository($this->db);

    /**
     * @param list<array{child_id: int, parent_id: int}> $rows
     */
    $this->mockInheritanceRows = function (array $rows): void {
        $statement = $this->createMock(\PDOStatement::class);
        $statement->method('fetch')->willReturnOnConsecutiveCalls(...[...$rows, false]);
        $this->db->method('prepare')->willReturn($statement);
    };

    /**
     * @param ServiceInheritance[] $inheritances
     *
     * @return list<array{int, int}> pairs of [child ID, parent ID]
     */
    $this->toPairs = static fn (array $inheritances): array => array_map(
        static fn (ServiceInheritance $inheritance): array => [$inheritance->getChildId(), $inheritance->getParentId()],
        $inheritances
    );
});

it('returns an empty array without querying the database when no service ID is given', function (): void {
    $this->db->expects($this->never())->method('prepare');

    expect($this->repository->findParentsByServiceIds([]))->toBe([]);
});

it('returns the whole inheritance chain of each service', function (): void {
    // Services 10 and 11 share the template chain 1 -> 2 -> 3, service 12 has no template.
    ($this->mockInheritanceRows)([
        ['child_id' => 2, 'parent_id' => 3],
        ['child_id' => 10, 'parent_id' => 1],
        ['child_id' => 1, 'parent_id' => 2],
        ['child_id' => 11, 'parent_id' => 1],
    ]);

    $result = $this->repository->findParentsByServiceIds([10, 11, 12]);

    expect(array_keys($result))->toBe([10, 11, 12])
        ->and(($this->toPairs)($result[10]))->toBe([[10, 1], [1, 2], [2, 3]])
        ->and(($this->toPairs)($result[11]))->toBe([[11, 1], [1, 2], [2, 3]])
        ->and($result[12])->toBe([]);
});

it('follows every parent of a child', function (): void {
    ($this->mockInheritanceRows)([
        ['child_id' => 10, 'parent_id' => 1],
        ['child_id' => 1, 'parent_id' => 2],
        ['child_id' => 10, 'parent_id' => 5],
    ]);

    $result = $this->repository->findParentsByServiceIds([10]);

    expect(($this->toPairs)($result[10]))->toBe([[10, 1], [10, 5], [1, 2]]);
});

it('stops walking the inheritance chain when it contains a cycle', function (): void {
    ($this->mockInheritanceRows)([
        ['child_id' => 10, 'parent_id' => 1],
        ['child_id' => 1, 'parent_id' => 2],
        ['child_id' => 2, 'parent_id' => 1],
    ]);

    $result = $this->repository->findParentsByServiceIds([10]);

    expect(($this->toPairs)($result[10]))->toBe([[10, 1], [1, 2], [2, 1]]);
});
