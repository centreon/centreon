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

namespace Tests\Core\Platform\Infrastructure\Repository;

use Adaptation\Log\LoggerUpgrade;
use Centreon\Domain\Repository\RepositoryException;
use Core\Platform\Infrastructure\Repository\DbWriteUpdateRepository;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

beforeEach(function (): void {
    // Install a recording logger as the LoggerUpgrade singleton so the upgrade events emitted by
    // executeStep() can be asserted without writing to the real upgrade channel. The facade is a
    // singleton with a private constructor, so the spy is wired through reflection.
    $this->spy = new class () extends AbstractLogger {
        /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
        public array $records = [];

        /**
         * @param array<string, mixed> $context
         * @param mixed $level
         */
        public function log($level, string|\Stringable $message, array $context = []): void
        {
            $this->records[] = [
                'level' => is_scalar($level) ? (string) $level : '',
                'message' => (string) $message,
                'context' => $context,
            ];
        }
    };

    $reflection = new \ReflectionClass(LoggerUpgrade::class);
    $facade = $reflection->newInstanceWithoutConstructor();
    $reflection->getProperty('logger')->setValue($facade, $this->spy);
    $reflection->getProperty('instance')->setValue(null, $facade);

    // The methods under test use no instance state, so the constructor (DB, Pimple, Filesystem) is skipped.
    $this->repository = (new \ReflectionClass(DbWriteUpdateRepository::class))->newInstanceWithoutConstructor();
});

afterEach(function (): void {
    // Drop the spy-backed singleton so it cannot leak into other test files sharing the process.
    (new \ReflectionClass(LoggerUpgrade::class))->getProperty('instance')->setValue(null, null);
});

it('brackets a successful step with a running start and a completed event', function (): void {
    $ran = false;
    (new \ReflectionClass($this->repository))->getMethod('executeStep')->invoke(
        $this->repository,
        '24.10.1',
        'php_script',
        function () use (&$ran): void {
            $ran = true;
        }
    );

    expect($ran)->toBeTrue()
        ->and($this->spy->records)->toHaveCount(2);

    [$start, $completed] = $this->spy->records;
    expect($start['level'])->toBe(LogLevel::INFO)
        ->and($start['context']['event'])->toBe('upgrade.step')
        ->and($start['context']['status'])->toBe('running')
        ->and($start['context']['version'])->toBe('24.10.1')
        ->and($start['context']['step'])->toBe('php_script')
        ->and($completed['level'])->toBe(LogLevel::INFO)
        ->and($completed['context']['event'])->toBe('upgrade.step_completed')
        ->and($completed['context']['status'])->toBe('completed')
        ->and($completed['context']['step'])->toBe('php_script')
        ->and($completed['context']['duration_ms'])->toBeInt();
});

it('logs a step failure and re-throws the original exception when the step throws', function (): void {
    $failure = new \RuntimeException('boom');

    try {
        (new \ReflectionClass($this->repository))->getMethod('executeStep')->invoke(
            $this->repository,
            '24.10.1',
            'configuration_sql',
            function () use ($failure): void {
                throw $failure;
            }
        );
        // executeStep must surface the failure, never swallow it.
        $this->fail('Expected executeStep to re-throw the step failure');
    } catch (\RuntimeException $caught) {
        expect($caught)->toBe($failure);
    }

    // The start event is still emitted, then a step_failure carrying the throwable; no completed event.
    expect($this->spy->records)->toHaveCount(2);
    [$start, $stepFailure] = $this->spy->records;
    expect($start['context']['event'])->toBe('upgrade.step')
        ->and($stepFailure['level'])->toBe(LogLevel::ERROR)
        ->and($stepFailure['context']['event'])->toBe('upgrade.step_failure')
        ->and($stepFailure['context']['status'])->toBe('failure')
        ->and($stepFailure['context']['version'])->toBe('24.10.1')
        ->and($stepFailure['context']['step'])->toBe('configuration_sql')
        ->and($stepFailure['context']['exception'])->toBe($failure);
});

it('writes the executed-queries count to the temporary resume file', function (): void {
    $tmpFile = sys_get_temp_dir() . '/centreon-upgrade-cursor-' . uniqid() . '.tmp';

    try {
        (new \ReflectionClass($this->repository))
            ->getMethod('writeExecutedQueriesCountInTemporaryFile')
            ->invoke($this->repository, $tmpFile, 7);

        expect(file_get_contents($tmpFile))->toBe('7');
    } finally {
        @unlink($tmpFile);
    }
});

it('throws when the temporary resume file exists but is not writable', function (): void {
    if (function_exists('posix_getuid') && posix_getuid() === 0) {
        $this->markTestSkipped('Permission bits are bypassed when running as root.');
    }

    $tmpFile = sys_get_temp_dir() . '/centreon-upgrade-cursor-' . uniqid() . '.tmp';
    file_put_contents($tmpFile, '0');
    chmod($tmpFile, 0o444);

    try {
        (new \ReflectionClass($this->repository))
            ->getMethod('writeExecutedQueriesCountInTemporaryFile')
            ->invoke($this->repository, $tmpFile, 3);
        $this->fail('Expected a non-writable resume file to abort the step');
    } catch (RepositoryException $exception) {
        expect($exception->getMessage())->toContain('temporary file');
    } finally {
        chmod($tmpFile, 0o644);
        @unlink($tmpFile);
    }
});

it('throws when the resume cursor write fails entirely', function (): void {
    // A regular file used as a parent makes file_put_contents() fail (ENOTDIR) and return false,
    // exercising the write-failure branch itself rather than the earlier is_writable guard.
    $blocker = sys_get_temp_dir() . '/centreon-upgrade-blocker-' . uniqid();
    file_put_contents($blocker, 'x');
    $tmpFile = $blocker . '/cursor.tmp';

    // The failed write emits an expected E_WARNING; swallow it so only our exception is asserted.
    set_error_handler(
        static fn (int $severity, string $message): bool => $severity === E_WARNING
            && str_contains($message, 'file_put_contents')
            && str_contains($message, $tmpFile)
    );
    try {
        (new \ReflectionClass($this->repository))
            ->getMethod('writeExecutedQueriesCountInTemporaryFile')
            ->invoke($this->repository, $tmpFile, 5);
        $this->fail('Expected the failed write to abort the step');
    } catch (RepositoryException $exception) {
        expect($exception->getMessage())->toContain('temporary file');
    } finally {
        restore_error_handler();
        @unlink($blocker);
    }
});
