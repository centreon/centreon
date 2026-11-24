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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Tests\App\Shared\Infrastructure\Legacy\Double\RecordingLogger;

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
        $body = json_decode($capturedOptions['body'], true, 512, \JSON_THROW_ON_ERROR);
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
                $body = json_decode($options['body'], true, 512, \JSON_THROW_ON_ERROR);
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
                $body = json_decode($options['body'], true, 512, \JSON_THROW_ON_ERROR);
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
                self::assertSame('https://api.anthropic.com/v1/models?limit=1000', $url);

                return new MockResponse(json_encode([
                    'data' => [
                        ['id' => 'claude-sonnet-4-6'],
                        ['id' => 'claude-haiku-4-5'],
                        ['id' => 42],
                    ],
                    'has_more' => false,
                ], \JSON_THROW_ON_ERROR));
            }
        );

        self::assertSame(
            ['claude-haiku-4-5', 'claude-sonnet-4-6'],
            $this->createClient($httpClient)->listModels()
        );
    }

    public function testListModelsFollowsThePages(): void
    {
        $urls = [];
        $httpClient = new MockHttpClient(
            function (string $method, string $url) use (&$urls): MockResponse {
                $urls[] = $url;

                return new MockResponse(json_encode(
                    count($urls) === 1
                        ? ['data' => [['id' => 'claude-sonnet-4-6']], 'has_more' => true, 'last_id' => 'claude-sonnet-4-6']
                        : ['data' => [['id' => 'claude-3-haiku']], 'has_more' => false, 'last_id' => 'claude-3-haiku'],
                    \JSON_THROW_ON_ERROR
                ));
            }
        );

        self::assertSame(['claude-3-haiku', 'claude-sonnet-4-6'], $this->createClient($httpClient)->listModels());
        self::assertSame(
            [
                'https://api.anthropic.com/v1/models?limit=1000',
                'https://api.anthropic.com/v1/models?limit=1000&after_id=claude-sonnet-4-6',
            ],
            $urls
        );
    }

    public function testListModelsStopsAfterABoundedNumberOfPages(): void
    {
        // a provider that always claims more pages is not followed endlessly
        $httpClient = new MockHttpClient(
            static fn (): MockResponse => new MockResponse(
                '{"data":[{"id":"claude-sonnet-4-6"}],"has_more":true,"last_id":"claude-sonnet-4-6"}'
            )
        );

        self::assertSame(['claude-sonnet-4-6'], $this->createClient($httpClient)->listModels());
        self::assertSame(10, $httpClient->getRequestsCount());
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
        $this->expectExceptionMessage('sent nothing for 120 seconds');

        $this->createClient($httpClient)->complete($this->createRequest());
    }

    public function testMaxTokensStopReasonMarksTheResponseTruncated(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'content' => [['type' => 'thinking', 'thinking' => '…']],
            'model' => 'claude-sonnet-4-6',
            'stop_reason' => 'max_tokens',
            'usage' => ['input_tokens' => 12, 'output_tokens' => 16],
        ], \JSON_THROW_ON_ERROR)));

        $response = $this->createClient($httpClient)->complete($this->createRequest());

        self::assertSame('', $response->content);
        self::assertTrue($response->truncated);
    }

    public function testAMalformedSuccessfulBodyIsAnUnexpectedResponse(): void
    {
        $httpClient = new MockHttpClient(new MockResponse('<html>not json</html>', ['http_code' => 200]));

        $this->expectException(LlmRequestFailedException::class);
        $this->expectExceptionMessage('The AI provider returned an unexpected response');

        $this->createClient($httpClient)->complete($this->createRequest());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideSuccessfulBodiesWithoutContent(): iterable
    {
        yield 'empty document' => ['{}', 'no content in the response.'];

        yield 'no content' => ['{"model":"claude-sonnet-4-6","usage":{"input_tokens":12}}', 'no content in the response.'];

        yield 'content of the wrong type' => ['{"content":"pong"}', 'no content in the response.'];

        yield 'an error document' => ['{"type":"error","error":{"type":"overloaded_error","message":"Overloaded"}}', 'Overloaded'];
    }

    #[DataProvider('provideSuccessfulBodiesWithoutContent')]
    public function testASuccessfulBodyWithoutContentIsAnUnexpectedResponse(string $body, string $details): void
    {
        $httpClient = new MockHttpClient(new MockResponse($body));

        $this->expectException(LlmRequestFailedException::class);
        $this->expectExceptionMessage('The AI provider returned an unexpected response: ' . $details);

        $this->createClient($httpClient)->complete($this->createRequest());
    }

    public function testFieldsOfTheWrongTypeAreIgnored(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'content' => [['type' => 'text', 'text' => 'pong'], ['type' => 'text', 'text' => 42], 'noise'],
            'model' => ['claude'],
            'stop_reason' => 7,
            'usage' => ['input_tokens' => '12', 'output_tokens' => 2.5],
        ], \JSON_THROW_ON_ERROR)));

        $response = $this->createClient($httpClient)->complete($this->createRequest());

        self::assertSame('pong', $response->content);
        self::assertSame('', $response->model);
        self::assertSame(0, $response->usage->inputTokens);
        self::assertSame(0, $response->usage->outputTokens);
        self::assertFalse($response->truncated);
        self::assertNull($response->refusal);
    }

    public function testARefusalIsCarriedByTheResponse(): void
    {
        // the metered client fails on it, after recording the tokens it was billed
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'content' => [['type' => 'text', 'text' => 'I can']],
            'model' => 'claude-sonnet-4-6',
            'stop_reason' => 'refusal',
            'usage' => ['input_tokens' => 12, 'output_tokens' => 3],
        ], \JSON_THROW_ON_ERROR)));

        $response = $this->createClient($httpClient)->complete($this->createRequest());

        self::assertSame('the model declined to answer this request.', $response->refusal);
        self::assertSame(3, $response->usage->outputTokens);
    }

    public function testAStreamedRefusalIsCarriedByTheResponse(): void
    {
        $sse = [
            "event: message_start\ndata: {\"type\":\"message_start\",\"message\":{\"model\":\"claude-sonnet-4-6\",\"usage\":{\"input_tokens\":12}}}\n\n",
            "event: content_block_delta\ndata: {\"type\":\"content_block_delta\",\"delta\":{\"type\":\"text_delta\",\"text\":\"I can\"}}\n\n",
            "event: message_delta\ndata: {\"type\":\"message_delta\",\"delta\":{\"stop_reason\":\"refusal\"},\"usage\":{\"output_tokens\":3}}\n\n",
        ];
        $httpClient = new MockHttpClient(new MockResponse($sse));

        $generator = $this->createClient($httpClient)->stream($this->createRequest());
        iterator_to_array($generator, false);
        $response = $generator->getReturn();

        self::assertSame('the model declined to answer this request.', $response->refusal);
        self::assertSame(3, $response->usage->outputTokens);
    }

    public function testAnErrorMessageOfTheWrongTypeIsNotReported(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(
            json_encode(['error' => ['message' => ['nested' => 'value']]], \JSON_THROW_ON_ERROR),
            ['http_code' => 400]
        ));

        $this->expectException(LlmRequestFailedException::class);
        $this->expectExceptionMessage('(HTTP 400): no error message in the response');

        $this->createClient($httpClient)->complete($this->createRequest());
    }

    public function testAnErrorBodyIsLoggedServerSide(): void
    {
        $logger = new RecordingLogger();
        $httpClient = new MockHttpClient(new MockResponse('<html>gateway timeout</html>', ['http_code' => 504]));

        try {
            $this->createClient($httpClient, $logger)->complete($this->createRequest());
            self::fail('Expected the provider error to be reported');
        } catch (LlmRequestFailedException $exception) {
            self::assertStringNotContainsString('gateway timeout', $exception->getMessage());
        }

        $record = $logger->lastRecord();
        self::assertNotNull($record);
        self::assertSame('warning', $record['level']);
        self::assertSame(504, $record['context']['status_code']);
        self::assertArrayNotHasKey('body_excerpt', $record['context']);
        self::assertGreaterThan(0, $record['context']['body_length']);
    }

    public function testAStreamedErrorEventWithoutAStringMessageIsReported(): void
    {
        $sse = ["event: error\ndata: {\"type\":\"error\",\"error\":{\"message\":[\"Overloaded\"]}}\n\n"];
        $httpClient = new MockHttpClient(new MockResponse($sse));

        $this->expectException(LlmRequestFailedException::class);
        $this->expectExceptionMessage('The AI provider returned an unexpected response: unknown streaming error');

        iterator_to_array($this->createClient($httpClient)->stream($this->createRequest()), false);
    }

    public function testRedirectsAreNeverFollowed(): void
    {
        $maxRedirects = [];
        $httpClient = new MockHttpClient(
            static function (string $method, string $url, array $options) use (&$maxRedirects): MockResponse {
                $maxRedirects[] = $options['max_redirects'];

                return new MockResponse($method === 'GET' ? '{"data":[]}' : '{"content":[{"type":"text","text":"pong"}]}');
            }
        );
        $client = $this->createClient($httpClient);

        $client->complete($this->createRequest());
        $client->listModels();

        self::assertSame([0, 0], $maxRedirects);
    }

    public function testAStreamedErrorEventIsReported(): void
    {
        $sse = [
            "event: content_block_delta\ndata: {\"type\":\"content_block_delta\",\"delta\":{\"type\":\"text_delta\",\"text\":\"po\"}}\n\n",
            "event: error\ndata: {\"type\":\"error\",\"error\":{\"type\":\"overloaded_error\",\"message\":\"Overloaded\"}}\n\n",
        ];
        $httpClient = new MockHttpClient(new MockResponse($sse));

        $this->expectException(LlmRequestFailedException::class);
        $this->expectExceptionMessage('The AI provider returned an unexpected response: Overloaded');

        iterator_to_array($this->createClient($httpClient)->stream($this->createRequest()), false);
    }

    public function testAStreamStalledAfterTheFirstChunkIsReportedAsATimeout(): void
    {
        // an empty chunk is how MockResponse simulates an idle timeout
        $sse = [
            "event: content_block_delta\ndata: {\"type\":\"content_block_delta\",\"delta\":{\"type\":\"text_delta\",\"text\":\"po\"}}\n\n",
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

    private function createClient(HttpClientInterface $httpClient, ?LoggerInterface $logger = null): AnthropicLlmClient
    {
        return new AnthropicLlmClient(
            $httpClient,
            new EndpointUrl('https://api.anthropic.com'),
            new ApiToken('a-token'),
            new ModelName('claude-sonnet-4-6'),
            new ModelName('claude-haiku-4-5'),
            logger: $logger ?? new NullLogger(),
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
