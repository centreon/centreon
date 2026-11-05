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

namespace App\Upgrade\Application;

use App\Upgrade\Domain\MigrationSet;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Writes the migration lifecycle to the upgrade log, with the context keys of the legacy update scripts.
 */
final readonly class MigrationLogger
{
    private const string CORE_SETS = 'core';

    public function __construct(
        private LoggerInterface $upgradeLogger,
        #[Autowire(param: 'centreon.version')]
        private string $platformVersion,
    ) {
    }

    /**
     * @param MigrationSet|null $set null for all the core sets
     *
     * @return int the start time, to pass to success() or failure()
     */
    public function start(MigrationOperationEnum $operation, ?MigrationSet $set): int
    {
        $this->write(LogLevel::INFO, $operation->value . '.start', 'started', sprintf('%s of the %s started', $this->title($operation), $this->label($set)), $set);

        return hrtime(true);
    }

    /**
     * @param MigrationSet|null $set null for all the core sets
     * @param list<string> $migrations the executed or reverted versions
     */
    public function success(MigrationOperationEnum $operation, ?MigrationSet $set, array $migrations, int $startedAt): void
    {
        $durationMs = $this->elapsedMs($startedAt);
        $this->write(
            LogLevel::INFO,
            $operation->value . '.success',
            'success',
            sprintf('%s of the %s completed: %d migration(s) in %dms', $this->title($operation), $this->label($set), \count($migrations), $durationMs),
            $set,
            ['migrations' => $migrations, 'duration_ms' => $durationMs],
        );
    }

    /**
     * @param MigrationSet|null $set null for all the core sets
     */
    public function failure(MigrationOperationEnum $operation, ?MigrationSet $set, \Throwable $exception, int $startedAt): void
    {
        $durationMs = $this->elapsedMs($startedAt);
        $this->write(
            LogLevel::ERROR,
            $operation->value . '.failure',
            'failure',
            sprintf('%s of the %s failed: %s', $this->title($operation), $this->label($set), $exception->getMessage()),
            $set,
            ['duration_ms' => $durationMs, 'exception' => $exception],
        );
    }

    public function versionRecorded(string $version, string $previousVersion): void
    {
        $this->write(
            LogLevel::INFO,
            'migration.version_recorded',
            'recorded',
            sprintf('Recorded the platform version %s, previously %s', $version, $previousVersion),
            null,
            ['recorded_version' => $version, 'previous_version' => $previousVersion],
        );
    }

    /**
     * @param MigrationSet|null $set null for all the core sets
     */
    public function statusFailure(?MigrationSet $set, \Throwable $exception): void
    {
        $this->write(
            LogLevel::ERROR,
            'migration.status_failure',
            'failure',
            sprintf('Cannot read the status of the %s: %s', $this->label($set), $exception->getMessage()),
            $set,
            ['exception' => $exception],
        );
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function write(string $level, string $event, string $status, string $message, ?MigrationSet $set, array $extra = []): void
    {
        $this->upgradeLogger->log($level, $message, [
            'event' => $event,
            'status' => $status,
            'version' => $this->platformVersion,
            'set' => $set->name ?? self::CORE_SETS,
            ...$extra,
        ]);
    }

    private function elapsedMs(int $startedAt): int
    {
        return intdiv(hrtime(true) - $startedAt, 1_000_000);
    }

    private function title(MigrationOperationEnum $operation): string
    {
        return $operation === MigrationOperationEnum::Migrate ? 'Migration' : 'Rollback';
    }

    private function label(?MigrationSet $set): string
    {
        return $set?->label() ?? 'core migration sets';
    }
}
