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

use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderTypeEnum;
use App\AiConfiguration\Domain\Aggregate\AiProvider\TierPricing;
use App\AiConfiguration\Domain\Llm\Exception\LlmRequestFailedException;
use App\AiConfiguration\Domain\Llm\ModelPriceCatalog;
use App\Shared\Infrastructure\Proxy\HttpProxyProvider;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reads token prices from LiteLLM's community price catalog (hosted on GitHub).
 * The catalog is fetched once per instance, through the platform proxy. Prices
 * are published per token in USD; they are returned per 1M tokens.
 */
final class LiteLlmModelPriceCatalog implements ModelPriceCatalog
{
    private const CATALOG_URL = 'https://raw.githubusercontent.com/BerriAI/litellm/main/model_prices_and_context_window.json';
    private const TIMEOUT_SECONDS = 20;

    /** LiteLLM key prefix per provider type (curated providers use bare or prefixed keys). */
    private const PROVIDER_SLUG = [
        AiProviderTypeEnum::Anthropic->value => 'anthropic',
        AiProviderTypeEnum::OpenAi->value => 'openai',
        AiProviderTypeEnum::Mistral->value => 'mistral',
    ];

    /** @var array<string, array{input_cost_per_token?: float, output_cost_per_token?: float}>|null */
    private ?array $catalog = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly HttpProxyProvider $proxyProvider,
    ) {
    }

    public function priceFor(string $model, AiProviderTypeEnum $type): ?TierPricing
    {
        $catalog = $this->catalog();

        // LiteLLM keys are sometimes bare (gpt-4o, claude-sonnet-4-6) and
        // sometimes provider-prefixed (mistral/mistral-small-latest); try the
        // name as given, then prefixed with the provider slug. Exact match
        // only — no fuzzy matching that could return a wrong price.
        $entry = $catalog[$model] ?? null;
        $prefix = self::PROVIDER_SLUG[$type->value] ?? null;
        if ($entry === null && $prefix !== null) {
            $entry = $catalog["{$prefix}/{$model}"] ?? null;
        }

        if ($entry === null
            || ! isset($entry['input_cost_per_token'], $entry['output_cost_per_token'])
        ) {
            return null;
        }

        return new TierPricing(
            inputPricePerMillion: (float) $entry['input_cost_per_token'] * 1_000_000,
            outputPricePerMillion: (float) $entry['output_cost_per_token'] * 1_000_000,
        );
    }

    /**
     * @return array<string, array{input_cost_per_token?: float, output_cost_per_token?: float}>
     */
    private function catalog(): array
    {
        if ($this->catalog !== null) {
            return $this->catalog;
        }

        $options = ['timeout' => self::TIMEOUT_SECONDS];
        $proxyUrl = $this->proxyProvider->getProxyUrl();
        if ($proxyUrl !== null) {
            $options['proxy'] = $proxyUrl;
        }

        try {
            /** @var array<string, array{input_cost_per_token?: float, output_cost_per_token?: float}> $catalog */
            $catalog = $this->httpClient->request('GET', self::CATALOG_URL, $options)->toArray();
        } catch (\Throwable $exception) {
            throw LlmRequestFailedException::fromProviderError(0, $exception->getMessage());
        }

        $this->catalog = $catalog;

        return $catalog;
    }
}
