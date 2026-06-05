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

beforeEach(function (): void {
    $_SERVER['APP_ENV'] = 'test';
});

afterEach(function (): void {
    foreach (centreonLogTestSlugs() as $slug) {
        $file = centreonLogPath($slug);
        if (file_exists($file)) {
            @unlink($file);
        }
    }
});

function centreonLogPath(string $slug): string
{
    return _CENTREON_LOG_ . 'test.' . $slug . '.log';
}

/**
 * @return list<string>
 */
function centreonLogTestSlugs(): array
{
    return ['web', 'access', 'upgrade', 'plugin-pack-manager'];
}

it('exposes a CentreonLog instance through its factory', function (): void {
    expect(CentreonLog::create())->toBeInstanceOf(CentreonLog::class);
});

it('writes a log line for every PSR-3 level wrapper without erroring', function (): void {
    $logger = CentreonLog::create();

    expect(fn () => $logger->debug(CentreonLog::TYPE_BUSINESS_LOG, 'debug_message'))->not->toThrow(Throwable::class);
    expect(fn () => $logger->info(CentreonLog::TYPE_BUSINESS_LOG, 'info_message'))->not->toThrow(Throwable::class);
    expect(fn () => $logger->notice(CentreonLog::TYPE_BUSINESS_LOG, 'notice_message'))->not->toThrow(Throwable::class);
    expect(fn () => $logger->warning(CentreonLog::TYPE_BUSINESS_LOG, 'warning_message'))->not->toThrow(Throwable::class);
    expect(fn () => $logger->error(CentreonLog::TYPE_BUSINESS_LOG, 'error_message'))->not->toThrow(Throwable::class);
    expect(fn () => $logger->critical(CentreonLog::TYPE_BUSINESS_LOG, 'critical_message'))->not->toThrow(Throwable::class);
    expect(fn () => $logger->alert(CentreonLog::TYPE_BUSINESS_LOG, 'alert_message'))->not->toThrow(Throwable::class);
    expect(fn () => $logger->emergency(CentreonLog::TYPE_BUSINESS_LOG, 'emergency_message'))->not->toThrow(Throwable::class);
});

it('routes every TYPE_* constant to the expected channel file', function (int $type, string $slug): void {
    $expectedFile = centreonLogPath($slug);

    CentreonLog::create()->error($type, 'routing_marker');

    expect(file_exists($expectedFile))->toBeTrue()
        ->and(file_get_contents($expectedFile))->toContain('routing_marker');
})->with([
    'login goes to access.log' => [CentreonLog::TYPE_LOGIN, 'access'],
    'ldap goes to access.log' => [CentreonLog::TYPE_LDAP, 'access'],
    'sql falls back to web.log' => [CentreonLog::TYPE_SQL, 'web'],
    'upgrade goes to upgrade.log' => [CentreonLog::TYPE_UPGRADE, 'upgrade'],
    'plugin-pack goes to plugin-pack-manager.log' => [CentreonLog::TYPE_PLUGIN_PACK_MANAGER, 'plugin-pack-manager'],
    'business log goes to web.log' => [CentreonLog::TYPE_BUSINESS_LOG, 'web'],
]);

it('falls back to the error level when an unknown level string is given', function (): void {
    $expectedFile = centreonLogPath('web');

    // "toto" is not a PSR-3 level: it must be normalized to error rather than
    // bubbling up a Monolog InvalidArgumentException.
    expect(fn () => CentreonLog::create()->log(CentreonLog::TYPE_BUSINESS_LOG, 'toto', 'unknown_level_marker'))
        ->not->toThrow(Throwable::class);

    expect(file_exists($expectedFile))->toBeTrue()
        ->and(file_get_contents($expectedFile))->toContain('unknown_level_marker');
});

it('accepts an exception payload alongside the custom context', function (): void {
    $exception = new RuntimeException('boom', 42);

    expect(fn () => CentreonLog::create()->error(CentreonLog::TYPE_BUSINESS_LOG, 'with_exception', ['ctx' => 'foo'], $exception))
        ->not->toThrow(Throwable::class);
});

it('keeps the legacy insertLog signature working', function (): void {
    expect(fn () => CentreonLog::create()->insertLog(CentreonLog::TYPE_BUSINESS_LOG, 'legacy entry'))->not->toThrow(Throwable::class);
});
