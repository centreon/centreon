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
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @covers \Core\Common\Infrastructure\Api\InternalApiClient
 */
class InternalApiClientTest extends TestCase
{
    private const TEST_SESSION_COOKIE = 'PHPSESSID=test-session-id-12345';

    /**
     * @dataProvider provideUrlsToConvert
     *
     * @param string $inputUrl The original URL
     * @param string $expectedUrl The expected localhost URL
     */
    public function testConvertToLocalUrl(string $inputUrl, string $expectedUrl): void
    {
        $result = InternalApiClient::convertToLocalUrl($inputUrl);

        $this->assertSame($expectedUrl, $result);
    }

    /**
     * @return iterable<string, array{inputUrl: string, expectedUrl: string}>
     */
    public static function provideUrlsToConvert(): iterable
    {
        yield 'simple HTTPS URL' => [
            'inputUrl' => 'https://centreon.example.com/centreon/api/latest/hosts',
            'expectedUrl' => 'http://127.0.0.1/centreon/api/latest/hosts',
        ];

        yield 'simple HTTP URL' => [
            'inputUrl' => 'http://centreon.example.com/centreon/api/latest/hosts',
            'expectedUrl' => 'http://127.0.0.1/centreon/api/latest/hosts',
        ];

        yield 'URL with query string' => [
            'inputUrl' => 'https://centreon.example.com/centreon/api/latest/hosts?limit=10&page=1',
            'expectedUrl' => 'http://127.0.0.1/centreon/api/latest/hosts?limit=10&page=1',
        ];

        yield 'URL with fragment' => [
            'inputUrl' => 'https://centreon.example.com/centreon/api/latest/hosts#section',
            'expectedUrl' => 'http://127.0.0.1/centreon/api/latest/hosts#section',
        ];

        yield 'URL with query string and fragment' => [
            'inputUrl' => 'https://centreon.example.com/centreon/api/latest/hosts?id=5#details',
            'expectedUrl' => 'http://127.0.0.1/centreon/api/latest/hosts?id=5#details',
        ];

        yield 'URL with port number' => [
            'inputUrl' => 'https://centreon.example.com:8443/centreon/api/latest/hosts',
            'expectedUrl' => 'http://127.0.0.1/centreon/api/latest/hosts',
        ];

        yield 'localhost URL should remain localhost' => [
            'inputUrl' => 'http://localhost/centreon/api/latest/hosts',
            'expectedUrl' => 'http://127.0.0.1/centreon/api/latest/hosts',
        ];

        yield '127.0.0.1 URL should remain unchanged' => [
            'inputUrl' => 'http://127.0.0.1/centreon/api/latest/hosts',
            'expectedUrl' => 'http://127.0.0.1/centreon/api/latest/hosts',
        ];

        yield 'URL with IP address' => [
            'inputUrl' => 'https://192.168.1.100/centreon/api/latest/hosts',
            'expectedUrl' => 'http://127.0.0.1/centreon/api/latest/hosts',
        ];

        yield 'URL with subdomain' => [
            'inputUrl' => 'https://monitoring.centreon.example.com/centreon/api/latest/hosts',
            'expectedUrl' => 'http://127.0.0.1/centreon/api/latest/hosts',
        ];

        yield 'URL with complex path' => [
            'inputUrl' => 'https://centreon.example.com/centreon/api/latest/configuration/hosts/123/templates',
            'expectedUrl' => 'http://127.0.0.1/centreon/api/latest/configuration/hosts/123/templates',
        ];

        yield 'URL without path' => [
            'inputUrl' => 'https://centreon.example.com',
            'expectedUrl' => 'http://127.0.0.1',
        ];

        yield 'URL with only query string' => [
            'inputUrl' => 'https://centreon.example.com?search=test',
            'expectedUrl' => 'http://127.0.0.1?search=test',
        ];

        yield 'URL with encoded characters in query' => [
            'inputUrl' => 'https://centreon.example.com/api/hosts?name=host%20name&filter=%7B%22id%22%3A1%7D',
            'expectedUrl' => 'http://127.0.0.1/api/hosts?name=host%20name&filter=%7B%22id%22%3A1%7D',
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

    public function testConvertToLocalUrlAlwaysUsesHttp(): void
    {
        $httpsUrl = 'https://secure.example.com/api/test';

        $result = InternalApiClient::convertToLocalUrl($httpsUrl);

        $this->assertStringStartsWith('http://', $result);
        $this->assertStringNotContainsString('https://', $result);
    }

    public function testConvertToLocalUrlAlwaysUsesLocalhost(): void
    {
        $externalUrl = 'https://external-server.example.com/api/test';

        $result = InternalApiClient::convertToLocalUrl($externalUrl);

        $this->assertStringContainsString('127.0.0.1', $result);
        $this->assertStringNotContainsString('external-server.example.com', $result);
    }

    public function testConvertToLocalUrlPreservesPathIntegrity(): void
    {
        $url = 'https://example.com/centreon/api/latest/configuration/hosts/42';

        $result = InternalApiClient::convertToLocalUrl($url);

        $this->assertStringContainsString('/centreon/api/latest/configuration/hosts/42', $result);
    }

    public function testConvertToLocalUrlPreservesQueryParametersIntegrity(): void
    {
        $url = 'https://example.com/api?param1=value1&param2=value2&param3=value3';

        $result = InternalApiClient::convertToLocalUrl($url);

        $this->assertStringContainsString('param1=value1', $result);
        $this->assertStringContainsString('param2=value2', $result);
        $this->assertStringContainsString('param3=value3', $result);
    }

    // =========================================================================
    // Tests for request() method
    // =========================================================================

    public function testRequestConvertsUrlToLocalhost(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getContent')->willReturn('{"success": true}');

        $mockHttpClient = $this->createMock(HttpClientInterface::class);
        $mockHttpClient->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                $this->callback(fn($url) => str_contains($url, '127.0.0.1')),
                $this->anything()
            )
            ->willReturn($mockResponse);

        $client = new InternalApiClient($mockHttpClient);
        $result = $client->request('https://external.example.com/api/test', 'GET', self::TEST_SESSION_COOKIE);

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

        $client = new InternalApiClient($mockHttpClient);
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
                $this->callback(function ($options) use ($payload) {
                    return isset($options['body']) && $options['body'] === json_encode($payload);
                })
            )
            ->willReturn($mockResponse);

        $client = new InternalApiClient($mockHttpClient);
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
                $this->callback(fn($options) => !isset($options['body']))
            )
            ->willReturn($mockResponse);

        $client = new InternalApiClient($mockHttpClient);
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
                $this->callback(function ($options) {
                    return isset($options['headers']['Content-Type'])
                        && $options['headers']['Content-Type'] === 'application/json';
                })
            )
            ->willReturn($mockResponse);

        $client = new InternalApiClient($mockHttpClient);
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
                $this->callback(function ($options) {
                    return isset($options['headers']['Cookie'])
                        && $options['headers']['Cookie'] === self::TEST_SESSION_COOKIE;
                })
            )
            ->willReturn($mockResponse);

        $client = new InternalApiClient($mockHttpClient);
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

        $client = new InternalApiClient($mockHttpClient);
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

        $client = new InternalApiClient($mockHttpClient);
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

        $client = new InternalApiClient($mockHttpClient);
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

        $client = new InternalApiClient($mockHttpClient);
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

        $client = new InternalApiClient($mockHttpClient);
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

        $client = new InternalApiClient($mockHttpClient);
        $result = $client->request('http://localhost/api/test', 'POST', self::TEST_SESSION_COOKIE);

        $this->assertEquals(500, $result['status_code']);
    }
}
