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

namespace Tests\Centreon\Infrastructure\MonitoringServer\Repository;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Infrastructure\MonitoringServer\Repository\MonitoringServerConfigurationRepositoryApi;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Security\Domain\Authentication\Interfaces\AuthenticationTokenServiceInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @covers \Centreon\Infrastructure\MonitoringServer\Repository\MonitoringServerConfigurationRepositoryApi
 */
class MonitoringServerConfigurationRepositoryApiTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $serverBackup;

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
    }

    /**
     * @param array<string, string> $server
     */
    #[DataProvider('provideServerUris')]
    public function testInitUri(?string $internalApiBaseUrl, array $server, string $expectedUri): void
    {
        unset(
            $_SERVER['REQUEST_SCHEME'],
            $_SERVER['SERVER_NAME'],
            $_SERVER['SERVER_ADDR'],
            $_SERVER['SERVER_PORT'],
            $_SERVER['REQUEST_URI'],
        );
        $_SERVER = array_merge($_SERVER, $server);

        $repository = new MonitoringServerConfigurationRepositoryApi(
            $this->createMock(AuthenticationTokenServiceInterface::class),
            $this->createMock(ContactInterface::class),
            $this->createMock(HttpClientInterface::class),
            $internalApiBaseUrl,
        );
        (new \ReflectionMethod($repository, 'initUri'))->invoke($repository);

        $this->assertSame(
            $expectedUri,
            (new \ReflectionProperty($repository, 'serverUri'))->getValue($repository)
        );
    }

    /**
     * @return iterable<string, array{?string, array<string, string>, string}>
     */
    public static function provideServerUris(): iterable
    {
        $httpRequest = [
            'REQUEST_SCHEME' => 'https',
            'SERVER_NAME' => 'central.example.com',
            'SERVER_ADDR' => '10.0.0.5',
            'SERVER_PORT' => '443',
            'REQUEST_URI' => '/centreon/api/latest/configuration/monitoring-servers/generate-and-reload',
        ];

        yield 'without internal base URL, SERVER_NAME without port' => [
            null,
            $httpRequest,
            'https://central.example.com/centreon',
        ];

        yield 'with an empty internal base URL, SERVER_NAME without port' => [
            '',
            $httpRequest,
            'https://central.example.com/centreon',
        ];

        yield 'with an internal base URL, its scheme, host and port' => [
            'http://httpd:80',
            $httpRequest,
            'http://httpd:80/centreon',
        ];

        yield 'with an internal base URL, the request prefix' => [
            'http://httpd:80',
            array_merge($httpRequest, ['REQUEST_URI' => '/monitoring/api/latest/configuration']),
            'http://httpd:80/monitoring',
        ];

        yield 'with an internal base URL outside of an HTTP request, the default prefix' => [
            'http://httpd:80',
            [],
            'http://httpd:80/centreon',
        ];
    }
}
