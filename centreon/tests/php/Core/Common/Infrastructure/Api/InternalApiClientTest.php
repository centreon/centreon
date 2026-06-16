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

namespace Tests\Core\Common\Infrastructure\Api;

use Core\Common\Infrastructure\Api\InternalApiClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @covers \Core\Common\Infrastructure\Api\InternalApiClient
 */
class InternalApiClientTest extends TestCase
{
    private const TEST_SESSION_COOKIE = 'PHPSESSID=test-session-id-12345';

    /**
     * @param string $inputUrl The original URL
     * @param string $expectedUrl The expected localhost URL
     * @param array{
     *     'CENTREON_INTERNAL_API_BASE_URL': ?string,
     *     'requestScheme': ?string,
     *     'serverAddress': ?string,
     *     'serverPort': ?int,
     * } $env
     */
    #[DataProvider('provideUrlsToConvert')]
    public function testConvertToLocalUrl(string $inputUrl, string $expectedUrl, array $env): void
    {
        $result = InternalApiClient::convertToLocalUrl(
            $inputUrl,
            $env['CENTREON_INTERNAL_API_BASE_URL'] ?? null,
            $env['requestScheme'] ?? null,
            $env['serverAddress'] ?? null,
            $env['serverPort'] ?? null,
        );

        $this->assertSame($expectedUrl, $result);
    }

