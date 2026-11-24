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

namespace CentreonModule\Domain\Repository;

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Adaptation\Database\QueryBuilder\QueryBuilderInterface;
use Centreon\Test\Mock\CentreonDB;
use Core\Common\Domain\Exception\RepositoryException;
use PHPUnit\Framework\TestCase;
use Tests\CentreonLegacy\Core\Module\Double\OfflineQueryBuilder;

class ModulesInformationsRepositoryTest extends TestCase
{
    private const string TABLE = 'doctrine_migration_versions_centreon_open_tickets';

    public function testFindsTheExecutedMigrationsOfAModule(): void
    {
        $db = $this->databaseWithTables([self::TABLE => [
            'CentreonOpenTickets\\Migrations\\Version20260101000001',
            'CentreonOpenTickets\\Migrations\\Version20260101000002',
        ]]);

        $this->assertSame(
            ['Version20260101000001', 'Version20260101000002'],
            (new ModulesInformationsRepository($db))->findExecutedMigrations('centreon-open-tickets'),
        );
    }

    public function testNoMigrationRanBeforeTheMetadataTableExists(): void
    {
        // Created by the first migration run. Not queried: the failure would be logged as an SQL error.
        $db = $this->databaseWithTables([]);

        $this->assertSame([], (new ModulesInformationsRepository($db))->findExecutedMigrations('centreon-open-tickets'));
    }

    public function testAReadFailureIsWrappedInARepositoryException(): void
    {
        $failure = new \RuntimeException('Lock wait timeout exceeded');
        $db = $this->databaseWithTables([self::TABLE => []], $failure);

        try {
            (new ModulesInformationsRepository($db))->findExecutedMigrations('centreon-open-tickets');
            $this->fail('The read failure must propagate');
        } catch (RepositoryException $exception) {
            $this->assertSame($failure, $exception->getPrevious());
            $this->assertSame('centreon-open-tickets', $exception->getBusinessContext()['module'] ?? null);
        }
    }

    public function testIgnoresUnsupportedModuleNames(): void
    {
        $this->assertNull((new ModulesInformationsRepository(new CentreonDB()))->findExecutedMigrations('../../etc'));
    }

    /**
     * @param array<string, list<string>> $tables the migration metadata tables and their versions
     */
    private function databaseWithTables(array $tables, ?\Throwable $readFailure = null): CentreonDB
    {
        return new class ($tables, $readFailure) extends CentreonDB {
            /**
             * @param array<string, list<string>> $tables
             */
            public function __construct(private readonly array $tables, private readonly ?\Throwable $readFailure)
            {
            }

            public function createQueryBuilder(): QueryBuilderInterface
            {
                return OfflineQueryBuilder::create();
            }

            public function fetchOne(string $query, ?QueryParameters $queryParameters = null): mixed
            {
                foreach ($queryParameters?->getIterator() ?? [] as $parameter) {
                    if ($parameter instanceof QueryParameter && $parameter->getName() === 'table') {
                        return array_key_exists((string) $parameter->getValue(), $this->tables) ? 1 : false;
                    }
                }

                throw new \LogicException('The table name is not bound');
            }

            public function fetchFirstColumn(string $query, ?QueryParameters $queryParameters = null): array
            {
                if ($this->readFailure !== null) {
                    throw $this->readFailure;
                }

                if (preg_match('/^SELECT `version` FROM `(\w+)`$/', $query, $matches) !== 1 || ! isset($this->tables[$matches[1]])) {
                    throw new \LogicException('Unexpected query: ' . $query);
                }

                return $this->tables[$matches[1]];
            }
        };
    }
}
