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

namespace Tests\App\Shared\Domain;

use App\Shared\Domain\TriStateEnum;
use PHPUnit\Framework\TestCase;

final class TriStateEnumTest extends TestCase
{
    /**
     * @return iterable<string, array{string, TriStateEnum}>
     */
    public static function contractValueProvider(): iterable
    {
        yield 'false' => ['false', TriStateEnum::False];

        yield 'true' => ['true', TriStateEnum::True];

        yield 'use_default' => ['use_default', TriStateEnum::UseDefault];
    }

    /**
     * @dataProvider contractValueProvider
     */
    public function testBackingValueIsTheContractString(string $contractValue, TriStateEnum $case): void
    {
        self::assertSame($contractValue, $case->value);
    }

    /**
     * @dataProvider contractValueProvider
     */
    public function testFromContractString(string $contractValue, TriStateEnum $case): void
    {
        self::assertSame($case, TriStateEnum::from($contractValue));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonContractValueProvider(): iterable
    {
        yield 'unknown word' => ['maybe'];

        // The database column values ('0'/'1'/'2') are a persistence detail, not contract values.
        yield 'raw column value' => ['2'];
    }

    /**
     * @dataProvider nonContractValueProvider
     */
    public function testTryFromRejectsUnknownOrRawColumnValues(string $rawValue): void
    {
        self::assertNull(TriStateEnum::tryFrom($rawValue));
    }
}
