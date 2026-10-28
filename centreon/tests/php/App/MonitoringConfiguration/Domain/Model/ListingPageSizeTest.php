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

namespace Tests\App\MonitoringConfiguration\Domain\Model;

use App\MonitoringConfiguration\Domain\Model\ListingPageSize;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ListingPageSizeTest extends TestCase
{
    /**
     * @return iterable<string, array{?string, int}>
     */
    public static function provideOptionValues(): iterable
    {
        yield 'a positive integer' => ['50', 50];

        yield 'leading zeros, read as a decimal integer' => ['050', 50];

        yield 'surrounding blanks' => [' 50 ', 50];

        yield 'one' => ['1', 1];

        yield 'a large value, no maximum' => ['100000', 100000];

        yield 'the largest integer' => ['9223372036854775807', PHP_INT_MAX];

        yield 'absent or NULL' => [null, 30];

        yield 'empty' => ['', 30];

        yield 'blank' => ['   ', 30];

        yield 'zero' => ['0', 30];

        yield 'only zeros' => ['000', 30];

        yield 'negative' => ['-5', 30];

        yield 'trailing non-digits' => ['50abc', 30];

        yield 'decimal' => ['1.5', 30];

        yield 'explicit plus sign' => ['+50', 30];

        yield 'beyond the largest integer' => ['9223372036854775808', 30];

        yield 'beyond the largest integer with leading zeros' => ['009223372036854775808', 30];

        yield 'far beyond the largest integer' => ['99999999999999999999999', 30];
    }

    #[DataProvider('provideOptionValues')]
    public function testThePageSizeComesFromTheOptionValueOrDefaultsToThirty(?string $optionValue, int $expected): void
    {
        self::assertSame($expected, ListingPageSize::fromOptionValue($optionValue)->value);
    }

    public function testAPageSizeIsPositive(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ListingPageSize(0);
    }
}
