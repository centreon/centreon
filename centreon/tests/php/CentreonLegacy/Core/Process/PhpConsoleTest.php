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

namespace CentreonLegacy\Core\Process;

use PHPUnit\Framework\TestCase;

class PhpConsoleTest extends TestCase
{
    public function testRunsAConsoleOfTheCodeBaseWithTheCurrentPhpBinaryAndNoTimeout(): void
    {
        $process = PhpConsole::process(['bin/console', 'cache:clear'], ['COLUMNS' => '1000']);

        $this->assertSame(\dirname(__DIR__, 5), $process->getWorkingDirectory());
        $this->assertFileExists($process->getWorkingDirectory() . '/bin/console');
        $this->assertStringEndsWith("'bin/console' 'cache:clear'", $process->getCommandLine());
        $this->assertStringStartsWith("'" . PHP_BINARY . "'", $process->getCommandLine());
        $this->assertNull($process->getTimeout());
        $this->assertSame(['COLUMNS' => '1000'], $process->getEnv());
    }
}
