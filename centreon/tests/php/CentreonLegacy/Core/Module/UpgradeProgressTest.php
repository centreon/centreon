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

use Centreon\Test\Mock\CentreonDB;
use PHPUnit\Framework\TestCase;
use Tests\CentreonLegacy\Core\Module\InMemoryOptionsDB;

class UpgradeProgressTest extends TestCase
{
    private const array NOTHING_PASSED = ['pre' => false, 'sql' => 0, 'checksum' => ''];

    private InMemoryOptionsDB $db;

    private UpgradeProgress $progress;

    protected function setUp(): void
    {
        $this->db = new InMemoryOptionsDB();
        $this->progress = new UpgradeProgress($this->db);
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

    public function testACorruptedProgressStartsOver(): void
    {
        $this->db->rows['module_upgrade_progress:centreon-open-tickets'] = '{"version":"26.01.0","pre":"yes"';

        $this->assertSame(self::NOTHING_PASSED, $this->progress->get('centreon-open-tickets', '26.01.0'));
    }

    public function testAProgressThatCannotBeWrittenIsNotFatal(): void
    {
        // No query registered: every one of them fails.
        $progress = new UpgradeProgress(new CentreonDB());

        $progress->save('centreon-open-tickets', '26.01.0', ['pre' => true, 'sql' => 3, 'checksum' => 'abc']);
        $progress->clear('centreon-open-tickets');

        $this->addToAssertionCount(1);
    }
}
