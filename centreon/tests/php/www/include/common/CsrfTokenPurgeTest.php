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

// The legacy helpers live outside the Composer classmap.
require_once __DIR__ . '/../../../../../www/include/common/common-Func.php';

beforeEach(function (): void {
    $_SESSION['x-centreon-token'] = [];
    $_SESSION['x-centreon-token-generated-at'] = [];
});

afterEach(function (): void {
    unset($_SESSION['x-centreon-token'], $_SESSION['x-centreon-token-generated-at']);
});

it('drops an expired token from both structures', function (): void {
    $_SESSION['x-centreon-token'] = ['fresh-token', 'old-token'];
    $_SESSION['x-centreon-token-generated-at'] = [
        'fresh-token' => time(),
        'old-token' => time() - (16 * 60),
    ];

    purgeOutdatedCSRFTokens();

    expect(array_values($_SESSION['x-centreon-token']))->toBe(['fresh-token']);
    expect($_SESSION['x-centreon-token-generated-at'])->toHaveKey('fresh-token');
    expect($_SESSION['x-centreon-token-generated-at'])->not->toHaveKey('old-token');
});

it('keeps a live token when an expired entry is missing from the token list', function (): void {
    // The regression this pins: the two structures can drift apart, and a loose
    // array_search then returns false — unset(...[false]) deleted index 0, a
    // token that may well be live.
    $_SESSION['x-centreon-token'] = ['live-token'];
    $_SESSION['x-centreon-token-generated-at'] = [
        'live-token' => time(),
        'ghost-token' => time() - (16 * 60),
    ];

    purgeOutdatedCSRFTokens();

    expect($_SESSION['x-centreon-token'])->toBe(['live-token']);
    expect($_SESSION['x-centreon-token-generated-at'])->not->toHaveKey('ghost-token');
});

it('leaves fresh tokens untouched', function (): void {
    $_SESSION['x-centreon-token'] = ['a', 'b'];
    $_SESSION['x-centreon-token-generated-at'] = ['a' => time(), 'b' => time() - 60];

    purgeOutdatedCSRFTokens();

    expect($_SESSION['x-centreon-token'])->toBe(['a', 'b']);
});
