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

namespace App\AiConfiguration\Domain\Llm;

use App\AiConfiguration\Domain\Aggregate\AiProvider\ReasoningModeEnum;

final readonly class LlmResponse
{
    /**
     * @param bool $truncated whether the model stopped because it reached the
     *                        token limit of the request
     * @param string|null $refusal why the provider refused to answer, if it did:
     *                             the tokens are billed all the same
     * @param string|null $reasoning the setting sent to turn the model's reasoning down
     *                               (e.g. "reasoning_effort: none"), if one was
     * @param int|null $maxTokens the output token limit sent, which may exceed the requested
     *                            one to leave a reasoning model room to answer; null if unknown
     * @param ReasoningModeEnum|null $reasoningMode how much the model was set to reason; null if unknown
     */
    public function __construct(
        public string $content,
        public string $model,
        public LlmUsage $usage,
        public bool $truncated = false,
        public ?string $refusal = null,
        public ?string $reasoning = null,
        public ?int $maxTokens = null,
        public ?ReasoningModeEnum $reasoningMode = null,
    ) {
    }
}
