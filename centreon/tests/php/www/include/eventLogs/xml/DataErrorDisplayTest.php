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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class DataErrorDisplayTest extends TestCase
{
    private const PROBE_MESSAGE = 'MON-216209-probe';

    private string $rootDirectory;

    protected function setUp(): void
    {
        $this->rootDirectory = sys_get_temp_dir() . '/centreon-event-logs-data-' . bin2hex(random_bytes(8));
        mkdir($this->rootDirectory . '/config', 0o700, true);
        mkdir($this->rootDirectory . '/a/b/c/d', 0o700, true);

        // data.php includes the configuration file through a path relative to the working directory:
        // this fake one raises a PHP notice and stops the script before any database access.
        file_put_contents(
            $this->rootDirectory . '/config/centreon.config.php',
            "<?php\ntrigger_error('" . self::PROBE_MESSAGE . "', E_USER_DEPRECATED);\nexit(0);\n"
        );
    }

    protected function tearDown(): void
    {
        unlink($this->rootDirectory . '/config/centreon.config.php');
        foreach (['/a/b/c/d', '/a/b/c', '/a/b', '/a', '/config', ''] as $directory) {
            rmdir($this->rootDirectory . $directory);
        }
    }

    public function testPhpNoticeRaisedDuringRequestIsNotWrittenToResponse(): void
    {
        $process = new Process(
            [
                PHP_BINARY,
                '-d', 'display_errors=1',
                '-d', 'error_reporting=-1',
                '-d', 'log_errors=0',
                __DIR__ . '/../../../../../../www/include/eventLogs/xml/data.php',
            ],
            $this->rootDirectory . '/a/b/c/d'
        );
        $process->run();

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertStringNotContainsString(self::PROBE_MESSAGE, $process->getOutput());
    }
}
