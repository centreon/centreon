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

namespace App\AiConfiguration\Domain\Aggregate\AiProvider;

use Webmozart\Assert\Assert;

final readonly class EndpointUrl
{
    public function __construct(
        public string $value,
    ) {
        Assert::lengthBetween($value, 1, 2048);
        Assert::regex($value, '~^https?://~', 'The endpoint URL must start with http:// or https://.');
        Assert::notFalse(filter_var($value, \FILTER_VALIDATE_URL), 'The endpoint URL is not a valid URL.');

        // a base URL only: the clients append their paths to it, and credentials
        // in it would travel (and be logged) with every request
        $parts = parse_url($value);
        Assert::isArray($parts, 'The endpoint URL is not a valid URL.');
        Assert::false(isset($parts['user']) || isset($parts['pass']), 'The endpoint URL can not contain credentials.');
        Assert::keyNotExists($parts, 'query', 'The endpoint URL can not contain a query string.');
        Assert::keyNotExists($parts, 'fragment', 'The endpoint URL can not contain a fragment.');

        // link-local addresses include cloud instance metadata services
        Assert::false(
            self::isLinkLocal(trim((string) ($parts['host'] ?? ''), '[]')),
            'The endpoint URL can not target a link-local address.'
        );
    }

    /**
     * Whether a literal IP is link-local (169.254.0.0/16, fe80::/10). Host names
     * are not resolved.
     */
    public static function isLinkLocal(string $host): bool
    {
        $packed = filter_var($host, \FILTER_VALIDATE_IP) === false ? false : inet_pton($host);
        if ($packed === false) {
            return false;
        }

        return mb_strlen($packed) === 4
            ? $packed[0] === "\xA9" && $packed[1] === "\xFE"
            : $packed[0] === "\xFE" && (ord($packed[1]) & 0xC0) === 0x80;
    }

    /**
     * A trailing slash does not change the target: the clients strip it.
     */
    public function equals(self $other): bool
    {
        return rtrim($this->value, '/') === rtrim($other->value, '/');
    }
}
