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

use App\Upgrade\Infrastructure\Log\UpgradeLogger;
use PHPUnit\Framework\TestCase;
use Tests\App\Shared\Infrastructure\Legacy\Double\RecordingLogger;

final class UpgradeLoggerTest extends TestCase
{
    private RecordingLogger $logger;

    private UpgradeLogger $upgradeLogger;

    protected function setUp(): void
    {
        $this->logger = new RecordingLogger();
        $this->upgradeLogger = new UpgradeLogger($this->logger);
    }

    public function testWritesTheContextOfTheLegacyLoggerUpgrade(): void
    {
        $exception = new \RuntimeException('Duplicate column');

        $this->upgradeLogger->start('26.10.0', '26.12.0');
        $this->upgradeLogger->step('26.12.0', 'migrations:realtime', "Starting step 'migrations:realtime'");
        $this->upgradeLogger->stepCompleted('26.12.0', 'migrations:realtime', 42, 'Done');
        $this->upgradeLogger->stepFailure('26.12.0', 'migrations:configuration', 'Failed', $exception);
        $this->upgradeLogger->info('26.12.0', 'Recorded');
        $this->upgradeLogger->warning('26.12.0', 'Refused');
        $this->upgradeLogger->success('26.10.0', '26.12.0', 42);
        $this->upgradeLogger->failure('26.10.0', '26.12.0', 'Failed', $exception);

        self::assertEquals(
            [
                ['level' => 'info', 'message' => 'Upgrade started from 26.10.0 to 26.12.0', 'context' => ['event' => 'upgrade.start', 'status' => 'started', 'from_version' => '26.10.0', 'to_version' => '26.12.0']],
                ['level' => 'info', 'message' => "Starting step 'migrations:realtime'", 'context' => ['event' => 'upgrade.step', 'status' => 'running', 'version' => '26.12.0', 'step' => 'migrations:realtime']],
                ['level' => 'info', 'message' => 'Done', 'context' => ['event' => 'upgrade.step_completed', 'status' => 'completed', 'version' => '26.12.0', 'step' => 'migrations:realtime', 'duration_ms' => 42]],
                ['level' => 'error', 'message' => 'Failed', 'context' => ['event' => 'upgrade.step_failure', 'status' => 'failure', 'version' => '26.12.0', 'step' => 'migrations:configuration', 'exception' => $exception]],
                ['level' => 'info', 'message' => 'Recorded', 'context' => ['event' => 'upgrade.info', 'status' => 'info', 'version' => '26.12.0']],
                ['level' => 'warning', 'message' => 'Refused', 'context' => ['event' => 'upgrade.warning', 'status' => 'warning', 'version' => '26.12.0']],
                ['level' => 'info', 'message' => 'Upgrade from 26.10.0 to 26.12.0 completed successfully', 'context' => ['event' => 'upgrade.success', 'status' => 'success', 'from_version' => '26.10.0', 'to_version' => '26.12.0', 'duration_ms' => 42]],
                ['level' => 'error', 'message' => 'Failed', 'context' => ['event' => 'upgrade.failure', 'status' => 'failure', 'from_version' => '26.10.0', 'to_version' => '26.12.0', 'exception' => $exception]],
            ],
            $this->logger->records(),
        );
    }

    public function testLeavesTheExceptionOutWhenThereIsNone(): void
    {
        $this->upgradeLogger->error('26.12.0', 'Refused');

        self::assertSame(['event' => 'upgrade.error', 'status' => 'error', 'version' => '26.12.0'], $this->logger->lastRecord()['context'] ?? null);
    }
}
