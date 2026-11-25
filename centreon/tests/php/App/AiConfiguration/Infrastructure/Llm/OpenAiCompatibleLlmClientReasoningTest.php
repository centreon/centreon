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
use App\AiConfiguration\Infrastructure\Llm\OpenAiCompatibleLlmClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\Cache\CacheInterface;
use Tests\App\Shared\Infrastructure\Legacy\Double\RecordingLogger;

final class OpenAiCompatibleLlmClientReasoningTest extends TestCase
{
    /** @var list<array<string, mixed>> */
    private array $sentBodies = [];

    public function testAMinimalModelIsFirstAskedNotToReason(): void
    {
        $response = $this->createClient([$this->answer('pong')])->complete($this->createRequest(maxTokens: 2048));

        self::assertSame('pong', $response->content);
        self::assertSame('reasoning_effort: none', $response->reasoning);
        self::assertCount(1, $this->sentBodies);
        self::assertSame('none', $this->sentBodies[0]['reasoning_effort'] ?? null);
        // the model does not reason: no allowance
        self::assertSame(2048, $this->sentBodies[0]['max_tokens'] ?? null);
    }

    public function testARejectedEffortFallsBackToTheNextOne(): void
    {
        $response = $this->createClient([
            $this->rejection("Invalid value for 'reasoning_effort': 'none' is not supported with this model."),
            $this->rejection('reasoning_effort minimal is not supported'),
            $this->answer('pong'),
        ])->complete($this->createRequest(maxTokens: 2048));

        self::assertSame('pong', $response->content);
        self::assertSame('reasoning_effort: low', $response->reasoning);
        self::assertSame(['none', 'minimal', 'low'], array_column($this->sentBodies, 'reasoning_effort'));
        // the model still reasons a little: it gets an allowance on top of the answer budget
        self::assertSame(4096, $this->sentBodies[2]['max_tokens'] ?? null);
    }

    public function testAModelAcceptingNoEffortIsSentNoneAndLoggedOnce(): void
    {
        $logger = new RecordingLogger();
        $client = $this->createClient([
            $this->rejection('Unrecognized request argument supplied: reasoning_effort'),
            $this->rejection('Unrecognized request argument supplied: reasoning_effort'),
            $this->rejection('Unrecognized request argument supplied: reasoning_effort'),
            $this->answer('pong'),
            $this->answer('pong again'),
        ], logger: $logger);

        $first = $client->complete($this->createRequest());
        $second = $client->complete($this->createRequest());

        self::assertNull($first->reasoning);
        self::assertSame('pong again', $second->content);
        self::assertNull($second->reasoning);
        self::assertCount(5, $this->sentBodies);
        self::assertArrayNotHasKey('reasoning_effort', $this->sentBodies[3]);
        // remembered: the second request goes straight without a setting
        self::assertArrayNotHasKey('reasoning_effort', $this->sentBodies[4]);
        $warnings = array_filter(
            $logger->records(),
            static fn (array $record): bool => str_contains($record['message'], 'reasoning'),
        );
        self::assertCount(1, $warnings);
        self::assertSame('warning', array_values($warnings)[0]['level']);
    }

    public function testAnErrorNotAboutReasoningIsNotRetried(): void
    {
        $client = $this->createClient([$this->rejection('The model `mistral-unknown` does not exist'), $this->answer('pong')]);

        try {
            $client->complete($this->createRequest());
            self::fail('The request should have failed');
        } catch (LlmRequestFailedException $exception) {
            self::assertStringContainsString('does not exist', $exception->getMessage());
        }
        self::assertCount(1, $this->sentBodies);
    }

    public function testAServerErrorNamingReasoningIsNotRetried(): void
    {
        $client = $this->createClient([
            new MockResponse('{"error":{"message":"reasoning backend unavailable"}}', ['http_code' => 500]),
            $this->answer('pong'),
        ]);

        $this->expectException(LlmRequestFailedException::class);

        try {
            $client->complete($this->createRequest());
        } finally {
            self::assertCount(1, $this->sentBodies);
        }
    }

