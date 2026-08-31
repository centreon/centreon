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

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
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
    private readonly ?string $internalApiBaseUrl;

    public function __construct(
        #[Autowire('%env(CENTREON_INTERNAL_API_BASE_URL)%')]
        string $internalApiBaseUrl,
        private readonly RequestStack $requestStack,
        private HttpClientInterface $httpClient,
    ) {
        $this->internalApiBaseUrl = $internalApiBaseUrl ?: null;
        $this->httpClient = $httpClient->withOptions([
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
        $request = $this->requestStack->getCurrentRequest();
        $url = self::convertToLocalUrl(
            $url,
            $this->internalApiBaseUrl,
            $request?->server->get('REQUEST_SCHEME'),
            $request?->server->get('SERVER_ADDR'),
            $request?->server->getInt('SERVER_PORT') ?: null,
        );
        $headers = array_merge(
            [
                'Content-Type' => 'application/json',
                'Cookie' => $sessionCookie,
            ],
            $additionalHeaders
        );

        $options = ['headers' => $headers];

        if ($payload !== []) {
            $options['body'] = json_encode($payload, JSON_THROW_ON_ERROR);
        }

        $response = $this->httpClient->request($httpMethod, $url, $options);

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
     * When $internalApiBaseUrl explicitly defines a scheme (e.g. "http://host"), that
     * scheme is honored; otherwise the request scheme is used. This allows an internal
     * HTTPS -> HTTP redirection even when the external request is HTTPS.
     *
     * @param string $url The original URL (may contain external hostname)
     *
     * @throws \RuntimeException When required parameters are null and cannot be resolved from the URL
     *
     * @return string The localhost URL
     */
    public static function convertToLocalUrl(
        string $url,
        ?string $internalApiBaseUrl = null,
        ?string $requestScheme = null,
        ?string $serverAddress = null,
        ?int $serverPort = null,
    ): string {
        if (str_contains($url, '://')) {
            $scheme = parse_url($url, PHP_URL_SCHEME);
            if (! is_string($scheme) || filter_var($url, FILTER_VALIDATE_URL) === false) {
                return $url;
            }
            $requestScheme = $scheme;
        } elseif (! str_starts_with($url, '/')) {
            $url = '/' . $url;
        }

        $parsedUrl = parse_url($url);

        if ($parsedUrl === false) {
            return $url;
        }

        $localUrl = null;
        if (! empty($internalApiBaseUrl)) {
            $localUrl = self::generateUrlWithoutScheme($internalApiBaseUrl);
            if ($localUrl !== null) {
                if ($serverPort !== null && ! str_contains($localUrl, ':')) {
                    $localUrl .= ':' . $serverPort;
                }
                // Honor the scheme explicitly defined in the base URL so an internal
                // HTTPS -> HTTP redirection stays possible even when the external request
                // is HTTPS (e.g. behind a TLS-terminating proxy). Fall back to the request
                // scheme when the base URL does not define one.
                $baseScheme = str_contains($internalApiBaseUrl, '://')
                    ? parse_url($internalApiBaseUrl, PHP_URL_SCHEME)
                    : null;
                if (is_string($baseScheme) && $baseScheme !== '') {
                    $requestScheme = $baseScheme;
                }
                if ($requestScheme === null) {
                    throw new \RuntimeException(
                        'Cannot build local URL: request scheme is null.'
                    );
                }
                $localUrl = $requestScheme . '://' . $localUrl;
            }
        }

        if ($localUrl === null) {
            if ($requestScheme === null) {
                throw new \RuntimeException(
                    'Cannot build local URL: request scheme is null.'
                );
            }
            if ($serverAddress === null) {
                throw new \RuntimeException(
                    'Cannot build local URL: server address is null.'
                );
            }
            $portSuffix = ($serverPort !== null) ? ':' . $serverPort : '';
            $localUrl = $requestScheme . '://' . $serverAddress . $portSuffix;
        }

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

    private static function generateUrlWithoutScheme(string $url): ?string
    {
        if (str_contains($url, '://')) {
            $parsed = parse_url($url);
            if ($parsed === false || ! isset($parsed['host'])) {
                return null;
            }
            $host = $parsed['host'];
            $port = $parsed['port'] ?? null;
        } else {
            $parts = explode(':', $url, 2);
            $host = $parts[0];
            $port = isset($parts[1]) ? (int) $parts[1] : null;
        }

        if (empty($host)) {
            return null;
        }

        if ($port !== null && ($port < 1 || $port > 65535)) {
            return null;
        }

        return $port !== null ? $host . ':' . $port : $host;
    }
}
