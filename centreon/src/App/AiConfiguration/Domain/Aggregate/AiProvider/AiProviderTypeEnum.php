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

namespace App\AiConfiguration\Domain\Aggregate\AiProvider;

use App\AiConfiguration\Domain\Exception\InvalidAiProviderConfigurationException;

enum AiProviderTypeEnum: string
{
    case Anthropic = 'anthropic';
    case OpenAi = 'openai';
    case Mistral = 'mistral';
    case Google = 'google';
    case Bedrock = 'bedrock';
    case OpenAiCompatible = 'openai_compatible';

    /** Bedrock is reached through the Mantle endpoint of an AWS region. */
    private const BEDROCK_ENDPOINT_PATTERN = '~^https://bedrock-mantle\.[a-z0-9]+(?:-[a-z0-9]+)*\.api\.aws/?$~';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }

    /**
     * Official endpoint of a curated provider, always used for it. OpenAI-compatible
     * and Bedrock providers have none: their endpoint is configured (for Bedrock,
     * the Mantle endpoint of an AWS region).
     */
    public function defaultEndpointUrl(): ?EndpointUrl
    {
        return match ($this) {
            self::Anthropic => new EndpointUrl('https://api.anthropic.com'),
            self::OpenAi => new EndpointUrl('https://api.openai.com'),
            self::Mistral => new EndpointUrl('https://api.mistral.ai'),
            self::Google => new EndpointUrl('https://generativelanguage.googleapis.com'),
            self::Bedrock, self::OpenAiCompatible => null,
        };
    }

    /**
     * Endpoint a provider of this type is reached at: the official one of the
     * curated providers, whatever the input (it is ignored), or the input
     * otherwise, which must then be the Mantle endpoint of a region for Bedrock.
     *
     * @throws InvalidAiProviderConfigurationException when the input is missing or not acceptable
     */
    public function resolveEndpoint(?string $input): EndpointUrl
    {
        $defaultEndpointUrl = $this->defaultEndpointUrl();
        if ($defaultEndpointUrl instanceof EndpointUrl) {
            return $defaultEndpointUrl;
        }

        if ($input === null || trim($input) === '') {
            throw InvalidAiProviderConfigurationException::endpointRequired($this);
        }

        try {
            $endpointUrl = new EndpointUrl($input);
        } catch (\InvalidArgumentException $exception) {
            throw InvalidAiProviderConfigurationException::invalidEndpoint($exception);
        }

        if ($this === self::Bedrock && preg_match(self::BEDROCK_ENDPOINT_PATTERN, $endpointUrl->value) !== 1) {
            throw InvalidAiProviderConfigurationException::invalidBedrockEndpoint();
        }

        return $endpointUrl;
    }

    /**
     * Path of the OpenAI-compatible chat completions endpoint. Gemini exposes
     * it under its OpenAI compatibility layer rather than the usual /v1.
     */
    public function chatCompletionsPath(): string
    {
        return match ($this) {
            self::Google => '/v1beta/openai/chat/completions',
            default => '/v1/chat/completions',
        };
    }

    /**
     * Request field bounding the output length. OpenAI's recent models (GPT-5,
     * o-series) reject the legacy `max_tokens` and require
     * `max_completion_tokens`; the other compatible providers (Mistral, Ollama,
     * vLLM, …) still expect `max_tokens`.
     */
    public function chatMaxTokensField(): string
    {
        return match ($this) {
            self::OpenAi => 'max_completion_tokens',
            default => 'max_tokens',
        };
    }
}
