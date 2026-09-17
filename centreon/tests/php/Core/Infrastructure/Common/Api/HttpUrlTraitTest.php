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

namespace Tests\Core\Infrastructure\Common\Api;

use Core\Infrastructure\Common\Api\HttpUrlTrait;
use Symfony\Component\HttpFoundation\Request;

uses(HttpUrlTrait::class);

/**
 * Build a Symfony Request from raw server values, coming from a trusted proxy
 * so that X-Forwarded-* headers are honoured by the framework.
 *
 * @param array<string, string|null> $server
 */
function makeHttpUrlRequest(array $server): Request
{
    return new Request(server: array_merge(['REMOTE_ADDR' => '127.0.0.1'], $server));
}

beforeEach(function (): void {
    $this->server = $_SERVER;

    // Trust the local proxy so Symfony resolves the X-Forwarded-* headers.
    Request::setTrustedProxies(
        ['127.0.0.1'],
        Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_HOST
        | Request::HEADER_X_FORWARDED_PROTO
        | Request::HEADER_X_FORWARDED_PORT
    );
});

afterEach(function (): void {
    Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
    $_SERVER = $this->server;
});

it('throws RuntimeException from getHost when there is no current request', function (): void {
    $this->request = null;

    expect(fn () => $this->getHost(true))
        ->toThrow(\RuntimeException::class, 'Unable to resolve HTTP host: no current request available in the request stack');
});

it('throws RuntimeException from getBaseUri when there is no current request', function (): void {
    $this->request = null;

    expect(fn () => $this->getBaseUri())
        ->toThrow(\RuntimeException::class, 'Unable to resolve HTTP base URI: no current request available in the request stack');
});

it('returns empty base uri when the request uri has no recognizable prefix', function (): void {
    $this->request = makeHttpUrlRequest(['REQUEST_URI' => '/']);

    expect($this->getBaseUri())->toBe('');
});

it('returns base url without trailing slash when base uri is empty', function (): void {
    $this->request = makeHttpUrlRequest([
        'HTTP_HOST' => 'localhost',
        'HTTPS' => 'off',
        'REQUEST_URI' => '/api/latest/test',
    ]);

    expect($this->getBaseUrl())->toBe('http://localhost');
});

it(
    'formats properly base uri',
    function (
        $requestUri,
        $baseUri,
    ): void {
        $this->request = makeHttpUrlRequest(['REQUEST_URI' => $requestUri]);

        expect($this->getBaseUri())->toBe($baseUri);
    }
)->with([
    ['/authentication/providers/configurations/local', ''],
    ['/monitoring/authentication/providers/configurations/local', '/monitoring'],
    ['/monitoring/centreon/authentication/providers/configurations/local', '/monitoring/centreon'],
    ['/administration/authentication/providers/local', ''],
    ['/my-monitoring/api/v22.04/administration/authentication/providers/local', '/my-monitoring'],
    ['/api/latest/monitoring/resources', ''],
    ['/centreon/api/latest/monitoring/resources', '/centreon'],
    ['/monitoring/authentication/logout', '/monitoring'],
]);

it(
    'formats properly base url',
    function (
        $requestParameters,
        $baseUrl,
    ): void {
        $this->request = makeHttpUrlRequest([
            'HTTP_HOST' => $requestParameters[1],
            'HTTPS' => $requestParameters[0] === 'https' ? 'on' : 'off',
            'SERVER_PORT' => $requestParameters[3],
            'REQUEST_URI' => $requestParameters[4],
        ]);

        expect($this->getBaseUrl())->toBe($baseUrl);
    }
)->with([
    'docker port mapping (HTTP_HOST contains external port)' => [
        [
            'http',
            'localhost:8082',
            '127.0.0.1',
            '8080',
            '/centreon/api/latest/test',
        ],
        'http://localhost:8082/centreon',
    ],
    'standard https (HTTP_HOST without port)' => [
        [
            'https',
            'my.monitoring',
            '127.0.0.1',
            '443',
            '/api/latest/test',
        ],
        'https://my.monitoring',
    ],
    'https with non-standard port in HTTP_HOST' => [
        [
            'https',
            'my.monitoring:4443',
            '127.0.0.1',
            '4443',
            '/api/latest/test',
        ],
        'https://my.monitoring:4443',
    ],
    'standard http (HTTP_HOST without port)' => [
        [
            'https',
            'test',
            '127.0.0.1',
            null,
            '/api/latest/test',
        ],
        'https://test',
    ],
]);

it(
    'formats properly base url behind a reverse proxy',
    function (
        $requestParameters,
        $baseUrl,
    ): void {
        $this->request = makeHttpUrlRequest([
            'HTTP_HOST' => $requestParameters[1],
            'HTTPS' => 'off',
            'SERVER_PORT' => $requestParameters[3],
            'REQUEST_URI' => $requestParameters[4],
            'HTTP_X_FORWARDED_PROTO' => $requestParameters[5],
            'HTTP_X_FORWARDED_HOST' => $requestParameters[6],
        ]);

        expect($this->getBaseUrl())->toBe($baseUrl);
    }
)->with([
    'ssl termination with custom domain' => [
        [
            'http',
            'internal-host:8080',
            '127.0.0.1',
            '8080',
            '/centreon/api/latest/test',
            'https',
            'centreon.company.com',
        ],
        'https://centreon.company.com/centreon',
    ],
    'ssl termination with multiple forwarded hosts (first wins)' => [
        [
            'http',
            'internal-host:8080',
            '127.0.0.1',
            '8080',
            '/centreon/api/latest/test',
            'https',
            'centreon.company.com, proxy.internal',
        ],
        'https://centreon.company.com/centreon',
    ],
]);

it(
    'formats properly host with and without scheme',
    function (string $httpHost, ?string $forwardedHost, ?string $forwardedProto, string $requestScheme, bool $withScheme, string $expected): void {
        $server = [
            'HTTP_HOST' => $httpHost,
            'REQUEST_SCHEME' => $requestScheme,
        ];
        if ($forwardedHost !== null) {
            $server['HTTP_X_FORWARDED_HOST'] = $forwardedHost;
        }
        if ($forwardedProto !== null) {
            $server['HTTP_X_FORWARDED_PROTO'] = $forwardedProto;
        }
        $this->request = makeHttpUrlRequest($server);

        expect($this->getHost($withScheme))->toBe($expected);
    }
)->with([
    'host without scheme' => ['localhost:8082', null, null, 'http', false, 'localhost:8082'],
    'host with scheme' => ['localhost:8082', null, null, 'http', true, 'http://localhost:8082'],
    'forwarded host overrides HTTP_HOST' => ['internal:8080', 'centreon.company.com', null, 'http', false, 'centreon.company.com'],
    'forwarded proto overrides REQUEST_SCHEME' => ['localhost:8080', null, 'https', 'http', true, 'https://localhost:8080'],
    'forwarded host and proto combined' => ['internal:8080', 'centreon.company.com', 'https', 'http', true, 'https://centreon.company.com'],
    'multiple forwarded hosts (first wins)' => ['internal:8080', 'centreon.company.com, proxy.internal', null, 'http', false, 'centreon.company.com'],
]);
