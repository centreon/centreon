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

namespace App\AiConfiguration\Infrastructure\Dbal;

use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProvider;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderDescription;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderId;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderTypeEnum;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ApiToken;
use App\AiConfiguration\Domain\Aggregate\AiProvider\Currency;
use App\AiConfiguration\Domain\Aggregate\AiProvider\EndpointUrl;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ModelName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\Pricing;
use App\AiConfiguration\Domain\Aggregate\AiProvider\SpendLimit;
use App\AiConfiguration\Domain\Aggregate\AiProvider\TierPricing;
use App\Shared\Infrastructure\TransformerInterface;
use Webmozart\Assert\Assert;

/**
 * @phpstan-import-type RowTypeAlias from DbalAiProviderRepository
 *
 * @implements TransformerInterface<RowTypeAlias, AiProvider>
 */
final readonly class DbalAiProviderTransformer implements TransformerInterface
{
    /**
     * @param RowTypeAlias $from
     */
    public function transform(mixed $from): AiProvider
    {
        /**
         * @var array{
         *   endpoint_url: string,
         *   api_token: string,
         *   default_model: string,
         *   fast_model?: string|null,
         *   currency?: string|null,
         *   pricing?: array{default: array{input: float, output: float}, fast?: array{input: float, output: float}|null}|null,
         *   limits?: array{daily?: float|null, monthly?: float|null}|null
         * } $configuration
         */
        $configuration = json_decode($from['ap_configuration'], true, 512, \JSON_THROW_ON_ERROR);
        Assert::keyExists($configuration, 'endpoint_url');
        Assert::keyExists($configuration, 'api_token');
        Assert::keyExists($configuration, 'default_model');

        return new AiProvider(
            id: new AiProviderId((int) $from['ap_id']),
            name: new AiProviderName($from['ap_name']),
            type: AiProviderTypeEnum::from($from['ap_type']),
            endpointUrl: new EndpointUrl($configuration['endpoint_url']),
            apiToken: new ApiToken($configuration['api_token']),
            defaultModel: new ModelName($configuration['default_model']),
            fastModel: isset($configuration['fast_model'])
                ? new ModelName($configuration['fast_model'])
                : null,
            description: $from['ap_description'] !== null && $from['ap_description'] !== ''
                ? new AiProviderDescription($from['ap_description'])
                : null,
            isActivated: (bool) $from['ap_is_activated'],
            currency: isset($configuration['currency'])
                ? new Currency($configuration['currency'])
                : null,
            pricing: $this->transformPricing($configuration['pricing'] ?? null),
            limit: $this->transformLimit($configuration['limits'] ?? null),
        );
    }

    /**
     * @param array{default: array{input: float, output: float}, fast?: array{input: float, output: float}|null}|null $pricing
     */
    private function transformPricing(?array $pricing): ?Pricing
    {
        if ($pricing === null) {
            return null;
        }

        return new Pricing(
            default: new TierPricing((float) $pricing['default']['input'], (float) $pricing['default']['output']),
            fast: isset($pricing['fast'])
                ? new TierPricing((float) $pricing['fast']['input'], (float) $pricing['fast']['output'])
                : null,
        );
    }

    /**
     * @param array{daily?: float|null, monthly?: float|null}|null $limits
     */
    private function transformLimit(?array $limits): ?SpendLimit
    {
        if ($limits === null) {
            return null;
        }

        return new SpendLimit(
            daily: isset($limits['daily']) ? (float) $limits['daily'] : null,
            monthly: isset($limits['monthly']) ? (float) $limits['monthly'] : null,
        );
    }
}
