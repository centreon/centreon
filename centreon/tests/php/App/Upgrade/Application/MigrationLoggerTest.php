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

namespace Tests\App\Upgrade\Application;

use App\Upgrade\Application\MigrationLogger;
use App\Upgrade\Application\MigrationOperationEnum;
use App\Upgrade\Domain\MigrationSet;
use PHPUnit\Framework\TestCase;
use Tests\App\Shared\Infrastructure\Legacy\Double\RecordingLogger;

final class MigrationLoggerTest extends TestCase
{
    private RecordingLogger $logger;

    private MigrationLogger $migrationLogger;

    protected function setUp(): void
    {
        $this->logger = new RecordingLogger();
        $this->migrationLogger = new MigrationLogger($this->logger, '26.12.0');
    }

    public function testWritesABalancedLifecycleWithTheContextOfTheLegacyUpdateScripts(): void
    {
        $set = MigrationSet::module('centreon-open-tickets');

        $startedAt = $this->migrationLogger->start(MigrationOperationEnum::Migrate, $set);
        $this->migrationLogger->success(MigrationOperationEnum::Migrate, $set, ['Version1'], $startedAt);

        [$start, $success] = $this->logger->records();
        self::assertSame('info', $start['level']);
        self::assertSame('Migration of the "centreon-open-tickets" module migration set started', $start['message']);
        self::assertSame(
            ['event' => 'migration.start', 'status' => 'started', 'version' => '26.12.0', 'set' => 'module:centreon-open-tickets'],
            $start['context'],
        );
        self::assertSame('migration.success', $success['context']['event'] ?? null);
        self::assertSame('success', $success['context']['status'] ?? null);
        self::assertSame(['Version1'], $success['context']['migrations'] ?? null);
        self::assertIsInt($success['context']['duration_ms'] ?? null);
    }

    public function testLogsAFailureAsAnErrorWithItsException(): void
    {
        $exception = new \RuntimeException('Lock refused');

        $this->migrationLogger->failure(MigrationOperationEnum::Rollback, MigrationSet::core(MigrationSet::REALTIME), $exception, $this->migrationLogger->start(MigrationOperationEnum::Rollback, MigrationSet::core(MigrationSet::REALTIME)));

        $record = $this->logger->lastRecord();
        self::assertSame('error', $record['level'] ?? null);
        self::assertSame('Rollback of the "realtime" migration set failed: Lock refused', $record['message']);
        self::assertSame('migration.rollback.failure', $record['context']['event'] ?? null);
        self::assertSame('realtime', $record['context']['set'] ?? null);
        self::assertSame($exception, $record['context']['exception'] ?? null);
    }

    public function testNamesTheCoreSetsWhenNoSetIsGiven(): void
    {
        $this->migrationLogger->statusFailure(null, new \LogicException('Database unavailable'));

        $record = $this->logger->lastRecord();
        self::assertSame('Cannot read the status of the core migration sets: Database unavailable', $record['message'] ?? null);
        self::assertSame('core', $record['context']['set'] ?? null);
        self::assertSame('migration.status_failure', $record['context']['event'] ?? null);
    }
}
