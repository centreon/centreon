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
use App\AiConfiguration\Domain\Aggregate\AiProvider\ReasoningModeEnum;
use App\AiConfiguration\Domain\Llm\AiFeatureEnum;
use App\AiConfiguration\Domain\Llm\Exception\LlmRequestFailedException;
use App\AiConfiguration\Domain\Llm\LlmMessage;
use App\AiConfiguration\Domain\Llm\LlmModelTierEnum;
use App\AiConfiguration\Domain\Llm\LlmRequest;
use App\AiConfiguration\Domain\Llm\LlmRoleEnum;
use App\AiConfiguration\Infrastructure\Llm\AnthropicLlmClient;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Tests\App\Shared\Infrastructure\Legacy\Double\RecordingLogger;

final class AnthropicLlmClientReasoningTest extends TestCase
{
    /** @var list<array<string, mixed>> */
    private array $sentBodies = [];

    public function testAMinimalModelIsFirstAskedNotToThink(): void
    {
        $response = $this->createClient([$this->answer('pong')])->complete($this->createRequest(maxTokens: 2048));

        self::assertSame('pong', $response->content);
        self::assertSame('thinking: disabled', $response->reasoning);
        self::assertSame(['type' => 'disabled'], $this->sentBodies[0]['thinking'] ?? null);
        self::assertSame(2048, $this->sentBodies[0]['max_tokens'] ?? null);
    }

    public function testARejectedThinkingSettingFallsBackToTheNextOne(): void
    {
        $response = $this->createClient([
            $this->rejection("thinking.type: Input should be 'enabled' or 'adaptive'"),
            $this->rejection('thinking.type: between_tools is not supported for this model'),
            $this->answer('pong'),
        ])->complete($this->createRequest(maxTokens: 256));

        self::assertSame('effort: low', $response->reasoning);
        self::assertSame(['type' => 'disabled'], $this->sentBodies[0]['thinking'] ?? null);
        self::assertSame(['type' => 'between_tools'], $this->sentBodies[1]['thinking'] ?? null);
        self::assertArrayNotHasKey('thinking', $this->sentBodies[2]);
        self::assertSame(['effort' => 'low'], $this->sentBodies[2]['output_config'] ?? null);
        self::assertSame(256 + 1024, $this->sentBodies[2]['max_tokens'] ?? null);
    }

    public function testAModelAcceptingNoThinkingSettingIsSentNone(): void
    {
        $logger = new RecordingLogger();
        $response = $this->createClient([
            $this->rejection('thinking: Extra inputs are not permitted'),
            $this->rejection('thinking: Extra inputs are not permitted'),
            // a server that does not know the field names only it
            $this->rejection('output_config: Extra inputs are not permitted'),
            $this->answer('pong'),
        ], logger: $logger)->complete($this->createRequest(maxTokens: 512));

        self::assertNull($response->reasoning);
        self::assertArrayNotHasKey('thinking', $this->sentBodies[3]);
        self::assertArrayNotHasKey('output_config', $this->sentBodies[3]);
        self::assertSame(512, $this->sentBodies[3]['max_tokens'] ?? null);
        self::assertSame('warning', $logger->lastRecord()['level'] ?? null);
    }

    public function testWithoutAFastModelTheFastTierFollowsTheDefaultModel(): void
    {
        $this->createClient(
            [$this->answer('pong')],
            defaultModelReasoning: ReasoningModeEnum::Default,
            fastModelReasoning: ReasoningModeEnum::Minimal,
            fastModel: null,
        )->complete($this->createRequest(tier: LlmModelTierEnum::Fast));

        self::assertSame('claude-sonnet-4-6', $this->sentBodies[0]['model'] ?? null);
        self::assertArrayNotHasKey('thinking', $this->sentBodies[0]);
        self::assertArrayNotHasKey('output_config', $this->sentBodies[0]);
    }

    public function testAFastModelEqualToTheDefaultOneFollowsTheTier(): void
    {
        $client = $this->createClient(
            [$this->answer('pong'), $this->answer('pong')],
            defaultModelReasoning: ReasoningModeEnum::Default,
            fastModelReasoning: ReasoningModeEnum::Minimal,
            fastModel: new ModelName('claude-sonnet-4-6'),
        );

        $client->complete($this->createRequest());
        $client->complete($this->createRequest(tier: LlmModelTierEnum::Fast));

        self::assertArrayNotHasKey('thinking', $this->sentBodies[0]);
        self::assertSame(['type' => 'disabled'], $this->sentBodies[1]['thinking'] ?? null);
    }

