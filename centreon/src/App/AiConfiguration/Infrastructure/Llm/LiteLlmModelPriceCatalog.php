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
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
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
    private const CACHE_KEY = 'ai_provider_litellm_model_prices';
    private const CACHE_TTL_SECONDS = 86400;

    /** LiteLLM key prefix per provider type (curated providers use bare or prefixed keys). */
    private const PROVIDER_SLUG = [
        AiProviderTypeEnum::Anthropic->value => 'anthropic',
        AiProviderTypeEnum::OpenAi->value => 'openai',
        AiProviderTypeEnum::Mistral->value => 'mistral',
        AiProviderTypeEnum::Google->value => 'gemini',
    ];

    /** @var array<string, array{input_cost_per_token?: float, output_cost_per_token?: float}>|null */
    private ?array $catalog = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly HttpProxyProvider $proxyProvider,
        private readonly CacheInterface $cache,
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

        // LiteLLM publishes per-token prices (e.g. 4e-07); multiplying by 1M to
        // get the per-million price reintroduces binary float error (0.4 lands
        // on 0.39999999999999997). Round to 6 decimals: enough for sub-cent
        // per-million prices, and it removes the artifact from the prefill.
        return new TierPricing(
            inputPricePerMillion: round((float) $entry['input_cost_per_token'] * 1_000_000, 6),
            outputPricePerMillion: round((float) $entry['output_cost_per_token'] * 1_000_000, 6),
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

        // cached for a day: the catalog is ~1.5 MB and prices change slowly, so
        // the form prefill must not re-download it on every click. A failed
        // fetch is not cached (the callback exception propagates).
        /** @var array<string, array{input_cost_per_token?: float, output_cost_per_token?: float}> $catalog */
        $catalog = $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): array {
            $item->expiresAfter(self::CACHE_TTL_SECONDS);

            return $this->fetch();
        });

        $this->catalog = $catalog;

        return $catalog;
    }

    /**
     * @return array<string, array{input_cost_per_token?: float, output_cost_per_token?: float}>
     */
    private function fetch(): array
    {
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

        return $catalog;
    }
}
