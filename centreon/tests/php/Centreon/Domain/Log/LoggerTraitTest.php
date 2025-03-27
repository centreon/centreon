<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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
    $this->logFileName = 'test.log';
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

it('test a log with debug level without context without exception', function () {
    $this->logger->debug('debug_message');
    expect(file_exists($this->logPathFileName))->toBeTrue();
    $contentLog = file_get_contents($this->logPathFileName);
    expect($contentLog)->toContain(
        'test_logger.DEBUG: debug_message {"context":{"custom":null,"exception":null,"default":{"request_infos":{"url":null,"http_method":null,"server":null,"referrer":null}}}}'
    );
});

it('test a log with debug level with a context without exception', function () {
    $this->logger->debug('debug_message', ['contact' => 1, 'name' => 'John Doe', 'is_admin' => true]);
    expect(file_exists($this->logPathFileName))->toBeTrue();
    $contentLog = file_get_contents($this->logPathFileName);
    expect($contentLog)->toContain(
        'test_logger.DEBUG: debug_message {"context":{"custom":{"contact":1,"name":"John Doe","is_admin":true},"exception":null,"default":{"request_infos":{"url":null,"http_method":null,"server":null,"referrer":null}}}}'
    );
});

it('test a log with info level without context without exception', function () {
    $this->logger->info('info_message');
    expect(file_exists($this->logPathFileName))->toBeTrue();
    $contentLog = file_get_contents($this->logPathFileName);
    expect($contentLog)->toContain(
        'test_logger.INFO: info_message {"context":{"custom":null,"exception":null,"default":{"request_infos":{"url":null,"http_method":null,"server":null,"referrer":null}}}}'
    );
});

it('test a log with info level with a context without exception', function () {
    $this->logger->info('info_message', ['contact' => 1, 'name' => 'John Doe', 'is_admin' => true]);
    expect(file_exists($this->logPathFileName))->toBeTrue();
    $contentLog = file_get_contents($this->logPathFileName);
    expect($contentLog)->toContain(
        'test_logger.INFO: info_message {"context":{"custom":{"contact":1,"name":"John Doe","is_admin":true},"exception":null,"default":{"request_infos":{"url":null,"http_method":null,"server":null,"referrer":null}}}}'
    );
});

it('test a log with notice level without context without exception', function () {
    $this->logger->notice('notice_message');
    expect(file_exists($this->logPathFileName))->toBeTrue();
    $contentLog = file_get_contents($this->logPathFileName);
    expect($contentLog)->toContain(
        'test_logger.NOTICE: notice_message {"context":{"custom":null,"exception":null,"default":{"request_infos":{"url":null,"http_method":null,"server":null,"referrer":null}}}}'
    );
});

it('test a log with notice level with a context without exception', function () {
    $this->logger->notice('notice_message', ['contact' => 1, 'name' => 'John Doe', 'is_admin' => true]);
    expect(file_exists($this->logPathFileName))->toBeTrue();
    $contentLog = file_get_contents($this->logPathFileName);
    expect($contentLog)->toContain(
        'test_logger.NOTICE: notice_message {"context":{"custom":{"contact":1,"name":"John Doe","is_admin":true},"exception":null,"default":{"request_infos":{"url":null,"http_method":null,"server":null,"referrer":null}}}}'
    );
});

it('test a log with warning level without context without exception', function () {
    $this->logger->warning('warning_message');
    expect(file_exists($this->logPathFileName))->toBeTrue();
    $contentLog = file_get_contents($this->logPathFileName);
    expect($contentLog)->toContain(
        'test_logger.WARNING: warning_message {"context":{"custom":null,"exception":null,"default":{"request_infos":{"url":null,"http_method":null,"server":null,"referrer":null}}}}'
    );
});

it('test a log with warning level with a context without exception', function () {
    $this->logger->warning('warning_message', ['contact' => 1, 'name' => 'John Doe', 'is_admin' => true]);
    expect(file_exists($this->logPathFileName))->toBeTrue();
    $contentLog = file_get_contents($this->logPathFileName);
    expect($contentLog)->toContain(
        'test_logger.WARNING: warning_message {"context":{"custom":{"contact":1,"name":"John Doe","is_admin":true},"exception":null,"default":{"request_infos":{"url":null,"http_method":null,"server":null,"referrer":null}}}}'
    );
});

it('test a log with error level without context without exception', function () {
    $this->logger->error('error_message');
    expect(file_exists($this->logPathFileName))->toBeTrue();
    $contentLog = file_get_contents($this->logPathFileName);
    expect($contentLog)->toContain(
        'test_logger.ERROR: error_message {"context":{"custom":null,"exception":null,"default":{"request_infos":{"url":null,"http_method":null,"server":null,"referrer":null}}}}'
    );
});

