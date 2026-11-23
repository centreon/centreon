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

namespace Tests\www\include\eventLogs\xml;

use CentreonDbException;
use LogIterator;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\TestCase;

// The legacy page classes live outside the Composer classmap.
require_once __DIR__ . '/../../../../../../www/include/eventLogs/xml/LogIterator.php';

final class LogIteratorTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function testGivesAllTheRowsInOrder(): void
    {
        $statement = $this->execute('SELECT 1 AS id UNION ALL SELECT 2 UNION ALL SELECT 3');

        $rows = iterator_to_array(new LogIterator($statement), false);

        $this->assertSame([['id' => 1], ['id' => 2], ['id' => 3]], $rows);
    }

    public function testGivesTheFirstRowOnceWhenItWasReadBeforeTheIteration(): void
    {
        $logs = (new LogIterator($this->execute('SELECT 1 AS id UNION ALL SELECT 2')))->getIterator();
        $logs->current();

        $this->assertSame([['id' => 1], ['id' => 2]], iterator_to_array($logs, false));
    }

    public function testTurnsAReadErrorIntoACentreonDbException(): void
    {
        // The second row overflows: SQLite reports it only when that row is read, like MySQL for a timeout.
        $statement = $this->execute('SELECT 1 AS id UNION ALL SELECT abs(-9223372036854775807 - 1)');
        $readRows = [];

        try {
            foreach (new LogIterator($statement) as $row) {
                $readRows[] = $row;
            }
            $this->fail('The read error was not reported.');
        } catch (CentreonDbException $exception) {
            $this->assertSame([['id' => 1]], $readRows);
            $this->assertInstanceOf(PDOException::class, $exception->getPrevious());
            $this->assertSame($statement->queryString, $exception->getBusinessContext()['query']);
        }
    }

    private function execute(string $query): PDOStatement
    {
        $statement = $this->pdo->prepare($query);
        $statement->execute();

        return $statement;
    }
}
