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
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\Service\Infrastructure\Repository\DbWriteRealTimeServiceRepository;
use PHPUnit\Framework\TestCase;

final class DbWriteRealTimeServiceRepositoryTest extends TestCase
{
    /** @var list<string> every prepared query, in order */
    private array $queries = [];

    /** @var array<int, array<string, mixed>> the values bound to each prepared query */
    private array $bindings = [];

    private DbWriteRealTimeServiceRepository $repository;

    protected function setUp(): void
    {
        $db = $this->createMock(DatabaseConnection::class);
        $db->method('getConnectionConfig')->willReturn(
            new ConnectionConfig('localhost', 'user', 'password', 'centreon', 'centreon_storage')
        );
        $db->method('prepare')->willReturnCallback(function (string $query): \PDOStatement {
            $index = count($this->queries);
            $this->queries[] = $query;
            $this->bindings[$index] = [];
            $statement = $this->createMock(\PDOStatement::class);
            $statement->method('bindValue')->willReturnCallback(
                function (string $name, mixed $value) use ($index): bool {
                    $this->bindings[$index][$name] = $value;

                    return true;
                }
            );
            $statement->method('execute')->willReturn(true);

            return $statement;
        });

        $this->repository = new DbWriteRealTimeServiceRepository($db);
    }

    public function testItRemovesTheFingerprintOfTheAccessGroupsThatGotTheService(): void
    {
        // centAcl.php must not skip these groups at their next recompute.
        $this->repository->addServiceToResourceAcls(42, 7, [new AccessGroup(3, 'group-3', 'group-3'), new AccessGroup(5, 'group-5', 'group-5')]);

        self::assertCount(2, $this->queries);
        self::assertStringContainsString('INSERT INTO `centreon_storage`.`centreon_acl`', $this->queries[0]);
        self::assertSame(
            'DELETE FROM `centreon_storage`.`centreon_acl_checksum` WHERE `group_id` IN (:group_0, :group_1)',
            $this->queries[1]
        );
        self::assertSame([':group_0' => 3, ':group_1' => 5], $this->bindings[1]);
    }
}