it('test a log with error level with a context without exception', function () {
    $this->logger->error('error_message', ['contact' => 1, 'name' => 'John Doe', 'is_admin' => true]);
    expect(file_exists($this->logPathFileName))->toBeTrue();
    $contentLog = file_get_contents($this->logPathFileName);
    expect($contentLog)->toContain(
        'test_logger.ERROR: error_message {"context":{"custom":{"contact":1,"name":"John Doe","is_admin":true},"exception":null,"default":{"request_infos":{"url":null,"http_method":null,"server":null,"referrer":null}}}}'
    );
});

it('test a log with critical level without context without exception', function () {
    $this->logger->critical('critical_message');
    expect(file_exists($this->logPathFileName))->toBeTrue();
    $contentLog = file_get_contents($this->logPathFileName);
    expect($contentLog)->toContain(
        'test_logger.CRITICAL: critical_message {"context":{"custom":null,"exception":null,"default":{"request_infos":{"url":null,"http_method":null,"server":null,"referrer":null}}}}'
    );
});

it('test a log with critical level with a context without exception', function () {
    $this->logger->critical('critical_message', ['contact' => 1, 'name' => 'John Doe', 'is_admin' => true]);
    expect(file_exists($this->logPathFileName))->toBeTrue();
    $contentLog = file_get_contents($this->logPathFileName);
    expect($contentLog)->toContain(
        'test_logger.CRITICAL: critical_message {"context":{"custom":{"contact":1,"name":"John Doe","is_admin":true},"exception":null,"default":{"request_infos":{"url":null,"http_method":null,"server":null,"referrer":null}}}}'
    );
});

it('test a log with alert level without context without exception', function () {
    $this->logger->alert('alert_message');
    expect(file_exists($this->logPathFileName))->toBeTrue();
    $contentLog = file_get_contents($this->logPathFileName);
    expect($contentLog)->toContain(
        'test_logger.ALERT: alert_message {"context":{"custom":null,"exception":null,"default":{"request_infos":{"url":null,"http_method":null,"server":null,"referrer":null}}}}'
    );
});

it('test a log with alert level with a context without exception', function () {
    $this->logger->alert('alert_message', ['contact' => 1, 'name' => 'John Doe', 'is_admin' => true]);
    expect(file_exists($this->logPathFileName))->toBeTrue();
    $contentLog = file_get_contents($this->logPathFileName);
    expect($contentLog)->toContain(
        'test_logger.ALERT: alert_message {"context":{"custom":{"contact":1,"name":"John Doe","is_admin":true},"exception":null,"default":{"request_infos":{"url":null,"http_method":null,"server":null,"referrer":null}}}}'
    );
});

it('test a log with emergency level without context without exception', function () {
    $this->logger->emergency('emergency_message');
    expect(file_exists($this->logPathFileName))->toBeTrue();
    $contentLog = file_get_contents($this->logPathFileName);
    expect($contentLog)->toContain(
        'test_logger.EMERGENCY: emergency_message {"context":{"custom":null,"exception":null,"default":{"request_infos":{"url":null,"http_method":null,"server":null,"referrer":null}}}}'
    );
});

it('test a log with emergency level with a context without exception', function () {
    $this->logger->emergency('emergency_message', ['contact' => 1, 'name' => 'John Doe', 'is_admin' => true]);
    expect(file_exists($this->logPathFileName))->toBeTrue();
    $contentLog = file_get_contents($this->logPathFileName);
    expect($contentLog)->toContain(
        'test_logger.EMERGENCY: emergency_message {"context":{"custom":{"contact":1,"name":"John Doe","is_admin":true},"exception":null,"default":{"request_infos":{"url":null,"http_method":null,"server":null,"referrer":null}}}}'
    );
});

it('test a log with exception', function () {
    $this->logger->error(
        'error_message',
        [
            'contact' => 1,
            'name' => 'John Doe',
            'is_admin' => true,
            'exception' => new LogicException('exception_message', 99)
        ]
    );
    expect(file_exists($this->logPathFileName))->toBeTrue();
    $contentLog = file_get_contents($this->logPathFileName);
    expect($contentLog)->toContain(
        'test_logger.ERROR: error_message {"context":{"custom":{"contact":1,"name":"John Doe","is_admin":true},"exception":"[object] (LogicException(code: 99): exception_message at ' . __FILE__ . ':' . (__LINE__ - 6) . ')","default":{"request_infos":{"url":null,"http_method":null,"server":null,"referrer":null}}}}'
    );
});
