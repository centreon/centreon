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

namespace Tests\App\Upgrade\Infrastructure\Log;

use App\Upgrade\Infrastructure\Log\FailSafeLogger;
use PHPUnit\Framework\TestCase;
use Tests\App\Shared\Infrastructure\Legacy\Double\RecordingLogger;
use Tests\App\Upgrade\Infrastructure\Double\UnwritableLogger;

final class FailSafeLoggerTest extends TestCase
{
    private string $errorLog;

    private string $previousErrorLog;

    protected function setUp(): void
    {
        $this->errorLog = (string) tempnam(sys_get_temp_dir(), 'upgrade');
        $this->previousErrorLog = (string) ini_set('error_log', $this->errorLog);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', $this->previousErrorLog);
        unlink($this->errorLog);
    }

    public function testForwardsTheLineToTheUpgradeChannel(): void
    {
        $channel = new RecordingLogger();

        (new FailSafeLogger($channel))->info('Step completed', ['step' => 'migrations:realtime']);

        self::assertSame(['level' => 'info', 'message' => 'Step completed', 'context' => ['step' => 'migrations:realtime']], $channel->lastRecord());
        self::assertSame('', file_get_contents($this->errorLog));
    }

    public function testKeepsTheLineAndItsExceptionInTheErrorLogWhenTheChannelFails(): void
    {
        $channel = new UnwritableLogger(new \UnexpectedValueException('Permission denied'));

        $cause = new \RuntimeException('Duplicate column');
        (new FailSafeLogger($channel))->error('Step failed', [
            'step' => 'migrations:realtime',
            'from_version' => '26.10.0',
            'to_version' => '26.12.0',
            'migration_version' => 'Version1',
            'duration_ms' => 12.5,
            'exception' => new \LogicException('Migration failed', 0, $cause),
        ]);

        $errorLog = (string) file_get_contents($this->errorLog);
        self::assertStringContainsString(
            'Upgrade log unavailable (Permission denied): [error] Step failed {"step":"migrations:realtime","from_version":"26.10.0","to_version":"26.12.0","migration_version":"Version1"}',
            $errorLog,
        );
        self::assertStringContainsString('LogicException: Migration failed in ' . __FILE__, $errorLog);
        self::assertStringContainsString('Next', $errorLog);
    }
}
