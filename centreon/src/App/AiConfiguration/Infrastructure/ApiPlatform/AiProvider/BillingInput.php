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

namespace App\AiConfiguration\Infrastructure\ApiPlatform\AiProvider;

use App\AiConfiguration\Domain\Aggregate\AiProvider\Currency;
use App\AiConfiguration\Domain\Aggregate\AiProvider\Pricing;
use App\AiConfiguration\Domain\Aggregate\AiProvider\SpendLimit;
use App\AiConfiguration\Domain\Aggregate\AiProvider\TierPricing;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Builds the currency, pricing and spend-limit value objects from the flat
 * billing fields of the create/update payloads, enforcing their coherence
 * (a price or a limit requires a currency; a limit requires the default tier
 * prices; a tier price needs both input and output).
 */
final readonly class BillingInput
{
    public function __construct(
        public ?Currency $currency,
        public ?Pricing $pricing,
        public ?SpendLimit $limit,
    ) {
    }

    public static function fromFields(
        ?string $currency,
        ?float $priceDefaultInput,
        ?float $priceDefaultOutput,
        ?float $priceFastInput,
        ?float $priceFastOutput,
        ?float $limitDaily,
        ?float $limitMonthly,
    ): self {
        $defaultPricing = self::tierPricing($priceDefaultInput, $priceDefaultOutput, 'default');
        $fastPricing = self::tierPricing($priceFastInput, $priceFastOutput, 'fast');

        if ($fastPricing !== null && $defaultPricing === null) {
            throw new UnprocessableEntityHttpException('The default tier prices are required when a fast tier price is set.');
        }

        $pricing = $defaultPricing !== null ? new Pricing($defaultPricing, $fastPricing) : null;

        $hasLimit = $limitDaily !== null || $limitMonthly !== null;
        $limit = $hasLimit ? new SpendLimit($limitDaily, $limitMonthly) : null;

        if ($hasLimit && $pricing === null) {
            throw new UnprocessableEntityHttpException('A spend limit requires the default tier prices to be set.');
        }

        if (($pricing !== null || $limit !== null) && ($currency === null || trim($currency) === '')) {
            throw new UnprocessableEntityHttpException('A currency is required when prices or spend limits are set.');
        }

        return new self(
            currency: $currency !== null && trim($currency) !== '' ? new Currency($currency) : null,
            pricing: $pricing,
            limit: $limit,
        );
    }

    private static function tierPricing(?float $input, ?float $output, string $tier): ?TierPricing
    {
        if ($input === null && $output === null) {
            return null;
        }

        if ($input === null || $output === null) {
            throw new UnprocessableEntityHttpException(
                sprintf('Both the input and output prices are required for the %s tier.', $tier)
            );
        }

        return new TierPricing($input, $output);
    }
}
