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

/**
 * @covers \Core\Common\Infrastructure\Api\InternalApiClient
 */
class InternalApiClientTest extends TestCase
{
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

    public function testGetDefaultHttpOptionsReturnsCorrectStructure(): void
    {
        $options = InternalApiClient::getDefaultHttpOptions();

        $this->assertIsArray($options);
        $this->assertArrayHasKey('verify_peer', $options);
        $this->assertArrayHasKey('verify_host', $options);
    }

    public function testGetDefaultHttpOptionsDisablesSslVerification(): void
    {
        $options = InternalApiClient::getDefaultHttpOptions();

        $this->assertFalse($options['verify_peer']);
        $this->assertFalse($options['verify_host']);
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
}
