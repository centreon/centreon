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

namespace Tests\Centreon\Domain\Log;

use LogicException;

beforeEach(function (): void {
    $this->logFilePath = __DIR__ . '/log';
    $this->logPathFileName = $this->logFilePath . '/test.log';

    if (! file_exists($this->logFilePath)) {
        mkdir($this->logFilePath);
    }

    $this->logger = new LoggerStub($this->logPathFileName);
});

afterEach(function (): void {
    if (file_exists($this->logPathFileName)) {
        expect(unlink($this->logPathFileName))->toBeTrue();
        $successDeleteFile = rmdir($this->logFilePath);
        expect($successDeleteFile)->toBeTrue();
    }
});

it('writes a record without context or exception for every PSR-3 level', function (string $method, string $rendered): void {
    $this->logger->{$method}("{$method}_message");
    expect(file_exists($this->logPathFileName))->toBeTrue()
        ->and(file_get_contents($this->logPathFileName))
        ->toContain("test_logger.{$rendered}: {$method}_message [] []");
})->with([
    ['debug', 'DEBUG'],
    ['info', 'INFO'],
    ['notice', 'NOTICE'],
    ['warning', 'WARNING'],
    ['error', 'ERROR'],
    ['critical', 'CRITICAL'],
    ['alert', 'ALERT'],
    ['emergency', 'EMERGENCY'],
]);

it('forwards the caller context as-is for every PSR-3 level', function (string $method, string $rendered): void {
    $this->logger->{$method}("{$method}_message", ['contact' => 1, 'name' => 'John Doe', 'is_admin' => true]);
    expect(file_exists($this->logPathFileName))->toBeTrue()
        ->and(file_get_contents($this->logPathFileName))
        ->toContain("test_logger.{$rendered}: {$method}_message {\"contact\":1,\"name\":\"John Doe\",\"is_admin\":true} []");
})->with([
    ['debug', 'DEBUG'],
    ['info', 'INFO'],
    ['notice', 'NOTICE'],
    ['warning', 'WARNING'],
    ['error', 'ERROR'],
    ['critical', 'CRITICAL'],
    ['alert', 'ALERT'],
    ['emergency', 'EMERGENCY'],
]);

it('keeps the Throwable under context.exception so the platform processor can format it', function (): void {
    $this->logger->error('error_message', [
        'contact' => 1,
        'name' => 'John Doe',
        'is_admin' => true,
        'exception' => new LogicException('exception_message', 99),
    ]);
    expect(file_exists($this->logPathFileName))->toBeTrue();
    $contentLog = file_get_contents($this->logPathFileName);
    expect($contentLog)->toContain(
        'test_logger.ERROR: error_message {"contact":1,"name":"John Doe","is_admin":true,"exception":"[object] (LogicException(code: 99): exception_message at ' . __FILE__ . ':' . (__LINE__ - 5) . ')"} []'
    );
});