    public function testAnErrorNotAboutThinkingIsNotRetried(): void
    {
        $client = $this->createClient([$this->rejection('max_tokens: must be greater than 0'), $this->answer('pong')]);

        $this->expectException(LlmRequestFailedException::class);

        try {
            $client->complete($this->createRequest());
        } finally {
            self::assertCount(1, $this->sentBodies);
        }
    }

    public function testTheAcceptedSettingIsRemembered(): void
    {
        $cache = new ArrayAdapter();
        $this->createClient([$this->rejection('thinking.type: disabled is not supported'), $this->answer('pong')], cache: $cache)
            ->complete($this->createRequest());
        $this->forgetSentBodies();

        $this->createClient([$this->answer('pong')], cache: $cache)->complete($this->createRequest());

        self::assertCount(1, $this->sentBodies);
        self::assertSame(['type' => 'between_tools'], $this->sentBodies[0]['thinking'] ?? null);
    }

    public function testADefaultModelIsSentNoSetting(): void
    {
        $response = $this->createClient([$this->answer('pong')], defaultModelReasoning: ReasoningModeEnum::Default)
            ->complete($this->createRequest(maxTokens: 2048));

        self::assertNull($response->reasoning);
        self::assertArrayNotHasKey('thinking', $this->sentBodies[0]);
        self::assertArrayNotHasKey('output_config', $this->sentBodies[0]);
        self::assertSame(2048 + 2048, $this->sentBodies[0]['max_tokens'] ?? null);
    }

    public function testTheFastTierGetsTheSettingOfTheFastModel(): void
    {
        $this->createClient([$this->answer('pong')], fastModelReasoning: ReasoningModeEnum::Default)
            ->complete($this->createRequest(tier: LlmModelTierEnum::Fast));

        self::assertSame('claude-haiku-4-5', $this->sentBodies[0]['model'] ?? null);
        self::assertArrayNotHasKey('thinking', $this->sentBodies[0]);
    }

    public function testThinkingBlocksAreNotPartOfTheAnswer(): void
    {
        $response = $this->createClient([new MockResponse(json_encode([
            'content' => [
                ['type' => 'thinking', 'thinking' => 'The user wants pong.', 'signature' => 'abc'],
                ['type' => 'redacted_thinking', 'data' => 'xyz'],
                ['type' => 'text', 'text' => 'pong'],
            ],
            'model' => 'claude-sonnet-4-6',
            'usage' => ['input_tokens' => 12, 'output_tokens' => 40],
        ], \JSON_THROW_ON_ERROR))])->complete($this->createRequest());

        self::assertSame('pong', $response->content);
    }

    public function testAnAnswerWithAClosingTagButNoOpeningOneIsKeptWhole(): void
    {
        $answer = "Close the reasoning block with:\n</think>\nthen write the answer.";
        $response = $this->createClient([new MockResponse(json_encode([
            'content' => [['type' => 'text', 'text' => $answer]],
            'model' => 'claude-sonnet-4-6',
        ], \JSON_THROW_ON_ERROR))])->complete($this->createRequest());

        self::assertSame($answer, $response->content);
    }

    public function testStreamedThinkingIsNotPartOfTheAnswer(): void
    {
        $generator = $this->createClient([new MockResponse([
            "event: content_block_delta\ndata: {\"type\":\"content_block_delta\",\"delta\":{\"type\":\"thinking_delta\",\"thinking\":\"The user wants\"}}\n\n",
            "event: content_block_delta\ndata: {\"type\":\"content_block_delta\",\"delta\":{\"type\":\"signature_delta\",\"signature\":\"abc\"}}\n\n",
            "event: content_block_delta\ndata: {\"type\":\"content_block_delta\",\"delta\":{\"type\":\"text_delta\",\"text\":\"<think>hmm</think>po\"}}\n\n",
            "event: content_block_delta\ndata: {\"type\":\"content_block_delta\",\"delta\":{\"type\":\"text_delta\",\"text\":\"ng\"}}\n\n",
            "event: message_stop\ndata: {\"type\":\"message_stop\"}\n\n",
        ])])->stream($this->createRequest());
        $deltas = iterator_to_array($generator, false);

        self::assertSame(['po', 'ng'], $deltas);
        self::assertSame('pong', $generator->getReturn()->content);
        self::assertSame('thinking: disabled', $generator->getReturn()->reasoning);
    }

