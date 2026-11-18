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

use Adaptation\Log\Enum\AuthProviderEnum;
use Adaptation\Log\LoggerAuthentication;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

beforeEach(function (): void {
    $_SERVER['APP_ENV'] = 'test';
});

afterEach(function (): void {
    foreach (['login.log', 'test.access.log'] as $name) {
        $file = _CENTREON_LOG_ . $name;
        if (file_exists($file)) {
            @unlink($file);
        }
    }
});

function loggerAuthLoginLogPath(): string
{
    return _CENTREON_LOG_ . 'login.log';
}

/**
 * Build a facade backed by a recording logger so the Monolog emission (level, message and
 * context shape) can be asserted without touching the real authentication channel file.
 * The constructor is private and the facade is a singleton, so the spy is wired through
 * reflection — this keeps the production API free of any test-only seam.
 *
 * @return array{0: LoggerAuthentication, 1: object{records: list<array{level: string, message: string, context: array<string, mixed>}>}}
 */
function loggerAuthWithSpy(): array
{
    $spy = new class () extends AbstractLogger {
        /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
        public array $records = [];

        public function log($level, string|Stringable $message, array $context = []): void
        {
            $this->records[] = ['level' => (string) $level, 'message' => (string) $message, 'context' => $context];
        }
    };

    $reflection = new ReflectionClass(LoggerAuthentication::class);
    /** @var LoggerAuthentication $facade */
    $facade = $reflection->newInstanceWithoutConstructor();
    $reflection->getProperty('logger')->setValue($facade, $spy);

    return [$facade, $spy];
}

it('emits a login failure as a WARNING carrying the event, status, provider and null user id', function (): void {
    [$facade, $spy] = loggerAuthWithSpy();

    $facade->loginFailure('bad credentials', null, AuthProviderEnum::LOCAL);

    expect($spy->records)->toHaveCount(1);
    $record = $spy->records[0];
    expect($record['level'])->toBe(LogLevel::WARNING)
        ->and($record['message'])->toBe('bad credentials')
        ->and($record['context']['event'])->toBe('login.failure')
        ->and($record['context']['status'])->toBe('failure')
        ->and($record['context']['provider'])->toBe('local')
        ->and($record['context']['user_id'])->toBeNull()
        ->and($record['context'])->toHaveKey('ip_address')
        ->and($record['context'])->not->toHaveKey('exception');
});

it('attaches the throwable to the context when a login failure carries an exception', function (): void {
    [$facade, $spy] = loggerAuthWithSpy();
    $exception = new RuntimeException('IdP unreachable');

    $facade->loginFailure('token exchange failed', 7, AuthProviderEnum::OPENID, $exception);

    $record = $spy->records[0];
    expect($record['context']['user_id'])->toBe(7)
        ->and($record['context']['provider'])->toBe('openid')
        ->and($record['context']['exception'])->toBe($exception);
});

it('emits a login success as an INFO with the resolved user id', function (): void {
    [$facade, $spy] = loggerAuthWithSpy();

    $facade->loginSuccess('welcome', 42, AuthProviderEnum::SAML);

    $record = $spy->records[0];
    expect($record['level'])->toBe(LogLevel::INFO)
        ->and($record['context']['event'])->toBe('login.success')
        ->and($record['context']['status'])->toBe('success')
        ->and($record['context']['user_id'])->toBe(42)
        ->and($record['context']['provider'])->toBe('saml');
});

it('emits token refresh success and failure on the expected levels and events', function (): void {
    [$facade, $spy] = loggerAuthWithSpy();
    $exception = new RuntimeException('refresh refused');

    $facade->tokenRefreshSuccess('refreshed', 9, AuthProviderEnum::OPENID);
    $facade->tokenRefreshFailure('refresh failed', 9, AuthProviderEnum::OPENID, $exception);

    expect($spy->records[0]['level'])->toBe(LogLevel::INFO)
        ->and($spy->records[0]['context']['event'])->toBe('token.refresh.success')
        ->and($spy->records[1]['level'])->toBe(LogLevel::WARNING)
        ->and($spy->records[1]['context']['event'])->toBe('token.refresh.failure')
        ->and($spy->records[1]['context']['exception'])->toBe($exception);
});

