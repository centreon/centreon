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

namespace Core\Common\Infrastructure\Api;

/**
 * Utility class for converting URLs to localhost for internal API calls.
 * This avoids issues with proxies, load balancers, and HTTPS termination.
 */
final class InternalApiClient
{
    private const DEFAULT_LOCAL_HOST = '127.0.0.1';
    private const DEFAULT_LOCAL_SCHEME = 'http';

    /**
     * Convert an external URL to a localhost URL for internal API calls.
     * This ensures the request stays on the local server and doesn't go through
     * proxies, load balancers, or external network infrastructure.
     *
     * @param string $url The original URL (may contain external hostname)
     *
     * @return string The localhost URL
     */
    public static function convertToLocalUrl(string $url): string
    {
        $parsedUrl = parse_url($url);

        if ($parsedUrl === false) {
            return $url;
        }

        // Rebuild URL with localhost
        $localUrl = self::DEFAULT_LOCAL_SCHEME . '://' . self::DEFAULT_LOCAL_HOST;

        // Add path
        if (isset($parsedUrl['path'])) {
            $localUrl .= $parsedUrl['path'];
        }

        // Add query string
        if (isset($parsedUrl['query'])) {
            $localUrl .= '?' . $parsedUrl['query'];
        }

        // Add fragment if present
        if (isset($parsedUrl['fragment'])) {
            $localUrl .= '#' . $parsedUrl['fragment'];
        }

        return $localUrl;
    }

    /**
     * Get the default options for internal HTTP client requests.
     * These options disable SSL verification since we're calling localhost.
     *
     * @return array{verify_peer: bool, verify_host: bool}
     */
    public static function getDefaultHttpOptions(): array
    {
        return [
            'verify_peer' => false,
            'verify_host' => false,
        ];
    }
}
