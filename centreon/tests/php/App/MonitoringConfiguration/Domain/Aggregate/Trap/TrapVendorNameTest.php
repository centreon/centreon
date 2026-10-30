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

namespace Tests\App\MonitoringConfiguration\Domain\Aggregate\Trap;

use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapVendorName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TrapVendorNameTest extends TestCase
{
    // Literal bounds: the maximum must equal the size of the `traps_vendor.name` column.
    /**
     * @return iterable<string, array{string}>
     */
    public static function provideBounds(): iterable
    {
        yield 'minimum length' => [str_repeat('a', 1)];

        yield 'maximum length' => [str_repeat('a', 254)];
    }

    #[DataProvider('provideBounds')]
    public function testItAcceptsTheBounds(string $value): void
    {
        self::assertSame($value, (new TrapVendorName($value))->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideOutOfBounds(): iterable
    {
        yield 'empty' => [''];

        yield 'one over the maximum length' => [str_repeat('a', 255)];
    }

    #[DataProvider('provideOutOfBounds')]
    public function testItRejectsOutOfBounds(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new TrapVendorName($value);
    }

    public function testItKeepsTheValueUntrimmed(): void
    {
        self::assertSame(' a name ', (new TrapVendorName(' a name '))->value);
    }
}
