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

use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client for making internal API calls that stay on localhost.
 * This avoids issues with proxies, load balancers, and HTTPS termination.
 */
final class InternalApiClient
{
    private const DEFAULT_LOCAL_HOST = '127.0.0.1';
    private const DEFAULT_LOCAL_SCHEME = 'http';

    private HttpClientInterface $httpClient;

    /**
     * @param HttpClientInterface|null $httpClient HTTP client (defaults to CurlHttpClient with SSL verification disabled)
     */
    public function __construct(?HttpClientInterface $httpClient = null)
    {
        $this->httpClient = $httpClient ?? new CurlHttpClient([
            'verify_peer' => false,
            'verify_host' => false,
        ]);
    }

    /**
     * Make an internal API request using localhost.
     *
     * @param string $url The original URL (will be converted to localhost)
     * @param string $httpMethod HTTP method (GET, POST, PATCH, PUT, DELETE)
     * @param string $sessionCookie The session cookie for authentication
     * @param array<string, mixed> $payload Request body payload (will be JSON encoded)
     * @param array<string, string> $additionalHeaders Additional headers to include
     *
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws \JsonException
     *
     * @return array{status_code: int, content: mixed}
     */
    public function request(
        string $url,
        string $httpMethod,
        string $sessionCookie,
        array $payload = [],
        array $additionalHeaders = [],
    ): array {
        $localUrl = self::convertToLocalUrl($url);

        $headers = array_merge(
            [
                'Content-Type' => 'application/json',
                'Cookie' => $sessionCookie,
            ],
            $additionalHeaders
        );

        $options = ['headers' => $headers];

        if (! empty($payload)) {
            $options['body'] = json_encode($payload, JSON_THROW_ON_ERROR);
        }

        $response = $this->httpClient->request($httpMethod, $localUrl, $options);

        return [
            'status_code' => $response->getStatusCode(),
            'content' => json_decode($response->getContent(false), true),
        ];
    }

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
}
