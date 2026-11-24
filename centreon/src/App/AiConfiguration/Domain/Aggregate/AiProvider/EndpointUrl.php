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
    /** Instance metadata services outside the link-local ranges (Alibaba Cloud, Google Cloud, AWS) */
    private const METADATA_HOSTS = [
        '100.100.100.200',
        'metadata',
        'metadata.google.internal',
        'metadata.goog',
        'instance-data',
        'instance-data.ec2.internal',
    ];

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
    }

    /**
     * Whether the URL targets a cloud instance metadata service, by a link-local IP or a
     * well-known address or name: an early, readable refusal of an endpoint being set.
     * Other spellings are caught when connecting (see ai_configuration.http_client).
     */
    public function targetsInstanceMetadata(): bool
    {
        $host = mb_strtolower(rtrim(trim((string) parse_url($this->value, \PHP_URL_HOST), '[]'), '.'));
        if (self::isLinkLocal($host)) {
            return true;
        }

        return \in_array($host, self::METADATA_HOSTS, true);
    }

    /**
     * Whether a literal IP is link-local (169.254.0.0/16, fe80::/10).
     */
    public static function isLinkLocal(string $host): bool
    {
        if (filter_var($host, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4) !== false) {
            return str_starts_with($host, '169.254.');
        }
        if (filter_var($host, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6) === false) {
            return false;
        }

        $packed = inet_pton($host);
        if ($packed === false) {
            return false;
        }
        // an IPv4-mapped IPv6 address (::ffff:a.b.c.d) is its IPv4 address
        if (str_starts_with($packed, str_repeat("\x00", 10) . "\xFF\xFF")) {
            return $packed[12] === "\xA9" && $packed[13] === "\xFE";
        }

        return $packed[0] === "\xFE" && (ord($packed[1]) & 0xC0) === 0x80;
    }

    /**
     * A trailing slash does not change the target: the clients strip it.
     */
    public function equals(self $other): bool
    {
        return rtrim($this->value, '/') === rtrim($other->value, '/');
    }
}
