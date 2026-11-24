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

namespace CentreonLegacy\Core\Module;

use Core\Common\Domain\Exception\RepositoryException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\CentreonLegacy\Core\Module\Double\InMemoryOptionsConnection;

class ModuleUpgradeProgressRepositoryTest extends TestCase
{
    private const array NOTHING_PASSED = ['pre' => false, 'sql' => 0, 'checksum' => ''];

    private InMemoryOptionsConnection $db;

    private ModuleUpgradeProgressRepository $progress;

    protected function setUp(): void
    {
        $this->db = new InMemoryOptionsConnection();
        $this->progress = new ModuleUpgradeProgressRepository($this->db);
    }

    public function testNothingPassedBeforeTheFirstAttempt(): void
    {
        $this->assertSame(self::NOTHING_PASSED, $this->progress->get('centreon-open-tickets', '26.01.0'));
    }

    public function testRemembersWhereAnAttemptStoppedUntilCleared(): void
    {
        $this->progress->save('centreon-open-tickets', '26.01.0', ['pre' => true, 'sql' => 3, 'checksum' => 'abc']);

        $this->assertSame(['pre' => true, 'sql' => 3, 'checksum' => 'abc'], $this->progress->get('centreon-open-tickets', '26.01.0'));

        $this->progress->clear('centreon-open-tickets');

        $this->assertSame(self::NOTHING_PASSED, $this->progress->get('centreon-open-tickets', '26.01.0'));
    }

    public function testKeepsASingleRowPerModule(): void
    {
        $this->progress->save('centreon-open-tickets', '26.01.0', ['pre' => true, 'sql' => 1, 'checksum' => 'abc']);
        $this->progress->save('centreon-open-tickets', '26.01.0', ['pre' => true, 'sql' => 2, 'checksum' => 'abc']);
        // Unchanged: no row affected by the update.
        $this->progress->save('centreon-open-tickets', '26.01.0', ['pre' => true, 'sql' => 2, 'checksum' => 'abc']);

        $this->assertCount(1, $this->db->rows);
        $this->assertSame(['pre' => true, 'sql' => 2, 'checksum' => 'abc'], $this->progress->get('centreon-open-tickets', '26.01.0'));
    }

    public function testBuildsEachQueryOnceWhateverTheNumberOfSaves(): void
    {
        for ($query = 1; $query <= 5; ++$query) {
            $this->progress->save('centreon-open-tickets', '26.01.0', ['pre' => true, 'sql' => $query, 'checksum' => "c{$query}"]);
            $this->progress->get('centreon-open-tickets', '26.01.0');
        }
        $this->progress->clear('centreon-open-tickets');

        $this->assertSame(3, $this->db->queryBuilders);
    }

    public function testTheProgressOfAnotherVersionIsStale(): void
    {
        $this->progress->save('centreon-open-tickets', '26.01.0', ['pre' => true, 'sql' => 3, 'checksum' => 'abc']);

        $this->assertSame(self::NOTHING_PASSED, $this->progress->get('centreon-open-tickets', '26.02.0'));
    }

    public function testEachModuleHasItsOwnProgress(): void
    {
        $this->progress->save('centreon-open-tickets', '26.01.0', ['pre' => true, 'sql' => 3, 'checksum' => 'abc']);
        $this->progress->save('centreon-open-tickets-extra', '26.01.0', ['pre' => true, 'sql' => 1, 'checksum' => 'def']);
        $this->progress->save('centreon-open-tickets', '26.01.0', ['pre' => true, 'sql' => 4, 'checksum' => 'abc']);

        $this->progress->clear('centreon-open-tickets-extra');

        $this->assertSame(['pre' => true, 'sql' => 4, 'checksum' => 'abc'], $this->progress->get('centreon-open-tickets', '26.01.0'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideUnreadableProgresses(): iterable
    {
        yield 'not json' => ['{"version":"26.01.0","pre":true'];

        yield 'not an object' => ['"26.01.0"'];

        yield 'no version' => ['{"pre":true,"sql":2,"checksum":"abc"}'];

        yield 'a query count that is not a number' => ['{"version":"26.01.0","pre":true,"sql":"2","checksum":"abc"}'];

        yield 'no checksum' => ['{"version":"26.01.0","pre":true,"sql":2}'];
    }

    #[DataProvider('provideUnreadableProgresses')]
    public function testAnUnreadableProgressIsRefusedRatherThanReplayed(string $value): void
    {
        $this->db->rows['module_upgrade_progress:centreon-open-tickets'] = $value;

        $this->expectException(RepositoryException::class);
        $this->expectExceptionMessage('delete the "module_upgrade_progress:centreon-open-tickets" option');

        $this->progress->get('centreon-open-tickets', '26.01.0');
    }

    public function testAProgressThatCannotBeWrittenIsReported(): void
    {
        $failure = new \RuntimeException('Lock wait timeout exceeded');
        $this->db->failOn('UPDATE', $failure);

        try {
            $this->progress->save('centreon-open-tickets', '26.01.0', ['pre' => true, 'sql' => 3, 'checksum' => 'abc']);
            $this->fail('The write failure must propagate');
        } catch (RepositoryException $exception) {
            $this->assertSame($failure, $exception->getPrevious());
            $this->assertSame('centreon-open-tickets', $exception->getBusinessContext()['module'] ?? null);
            $this->assertSame('26.01.0', $exception->getBusinessContext()['version'] ?? null);
        }
    }

    public function testAProgressThatCannotBeClearedIsReported(): void
    {
        $this->db->failOn('DELETE', new \RuntimeException('Lock wait timeout exceeded'));

        $this->expectException(RepositoryException::class);

        $this->progress->clear('centreon-open-tickets');
    }

    public function testAProgressThatCannotBeReadIsReported(): void
    {
        $this->db->failOn('SELECT', new \RuntimeException('Lock wait timeout exceeded'));

        $this->expectException(RepositoryException::class);

        $this->progress->get('centreon-open-tickets', '26.01.0');
    }
}
