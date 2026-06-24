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

use Adaptation\Log\LoggerUpgrade;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * Build a facade backed by a recording logger so the Monolog emission (level, message and
 * context shape) can be asserted without touching the real upgrade channel file. The
 * constructor is private and the facade is a singleton, so the spy is wired through
 * reflection — this keeps the production API free of any test-only seam.
 *
 * @return array{0: LoggerUpgrade, 1: object{records: list<array{level: string, message: string, context: array<string, mixed>}>}}
 */
function loggerUpgradeWithSpy(): array
{
    $spy = new class () extends AbstractLogger {
        /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
        public array $records = [];

        public function log($level, string|Stringable $message, array $context = []): void
        {
            $this->records[] = ['level' => (string) $level, 'message' => (string) $message, 'context' => $context];
        }
    };

    $reflection = new ReflectionClass(LoggerUpgrade::class);
    /** @var LoggerUpgrade $facade */
    $facade = $reflection->newInstanceWithoutConstructor();
    $reflection->getProperty('logger')->setValue($facade, $spy);

    return [$facade, $spy];
}

it('emits the start of the upgrade as an INFO carrying the from/to lifecycle context', function (): void {
    [$facade, $spy] = loggerUpgradeWithSpy();

    $facade->start('25.10.0', '25.11.0');

    expect($spy->records)->toHaveCount(1);
    $record = $spy->records[0];
    expect($record['level'])->toBe(LogLevel::INFO)
        ->and($record['message'])->toBe('Upgrade started from 25.10.0 to 25.11.0')
        ->and($record['context']['event'])->toBe('upgrade.start')
        ->and($record['context']['status'])->toBe('started')
        ->and($record['context']['from_version'])->toBe('25.10.0')
        ->and($record['context']['to_version'])->toBe('25.11.0')
        ->and($record['context'])->not->toHaveKey('exception');
});

it('emits the success with the measured duration', function (): void {
    [$facade, $spy] = loggerUpgradeWithSpy();

    $facade->success('25.10.0', '25.11.0', 4242);

    $record = $spy->records[0];
    expect($record['level'])->toBe(LogLevel::INFO)
        ->and($record['context']['event'])->toBe('upgrade.success')
        ->and($record['context']['status'])->toBe('success')
        ->and($record['context']['duration_ms'])->toBe(4242);
});

it('emits a failure as an ERROR and attaches the throwable, with nullable versions', function (): void {
    [$facade, $spy] = loggerUpgradeWithSpy();
    $exception = new RuntimeException('boom');

    $facade->failure('upgrade aborted', null, null, $exception);

    $record = $spy->records[0];
    expect($record['level'])->toBe(LogLevel::ERROR)
        ->and($record['message'])->toBe('upgrade aborted')
        ->and($record['context']['event'])->toBe('upgrade.failure')
        ->and($record['context']['status'])->toBe('failure')
        ->and($record['context']['from_version'])->toBeNull()
        ->and($record['context']['to_version'])->toBeNull()
        ->and($record['context']['exception'])->toBe($exception);
});

it('emits a step start as a running upgrade.step carrying the step name', function (): void {
    [$facade, $spy] = loggerUpgradeWithSpy();

    $facade->step('25.11.0', 'php_script', "Starting step 'php_script'");

    $record = $spy->records[0];
    expect($record['level'])->toBe(LogLevel::INFO)
        ->and($record['context']['event'])->toBe('upgrade.step')
        ->and($record['context']['status'])->toBe('running')
        ->and($record['context']['version'])->toBe('25.11.0')
        ->and($record['context']['step'])->toBe('php_script');
});

it('emits a step completion as a completed event carrying duration and step name', function (): void {
    [$facade, $spy] = loggerUpgradeWithSpy();

    $facade->stepCompleted('25.11.0', 'php_script', 124, "Step 'php_script' completed in 124ms");

    $record = $spy->records[0];
    expect($record['level'])->toBe(LogLevel::INFO)
        ->and($record['context']['event'])->toBe('upgrade.step_completed')
        ->and($record['context']['status'])->toBe('completed')
        ->and($record['context']['step'])->toBe('php_script')
        ->and($record['context']['duration_ms'])->toBe(124);
});

it('emits a step failure as an ERROR with the step name and the throwable', function (): void {
    [$facade, $spy] = loggerUpgradeWithSpy();
    $exception = new RuntimeException('SQL failed');

    $facade->stepFailure('step failed', '25.11.0', 'configuration_sql', $exception);

    $record = $spy->records[0];
    expect($record['level'])->toBe(LogLevel::ERROR)
        ->and($record['context']['event'])->toBe('upgrade.step_failure')
        ->and($record['context']['status'])->toBe('failure')
        ->and($record['context']['step'])->toBe('configuration_sql')
        ->and($record['context']['exception'])->toBe($exception);
});

it('emits free-form info and error events on the per-version context', function (): void {
    [$facade, $spy] = loggerUpgradeWithSpy();
    $exception = new RuntimeException('check failed');

    $facade->info('25.11.0', 'Adding column X');
    $facade->error('25.11.0', 'Schema check failed', $exception);

    expect($spy->records[0]['level'])->toBe(LogLevel::INFO)
        ->and($spy->records[0]['context']['event'])->toBe('upgrade.info')
        ->and($spy->records[0]['context']['version'])->toBe('25.11.0')
        ->and($spy->records[1]['level'])->toBe(LogLevel::ERROR)
        ->and($spy->records[1]['context']['event'])->toBe('upgrade.error')
        ->and($spy->records[1]['context']['exception'])->toBe($exception);
});

it('never lets a logging failure abort the upgrade', function (): void {
    $throwingLogger = new class () extends AbstractLogger {
        public int $attempts = 0;

        public function log($level, string|Stringable $message, array $context = []): void
        {
            $this->attempts++;

            throw new RuntimeException('log sink is down');
        }
    };

    $reflection = new ReflectionClass(LoggerUpgrade::class);
    /** @var LoggerUpgrade $facade */
    $facade = $reflection->newInstanceWithoutConstructor();
    $reflection->getProperty('logger')->setValue($facade, $throwingLogger);

    // The call must return normally: write() swallows the throwable (falling back to
    // error_log) so a broken log sink can never abort — or falsely fail — an upgrade.
    $facade->success('25.10.0', '25.11.0', 10);

    // The underlying logger was invoked and threw, yet the facade call completed.
    expect($throwingLogger->attempts)->toBe(1);
});
