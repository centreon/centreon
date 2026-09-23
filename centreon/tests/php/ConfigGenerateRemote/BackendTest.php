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

namespace Tests\ConfigGenerateRemote;

use ConfigGenerateRemote\Backend;
use PHPUnit\Framework\TestCase;

class BackendTest extends TestCase
{
    /** @var string */
    private $exportPath;

    protected function setUp(): void
    {
        $this->exportPath = sys_get_temp_dir() . '/centreon-export-' . uniqid('', true);
        mkdir($this->exportPath, 0770, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->exportPath)) {
            system('rm -rf ' . escapeshellarg($this->exportPath));
        }
    }

    public function testCleanOrphanedTmpDirsRemovesStaleDirectory(): void
    {
        $stale = $this->exportPath . '/' . Backend::TMP_DIR_PREFIX . 'AAAAAA' . Backend::TMP_DIR_SUFFIX;
        mkdir($stale, 0770, true);
        touch($stale, time() - 7200);

        $removed = Backend::cleanOrphanedTmpDirs(3600, $this->exportPath);

        $this->assertSame(1, $removed);
        $this->assertDirectoryDoesNotExist($stale);
    }

    public function testCleanOrphanedTmpDirsKeepsFreshDirectory(): void
    {
        $fresh = $this->exportPath . '/' . Backend::TMP_DIR_PREFIX . 'BBBBBB' . Backend::TMP_DIR_SUFFIX;
        mkdir($fresh, 0770, true);
        touch($fresh, time() - 60);

        $removed = Backend::cleanOrphanedTmpDirs(3600, $this->exportPath);

        $this->assertSame(0, $removed);
        $this->assertDirectoryExists($fresh);
    }

    public function testCleanOrphanedTmpDirsRemovesStaleWitnessFile(): void
    {
        $witness = $this->exportPath . '/' . Backend::TMP_DIR_PREFIX . 'CCCCCC';
        file_put_contents($witness, '');
        touch($witness, time() - 7200);

        $removed = Backend::cleanOrphanedTmpDirs(3600, $this->exportPath);

        $this->assertSame(1, $removed);
        $this->assertFileDoesNotExist($witness);
    }

    public function testCleanOrphanedTmpDirsIgnoresFinalizedPollerDirectories(): void
    {
        $pollerDir = $this->exportPath . '/1';
        mkdir($pollerDir, 0770, true);
        touch($pollerDir, time() - 7200);

        $removed = Backend::cleanOrphanedTmpDirs(3600, $this->exportPath);

        $this->assertSame(0, $removed);
        $this->assertDirectoryExists($pollerDir);
    }

    public function testCleanOrphanedTmpDirsIsNoOpForNonPositiveMaxAge(): void
    {
        $stale = $this->exportPath . '/' . Backend::TMP_DIR_PREFIX . 'DDDDDD' . Backend::TMP_DIR_SUFFIX;
        mkdir($stale, 0770, true);
        touch($stale, time() - 7200);

        $this->assertSame(0, Backend::cleanOrphanedTmpDirs(0, $this->exportPath));
        $this->assertSame(0, Backend::cleanOrphanedTmpDirs(-10, $this->exportPath));
        $this->assertDirectoryExists($stale);
    }

    public function testCleanOrphanedTmpDirsReturnsZeroWhenExportDirectoryMissing(): void
    {
        $removed = Backend::cleanOrphanedTmpDirs(3600, $this->exportPath . '/does-not-exist');

        $this->assertSame(0, $removed);
    }
}