    /**
     * @return iterable<string, array{inputUrl: string, expectedUrl: string}>
     */
    public static function provideUrlsToConvert(): iterable
    {
        yield 'simple HTTPS URL' => [
            'inputUrl' => 'https://centreon.example.com/centreon/api/latest/hosts',
            'expectedUrl' => 'https://127.0.0.1/centreon/api/latest/hosts',
            'env' => ['CENTREON_INTERNAL_API_BASE_URL' => '127.0.0.1'],
        ];

        yield 'simple HTTP URL' => [
            'inputUrl' => 'http://centreon.example.com/centreon/api/latest/hosts',
            'expectedUrl' => 'http://127.0.0.1/centreon/api/latest/hosts',
            'env' => ['CENTREON_INTERNAL_API_BASE_URL' => '127.0.0.1'],
        ];

        yield 'URL with query string' => [
            'inputUrl' => 'https://centreon.example.com/centreon/api/latest/hosts?limit=10&page=1',
            'expectedUrl' => 'https://127.0.0.1/centreon/api/latest/hosts?limit=10&page=1',
            'env' => ['CENTREON_INTERNAL_API_BASE_URL' => '127.0.0.1'],
        ];

        yield 'URL with fragment' => [
            'inputUrl' => 'https://centreon.example.com/centreon/api/latest/hosts#section',
            'expectedUrl' => 'https://127.0.0.1/centreon/api/latest/hosts#section',
            'env' => ['CENTREON_INTERNAL_API_BASE_URL' => '127.0.0.1'],
        ];

        yield 'URL with query string and fragment' => [
            'inputUrl' => 'https://centreon.example.com/centreon/api/latest/hosts?id=5#details',
            'expectedUrl' => 'https://127.0.0.1/centreon/api/latest/hosts?id=5#details',
            'env' => ['CENTREON_INTERNAL_API_BASE_URL' => '127.0.0.1'],
        ];

        yield 'CENTREON_INTERNAL_API_BASE_URL contains host with port' => [
            'inputUrl' => 'https://centreon.example.com/centreon/api/latest/hosts',
            'expectedUrl' => 'https://127.0.0.1:8443/centreon/api/latest/hosts',
            'env' => ['CENTREON_INTERNAL_API_BASE_URL' => '127.0.0.1:8443'],
        ];

        yield 'localhost hostname converted to 127.0.0.1' => [
            'inputUrl' => 'http://localhost/centreon/api/latest/hosts',
            'expectedUrl' => 'http://127.0.0.1/centreon/api/latest/hosts',
            'env' => ['CENTREON_INTERNAL_API_BASE_URL' => '127.0.0.1'],
        ];

        yield 'server address and port used when CENTREON_INTERNAL_API_BASE_URL is null' => [
            'inputUrl' => 'http://127.0.1.1/centreon/api/latest/hosts',
            'expectedUrl' => 'http://127.0.1.1:80/centreon/api/latest/hosts',
            'env' => ['CENTREON_INTERNAL_API_BASE_URL' => null, 'serverAddress' => '127.0.1.1', 'serverPort' => 80],
        ];

        yield 'URL with IP address' => [
            'inputUrl' => 'https://192.168.1.100/centreon/api/latest/hosts',
            'expectedUrl' => 'https://127.0.0.1/centreon/api/latest/hosts',
            'env' => ['CENTREON_INTERNAL_API_BASE_URL' => '127.0.0.1'],
        ];

        yield 'URL with subdomain' => [
            'inputUrl' => 'https://monitoring.centreon.example.com/centreon/api/latest/hosts',
            'expectedUrl' => 'https://127.0.0.1/centreon/api/latest/hosts',
            'env' => ['CENTREON_INTERNAL_API_BASE_URL' => '127.0.0.1'],
        ];

        yield 'URL with complex path' => [
            'inputUrl' => 'https://centreon.example.com/centreon/api/latest/configuration/hosts/123/templates',
            'expectedUrl' => 'https://127.0.0.1/centreon/api/latest/configuration/hosts/123/templates',
            'env' => ['CENTREON_INTERNAL_API_BASE_URL' => '127.0.0.1'],
        ];

        yield 'URL without port and path' => [
            'inputUrl' => 'https://centreon.example.com',
            'expectedUrl' => 'https://127.0.0.1',
            'env' => ['CENTREON_INTERNAL_API_BASE_URL' => '127.0.0.1'],
        ];

        yield 'URL with port and without path' => [
            'inputUrl' => 'https://centreon.example.com:8080',
            'expectedUrl' => 'https://127.0.0.1',
            'env' => ['CENTREON_INTERNAL_API_BASE_URL' => '127.0.0.1'],
        ];

        yield 'URL with port different of CENTREON_INTERNAL_API_BASE_URL and without path' => [
            'inputUrl' => 'https://centreon.example.com:8080',
            'expectedUrl' => 'https://127.0.0.1:4000',
            'env' => ['CENTREON_INTERNAL_API_BASE_URL' => '127.0.0.1:4000'],
        ];

        yield 'URL with only query string' => [
            'inputUrl' => 'https://centreon.example.com?search=test',
            'expectedUrl' => 'https://127.0.0.1?search=test',
            'env' => ['CENTREON_INTERNAL_API_BASE_URL' => '127.0.0.1'],
        ];

        yield 'URL with encoded characters in query' => [
            'inputUrl' => 'https://centreon.example.com/api/hosts?name=host%20name&filter=%7B%22id%22%3A1%7D',
            'expectedUrl' => 'https://127.0.0.1/api/hosts?name=host%20name&filter=%7B%22id%22%3A1%7D',
            'env' => ['CENTREON_INTERNAL_API_BASE_URL' => '127.0.0.1'],
        ];

        yield 'Relative URL without / at start' => [
            'inputUrl' => 'api/hosts',
            'expectedUrl' => 'https://127.0.0.1:8080/api/hosts',
            'env' => ['CENTREON_INTERNAL_API_BASE_URL' => '127.0.0.1', 'requestScheme' => 'https', 'serverPort' => 8080],
        ];

        yield 'Relative URL with leading slash' => [
            'inputUrl' => '/api/hosts',
            'expectedUrl' => 'https://127.0.0.1:80/api/hosts',
            'env' => ['CENTREON_INTERNAL_API_BASE_URL' => '127.0.0.1', 'requestScheme' => 'https', 'serverPort' => 80],
        ];

        yield 'empty CENTREON_INTERNAL_API_BASE_URL falls back to server address and port' => [
            'inputUrl' => 'https://centreon.example.com/api/hosts',
            'expectedUrl' => 'https://127.0.0.1:80/api/hosts',
            'env' => ['CENTREON_INTERNAL_API_BASE_URL' => '', 'serverAddress' => '127.0.0.1', 'serverPort' => 80],
        ];

        yield 'serverPort ignored when CENTREON_INTERNAL_API_BASE_URL already contains a port' => [
            'inputUrl' => 'https://centreon.example.com/api/hosts',
            'expectedUrl' => 'https://127.0.0.1:9000/api/hosts',
            'env' => ['CENTREON_INTERNAL_API_BASE_URL' => '127.0.0.1:9000', 'serverPort' => 8080],
        ];

        yield 'CENTREON_INTERNAL_API_BASE_URL with full scheme has its scheme stripped' => [
            'inputUrl' => 'https://centreon.example.com/centreon/api/latest/hosts',
            'expectedUrl' => 'https://127.0.0.1/centreon/api/latest/hosts',
            'env' => ['CENTREON_INTERNAL_API_BASE_URL' => 'https://127.0.0.1'],
        ];

        yield 'Relative URL with leading slash and base URL with port ignores serverPort' => [
            'inputUrl' => '/api/hosts',
            'expectedUrl' => 'https://127.0.0.1:9000/api/hosts',
            'env' => ['CENTREON_INTERNAL_API_BASE_URL' => '127.0.0.1:9000', 'requestScheme' => 'https', 'serverPort' => 8080],
        ];

        yield 'Relative URL without CENTREON_INTERNAL_API_BASE_URL' => [
            'inputUrl' => '/api/hosts',
            'expectedUrl' => 'http://localhost:8080/api/hosts',
            'env' => ['requestScheme' => 'http', 'serverAddress' => 'localhost', 'serverPort' => 8080],
        ];
    }

