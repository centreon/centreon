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

use App\AiConfiguration\Domain\Aggregate\AiProvider\ModelName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ReasoningModeEnum;
use App\AiConfiguration\Domain\Llm\Exception\LlmRequestFailedException;
use App\AiConfiguration\Infrastructure\Llm\ReasoningCandidate;
use App\AiConfiguration\Infrastructure\Llm\ReasoningNegotiator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ReasoningNegotiatorTest extends TestCase
{
    /** @var list<string|null> the labels of the candidates sent, null when none was */
    private array $sentLabels = [];

    /**
     * @return iterable<string, array{int, ReasoningModeEnum, ReasoningCandidate|null, int}>
     */
    public static function provideBudgets(): iterable
    {
        [$effortNone, $effortMinimal, $effortLow] = ReasoningCandidate::reasoningEffort();
        [$thinkingDisabled, $thinkingBetweenTools, $effortLowAnthropic] = ReasoningCandidate::anthropicThinking();
        $minimal = ReasoningModeEnum::Minimal;

        yield 'reasoning turned off' => [2048, $minimal, $effortNone, 2048];

        yield 'thinking turned off' => [256, $minimal, $thinkingDisabled, 256];

        yield 'a small budget, reasoning a little' => [256, $minimal, $effortMinimal, 256 + 1024];

        yield 'a large budget, reasoning a little' => [2048, $minimal, $effortLow, 2048 + 2048];

        yield 'just above the small budget' => [257, $minimal, $thinkingBetweenTools, 257 + 2048];

        yield 'a low effort' => [16, $minimal, $effortLowAnthropic, 16 + 1024];

        yield 'the model default' => [2048, ReasoningModeEnum::Default, null, 2048 + 2048];

        yield 'no setting accepted: most likely a model that does not reason' => [2048, $minimal, null, 2048];
    }

    #[DataProvider('provideBudgets')]
    public function testAModelThatMayReasonGetsAnAllowance(
        int $maxTokens,
        ReasoningModeEnum $mode,
        ?ReasoningCandidate $candidate,
        int $expected,
    ): void {
        self::assertSame($expected, ReasoningNegotiator::maxTokensFor($maxTokens, $mode, $candidate));
    }

    public function testOnlyTheFirstCandidatesTurnReasoningOff(): void
    {
        foreach ([ReasoningCandidate::reasoningEffort(), ReasoningCandidate::anthropicThinking()] as $candidates) {
            self::assertSame(
                [true, false, false],
                array_map(static fn (ReasoningCandidate $candidate): bool => $candidate->turnsReasoningOff, $candidates)
            );
        }
    }

    public function testTheCacheKeyChangesWithTheCandidateList(): void
    {
        $cache = new ArrayAdapter();
        $this->send($cache, ReasoningCandidate::reasoningEffort(), 'reasoning_effort: minimal');
        self::assertSame(['reasoning_effort: none', 'reasoning_effort: minimal'], $this->sentLabels);

        $this->sentLabels = [];
        $this->send($cache, ReasoningCandidate::reasoningEffort(), 'reasoning_effort: minimal');
        self::assertSame(['reasoning_effort: minimal'], $this->sentLabels);

        // the remembered index 1 would still name an accepted setting: it is not used
        $this->sentLabels = [];
        $candidates = [
            new ReasoningCandidate('reasoning_effort: low', ['reasoning_effort' => 'low']),
            new ReasoningCandidate('reasoning_effort: minimal', ['reasoning_effort' => 'minimal']),
        ];
        $this->send($cache, $candidates, 'reasoning_effort: minimal');
        self::assertSame(['reasoning_effort: low', 'reasoning_effort: minimal'], $this->sentLabels);
        self::assertCount(2, $cache->getValues());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideRememberedValuesNotMatchingTheList(): iterable
    {
        yield 'an index out of range' => [7];

        yield 'not an index' => ['reasoning_effort: minimal'];
    }

    #[DataProvider('provideRememberedValuesNotMatchingTheList')]
    public function testARememberedValueNotMatchingTheListIsRediscoveredAndReplaced(mixed $remembered): void
    {
        $cache = new ArrayAdapter();
        $this->send($cache, ReasoningCandidate::reasoningEffort(), 'reasoning_effort: minimal');
        $key = array_key_first($cache->getValues());
        self::assertIsString($key);
        $item = $cache->getItem($key);
        $item->set($remembered);

        $cache->save($item);
        $this->sentLabels = [];

        $this->send($cache, ReasoningCandidate::reasoningEffort(), 'reasoning_effort: minimal');

        self::assertSame(['reasoning_effort: none', 'reasoning_effort: minimal'], $this->sentLabels);
        self::assertSame(1, $cache->getItem($key)->get());
    }

    /**
     * Sends a request to a model that only accepts the given setting.
     *
     * @param non-empty-list<ReasoningCandidate> $candidates
     *
     * @throws LlmRequestFailedException
     */
    private function send(ArrayAdapter $cache, array $candidates, string $acceptedLabel): void
    {
        $negotiator = new ReasoningNegotiator($cache, new NullLogger(), 'openai_compatible https://api.example.com', $candidates);
        $negotiator->send(
            new ModelName('a-model'),
            ReasoningModeEnum::Minimal,
            function (?ReasoningCandidate $candidate) use ($acceptedLabel): ResponseInterface {
                $this->sentLabels[] = $candidate?->label;
                if ($candidate?->label !== $acceptedLabel) {
                    throw LlmRequestFailedException::fromProviderError(400, 'this reasoning setting is not supported');
                }

                return (new MockHttpClient(new MockResponse('{}')))->request('POST', 'https://api.example.com');
            }
        );
    }
}
