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

use App\AiConfiguration\Infrastructure\Llm\ThinkBlockFilter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ThinkBlockFilterTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideAnswers(): iterable
    {
        yield 'no block' => ['pong', 'pong'];

        yield 'a leading block' => ["<think>\nThe user wants pong.\n</think>\n\npong", 'pong'];

        yield 'a block after blank space' => ["\n <think>hmm</think>pong", 'pong'];

        yield 'an unterminated block' => ['<think>The user wants', ''];

        yield 'a block that is not leading' => ['pong <think>hmm</think>', 'pong <think>hmm</think>'];

        yield 'an answer shorter than the tag' => ['<th', '<th'];

        yield 'an empty answer' => ['', ''];

        yield 'an answer about the tag' => ['<thinking> is not a block', '<thinking> is not a block'];

        yield 'only a block' => ['<think>hmm</think>', ''];
    }

    #[DataProvider('provideAnswers')]
    public function testTheLeadingBlockIsStripped(string $answer, string $expected): void
    {
        self::assertSame($expected, ThinkBlockFilter::strip($answer));
    }

    #[DataProvider('provideAnswers')]
    public function testTheLeadingBlockIsStrippedFromAnAnswerStreamedCharacterByCharacter(string $answer, string $expected): void
    {
        $filter = new ThinkBlockFilter();
        $streamed = '';
        foreach (mb_str_split($answer) as $character) {
            $streamed .= $filter->push($character);
        }

        self::assertSame($expected, $streamed . $filter->finish());
    }

    public function testTheAnswerIsPassedOnAsSoonAsItIsKnownNotToBeABlock(): void
    {
        $filter = new ThinkBlockFilter();

        self::assertSame('', $filter->push('<t'));
        self::assertSame('<tr>', $filter->push('r>'));
        self::assertSame(' ok', $filter->push(' ok'));
        self::assertSame('', $filter->finish());
    }

    public function testMultibyteReasoningIsDropped(): void
    {
        self::assertSame('réponse', ThinkBlockFilter::strip('<think>réfléchir… 思考</think>réponse'));
    }
}
