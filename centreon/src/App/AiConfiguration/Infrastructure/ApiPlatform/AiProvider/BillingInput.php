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

use App\AiConfiguration\Domain\Aggregate\AiProvider\Billing;
use App\AiConfiguration\Domain\Aggregate\AiProvider\Currency;
use App\AiConfiguration\Domain\Aggregate\AiProvider\SpendLimit;
use App\AiConfiguration\Domain\Aggregate\AiProvider\TierPricing;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Maps the flat billing fields of the create/update payloads to the Billing
 * value object, which enforces their coherence. Only the shape of the payload
 * is checked here: a tier price needs both its input and output prices.
 */
final readonly class BillingInput
{
    /**
     * @throws UnprocessableEntityHttpException when the fields are not valid or do not go together
     */
    public static function toBilling(
        ?string $currency,
        ?float $priceDefaultInput,
        ?float $priceDefaultOutput,
        ?float $priceFastInput,
        ?float $priceFastOutput,
        ?float $limitDaily,
        ?float $limitMonthly,
    ): Billing {
        try {
            return Billing::fromTierPrices(
                currency: $currency !== null && trim($currency) !== '' ? new Currency($currency) : null,
                defaultPrice: self::tierPricing($priceDefaultInput, $priceDefaultOutput, 'default'),
                fastPrice: self::tierPricing($priceFastInput, $priceFastOutput, 'fast'),
                limit: $limitDaily !== null || $limitMonthly !== null ? new SpendLimit($limitDaily, $limitMonthly) : null,
            );
        } catch (\InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }
    }

    /**
     * @throws UnprocessableEntityHttpException when only one of the prices is set
     * @throws \InvalidArgumentException when a price is not valid
     */
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