    /**
     * @dataProvider provideConvertToLocalUrlExceptionCases
     */
    public function testConvertToLocalUrlThrowsRuntimeException(
        string $url,
        ?string $internalApiBaseUrl,
        ?string $requestScheme,
        ?string $serverAddress,
        ?int $serverPort,
        string $expectedMessage,
    ): void {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($expectedMessage);

        InternalApiClient::convertToLocalUrl($url, $internalApiBaseUrl, $requestScheme, $serverAddress, $serverPort);
    }

    /**
     * @return iterable<string, array{0: string, 1: ?string, 2: ?string, 3: ?string, 4: ?int, 5: string}>
     */
    public static function provideConvertToLocalUrlExceptionCases(): iterable
    {
        // Path 1: valid internalApiBaseUrl + null requestScheme
        // generateUrlWithoutScheme returns non-null but the scheme is missing
        yield 'relative URL with valid base URL and null request scheme' => [
            '/api/hosts', '127.0.0.1', null, null, null,
            'Cannot build local URL: request scheme is null.',
        ];

        yield 'relative URL without leading slash, valid base URL and null request scheme' => [
            'api/hosts', '127.0.0.1', null, null, null,
            'Cannot build local URL: request scheme is null.',
        ];

        // Path 2: $localUrl is null (empty or null internalApiBaseUrl) + null requestScheme
        yield 'relative URL with null base URL and null request scheme' => [
            '/api/hosts', null, null, null, null,
            'Cannot build local URL: request scheme is null.',
        ];

        yield 'relative URL with empty base URL and null request scheme' => [
            '/api/hosts', '', null, null, null,
            'Cannot build local URL: request scheme is null.',
        ];

        // Path 2 (variant): generateUrlWithoutScheme returns null + null requestScheme
        yield 'relative URL with invalid base URL causing null host and null request scheme' => [
            '/api/hosts', 'http://', null, '127.0.0.1', null,
            'Cannot build local URL: request scheme is null.',
        ];

        // Path 3: $localUrl is null + defined requestScheme + null serverAddress
        yield 'relative URL with null base URL, defined request scheme and null server address' => [
            '/api/hosts', null, 'https', null, null,
            'Cannot build local URL: server address is null.',
        ];

        // Path 3 (variant): absolute URL (scheme extracted from the URL) + null internalApiBaseUrl + null serverAddress
        yield 'absolute URL with null base URL and null server address' => [
            'https://example.com/api', null, null, null, null,
            'Cannot build local URL: server address is null.',
        ];
    }

