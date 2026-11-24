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

namespace Tests\App\AiConfiguration\Infrastructure\ApiPlatform;

use App\AiConfiguration\Domain\Aggregate\AiProvider\Billing;
use App\AiConfiguration\Infrastructure\ApiPlatform\AiProvider\BillingInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class BillingInputTest extends TestCase
{
    /**
     * @return iterable<string, array{array{currency?: string, defaultInput?: float, defaultOutput?: float, fastInput?: float, fastOutput?: float, daily?: float, monthly?: float}, string}>
     */
    public static function provideInvalidBillings(): iterable
    {
        $priced = ['currency' => 'USD', 'defaultInput' => 3.0, 'defaultOutput' => 15.0];

        yield 'a zero daily limit' => [[...$priced, 'daily' => 0.0], 'The daily limit must be greater than zero.'];

        yield 'a negative monthly limit' => [[...$priced, 'monthly' => -5.0], 'The monthly limit must be greater than zero.'];

        yield 'a negative input price' => [[...$priced, 'defaultInput' => -1.0], 'The input price can not be negative.'];

        yield 'a negative fast output price' => [
            [...$priced, 'fastInput' => 1.0, 'fastOutput' => -1.0],
            'The output price can not be negative.',
        ];

        yield 'only the default input price' => [
            ['currency' => 'USD', 'defaultInput' => 3.0],
            'Both the input and output prices are required for the default tier.',
        ];

        yield 'only the fast output price' => [
            [...$priced, 'fastOutput' => 5.0],
            'Both the input and output prices are required for the fast tier.',
        ];

        yield 'a currency that is not an ISO code' => [[...$priced, 'currency' => 'dollars'], 'The currency must be a 3-letter ISO 4217 code.'];

        yield 'prices without a currency' => [
            ['defaultInput' => 3.0, 'defaultOutput' => 15.0],
            'A currency is required when prices or spend limits are set.',
        ];
    }

    /**
     * @param array{currency?: string, defaultInput?: float, defaultOutput?: float, fastInput?: float, fastOutput?: float, daily?: float, monthly?: float} $fields
     */
    #[DataProvider('provideInvalidBillings')]
    public function testAnInvalidBillingIsUnprocessable(array $fields, string $message): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage($message);

        $this->toBilling($fields);
    }

    public function testALowercaseCurrencyIsNormalized(): void
    {
        $billing = $this->toBilling(['currency' => 'eur', 'defaultInput' => 3.0, 'defaultOutput' => 15.0]);

        self::assertSame('EUR', $billing->currency?->value);
    }

    public function testFreePricesAreAccepted(): void
    {
        // a self-hosted model costs nothing per token
        $billing = $this->toBilling(['currency' => 'USD', 'defaultInput' => 0.0, 'defaultOutput' => 0.0]);

        self::assertSame(0.0, $billing->pricing?->default->inputPricePerMillion);
    }

    /**
     * @param array{currency?: string, defaultInput?: float, defaultOutput?: float, fastInput?: float, fastOutput?: float, daily?: float, monthly?: float} $fields
     */
    private function toBilling(array $fields): Billing
    {
        return BillingInput::toBilling(
            currency: $fields['currency'] ?? null,
            priceDefaultInput: $fields['defaultInput'] ?? null,
            priceDefaultOutput: $fields['defaultOutput'] ?? null,
            priceFastInput: $fields['fastInput'] ?? null,
            priceFastOutput: $fields['fastOutput'] ?? null,
            limitDaily: $fields['daily'] ?? null,
            limitMonthly: $fields['monthly'] ?? null,
        );
    }
}