    public function testTheAcceptedEffortIsRemembered(): void
    {
        $cache = new ArrayAdapter();
        $this->createClient([$this->rejection('reasoning_effort none is not supported'), $this->answer('pong')], cache: $cache)
            ->complete($this->createRequest());
        $this->forgetSentBodies();

        $response = $this->createClient([$this->answer('pong')], cache: $cache)->complete($this->createRequest());

        self::assertSame('reasoning_effort: minimal', $response->reasoning);
        self::assertSame(['minimal'], array_column($this->sentBodies, 'reasoning_effort'));
    }

    public function testARememberedEffortRejectedLaterIsDiscoveredAgain(): void
    {
        $cache = new ArrayAdapter();
        $this->createClient([$this->rejection('reasoning_effort none is not supported'), $this->answer('pong')], cache: $cache)
            ->complete($this->createRequest());
        $this->forgetSentBodies();

        // the alias now names a model that only accepts "none"
        $client = $this->createClient([
            $this->rejection('reasoning_effort minimal is not supported'),
            $this->answer('pong'),
            $this->answer('pong again'),
        ], cache: $cache);
        $response = $client->complete($this->createRequest());
        $client->complete($this->createRequest());

        self::assertSame('reasoning_effort: none', $response->reasoning);
        self::assertSame(['minimal', 'none', 'none'], array_column($this->sentBodies, 'reasoning_effort'));
    }

    public function testTheOutcomeIsRememberedPerModel(): void
    {
        $cache = new ArrayAdapter();
        $this->createClient([$this->rejection('reasoning_effort none is not supported'), $this->answer('pong')], cache: $cache)
            ->complete($this->createRequest());
        $this->forgetSentBodies();

        $this->createClient([$this->answer('pong')], cache: $cache)
            ->complete($this->createRequest(model: new ModelName('another-model')));

        self::assertSame(['none'], array_column($this->sentBodies, 'reasoning_effort'));
    }

    public function testADefaultModelIsSentNoSettingAndAnAllowance(): void
    {
        $response = $this->createClient([$this->answer('pong')], defaultModelReasoning: ReasoningModeEnum::Default)
            ->complete($this->createRequest(maxTokens: 256));

        self::assertNull($response->reasoning);
        self::assertArrayNotHasKey('reasoning_effort', $this->sentBodies[0]);
        self::assertSame(256 + 1024, $this->sentBodies[0]['max_tokens'] ?? null);
    }

    public function testEachTierGetsTheSettingOfItsModel(): void
    {
        $client = $this->createClient(
            [$this->answer('pong'), $this->answer('pong')],
            fastModel: new ModelName('mistral-small-latest'),
            defaultModelReasoning: ReasoningModeEnum::Default,
        );

        $client->complete($this->createRequest());
        $client->complete($this->createRequest(tier: LlmModelTierEnum::Fast));

        self::assertSame('mistral-large-latest', $this->sentBodies[0]['model'] ?? null);
        self::assertArrayNotHasKey('reasoning_effort', $this->sentBodies[0]);
        self::assertSame('mistral-small-latest', $this->sentBodies[1]['model'] ?? null);
        self::assertSame('none', $this->sentBodies[1]['reasoning_effort'] ?? null);
    }

    public function testWithoutAFastModelTheFastTierFollowsTheDefaultModel(): void
    {
        $this->createClient(
            [$this->answer('pong')],
            defaultModelReasoning: ReasoningModeEnum::Default,
            fastModelReasoning: ReasoningModeEnum::Minimal,
        )->complete($this->createRequest(tier: LlmModelTierEnum::Fast));

        self::assertSame('mistral-large-latest', $this->sentBodies[0]['model'] ?? null);
        self::assertArrayNotHasKey('reasoning_effort', $this->sentBodies[0]);
    }

