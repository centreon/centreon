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

namespace Tests\App\Upgrade\Infrastructure\Doctrine;

use App\Upgrade\Infrastructure\Doctrine\MigrationStepLogger;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use PHPUnit\Framework\TestCase;
use Tests\App\Shared\Infrastructure\Legacy\Double\RecordingLogger;

final class MigrationStepLoggerTest extends TestCase
{
    public function testGivesTheDoctrineLinesTheContextOfTheUpgradeSteps(): void
    {
        $logger = new RecordingLogger();

        (new MigrationStepLogger($logger, '26.12.0', 'migrations:realtime'))->info(
            'Migration {version} {direction} (took {time}ms)',
            ['version' => 'Centreon\Migrations\Realtime\Version20261201000000', 'direction' => 'up', 'time' => 12.5],
        );

        self::assertSame(
            [
                'level' => 'info',
                'message' => 'Migration Centreon\Migrations\Realtime\Version20261201000000 up (took 12.5ms)',
                'context' => [
                    'direction' => 'up',
                    'time' => 12.5,
                    'migration_version' => 'Centreon\Migrations\Realtime\Version20261201000000',
                    'event' => 'upgrade.migration',
                    'status' => 'running',
                    'version' => '26.12.0',
                    'step' => 'migrations:realtime',
                ],
            ],
            $logger->lastRecord(),
        );
    }

    public function testMarksAnErrorAsAFailure(): void
    {
        $logger = new RecordingLogger();

        (new MigrationStepLogger($logger, '26.12.0', 'migrations_rollback:configuration'))->error('Migration {version} failed', ['version' => 'Version1']);

        self::assertSame('failure', $logger->lastRecord()['context']['status'] ?? null);
        self::assertSame('Migration Version1 failed', $logger->lastRecord()['message']);
    }

    public function testKeepsTheReasonOfASkipAndMarksItAsAFailure(): void
    {
        $logger = new RecordingLogger();
        $stepLogger = new MigrationStepLogger($logger, '26.12.0', 'migrations_rollback:configuration');

        $stepLogger->notice('Migration {version} skipped during {state}. Reason: "{reason}"', ['version' => 'Version1', 'reason' => 'Irreversible', 'state' => 'Execution']);

        self::assertSame('failure', $logger->lastRecord()['context']['status'] ?? null);
        self::assertSame('Irreversible', $stepLogger->skipReason('Version1'));
        self::assertNull($stepLogger->skipReason('Version2'));
    }

    public function testKeepsOnlyTheWarningsOfTheMigrations(): void
    {
        $stepLogger = new MigrationStepLogger(new RecordingLogger(), '26.12.0', 'migrations:configuration');
        $migration = new class (DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $stepLogger) extends AbstractMigration {
            public function up(Schema $schema): void
            {
            }
        };

        $stepLogger->warning('2 rows were left out', ['migration' => $migration]);
        $stepLogger->warning('Migration {version} was executed but did not result in any SQL statements.', ['version' => 'Version1']);
        $stepLogger->info('Not a warning', ['migration' => $migration]);

        self::assertSame([$migration::class . ': 2 rows were left out'], $stepLogger->warnings());
    }
}
