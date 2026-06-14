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

namespace Tests\App\AiConfiguration\Infrastructure\Llm;

use App\AiConfiguration\Domain\Aggregate\AiProvider\ApiToken;
use App\AiConfiguration\Domain\Aggregate\AiProvider\EndpointUrl;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ModelName;
use App\AiConfiguration\Domain\Llm\AiFeatureEnum;
use App\AiConfiguration\Domain\Llm\Exception\LlmRequestFailedException;
use App\AiConfiguration\Domain\Llm\LlmMessage;
use App\AiConfiguration\Domain\Llm\LlmModelTierEnum;
use App\AiConfiguration\Domain\Llm\LlmRequest;
use App\AiConfiguration\Domain\Llm\LlmRoleEnum;
use App\AiConfiguration\Infrastructure\Llm\AnthropicLlmClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AnthropicLlmClientTest extends TestCase
{
    public function testCompleteSendsTheExpectedPayloadAndParsesTheResponse(): void
    {
        $capturedOptions = null;
        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$capturedOptions): MockResponse {
                self::assertSame('POST', $method);
                self::assertSame('https://api.anthropic.com/v1/messages', $url);
                $capturedOptions = $options;

                return new MockResponse(json_encode([
                    'content' => [['type' => 'text', 'text' => 'pong']],
                    'model' => 'claude-sonnet-4-6',
                    'usage' => ['input_tokens' => 12, 'output_tokens' => 2],
                ], \JSON_THROW_ON_ERROR));
            }
        );

        $response = $this->createClient($httpClient)->complete($this->createRequest());

        self::assertSame('pong', $response->content);
        self::assertSame('claude-sonnet-4-6', $response->model);
        self::assertSame(12, $response->usage->inputTokens);
        self::assertSame(2, $response->usage->outputTokens);

        /** @var array{normalized_headers: array<string, array<string>>, body: string} $capturedOptions */
        /** @var array{model: string, max_tokens: int, system?: string, messages: array<array<string, string>>} $body */
        $body = json_decode($capturedOptions['body'], true);
        self::assertSame('claude-sonnet-4-6', $body['model']);
        self::assertSame(16, $body['max_tokens']);
        self::assertSame('a system prompt', $body['system'] ?? null);
        self::assertSame([['content' => 'ping', 'role' => 'user']], $body['messages']);
        self::assertContains('x-api-key: a-token', $capturedOptions['normalized_headers']['x-api-key']);
    }

    public function testFastTierUsesTheFastModel(): void
    {
        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options): MockResponse {
                /** @var array{body: string} $options */
                /** @var array{model: string} $body */
                $body = json_decode($options['body'], true);
                self::assertSame('claude-haiku-4-5', $body['model']);

                return new MockResponse('{"content":[],"model":"claude-haiku-4-5","usage":{}}');
            }
        );

        $request = new LlmRequest(
            messages: [new LlmMessage(LlmRoleEnum::User, 'ping')],
            feature: AiFeatureEnum::ConnectionTest,
            tier: LlmModelTierEnum::Fast,
        );

        self::assertSame('claude-haiku-4-5', $this->createClient($httpClient)->complete($request)->model);
    }

    public function testFastTierFallsBackToTheDefaultModel(): void
    {
        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options): MockResponse {
                /** @var array{body: string} $options */
                /** @var array{model: string} $body */
                $body = json_decode($options['body'], true);
                self::assertSame('claude-sonnet-4-6', $body['model']);

                return new MockResponse('{"content":[],"model":"claude-sonnet-4-6","usage":{}}');
            }
        );

        $client = new AnthropicLlmClient(
            $httpClient,
            new EndpointUrl('https://api.anthropic.com'),
            new ApiToken('a-token'),
            new ModelName('claude-sonnet-4-6'),
            fastModel: null,
        );

        $request = new LlmRequest(
            messages: [new LlmMessage(LlmRoleEnum::User, 'ping')],
            feature: AiFeatureEnum::ConnectionTest,
            tier: LlmModelTierEnum::Fast,
        );

        $client->complete($request);
    }

    public function testListModelsParsesAndSortsTheIds(): void
    {
        $httpClient = new MockHttpClient(
            function (string $method, string $url): MockResponse {
                self::assertSame('GET', $method);
                self::assertSame('https://api.anthropic.com/v1/models', $url);

                return new MockResponse(json_encode([
                    'data' => [
                        ['id' => 'claude-sonnet-4-6'],
                        ['id' => 'claude-haiku-4-5'],
                    ],
                ], \JSON_THROW_ON_ERROR));
            }
        );

        self::assertSame(
            ['claude-haiku-4-5', 'claude-sonnet-4-6'],
            $this->createClient($httpClient)->listModels()
        );
    }

    public function testProviderErrorIsReported(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(
            json_encode(['error' => ['message' => 'invalid x-api-key']], \JSON_THROW_ON_ERROR),
            ['http_code' => 401]
        ));

        $this->expectException(LlmRequestFailedException::class);
        $this->expectExceptionMessage('invalid x-api-key');

        $this->createClient($httpClient)->complete($this->createRequest());
    }

    public function testTransportErrorIsReported(): void
    {
        $httpClient = new MockHttpClient(new MockResponse('', ['error' => 'Connection refused']));

        $this->expectException(LlmRequestFailedException::class);
        $this->expectExceptionMessage('can not be reached');

        $this->createClient($httpClient)->complete($this->createRequest());
    }

    public function testStreamYieldsTextDeltasAndReturnsTheFullResponse(): void
    {
        $sse = [
            "event: message_start\ndata: {\"type\":\"message_start\",\"message\":{\"model\":\"claude-sonnet-4-6\",\"usage\":{\"input_tokens\":12}}}\n\n",
            "event: content_block_delta\ndata: {\"type\":\"content_block_delta\",\"delta\":{\"type\":\"text_delta\",\"text\":\"po\"}}\n\n",
            "event: content_block_delta\ndata: {\"type\":\"content_block_delta\",\"delta\":{\"type\":\"text_delta\",\"text\":\"ng\"}}\n\n",
            "event: message_delta\ndata: {\"type\":\"message_delta\",\"usage\":{\"output_tokens\":2}}\n\n",
            "event: message_stop\ndata: {\"type\":\"message_stop\"}\n\n",
        ];
        $httpClient = new MockHttpClient(new MockResponse($sse));

        $generator = $this->createClient($httpClient)->stream($this->createRequest());
        $deltas = iterator_to_array($generator, false);
        $response = $generator->getReturn();

        self::assertSame(['po', 'ng'], $deltas);
        self::assertSame('pong', $response->content);
        self::assertSame('claude-sonnet-4-6', $response->model);
        self::assertSame(12, $response->usage->inputTokens);
        self::assertSame(2, $response->usage->outputTokens);
    }

    private function createClient(HttpClientInterface $httpClient): AnthropicLlmClient
    {
        return new AnthropicLlmClient(
            $httpClient,
            new EndpointUrl('https://api.anthropic.com'),
            new ApiToken('a-token'),
            new ModelName('claude-sonnet-4-6'),
            new ModelName('claude-haiku-4-5'),
        );
    }

    private function createRequest(): LlmRequest
    {
        return new LlmRequest(
            messages: [new LlmMessage(LlmRoleEnum::User, 'ping')],
            feature: AiFeatureEnum::ConnectionTest,
            systemPrompt: 'a system prompt',
            maxTokens: 16,
        );
    }
}
