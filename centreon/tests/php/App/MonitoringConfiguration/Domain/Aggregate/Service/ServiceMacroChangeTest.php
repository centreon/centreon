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

namespace Tests\App\MonitoringConfiguration\Domain\Aggregate\Service;

use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroChange;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroParentEnum;
use PHPUnit\Framework\TestCase;

final class ServiceMacroChangeTest extends TestCase
{
    public function testItHoldsItsFields(): void
    {
        $change = new ServiceMacroChange(
            new ServiceMacroName('token'),
            'value',
            isPassword: true,
            id: new ServiceMacroId(4),
            parent: ServiceMacroParentEnum::Template,
        );

        self::assertSame(
            ['TOKEN', 'value', true, 4, ServiceMacroParentEnum::Template],
            [$change->name->value, $change->value, $change->isPassword, $change->id?->value, $change->parent],
        );
    }

    public function testANewMacroWithAValueIsValid(): void
    {
        $change = new ServiceMacroChange(new ServiceMacroName('foo'), 'bar', isPassword: false);

        self::assertTrue($change->isNew());
        self::assertFalse($change->keepsStoredValue());
        self::assertNull($change->parent);
    }

    public function testAnEmptyStringIsAValue(): void
    {
        $change = new ServiceMacroChange(new ServiceMacroName('foo'), '', isPassword: false);

        self::assertFalse($change->keepsStoredValue());
    }

    public function testAnExistingPasswordMayKeepItsStoredValue(): void
    {
        $change = new ServiceMacroChange(new ServiceMacroName('pwd'), null, isPassword: true, id: new ServiceMacroId(1));

        self::assertFalse($change->isNew());
        self::assertTrue($change->keepsStoredValue());
    }

    public function testANewMacroNeedsAValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServiceMacroChange(new ServiceMacroName('pwd'), null, isPassword: true);
    }

    public function testANonPasswordMacroNeedsAValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServiceMacroChange(new ServiceMacroName('foo'), null, isPassword: false, id: new ServiceMacroId(1));
    }

    public function testItAcceptsAValueOf4096Characters(): void
    {
        $change = new ServiceMacroChange(new ServiceMacroName('big'), str_repeat('a', 4096), isPassword: false);

        self::assertSame(4096, mb_strlen((string) $change->value));
    }

    public function testItRejectsAValueLongerThan4096(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServiceMacroChange(new ServiceMacroName('big'), str_repeat('a', 4097), isPassword: false);
    }
}
