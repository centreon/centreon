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

namespace Tests\App\AiAssistance\Infrastructure\ApiPlatform\State;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Link-local targets (cloud instance metadata) are refused when connecting,
 * whatever their spelling, while private networks stay reachable.
 */
final class LinkLocalTargetTest extends AiAssistanceApiTestCase
{
    private const ENDPOINT = '/api/latest/administration/ai-providers/test-connection';
    private const PROXY_URL = 'http://proxy.example.com:3128';

    /** @var \ArrayObject<int, array{url: string, proxy: mixed}> */
    private \ArrayObject $sentRequests;

    /**
     * Literal IPv6 spellings of 169.254.169.254 (or AWS metadata over IPv6)
     * that the endpoint URL validation lets through.
     *
     * @return iterable<string, array{string}>
     */
    public static function linkLocalTargets(): iterable
    {
        yield 'IPv4-compatible' => ['https://[::a9fe:a9fe]'];

        yield 'NAT64' => ['https://[64:ff9b::a9fe:a9fe]'];

        yield '6to4' => ['https://[2002:a9fe:a9fe::]'];

        yield 'AWS metadata over IPv6' => ['https://[fd00:ec2::254]'];

        yield 'Alibaba metadata, IPv4-mapped' => ['https://[::ffff:6464:64c8]'];

        yield 'Alibaba metadata, IPv4-compatible' => ['https://[::6464:64c8]'];

        yield 'Alibaba metadata, NAT64' => ['https://[64:ff9b::6464:64c8]'];

        yield 'Alibaba metadata, 6to4' => ['https://[2002:6464:64c8::]'];
    }

    #[DataProvider('linkLocalTargets')]
    public function testALinkLocalTargetIsNeverReached(string $endpointUrl): void
    {
        $this->useProvider();
        $this->login();

        $result = $this->requestConnectionTest($endpointUrl);

        self::assertFalse($result['success']);
        self::assertNotEmpty($result['message']);
        self::assertCount(0, $this->sentRequests);
    }

    #[DataProvider('linkLocalTargets')]
    public function testALinkLocalTargetIsNeverReachedThroughThePlatformProxy(string $endpointUrl): void
    {
        $this->useProvider();
        $this->configurePlatformProxy();
        $this->login();

        $result = $this->requestConnectionTest($endpointUrl);

        self::assertFalse($result['success']);
        self::assertCount(0, $this->sentRequests);
    }

    public function testAMetadataLiteralIsRefusedBeforeConnecting(): void
    {
        $this->useProvider();
        $this->login();

        $this->request('POST', self::ENDPOINT, [
            'json' => [
                'type' => 'openai_compatible',
                'default_model' => 'a-model',
                'api_token' => 'a-token',
                'endpoint_url' => 'http://[::ffff:169.254.169.254]',
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertCount(0, $this->sentRequests);
    }

    public function testAPrivateNetworkTargetIsReached(): void
    {
        $this->useProvider();
        $this->login();

        $result = $this->requestConnectionTest('http://10.0.0.5');

        self::assertTrue($result['success']);
        self::assertSame(
            [['url' => 'http://10.0.0.5/v1/chat/completions', 'proxy' => null]],
            $this->sentRequests->getArrayCopy(),
        );
    }

    public function testAHostThePlatformCanNotResolveIsReachedThroughThePlatformProxy(): void
    {
        $this->useProvider();
        $this->configurePlatformProxy();
        $this->login();

        // .invalid never resolves (RFC 6761): only the proxy can reach it
        $result = $this->requestConnectionTest('https://llm.invalid');

        self::assertTrue($result['success']);
        self::assertSame(
            [['url' => 'https://llm.invalid/v1/chat/completions', 'proxy' => self::PROXY_URL]],
            $this->sentRequests->getArrayCopy(),
        );
    }

    /**
     * Swapped in before the one request of the test: the kernel is rebooted between two requests.
     */
    private function useProvider(): void
    {
        /** @var \ArrayObject<int, array{url: string, proxy: mixed}> $sentRequests */
        $sentRequests = new \ArrayObject();
        $this->sentRequests = $sentRequests;

        $body = json_encode([
            'model' => 'a-model',
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'pong'], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
        ], \JSON_THROW_ON_ERROR);

        self::getContainer()->set('http_client', new MockHttpClient(
            static function (string $method, string $url, array $options) use ($sentRequests, $body): MockResponse {
                $sentRequests[] = ['url' => $url, 'proxy' => $options['proxy'] ?? null];

                return new MockResponse($body, ['response_headers' => ['content-type' => 'application/json']]);
            }
        ));
    }

    private function configurePlatformProxy(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $connection->executeStatement("DELETE FROM options WHERE `key` LIKE 'proxy%'");
        $connection->insert('options', ['`key`' => 'proxy_url', '`value`' => 'proxy.example.com']);
        $connection->insert('options', ['`key`' => 'proxy_port', '`value`' => '3128']);
    }

    /**
     * @return array<mixed>
     */
    private function requestConnectionTest(string $endpointUrl): array
    {
        $response = $this->request('POST', self::ENDPOINT, [
            'json' => [
                'type' => 'openai_compatible',
                'default_model' => 'a-model',
                'api_token' => 'a-token',
                'endpoint_url' => $endpointUrl,
            ],
        ]);

        self::assertResponseStatusCodeSame(200);

        return $response->toArray();
    }
}
