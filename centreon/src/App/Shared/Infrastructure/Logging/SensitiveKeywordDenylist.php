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

namespace App\Shared\Infrastructure\Logging;

/**
 * Single source of truth for the keyword-based masking net used by
 * {@see PayloadSanitizer} (ad-hoc logger contexts).
 *
 * Matched via str_contains on the lowercased key — intentionally
 * permissive: any field name that *contains* one of these tokens is
 * masked (e.g. `oauth_authorization_url`, `password_changed_at`,
 * `customer_token_id`). Over-masking is preferred to under-masking so a
 * variant we did not anticipate can't leak a real secret.
 */
final class SensitiveKeywordDenylist
{
    public const KEYWORDS = [
        'password', 'passwd', 'pwd', 'token', 'secret', 'api_key', 'apikey', 'api-key',
        'access_key', 'authorization', 'bearer', 'credential', 'private_key', 'signature',
        'session_id', 'sessionid',
    ];

    /**
     * Memoises the sensitive-or-not verdict per key to skip the keyword
     * scan on repeated fields.
     *
     * @var array<string, bool>
     */
    private static array $cache = [];

    public static function matches(string $key): bool
    {
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $lower = mb_strtolower($key);

        foreach (self::KEYWORDS as $keyword) {
            if (str_contains($lower, $keyword)) {
                return self::$cache[$key] = true;
            }
        }

        return self::$cache[$key] = false;
    }

    /**
     * Reset the cache. Reserved for tests — production callers must never
     * invoke this, the verdict is otherwise stable for the process.
     *
     * @internal
     */
    public static function reset(): void
    {
        self::$cache = [];
    }
}