it('omits the provider key for authorization events that have no provider', function (): void {
    [$facade, $spy] = loggerAuthWithSpy();

    $facade->unauthorized('missing bearer token', 13);

    $record = $spy->records[0];
    expect($record['level'])->toBe(LogLevel::WARNING)
        ->and($record['context']['event'])->toBe('unauthorized')
        ->and($record['context']['user_id'])->toBe(13)
        ->and($record['context'])->not->toHaveKey('provider');
});

it('adds the resource to the forbidden context only when one is supplied', function (): void {
    [$facade, $spy] = loggerAuthWithSpy();

    $facade->forbidden('denied on host', 13, 'host:42');
    $facade->forbidden('denied', 13);

    expect($spy->records[0]['context']['event'])->toBe('forbidden')
        ->and($spy->records[0]['context']['resource'])->toBe('host:42')
        ->and($spy->records[1]['context'])->not->toHaveKey('resource');
});

it('mirrors a login failure to the legacy login.log in the historical pipe format', function (): void {
    $legacyFile = loggerAuthLoginLogPath();
    if (file_exists($legacyFile)) {
        unlink($legacyFile);
    }

    LoggerAuthentication::create()->loginFailure(
        '[local] [10.0.0.1] Authentication failed for `admin` : bad password',
        null,
        AuthProviderEnum::LOCAL
    );

    // fail2ban-compatible line "date|uid|page|option|message" with backticks stripped and uid 0
    // when the user is not authenticated, exactly as legacy centreonAuth wrote it.
    expect(file_exists($legacyFile))->toBeTrue()
        ->and(file_get_contents($legacyFile))->toMatch(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\|0\|0\|0\|\[local\] \[10\.0\.0\.1\] Authentication failed for admin : bad password\n$/'
        );
});

it('mirrors a login success to the legacy login.log with the resolved user id', function (): void {
    $legacyFile = loggerAuthLoginLogPath();
    if (file_exists($legacyFile)) {
        unlink($legacyFile);
    }

    LoggerAuthentication::create()->loginSuccess(
        "[local] [10.0.0.1] Authentication succeeded for 'admin'",
        42,
        AuthProviderEnum::LOCAL
    );

    expect(file_exists($legacyFile))->toBeTrue()
        ->and(file_get_contents($legacyFile))->toContain("|42|0|0|[local] [10.0.0.1] Authentication succeeded for 'admin'");
});

it('does not mirror non-login lifecycle events to login.log', function (): void {
    $legacyFile = loggerAuthLoginLogPath();
    if (file_exists($legacyFile)) {
        unlink($legacyFile);
    }

    LoggerAuthentication::create()->logout('[local] logout', 42, AuthProviderEnum::LOCAL);

    expect(file_exists($legacyFile))->toBeFalse();
});

it('escapes asterisks and strips backticks in the legacy login.log mirror', function (): void {
    $legacyFile = loggerAuthLoginLogPath();
    if (file_exists($legacyFile)) {
        unlink($legacyFile);
    }

    LoggerAuthentication::create()->loginFailure(
        'Authentication failed for `a*b`',
        null,
        AuthProviderEnum::LOCAL
    );

    // Backticks are removed and asterisks are escaped, matching legacy centreonAuth output.
    expect(file_get_contents($legacyFile))->toContain('Authentication failed for a\*b');
});

it('neutralizes line breaks and field delimiters in the legacy login.log mirror', function (): void {
    $legacyFile = loggerAuthLoginLogPath();
    if (file_exists($legacyFile)) {
        unlink($legacyFile);
    }

    LoggerAuthentication::create()->loginFailure(
        "Authentication failed for 'ad\nmin|0|0|forged'",
        null,
        AuthProviderEnum::LOCAL
    );

    // A crafted message cannot split the record (one trailing newline) nor inject extra
    // pipe-delimited fields: CR/LF/pipe are replaced by spaces.
    $contents = file_get_contents($legacyFile);
    expect(mb_substr_count($contents, "\n"))->toBe(1)
        ->and($contents)->toContain("Authentication failed for 'ad min 0 0 forged'");
});