    public function testConvertToLocalUrlWithInvalidUrlReturnsOriginal(): void
    {
        // An extremely malformed URL that parse_url cannot handle
        $malformedUrl = 'http:///example.com';

        $result = InternalApiClient::convertToLocalUrl($malformedUrl);

        // parse_url returns false for severely malformed URLs,
        // so the original URL should be returned
        // Note: parse_url is quite permissive, so this tests the fallback behavior
        $this->assertIsString($result);
    }

    public function testConvertToLocalUrlAlwaysUsesLocalhost(): void
    {
        $externalUrl = 'https://external-server.example.com/api/test';

        $result = InternalApiClient::convertToLocalUrl($externalUrl, null, 'https', '127.0.0.1');

        $this->assertStringContainsString('127.0.0.1', $result);
        $this->assertStringNotContainsString('external-server.example.com', $result);
    }

    public function testConvertToLocalUrlPreservesPathIntegrity(): void
    {
        $url = 'https://example.com/centreon/api/latest/configuration/hosts/42';

        $result = InternalApiClient::convertToLocalUrl($url, null, 'https', '127.0.0.1', 80);

        $this->assertStringContainsString('/centreon/api/latest/configuration/hosts/42', $result);
    }

    public function testConvertToLocalUrlPreservesQueryParametersIntegrity(): void
    {
        $url = 'https://example.com/api?param1=value1&param2=value2&param3=value3';

        $result = InternalApiClient::convertToLocalUrl($url, null, 'https', '127.0.0.1', 80);

        $this->assertStringContainsString('param1=value1', $result);
        $this->assertStringContainsString('param2=value2', $result);
        $this->assertStringContainsString('param3=value3', $result);
    }

    // =========================================================================
    // Tests for generateUrlWithoutScheme() method
    // =========================================================================

    /**
     * @dataProvider provideUrlsForGenerateUrlWithoutScheme
     */
    public function testGenerateUrlWithoutScheme(string $input, ?string $expected): void
    {
        $method = new \ReflectionMethod(InternalApiClient::class, 'generateUrlWithoutScheme');
        $result = $method->invoke(null, $input);

        $this->assertSame($expected, $result);
    }

    /**
     * @return iterable<string, array{0: string, 1: string|null}>
     */
    public static function provideUrlsForGenerateUrlWithoutScheme(): iterable
    {
        yield 'host:port without scheme' => ['127.0.0.1:8080', '127.0.0.1:8080'];

        yield 'host only without scheme' => ['127.0.0.1', '127.0.0.1'];

        yield 'hostname:port without scheme' => ['myhost:9090', 'myhost:9090'];

        yield 'hostname only without scheme' => ['myhost', 'myhost'];

        yield 'http scheme with host and port' => ['http://127.0.0.1:8080', '127.0.0.1:8080'];

        yield 'https scheme with host and port' => ['https://127.0.0.1:443', '127.0.0.1:443'];

        yield 'http scheme with host only' => ['http://127.0.0.1', '127.0.0.1'];

        yield 'https scheme with host only' => ['https://myhost', 'myhost'];

        yield 'scheme with no host' => ['http://', null];

        yield 'scheme with empty host and port' => ['http://:8080', null];

        yield 'scheme empty' => ['', null];

        yield 'host and port empty' => [':', null];

        yield 'empty host with port without scheme' => [':8080', null];

        yield 'port 0 is invalid without scheme' => ['127.0.0.1:0', null];

        yield 'port 1 is the minimum valid port without scheme' => ['127.0.0.1:1', '127.0.0.1:1'];

        yield 'port 65535 is the maximum valid port without scheme' => ['127.0.0.1:65535', '127.0.0.1:65535'];

        yield 'port 65536 exceeds maximum without scheme' => ['127.0.0.1:65536', null];

        yield 'port 0 is invalid with scheme' => ['http://127.0.0.1:0', null];

        yield 'port 1 is the minimum valid port with scheme' => ['http://127.0.0.1:1', '127.0.0.1:1'];

        yield 'port 65535 is the maximum valid port with scheme' => ['https://127.0.0.1:65535', '127.0.0.1:65535'];

        yield 'port 65536 exceeds maximum with scheme' => ['http://127.0.0.1:65536', null];

        yield 'non-numeric port without scheme casts to 0 and is invalid' => ['myhost:notaport', null];

        yield 'negative port without scheme is invalid' => ['127.0.0.1:-5', null];

        yield 'url with path and query is stripped leaving host and port only' => ['http://myhost:8080/some/path?q=1', 'myhost:8080'];

        yield 'url with path and query and no port is stripped leaving host only' => ['https://myhost/path?query=value', 'myhost'];

        yield 'IPv6 address without port preserves brackets from parse_url' => ['http://[::1]', '[::1]'];

        yield 'IPv6 address with port preserves brackets from parse_url' => ['http://[::1]:8080', '[::1]:8080'];
    }

