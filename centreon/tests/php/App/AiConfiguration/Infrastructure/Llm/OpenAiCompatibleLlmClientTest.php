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
        $body = json_decode($capturedOptions['body'], true, flags: JSON_THROW_ON_ERROR);
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

    public function testUsesTheConfiguredMaxTokensField(): void
    {
        $capturedBody = '';
        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$capturedBody): MockResponse {
                \assert(is_string($options['body']));
                $capturedBody = $options['body'];

                return new MockResponse(json_encode([
                    'choices' => [['message' => ['content' => 'ok']]],
                    'model' => 'gpt-5.4',
                ], \JSON_THROW_ON_ERROR));
            }
        );

        // OpenAI's recent models require max_completion_tokens
        $client = new OpenAiCompatibleLlmClient(
            $httpClient,
            new EndpointUrl('https://api.openai.com'),
            new ApiToken('a-token'),
            new ModelName('gpt-5.4'),
            fastModel: null,
            maxTokensField: 'max_completion_tokens',
        );

        $client->complete($this->createRequest());

        /** @var array{max_completion_tokens?: int, max_tokens?: int} $body */
        $body = json_decode($capturedBody, true, 512, \JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('max_completion_tokens', $body);
        self::assertArrayNotHasKey('max_tokens', $body);
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

    public function testANonJsonErrorBodyIsNotReported(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(
            '<html>internal admin page</html>',
            ['http_code' => 404]
        ));

        try {
            $this->createClient($httpClient)->complete($this->createRequest());
            self::fail('Expected the provider error to be reported');
        } catch (LlmRequestFailedException $exception) {
            self::assertStringContainsString('HTTP 404', $exception->getMessage());
            self::assertStringNotContainsString('internal admin page', $exception->getMessage());
        }
    }

    public function testIdleTimeoutIsReportedWithItsDuration(): void
    {
        // an empty chunk is how MockResponse simulates an idle timeout
        $httpClient = new MockHttpClient(new MockResponse(['']));

        $this->expectException(LlmRequestFailedException::class);
        $this->expectExceptionMessage('sent nothing for 30 seconds');

        $this->createClient($httpClient)->complete(LlmRequest::connectionTest(null));
    }

    public function testSendsTheTimeoutOfTheRequest(): void
    {
        $timeouts = [];
        $httpClient = new MockHttpClient(
            static function (string $method, string $url, array $options) use (&$timeouts): MockResponse {
                $timeouts[] = $options['timeout'];

                return new MockResponse(json_encode([
                    'model' => 'mistral-small-latest',
                    'choices' => [['message' => ['content' => 'pong']]],
                ], \JSON_THROW_ON_ERROR));
            }
        );

        $client = $this->createClient($httpClient);
        $client->complete(LlmRequest::connectionTest(null));
        $client->complete($this->createRequest());

        self::assertEquals([30, 120], $timeouts);
    }

    public function testFinishReasonLengthMarksTheResponseTruncated(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'model' => 'gemini-4-hydrogen',
            'choices' => [['finish_reason' => 'length', 'message' => ['role' => 'assistant']]],
            'usage' => ['prompt_tokens' => 37, 'completion_tokens' => 3],
        ], \JSON_THROW_ON_ERROR)));

        $response = $this->createClient($httpClient)->complete($this->createRequest());

        self::assertSame('', $response->content);
        self::assertTrue($response->truncated);
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

    public function testContentPartsAreConcatenated(): void
    {
        // e.g. Mistral reasoning models: the thinking part is not the answer
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'model' => 'magistral-small-latest',
            'choices' => [['finish_reason' => 'stop', 'message' => ['content' => [
                ['type' => 'thinking', 'thinking' => [['type' => 'text', 'text' => 'let me think']]],
                ['type' => 'text', 'text' => 'po'],
                ['type' => 'text', 'text' => 'ng'],
            ]]]],
        ], \JSON_THROW_ON_ERROR)));

        $response = $this->createClient($httpClient)->complete($this->createRequest());

        self::assertSame('pong', $response->content);
    }

    public function testARefusalIsReportedAsSuch(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'model' => 'gpt-5',
            'choices' => [['finish_reason' => 'stop', 'message' => ['content' => null, 'refusal' => 'I can not help with that.']]],
        ], \JSON_THROW_ON_ERROR)));

        $this->expectException(LlmRequestFailedException::class);
        $this->expectExceptionMessage('The AI provider refused to answer: I can not help with that.');

        $this->createClient($httpClient)->complete($this->createRequest());
    }

    public function testAContentFilterStopIsReportedAsARefusal(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'model' => 'gpt-5',
            'choices' => [['finish_reason' => 'content_filter', 'message' => ['content' => null]]],
        ], \JSON_THROW_ON_ERROR)));

        $this->expectException(LlmRequestFailedException::class);
        $this->expectExceptionMessage('The AI provider refused to answer');

        $this->createClient($httpClient)->complete($this->createRequest());
    }

    public function testStreamConcatenatesContentParts(): void
    {
        $sse = [
            "data: {\"choices\":[{\"delta\":{\"content\":[{\"type\":\"thinking\",\"thinking\":[]},{\"type\":\"text\",\"text\":\"po\"}]}}]}\n\n",
            "data: {\"choices\":[{\"delta\":{\"content\":[{\"type\":\"text\",\"text\":\"ng\"}]}}]}\n\n",
            "data: [DONE]\n\n",
        ];
        $httpClient = new MockHttpClient(new MockResponse($sse));

        $generator = $this->createClient($httpClient)->stream($this->createRequest());

        self::assertSame(['po', 'ng'], iterator_to_array($generator, false));
        self::assertSame('pong', $generator->getReturn()->content);
    }

    public function testAStreamedRefusalIsReportedAsSuch(): void
    {
        $sse = [
            "data: {\"choices\":[{\"delta\":{\"refusal\":\"I can not \"}}]}\n\n",
            "data: {\"choices\":[{\"delta\":{\"refusal\":\"help with that.\"},\"finish_reason\":\"stop\"}]}\n\n",
            "data: [DONE]\n\n",
        ];
        $httpClient = new MockHttpClient(new MockResponse($sse));

        $this->expectException(LlmRequestFailedException::class);
        $this->expectExceptionMessage('The AI provider refused to answer: I can not help with that.');

        iterator_to_array($this->createClient($httpClient)->stream($this->createRequest()), false);
    }

    public function testAStreamStoppedByTheContentFilterIsReportedAsARefusal(): void
    {
        $sse = [
            "data: {\"choices\":[{\"delta\":{\"content\":\"Here\"}}]}\n\n",
            "data: {\"choices\":[{\"delta\":{},\"finish_reason\":\"content_filter\"}]}\n\n",
            "data: [DONE]\n\n",
        ];
        $httpClient = new MockHttpClient(new MockResponse($sse));

        $this->expectException(LlmRequestFailedException::class);
        $this->expectExceptionMessage('The AI provider refused to answer');

        iterator_to_array($this->createClient($httpClient)->stream($this->createRequest()), false);
    }

    public function testTransportErrorIsReported(): void
    {
        $httpClient = new MockHttpClient(new MockResponse('', ['error' => 'Connection refused']));

        $this->expectException(LlmRequestFailedException::class);
        $this->expectExceptionMessage('can not be reached');

        $this->createClient($httpClient)->complete($this->createRequest());
    }

    public function testAMalformedSuccessfulBodyIsAnUnexpectedResponse(): void
    {
        $httpClient = new MockHttpClient(new MockResponse('{"choices": [', ['http_code' => 200]));

        $this->expectException(LlmRequestFailedException::class);
        $this->expectExceptionMessage('The AI provider returned an unexpected response');

        $this->createClient($httpClient)->complete($this->createRequest());
    }

    public function testASuccessfulBodyWithoutChoicesHasNoContent(): void
    {
        // the metered client turns it into an "empty answer" failure when an answer is required
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'model' => 'mistral-small-latest',
            'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 0],
        ], \JSON_THROW_ON_ERROR)));

        $response = $this->createClient($httpClient)->complete($this->createRequest());

        self::assertSame('', $response->content);
        self::assertSame(12, $response->usage->inputTokens);
    }

    public function testAStreamedErrorEventIsReported(): void
    {
        $sse = [
            "data: {\"choices\":[{\"delta\":{\"content\":\"po\"}}]}\n\n",
            "data: {\"error\":{\"message\":\"The server is overloaded\"}}\n\n",
        ];
        $httpClient = new MockHttpClient(new MockResponse($sse));

        $this->expectException(LlmRequestFailedException::class);
        $this->expectExceptionMessage('The AI provider returned an unexpected response: The server is overloaded');

        iterator_to_array($this->createClient($httpClient)->stream($this->createRequest()), false);
    }

    public function testAStreamStalledAfterTheFirstChunkIsReportedAsATimeout(): void
    {
        // an empty chunk is how MockResponse simulates an idle timeout
        $sse = [
            "data: {\"choices\":[{\"delta\":{\"content\":\"po\"}}]}\n\n",
            '',
        ];
        $httpClient = new MockHttpClient(new MockResponse($sse));

        $generator = $this->createClient($httpClient)->stream($this->createRequest());

        // the text received before the stall is handed over
        self::assertSame('po', $generator->current());

        $this->expectException(LlmRequestFailedException::class);
        $this->expectExceptionMessage('sent nothing for 120 seconds');

        $generator->next();
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
