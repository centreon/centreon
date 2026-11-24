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

namespace App\AiConfiguration\Infrastructure\Llm;

use App\AiConfiguration\Domain\Aggregate\AiProvider\EndpointUrl;

/**
 * Tells whether a URL designates a host on the local network: localhost, or a
 * literal loopback or private (RFC 1918, IPv6 unique local) address; never a
 * link-local one. Host names are not resolved.
 */
final class LocalNetworkHost
{
    public static function isLocal(string $url): bool
    {
        $host = parse_url($url, \PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return false;
        }

        $host = mb_strtolower(trim($host, '[]'));
        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            return true;
        }

        if (filter_var($host, \FILTER_VALIDATE_IP) === false || EndpointUrl::isLinkLocal($host)) {
            return false;
        }

        // a literal IP that is no longer valid once the private and reserved
        // ranges (loopback, ...) are excluded is a local one
        return filter_var($host, \FILTER_VALIDATE_IP, \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
