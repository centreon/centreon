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
use App\AiConfiguration\Domain\Llm\LlmRequest;
use App\AiConfiguration\Domain\Llm\LlmRoleEnum;
use App\AiConfiguration\Infrastructure\Llm\OpenAiCompatibleLlmClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OpenAiCompatibleLlmClientTest extends TestCase
{
    public function testCompleteSendsTheExpectedPayloadAndParsesTheResponse(): void
    {
        $capturedOptions = null;
        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$capturedOptions): MockResponse {
                self::assertSame('POST', $method);
                self::assertSame('https://api.mistral.ai/v1/chat/completions', $url);
                $capturedOptions = $options;

                return new MockResponse(json_encode([
                    'choices' => [['message' => ['content' => 'pong', 'role' => 'assistant']]],
                    'model' => 'mistral-small-latest',
                    'usage' => ['completion_tokens' => 2, 'prompt_tokens' => 12],
                ], \JSON_THROW_ON_ERROR));
            }
        );

        $response = $this->createClient($httpClient)->complete($this->createRequest());

        self::assertSame('pong', $response->content);
        self::assertSame('mistral-small-latest', $response->model);
        self::assertSame(12, $response->usage->inputTokens);
        self::assertSame(2, $response->usage->outputTokens);

        /** @var array{normalized_headers: array<string, array<string>>, body: string} $capturedOptions */
        /** @var array{model: string, max_tokens: int, system?: string, messages: array<array<string, string>>} $body */
        $body = json_decode($capturedOptions['body'], true);
        self::assertSame('mistral-small-latest', $body['model']);
        self::assertSame(16, $body['max_tokens']);
        // the system prompt is prepended as a system message
        self::assertSame(
            [
                ['content' => 'a system prompt', 'role' => 'system'],
                ['content' => 'ping', 'role' => 'user'],
            ],
            $body['messages']
        );
        self::assertContains(
            'Authorization: Bearer a-token',
            $capturedOptions['normalized_headers']['authorization']
        );
    }

    public function testUsesTheConfiguredChatCompletionsPath(): void
    {
        $httpClient = new MockHttpClient(
            function (string $method, string $url): MockResponse {
                // Gemini exposes chat completions under its OpenAI compat layer
                self::assertSame(
                    'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
                    $url
                );

                return new MockResponse(json_encode([
                    'choices' => [['message' => ['content' => 'pong']]],
                    'model' => 'gemini-2.5-flash',
                ], \JSON_THROW_ON_ERROR));
            }
        );

        $client = new OpenAiCompatibleLlmClient(
            $httpClient,
            new EndpointUrl('https://generativelanguage.googleapis.com'),
            new ApiToken('a-token'),
            new ModelName('gemini-2.5-flash'),
            fastModel: null,
            chatCompletionsPath: '/v1beta/openai/chat/completions',
        );

        self::assertSame('pong', $client->complete($this->createRequest())->content);
    }

    public function testListModelsParsesAndSortsTheIds(): void
    {
        $httpClient = new MockHttpClient(
            function (string $method, string $url): MockResponse {
                self::assertSame('GET', $method);
                self::assertSame('https://api.mistral.ai/v1/models', $url);

                return new MockResponse(json_encode([
                    'data' => [
                        ['id' => 'mistral-small-latest'],
                        ['id' => 'mistral-medium-latest'],
                        ['id' => ''],
                        // dropped by the capability bit when provided
                        ['id' => 'mistral-embed', 'capabilities' => ['completion_chat' => false]],
                        // dropped by the id heuristic when no capability is given
                        ['id' => 'text-embedding-3-large'],
                        ['id' => 'whisper-1'],
                        ['id' => 'sora-2'],
                        ['id' => 'gpt-realtime'],
                        ['id' => 'davinci-002'],
                        ['id' => 'babbage-002'],
                        ['id' => 'computer-use-preview'],
                        // genuine chat models that must NOT be over-filtered
                        ['id' => 'gpt-5-chat-latest'],
                        ['id' => 'chatgpt-4o-latest'],
                    ],
                ], \JSON_THROW_ON_ERROR));
            }
        );

        // non-chat models are filtered out, the rest sorted and de-duplicated
        self::assertSame(
            [
                'chatgpt-4o-latest',
                'gpt-5-chat-latest',
                'mistral-medium-latest',
                'mistral-small-latest',
            ],
            $this->createClient($httpClient)->listModels()
        );
    }

    public function testProviderErrorIsReported(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(
            json_encode(['error' => ['message' => 'Incorrect API key provided']], \JSON_THROW_ON_ERROR),
            ['http_code' => 401]
        ));

        $this->expectException(LlmRequestFailedException::class);
        $this->expectExceptionMessage('Incorrect API key provided');

        $this->createClient($httpClient)->complete($this->createRequest());
    }

    public function testStreamYieldsTextDeltasUntilDone(): void
    {
        $sse = [
            "data: {\"model\":\"mistral-small-latest\",\"choices\":[{\"delta\":{\"content\":\"po\"}}]}\n\n",
            "data: {\"choices\":[{\"delta\":{\"content\":\"ng\"}}]}\n\n",
            "data: {\"choices\":[],\"usage\":{\"prompt_tokens\":12,\"completion_tokens\":2}}\n\n",
            "data: [DONE]\n\n",
        ];
        $httpClient = new MockHttpClient(new MockResponse($sse));

        $generator = $this->createClient($httpClient)->stream($this->createRequest());
        $deltas = iterator_to_array($generator, false);
        $response = $generator->getReturn();

        self::assertSame(['po', 'ng'], $deltas);
        self::assertSame('pong', $response->content);
        self::assertSame('mistral-small-latest', $response->model);
        self::assertSame(12, $response->usage->inputTokens);
        self::assertSame(2, $response->usage->outputTokens);
    }

    private function createClient(HttpClientInterface $httpClient): OpenAiCompatibleLlmClient
    {
        return new OpenAiCompatibleLlmClient(
            $httpClient,
            new EndpointUrl('https://api.mistral.ai'),
            new ApiToken('a-token'),
            new ModelName('mistral-small-latest'),
            fastModel: null,
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
