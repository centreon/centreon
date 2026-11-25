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

namespace App\AiConfiguration\Infrastructure\Llm;

/**
 * Drops the <think>…</think> block some reasoning models (e.g. Qwen3, DeepSeek R1
 * on Ollama or vLLM) write at the start of their answer: it is their reasoning,
 * not the answer. Fed the answer in pieces as they stream in; an unterminated
 * block is all reasoning, so nothing of it is kept.
 */
final class ThinkBlockFilter
{
    private const OPENING_TAG = '<think>';
    private const CLOSING_TAG = '</think>';
    private const STATE_START = 0;
    private const STATE_THINKING = 1;
    private const STATE_AFTER_THINKING = 2;
    private const STATE_ANSWER = 3;

    private int $state = self::STATE_START;

    private string $buffer = '';

    /**
     * The whole answer, without its leading reasoning block.
     */
    public static function strip(string $content): string
    {
        $filter = new self();

        return $filter->push($content) . $filter->finish();
    }

    /**
     * @return string the part of the answer that can be passed on so far
     */
    public function push(string $text): string
    {
        switch ($this->state) {
            case self::STATE_START:
                $this->buffer .= $text;
                $start = ltrim($this->buffer);
                if (mb_strlen($start) < mb_strlen(self::OPENING_TAG) && str_starts_with(self::OPENING_TAG, $start)) {
                    // not enough yet to tell whether the answer opens with a block
                    return '';
                }
                if (! str_starts_with($start, self::OPENING_TAG)) {
                    $this->state = self::STATE_ANSWER;

                    return $this->flush();
                }
                $this->state = self::STATE_THINKING;
                $this->buffer = '';

                return $this->push(mb_substr($start, mb_strlen(self::OPENING_TAG)));
            case self::STATE_THINKING:
                $this->buffer .= $text;
                $end = mb_strpos($this->buffer, self::CLOSING_TAG);
                if ($end === false) {
                    // only what may be the start of a split closing tag is kept
                    $this->buffer = mb_substr($this->buffer, -mb_strlen(self::CLOSING_TAG));

                    return '';
                }
                $rest = mb_substr($this->buffer, $end + mb_strlen(self::CLOSING_TAG));
                $this->buffer = '';
                $this->state = self::STATE_AFTER_THINKING;

                return $this->push($rest);
            case self::STATE_AFTER_THINKING:
                // the blank lines separating the block from the answer
                $text = ltrim($text);
                if ($text === '') {
                    return '';
                }
                $this->state = self::STATE_ANSWER;

                return $text;
            default:
                return $text;
        }
    }

    /**
     * @return string what was held back to tell whether the answer opens with a block
     */
    public function finish(): string
    {
        return $this->state === self::STATE_START ? $this->flush() : '';
    }

    private function flush(): string
    {
        $buffer = $this->buffer;
        $this->buffer = '';

        return $buffer;
    }
}
