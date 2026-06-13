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

it('mirrors a login failure to the legacy login.log in the historical pipe format', function (): void {
    $legacyFile = loggerAuthLoginLogPath();
    if (file_exists($legacyFile)) {
        unlink($legacyFile);
    }

    LoggerAuthentication::create()->loginFailure(
        "[local] [10.0.0.1] Authentication failed for `admin` : bad password",
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
