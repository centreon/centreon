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

use Adaptation\Log\Logger;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * Build the facade backed by a recording logger. The constructor is private, so the spy
 * is wired through reflection — this keeps the production API free of any test-only seam.
 *
 * @param null|Throwable $failWith when set, the spy throws it on every write
 *
 * @return array{0: Logger, 1: object{records: list<array{level: string, message: string, context: array<string, mixed>}>}}
 */
function loggerWithSpy(?Throwable $failWith = null): array
{
    $spy = new class ($failWith) extends AbstractLogger {
        /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
        public array $records = [];

        public function __construct(private readonly ?Throwable $failWith = null)
        {
        }

        /**
         * @param array<string, mixed> $context
         * @param mixed $level
         */
        public function log($level, string|Stringable $message, array $context = []): void
        {
            if ($this->failWith instanceof Throwable) {
                throw $this->failWith;
            }

            $this->records[] = [
                'level' => is_scalar($level) ? (string) $level : '',
                'message' => (string) $message,
                'context' => $context,
            ];
        }
    };

    $reflection = new ReflectionClass(Logger::class);
    /** @var Logger $facade */
    $facade = $reflection->newInstanceWithoutConstructor();
    $reflection->getProperty('logger')->setValue($facade, $spy);

    return [$facade, $spy];
}

/**
 * The severity helpers each name their own level constant, and a copy-paste slip is
 * invisible at runtime: the stream handler is mounted at INFO, so an error() demoted to
 * debug() makes real errors vanish, and a debug() promoted to error() floods production.
 */
it('emits each severity helper at its own level', function (string $method, string $expectedLevel): void {
    [$facade, $spy] = loggerWithSpy();

    $facade->{$method}('a message', ['key' => 'value']);

    expect($spy->records)->toHaveCount(1)
        ->and($spy->records[0]['level'])->toBe($expectedLevel)
        ->and($spy->records[0]['message'])->toBe('a message')
        ->and($spy->records[0]['context'])->toBe(['key' => 'value']);
})->with([
    ['emergency', LogLevel::EMERGENCY],
    ['alert', LogLevel::ALERT],
    ['critical', LogLevel::CRITICAL],
    ['error', LogLevel::ERROR],
    ['warning', LogLevel::WARNING],
    ['notice', LogLevel::NOTICE],
    ['info', LogLevel::INFO],
    ['debug', LogLevel::DEBUG],
]);

/**
 * A handler that cannot write — unwritable file, full disk — must not take the caller
 * down with it: a batch loop logging its own failures would otherwise die mid-batch and
 * never render its page.
 */
it('swallows a handler that throws instead of aborting the caller', function (string $method): void {
    [$facade] = loggerWithSpy(new RuntimeException('disk full'));

    expect(function () use ($facade, $method): void {
        $facade->{$method}('a message');
    })->not->toThrow(RuntimeException::class);
})->with(['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug']);

it('swallows a handler that throws when log() is called directly', function (): void {
    [$facade] = loggerWithSpy(new RuntimeException('disk full'));

    expect(function () use ($facade): void {
        $facade->log(LogLevel::ERROR, 'a message');
    })->not->toThrow(RuntimeException::class);
});

/**
 * Swallowing must still leave a trace: a catch block reduced to an empty swallow would
 * pass every test above while making logging failures invisible. Pin the error_log
 * fallback so that regression cannot slip through.
 */
it('records the failure through error_log when the handler throws', function (): void {
    [$facade] = loggerWithSpy(new RuntimeException('disk full'));

    $errorLog = tempnam(sys_get_temp_dir(), 'logger-test-');
    $previous = ini_set('error_log', $errorLog);

    try {
        $facade->error('a message');
        $logged = file_get_contents($errorLog);
    } finally {
        ini_set('error_log', $previous);
        unlink($errorLog);
    }

    expect($logged)->toContain('disk full');
});

it('passes the context through untouched', function (): void {
    [$facade, $spy] = loggerWithSpy();
    $context = ['id' => 42, 'nested' => ['a' => 1], 'exception' => new RuntimeException('boom')];

    $facade->error('a message', $context);

    expect($spy->records[0]['context'])->toBe($context);
});

it('keeps log() emitting the level it is given', function (): void {
    [$facade, $spy] = loggerWithSpy();

    $facade->log(LogLevel::WARNING, 'a message');

    expect($spy->records[0]['level'])->toBe(LogLevel::WARNING);
});
