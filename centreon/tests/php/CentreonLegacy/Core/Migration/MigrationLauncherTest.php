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

namespace CentreonLegacy\Core\Migration;

use PHPUnit\Framework\TestCase;

class MigrationLauncherTest extends TestCase
{
    public function testExtractsTheRenderedExceptionMessage(): void
    {
        // Shape of a failing `bin/console.new centreon:migrations:migrate` in prod: the console log line,
        // the exception rendered by the console application, then the command usage.
        $output = <<<'OUTPUT'
            [2026-10-13T07:07:40.895318+00:00] console.CRITICAL: Error thrown while running command "{command}". Message: "{message}" {"exception":{}} {"uid":"5d7eef5"}

            In DoctrineMigrationRunner.php line 146:
                                                                                                    
              The "configuration" migration set has executed migrations that are not available anymore.  
                                                                                                    

            centreon:migrations:migrate [--set SET]

            OUTPUT;

        self::assertSame(
            'The "configuration" migration set has executed migrations that are not available anymore.',
            MigrationLauncher::extractFailureReason($output),
        );
    }

    public function testKeepsTheWholeOutputWhenNoExceptionIsRendered(): void
    {
        self::assertSame('PHP Fatal error: Allowed memory size exhausted', MigrationLauncher::extractFailureReason("PHP Fatal error:\n  Allowed memory size exhausted\n"));
    }
}