    public function testALeadingThinkBlockIsNotPartOfTheAnswer(): void
    {
        $response = $this->createClient([$this->answer("<think>\nThe user wants pong.\n</think>\n\npong")])
            ->complete($this->createRequest());

        self::assertSame('pong', $response->content);
    }

    public function testAnUnterminatedThinkBlockLeavesNoAnswer(): void
    {
        $response = $this->createClient([$this->answer('<think>The user wants')])->complete($this->createRequest());

        self::assertSame('', $response->content);
    }

    public function testSeparateReasoningFieldsAreNotPartOfTheAnswer(): void
    {
        $response = $this->createClient([new MockResponse(json_encode([
            'choices' => [['message' => ['content' => 'pong', 'reasoning_content' => 'hmm', 'reasoning' => 'hmm']]],
            'model' => 'qwen3',
        ], \JSON_THROW_ON_ERROR))])->complete($this->createRequest());

        self::assertSame('pong', $response->content);
    }

    public function testAStreamedThinkBlockIsNotPartOfTheAnswer(): void
    {
        $generator = $this->createClient([$this->stream(['<th', 'ink>The user wants po', 'ng.</thi', "nk>\n\n", 'po', 'ng'])])
            ->stream($this->createRequest());
        $deltas = iterator_to_array($generator, false);

        self::assertSame(['po', 'ng'], $deltas);
        self::assertSame('pong', $generator->getReturn()->content);
        self::assertSame('reasoning_effort: none', $generator->getReturn()->reasoning);
    }

    public function testAStreamedAnswerShorterThanTheTagIsKept(): void
    {
        $generator = $this->createClient([$this->stream(['<', 'b'])])->stream($this->createRequest());

        self::assertSame(['<b'], iterator_to_array($generator, false));
        self::assertSame('<b', $generator->getReturn()->content);
    }

    public function testAStreamFallsBackBeforeAnyChunk(): void
    {
        $generator = $this->createClient([
            $this->rejection('reasoning_effort none is not supported'),
            $this->stream(['pong']),
        ])->stream($this->createRequest(maxTokens: 2048));
        $deltas = iterator_to_array($generator, false);

        self::assertSame(['pong'], $deltas);
        self::assertSame('reasoning_effort: minimal', $generator->getReturn()->reasoning);
        self::assertSame(['none', 'minimal'], array_column($this->sentBodies, 'reasoning_effort'));
        self::assertTrue($this->sentBodies[1]['stream'] ?? false);
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
        ?ModelName $fastModel = null,
        ReasoningModeEnum $defaultModelReasoning = ReasoningModeEnum::Minimal,
        ReasoningModeEnum $fastModelReasoning = ReasoningModeEnum::Minimal,
        ?CacheInterface $cache = null,
        ?LoggerInterface $logger = null,
    ): OpenAiCompatibleLlmClient {
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

        return new OpenAiCompatibleLlmClient(
            $httpClient,
            new EndpointUrl('https://api.mistral.ai'),
            new ApiToken('a-token'),
            new ModelName('mistral-large-latest'),
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

    private function answer(string $content): MockResponse
    {
        return new MockResponse(json_encode([
            'choices' => [['message' => ['content' => $content]]],
            'model' => 'mistral-large-latest',
        ], \JSON_THROW_ON_ERROR));
    }

    private function rejection(string $message): MockResponse
    {
        return new MockResponse(
            json_encode(['error' => ['message' => $message]], \JSON_THROW_ON_ERROR),
            ['http_code' => 400]
        );
    }

    /**
     * @param list<string> $deltas
     */
    private function stream(array $deltas): MockResponse
    {
        $events = array_map(
            static fn (string $delta): string => 'data: ' . json_encode(
                ['choices' => [['delta' => ['content' => $delta]]]],
                \JSON_THROW_ON_ERROR
            ) . "\n\n",
            $deltas
        );
        $events[] = "data: [DONE]\n\n";

        return new MockResponse($events);
    }
}
