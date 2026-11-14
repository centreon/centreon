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
use Centreon\Domain\MonitoringServer\Exception\GorgoneDeliveryException;
use Centreon\Domain\Repository\RepositoryException;
use Centreon\Infrastructure\MonitoringServer\Repository\MonitoringServerConfigurationRepositoryApi;
use Core\Security\Authentication\Domain\Model\AuthenticationTokens;
use Core\Security\Authentication\Domain\Model\NewProviderToken;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Security\Domain\Authentication\Interfaces\AuthenticationTokenServiceInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
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

    public function testGorgoneDeliveryFailureKeepsItsMessageAndReason(): void
    {
        $repository = $this->createRepositoryAnswering(
            '<response><statuscode>1</statuscode>'
            . '<error>Gorgone node 3 is not connected: nothing has been sent</error>'
            . '<errorcode>1</errorcode></response>'
        );

        try {
            $repository->moveExportFiles(3);
            $this->fail('A GorgoneDeliveryException was expected');
        } catch (GorgoneDeliveryException $ex) {
            $this->assertSame('Gorgone node 3 is not connected: nothing has been sent', $ex->getMessage());
            $this->assertSame(GorgoneDeliveryException::NODE_NOT_CONNECTED, $ex->getCode());
        }
    }

    public function testOtherFailureStaysARepositoryException(): void
    {
        $repository = $this->createRepositoryAnswering(
            '<response><statuscode>1</statuscode><error>Could not read file</error></response>'
        );

        $this->expectException(RepositoryException::class);
        $this->expectExceptionMessage('Could not read file');
        $repository->moveExportFiles(3);
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

    private function createRepositoryAnswering(string $xml): MonitoringServerConfigurationRepositoryApi
    {
        $authenticationTokens = $this->createStub(AuthenticationTokens::class);
        $authenticationTokens->method('getProviderToken')
            ->willReturn(new NewProviderToken('token', new \DateTimeImmutable()));
        $authenticationTokenService = $this->createMock(AuthenticationTokenServiceInterface::class);
        $authenticationTokenService->method('findByContact')->willReturn($authenticationTokens);

        return new MonitoringServerConfigurationRepositoryApi(
            $authenticationTokenService,
            $this->createMock(ContactInterface::class),
            new MockHttpClient(new MockResponse($xml)),
            'http://httpd:80',
        );
    }
}