    public function testAStreamFallsBackBeforeAnyChunk(): void
    {
        $generator = $this->createClient([
            $this->rejection('thinking.type: disabled is not supported'),
            new MockResponse([
                "event: content_block_delta\ndata: {\"type\":\"content_block_delta\",\"delta\":{\"type\":\"text_delta\",\"text\":\"pong\"}}\n\n",
            ]),
        ])->stream($this->createRequest());

        self::assertSame(['pong'], iterator_to_array($generator, false));
        self::assertSame('thinking: between_tools', $generator->getReturn()->reasoning);
    }

    public function testARequestNamingAModelGetsTheSettingOfTheTierItServes(): void
    {
        $client = $this->createClient(
            [$this->answer('pong'), $this->answer('pong'), $this->answer('pong')],
            defaultModelReasoning: ReasoningModeEnum::Default,
            fastModelReasoning: ReasoningModeEnum::Minimal,
        );

        $client->complete($this->createRequest(model: new ModelName('claude-haiku-4-5')));
        $client->complete($this->createRequest(tier: LlmModelTierEnum::Fast, model: new ModelName('claude-sonnet-4-6')));
        $client->complete($this->createRequest(tier: LlmModelTierEnum::Fast, model: new ModelName('claude-opus-4-6')));

        self::assertSame(['type' => 'disabled'], $this->sentBodies[0]['thinking'] ?? null);
        self::assertArrayNotHasKey('thinking', $this->sentBodies[1]);
        self::assertSame('claude-opus-4-6', $this->sentBodies[2]['model'] ?? null);
        self::assertArrayNotHasKey('thinking', $this->sentBodies[2]);
    }

    public function testTheResponseReportsTheTokenLimitSentAndTheReasoningMode(): void
    {
        $response = $this->createClient([$this->answer('pong')])->complete($this->createRequest(maxTokens: 256));

        self::assertSame(256, $response->maxTokens);
        self::assertSame(ReasoningModeEnum::Minimal, $response->reasoningMode);
    }

    private function forgetSentBodies(): void
    {
        $this->sentBodies = [];
    }

    /**
     * @param list<MockResponse> $responses
     */
    private function createClient(
        array $responses,
        ReasoningModeEnum $defaultModelReasoning = ReasoningModeEnum::Minimal,
        ReasoningModeEnum $fastModelReasoning = ReasoningModeEnum::Minimal,
        ?CacheItemPoolInterface $cache = null,
        ?LoggerInterface $logger = null,
        ?ModelName $fastModel = new ModelName('claude-haiku-4-5'),
    ): AnthropicLlmClient {
        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$responses): MockResponse {
                \assert(is_string($options['body']));
                $body = json_decode($options['body'], true, 512, \JSON_THROW_ON_ERROR);
                \assert(is_array($body));
                /** @var array<string, mixed> $body */
                $this->sentBodies[] = $body;

                $response = array_shift($responses);
                \assert($response instanceof MockResponse);

                return $response;
            }
        );

        return new AnthropicLlmClient(
            $httpClient,
            new EndpointUrl('https://api.anthropic.com'),
            new ApiToken('a-token'),
            new ModelName('claude-sonnet-4-6'),
            $fastModel,
            logger: $logger ?? new NullLogger(),
            defaultModelReasoning: $defaultModelReasoning,
            fastModelReasoning: $fastModelReasoning,
            cache: $cache ?? new ArrayAdapter(),
        );
    }

    private function createRequest(
        int $maxTokens = 512,
        LlmModelTierEnum $tier = LlmModelTierEnum::Default,
        ?ModelName $model = null,
    ): LlmRequest {
        return new LlmRequest(
            messages: [new LlmMessage(LlmRoleEnum::User, 'ping')],
            feature: AiFeatureEnum::ResourceExplanation,
            model: $model,
            tier: $tier,
            maxTokens: $maxTokens,
        );
    }

    private function answer(string $text): MockResponse
    {
        return new MockResponse(json_encode([
            'content' => [['type' => 'text', 'text' => $text]],
            'model' => 'claude-sonnet-4-6',
            'usage' => ['input_tokens' => 12, 'output_tokens' => 2],
        ], \JSON_THROW_ON_ERROR));
    }

    private function rejection(string $message): MockResponse
    {
        return new MockResponse(
            json_encode(['type' => 'error', 'error' => ['type' => 'invalid_request_error', 'message' => $message]], \JSON_THROW_ON_ERROR),
            ['http_code' => 400]
        );
    }
}
