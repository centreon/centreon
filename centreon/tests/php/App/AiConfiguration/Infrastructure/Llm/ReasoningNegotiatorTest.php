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

use App\AiConfiguration\Infrastructure\Llm\ReasoningCandidate;
use App\AiConfiguration\Infrastructure\Llm\ReasoningNegotiator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReasoningNegotiatorTest extends TestCase
{
    /**
     * @return iterable<string, array{int, ReasoningCandidate|null, int}>
     */
    public static function provideBudgets(): iterable
    {
        [$effortNone, $effortMinimal, $effortLow] = ReasoningCandidate::reasoningEffort();
        [$thinkingDisabled, $thinkingBetweenTools, $effortLowAnthropic] = ReasoningCandidate::anthropicThinking();

        yield 'reasoning turned off' => [2048, $effortNone, 2048];

        yield 'thinking turned off' => [256, $thinkingDisabled, 256];

        yield 'a small budget, reasoning a little' => [256, $effortMinimal, 256 + 1024];

        yield 'a large budget, reasoning a little' => [2048, $effortLow, 2048 + 2048];

        yield 'just above the small budget' => [257, $thinkingBetweenTools, 257 + 2048];

        yield 'a low effort' => [16, $effortLowAnthropic, 16 + 1024];

        yield 'no setting sent' => [2048, null, 2048 + 2048];
    }

    #[DataProvider('provideBudgets')]
    public function testAModelThatMayReasonGetsAnAllowance(int $maxTokens, ?ReasoningCandidate $candidate, int $expected): void
    {
        self::assertSame($expected, ReasoningNegotiator::maxTokensFor($maxTokens, $candidate));
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
}