    // =========================================================================
    // Tests for request() method
    // =========================================================================

    public function testRequestConvertsUrlToLocalhostWithRelativeUrl(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getContent')->willReturn('{"success": true}');

        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $mockHttpClient->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                $this->callback(fn ($url) => str_contains($url, 'localhost:80')),
                $this->anything()
            )
            ->willReturn($mockResponse);

        $client = $this->createClient($mockHttpClient);
        $result = $client->request('/api/test', 'GET', self::TEST_SESSION_COOKIE);

        $this->assertEquals(200, $result['status_code']);
        $this->assertEquals(['success' => true], $result['content']);
    }

    public function testRequestSetsCorrectHttpMethod(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(201);
        $mockResponse->method('getContent')->willReturn('{}');

        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $mockHttpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                $this->anything(),
                $this->anything()
            )
            ->willReturn($mockResponse);

        $client = $this->createClient($mockHttpClient);
        $result = $client->request('http://localhost/api/test', 'POST', self::TEST_SESSION_COOKIE);

        $this->assertEquals(201, $result['status_code']);
    }

    public function testRequestEncodesPayloadAsJson(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getContent')->willReturn('{}');

        $payload = ['name' => 'test-host', 'address' => '192.168.1.1'];

        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $mockHttpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                $this->anything(),
                $this->callback(fn ($options) => isset($options['body']) && $options['body'] === json_encode($payload))
            )
            ->willReturn($mockResponse);

        $client = $this->createClient($mockHttpClient);
        $client->request('http://localhost/api/test', 'POST', self::TEST_SESSION_COOKIE, $payload);
    }

    public function testRequestDoesNotSetBodyForEmptyPayload(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getContent')->willReturn('{}');

        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $mockHttpClient->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                $this->anything(),
                $this->callback(fn ($options) => ! isset($options['body']))
            )
            ->willReturn($mockResponse);

        $client = $this->createClient($mockHttpClient);
        $client->request('http://localhost/api/test', 'GET', self::TEST_SESSION_COOKIE);
    }

    public function testRequestSetsContentTypeHeader(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getContent')->willReturn('{}');

        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $mockHttpClient->expects($this->once())
            ->method('request')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(fn ($options) => isset($options['headers']['Content-Type'])
                        && $options['headers']['Content-Type'] === 'application/json')
            )
            ->willReturn($mockResponse);

        $client = $this->createClient($mockHttpClient);
        $client->request('http://localhost/api/test', 'GET', self::TEST_SESSION_COOKIE);
    }

    public function testRequestSetsCookieHeader(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getContent')->willReturn('{}');

        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $mockHttpClient->expects($this->once())
            ->method('request')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(fn ($options) => isset($options['headers']['Cookie'])
                        && $options['headers']['Cookie'] === self::TEST_SESSION_COOKIE)
            )
            ->willReturn($mockResponse);

        $client = $this->createClient($mockHttpClient);
        $client->request('http://localhost/api/test', 'GET', self::TEST_SESSION_COOKIE);
    }

    public function testRequestParsesJsonResponse(): void
    {
        $responseData = ['id' => 42, 'name' => 'test', 'active' => true];

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getContent')->willReturn(json_encode($responseData));

        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $mockHttpClient->method('request')->willReturn($mockResponse);

        $client = $this->createClient($mockHttpClient);
        $result = $client->request('http://localhost/api/test', 'GET', self::TEST_SESSION_COOKIE);

        $this->assertEquals($responseData, $result['content']);
    }

    public function testRequestReturnsNullForInvalidJson(): void
    {
        $invalidJson = 'This is not JSON';

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getContent')->willReturn($invalidJson);

        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $mockHttpClient->method('request')->willReturn($mockResponse);

        $client = $this->createClient($mockHttpClient);
        $result = $client->request('http://localhost/api/test', 'GET', self::TEST_SESSION_COOKIE);

        // json_decode returns null for invalid JSON
        $this->assertNull($result['content']);
    }

    public function testRequestWithDeleteMethod(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(204);
        $mockResponse->method('getContent')->willReturn('');

        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $mockHttpClient->expects($this->once())
            ->method('request')
            ->with('DELETE', $this->anything(), $this->anything())
            ->willReturn($mockResponse);

        $client = $this->createClient($mockHttpClient);
        $result = $client->request('http://localhost/api/hosts/42', 'DELETE', self::TEST_SESSION_COOKIE);

        $this->assertEquals(204, $result['status_code']);
    }

    public function testRequestWithPatchMethod(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getContent')->willReturn('{"updated": true}');

        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $mockHttpClient->expects($this->once())
            ->method('request')
            ->with('PATCH', $this->anything(), $this->anything())
            ->willReturn($mockResponse);

        $client = $this->createClient($mockHttpClient);
        $result = $client->request('http://localhost/api/hosts/42', 'PATCH', self::TEST_SESSION_COOKIE, ['name' => 'new-name']);

        $this->assertEquals(200, $result['status_code']);
    }

    // =========================================================================
    // Tests for error scenarios
    // =========================================================================

    public function testRequestHandles4xxStatusCodes(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(404);
        $mockResponse->method('getContent')->willReturn('{"error": "Not found"}');

        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $mockHttpClient->method('request')->willReturn($mockResponse);

        $client = $this->createClient($mockHttpClient);
        $result = $client->request('http://localhost/api/hosts/9999', 'GET', self::TEST_SESSION_COOKIE);

        $this->assertEquals(404, $result['status_code']);
        $this->assertEquals(['error' => 'Not found'], $result['content']);
    }

    public function testRequestHandles5xxStatusCodes(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(500);
        $mockResponse->method('getContent')->willReturn('{"error": "Internal server error"}');

        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $mockHttpClient->method('request')->willReturn($mockResponse);

        $client = $this->createClient($mockHttpClient);
        $result = $client->request('http://localhost/api/test', 'POST', self::TEST_SESSION_COOKIE);

        $this->assertEquals(500, $result['status_code']);
    }

    private function createRequestStack(
        string $scheme = 'http',
        string $serverAddr = 'localhost',
        int $port = 80,
    ): RequestStack {
        $request = new Request([], [], [], [], [], [
            'REQUEST_SCHEME' => $scheme,
            'SERVER_ADDR' => $serverAddr,
            'SERVER_PORT' => (string) $port,
        ]);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        return $requestStack;
    }

    private function createClient(
        HttpClientInterface&MockObject $httpClient,
        string $internalApiBaseUrl = '',
        ?RequestStack $requestStack = null,
    ): InternalApiClient {
        $httpClient->method('withOptions')->willReturnSelf();

        return new InternalApiClient(
            $internalApiBaseUrl,
            $requestStack ?? $this->createRequestStack(),
            $httpClient,
        );
    }
}
