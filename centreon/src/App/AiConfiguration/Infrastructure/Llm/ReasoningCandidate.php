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
 * A request setting that turns a model's reasoning down, as one provider API
 * spells it. The providers accept different values per model, and reject the
 * others: a client tries its candidates in order, least reasoning first.
 */
final readonly class ReasoningCandidate
{
    /**
     * @param string $label the setting as reported to the user, e.g. "reasoning_effort: none"
     * @param array<string, mixed> $payload the request fields carrying the setting
     * @param bool $turnsReasoningOff whether the model then answers without reasoning at all
     */
    public function __construct(
        public string $label,
        public array $payload,
        public bool $turnsReasoningOff = false,
    ) {
    }

    /**
     * Candidates of the OpenAI chat completions API and the compatible ones.
     *
     * @return non-empty-list<self>
     */
    public static function reasoningEffort(): array
    {
        return [
            new self('reasoning_effort: none', ['reasoning_effort' => 'none'], turnsReasoningOff: true),
            new self('reasoning_effort: minimal', ['reasoning_effort' => 'minimal']),
            new self('reasoning_effort: low', ['reasoning_effort' => 'low']),
        ];
    }

    /**
     * Candidates of the Anthropic Messages API.
     *
     * @return non-empty-list<self>
     */
    public static function anthropicThinking(): array
    {
        return [
            new self('thinking: disabled', ['thinking' => ['type' => 'disabled']], turnsReasoningOff: true),
            new self('thinking: between_tools', ['thinking' => ['type' => 'between_tools']]),
            new self('effort: low', ['output_config' => ['effort' => 'low']]),
        ];
    }
}
