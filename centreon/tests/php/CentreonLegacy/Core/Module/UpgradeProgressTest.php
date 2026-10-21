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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class UpgradeProgressTest extends TestCase
{
    private string $directory;

    private UpgradeProgress $progress;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/' . uniqid('module-upgrades_', true);
        $this->progress = new UpgradeProgress($this->directory);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    public function testNothingPassedBeforeTheFirstAttempt(): void
    {
        $this->assertSame(['pre' => false, 'sql' => 0, 'checksum' => ''], $this->progress->get('centreon-open-tickets', '26.01.0'));
    }

    public function testRemembersWhereAnAttemptStoppedUntilCleared(): void
    {
        $this->progress->save('centreon-open-tickets', '26.01.0', ['pre' => true, 'sql' => 3, 'checksum' => 'abc']);

        $this->assertSame(['pre' => true, 'sql' => 3, 'checksum' => 'abc'], $this->progress->get('centreon-open-tickets', '26.01.0'));
        $this->assertSame(['pre' => false, 'sql' => 0, 'checksum' => ''], $this->progress->get('centreon-open-tickets', '26.02.0'));

        $this->progress->clear('centreon-open-tickets', '26.01.0');

        $this->assertSame(['pre' => false, 'sql' => 0, 'checksum' => ''], $this->progress->get('centreon-open-tickets', '26.01.0'));
    }

    public function testClearingAModuleForgetsAllItsVersionsOnly(): void
    {
        $this->progress->save('centreon-open-tickets', '26.01.0', ['pre' => true, 'sql' => 3, 'checksum' => 'abc']);
        $this->progress->save('centreon-open-tickets', '26.02.0', ['pre' => true, 'sql' => 3, 'checksum' => 'abc']);
        $this->progress->save('centreon-open-tickets-extra', '26.01.0', ['pre' => true, 'sql' => 3, 'checksum' => 'abc']);

        $this->progress->clear('centreon-open-tickets');

        $this->assertSame(['pre' => false, 'sql' => 0, 'checksum' => ''], $this->progress->get('centreon-open-tickets', '26.01.0'));
        $this->assertSame(['pre' => false, 'sql' => 0, 'checksum' => ''], $this->progress->get('centreon-open-tickets', '26.02.0'));
        $this->assertSame(['pre' => true, 'sql' => 3, 'checksum' => 'abc'], $this->progress->get('centreon-open-tickets-extra', '26.01.0'));
    }

    public function testAnUnwritableDirectoryIsNotFatal(): void
    {
        // A file where the directory should be: nothing can be written in it.
        touch($this->directory);

        $this->progress->save('centreon-open-tickets', '26.01.0', ['pre' => true, 'sql' => 3, 'checksum' => 'abc']);
        $this->progress->clear('centreon-open-tickets', '26.01.0');

        $this->assertSame(['pre' => false, 'sql' => 0, 'checksum' => ''], $this->progress->get('centreon-open-tickets', '26.01.0'));
    }

    public function testAProgressThatCannotBeClearedIsNotFatal(): void
    {
        $this->progress->save('centreon-open-tickets', '26.01.0', ['pre' => true, 'sql' => 3, 'checksum' => 'abc']);
        // A read-only directory: the progress file cannot be removed.
        chmod($this->directory . '/centreon-open-tickets', 0500);

        try {
            $this->progress->clear('centreon-open-tickets', '26.01.0');
            $this->assertSame(['pre' => true, 'sql' => 3, 'checksum' => 'abc'], $this->progress->get('centreon-open-tickets', '26.01.0'));
        } finally {
            chmod($this->directory . '/centreon-open-tickets', 0700);
        }
    }

    public function testKeepsItsFilesInItsDirectory(): void
    {
        $this->progress->save('../../etc', '1.0.0', ['pre' => true, 'sql' => 0, 'checksum' => '']);

        $this->assertCount(1, glob($this->directory . '/*/*.json') ?: []);
    }
}
