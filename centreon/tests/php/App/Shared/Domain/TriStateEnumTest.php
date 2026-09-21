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
    public function testBackingValuesAreTheContractStrings(): void
    {
        self::assertSame('false', TriStateEnum::False->value);
        self::assertSame('true', TriStateEnum::True->value);
        self::assertSame('use_default', TriStateEnum::UseDefault->value);
    }

    public function testFromContractString(): void
    {
        self::assertSame(TriStateEnum::UseDefault, TriStateEnum::from('use_default'));
        self::assertSame(TriStateEnum::True, TriStateEnum::from('true'));
    }

    public function testTryFromRejectsUnknownOrRawColumnValues(): void
    {
        self::assertNull(TriStateEnum::tryFrom('maybe'));
        // The database column values ('0'/'1'/'2') are a persistence detail, not contract values.
        self::assertNull(TriStateEnum::tryFrom('2'));
    }
}
