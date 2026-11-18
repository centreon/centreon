<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

namespace Core\RemoteChannel\Infrastructure\Broker;

use Core\RemoteChannel\Domain\Exception\RemoteChannelException;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * HTTP client of the central broker remote API (contracts.md § 1). Configured from the environment:
 * - CENTREON_BROKER_API_URL: base URL, e.g. http://cbd:8099 (required)
 * - CENTREON_BROKER_API_TOKEN: Bearer token (required)
 * - CENTREON_BROKER_API_TLS_VERIFY: 0 to accept a self-signed certificate (PoC), default 1
 */
class BrokerApiClient
{
    private const URL_ENV = 'CENTREON_BROKER_API_URL';
    private const TOKEN_ENV = 'CENTREON_BROKER_API_TOKEN';
    private const TLS_VERIFY_ENV = 'CENTREON_BROKER_API_TLS_VERIFY';

    /** Connection and per-call HTTP timeout, seconds: every status poll is short */
    private const HTTP_TIMEOUT = 5;

    private HttpClientInterface $client;

    public function __construct(?HttpClientInterface $client = null)
    {
        $this->client = $client ?? new CurlHttpClient();
    }

    /**
     * @param array<string, mixed>|null $json
     * @param float|null $timeout seconds, HTTP_TIMEOUT by default
     *
     * @throws RemoteChannelException when the API cannot be reached or answers an HTTP error
     * @return array<string, mixed> decoded body of a 200/202 answer
     */
    public function call(string $method, string $path, ?array $json = null, ?float $timeout = null): array
    {
        $baseUrl = $this->getEnv(self::URL_ENV);
        $token = $this->getEnv(self::TOKEN_ENV);
        if ($baseUrl === '' || $token === '') {
            throw RemoteChannelException::brokerUnreachable(
                sprintf('%s and %s must be set', self::URL_ENV, self::TOKEN_ENV)
            );
        }
        $verify = $this->getEnv(self::TLS_VERIFY_ENV) !== '0';
        $options = [
            'headers' => ['Authorization' => 'Bearer ' . $token],
            'timeout' => $timeout ?? self::HTTP_TIMEOUT,
            'max_duration' => $timeout ?? self::HTTP_TIMEOUT,
            'verify_peer' => $verify,
            'verify_host' => $verify,
            'proxy' => null,
            'no_proxy' => '*',
        ];
        if ($json !== null) {
            $options['json'] = $json;
        }
        $url = rtrim($baseUrl, '/') . $path;

        try {
            $response = $this->client->request($method, $url, $options);
            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (\Throwable $ex) {
            throw RemoteChannelException::brokerUnreachable(sprintf('%s %s: %s', $method, $url, $ex->getMessage()), $ex);
        }

        $decoded = json_decode($content, true);
        if ($statusCode !== 200 && $statusCode !== 202) {
            $message = is_array($decoded)
                ? (string) ($decoded['message'] ?? $decoded['error'] ?? $content)
                : $content;

            throw RemoteChannelException::brokerUnreachable(
                sprintf('%s %s answered HTTP %d: %s', $method, $path, $statusCode, $message)
            );
        }
        if (! is_array($decoded)) {
            throw RemoteChannelException::brokerUnreachable(sprintf('%s %s: invalid JSON answer', $method, $path));
        }

        return $decoded;
    }

    private function getEnv(string $name): string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);

        return is_string($value) ? trim($value) : '';
    }
}
