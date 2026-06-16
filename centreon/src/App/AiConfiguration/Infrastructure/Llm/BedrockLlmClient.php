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

use App\AiConfiguration\Domain\Aggregate\AiProvider\ModelName;
use App\AiConfiguration\Domain\Llm\LlmClientInterface;
use App\AiConfiguration\Domain\Llm\LlmModelTierEnum;
use App\AiConfiguration\Domain\Llm\LlmRequest;
use App\AiConfiguration\Domain\Llm\LlmResponse;

/**
 * Amazon Bedrock through its Mantle endpoint (bedrock-mantle.<region>.api.aws),
 * a single host exposing several APIs. Model discovery and the non-Anthropic
 * model families use the OpenAI-compatible surface (/v1/models, /v1/chat/
 * completions). Anthropic (Claude) models are not served there — they require
 * the Anthropic Messages API at /anthropic/v1/messages — so chat is routed by
 * model family.
 */
final class BedrockLlmClient implements LlmClientInterface
{
    public function __construct(
        private readonly LlmClientInterface $openAiCompatible,
        private readonly LlmClientInterface $anthropicMessages,
        private readonly ModelName $defaultModel,
        private readonly ?ModelName $fastModel,
    ) {
    }

    public function complete(LlmRequest $request): LlmResponse
    {
        return $this->clientFor($request)->complete($request);
    }

    public function stream(LlmRequest $request): \Generator
    {
        return yield from $this->clientFor($request)->stream($request);
    }

    public function listModels(): array
    {
        // the OpenAI-compatible /v1/models lists the whole Mantle catalog,
        // every family included
        return $this->openAiCompatible->listModels();
    }

    private function clientFor(LlmRequest $request): LlmClientInterface
    {
        $model = $request->model ?? $this->resolveTierModel($request->tier);

        // Mantle ids are bare vendor.model strings (e.g. anthropic.claude-...,
        // qwen.qwen3-32b); the anthropic. family speaks the Messages API
        return str_starts_with($model->value, 'anthropic.')
            ? $this->anthropicMessages
            : $this->openAiCompatible;
    }

    private function resolveTierModel(LlmModelTierEnum $tier): ModelName
    {
        return match ($tier) {
            LlmModelTierEnum::Default => $this->defaultModel,
            LlmModelTierEnum::Fast => $this->fastModel ?? $this->defaultModel,
        };
    }
}
