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

namespace Tests\App\MonitoringConfiguration\Domain\Aggregate\Host;

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroParentEnum;
use PHPUnit\Framework\TestCase;

final class HostMacroTest extends TestCase
{
    public function testItHoldsItsFields(): void
    {
        $macro = new HostMacro(new HostMacroName('community'), 'public', isPassword: false, id: new HostMacroId(4));

        self::assertSame('COMMUNITY', $macro->name->value);
        self::assertSame('public', $macro->value);
        self::assertFalse($macro->isPassword);
        self::assertSame(4, $macro->id?->value);
        self::assertTrue($macro->isDirect());
        self::assertFalse($macro->isInherited());
    }

    public function testItRejectsAValueLongerThan4096(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new HostMacro(new HostMacroName('big'), str_repeat('a', 4097), isPassword: false);
    }

    public function testAMacroWithAParentIsInherited(): void
    {
        $macro = new HostMacro(new HostMacroName('foo'), '', isPassword: false, parent: HostMacroParentEnum::Command);

        self::assertTrue($macro->isInherited());
        self::assertFalse($macro->isDirect());
    }

    public function testEquivalenceComparesNameValueAndPasswordOnly(): void
    {
        $direct = new HostMacro(new HostMacroName('foo'), 'bar', isPassword: false, id: new HostMacroId(1));
        $inherited = new HostMacro(new HostMacroName('FOO'), 'bar', isPassword: false, id: new HostMacroId(9), parent: HostMacroParentEnum::Template);

        self::assertTrue($direct->isEquivalentTo($inherited));
        self::assertFalse($direct->overrides($inherited));
    }

    public function testADifferentValueIsAnOverride(): void
    {
        $inherited = new HostMacro(new HostMacroName('foo'), 'bar', isPassword: false, parent: HostMacroParentEnum::Template);
        $direct = new HostMacro(new HostMacroName('foo'), 'baz', isPassword: false);

        self::assertFalse($direct->isEquivalentTo($inherited));
        self::assertTrue($direct->overrides($inherited));
    }

    public function testAnEmptyValueDiffersFromASetOne(): void
    {
        $inherited = new HostMacro(new HostMacroName('foo'), 'bar', isPassword: false, parent: HostMacroParentEnum::Template);

        self::assertTrue((new HostMacro(new HostMacroName('foo'), '', isPassword: false))->overrides($inherited));
    }

    public function testAChangeOfPasswordFlagAloneIsAnOverride(): void
    {
        $inherited = new HostMacro(new HostMacroName('foo'), 'bar', isPassword: false, parent: HostMacroParentEnum::Template);
        $direct = new HostMacro(new HostMacroName('foo'), 'bar', isPassword: true);

        self::assertTrue($direct->overrides($inherited));
    }

    public function testValuesAreComparedInTheirRawStoredForm(): void
    {
        // Two references are different values even if they might resolve to the same secret.
        $inherited = new HostMacro(new HostMacroName('pwd'), 'secret::vault::monitoring/hosts/a::_HOSTPWD', isPassword: true, parent: HostMacroParentEnum::Template);
        $direct = new HostMacro(new HostMacroName('pwd'), 'secret::vault::monitoring/hosts/b::_HOSTPWD', isPassword: true);

        self::assertTrue($direct->overrides($inherited));
    }

    public function testAMacroOfAnotherNameNeverOverrides(): void
    {
        $inherited = new HostMacro(new HostMacroName('foo'), 'bar', isPassword: false, parent: HostMacroParentEnum::Template);

        self::assertFalse((new HostMacro(new HostMacroName('other'), 'baz', isPassword: false))->overrides($inherited));
    }

    public function testItIsIdentifiedByItsParentAndId(): void
    {
        $macro = new HostMacro(new HostMacroName('foo'), 'bar', isPassword: false, id: new HostMacroId(3), parent: HostMacroParentEnum::Template);

        self::assertTrue($macro->isIdentifiedBy(HostMacroParentEnum::Template, new HostMacroId(3)));
        self::assertFalse($macro->isIdentifiedBy(HostMacroParentEnum::Command, new HostMacroId(3)));
        self::assertFalse($macro->isIdentifiedBy(null, new HostMacroId(3)));
        self::assertFalse($macro->isIdentifiedBy(HostMacroParentEnum::Template, new HostMacroId(4)));
    }

    public function testInheritedFromKeepsTheIdAndTagsTheParent(): void
    {
        $macro = (new HostMacro(new HostMacroName('foo'), 'bar', isPassword: true, id: new HostMacroId(3)))
            ->inheritedFrom(HostMacroParentEnum::Template);

        self::assertSame(3, $macro->id?->value);
        self::assertSame(HostMacroParentEnum::Template, $macro->parent);
        self::assertSame('bar', $macro->value);
        self::assertTrue($macro->isPassword);
    }

    public function testPromotingToDirectDropsTheSourceIdentity(): void
    {
        $macro = (new HostMacro(new HostMacroName('foo'), 'bar', isPassword: true, id: new HostMacroId(3), parent: HostMacroParentEnum::Template))
            ->promoteToDirect();

        self::assertNull($macro->id);
        self::assertTrue($macro->isDirect());
        self::assertSame('bar', $macro->value);
        self::assertTrue($macro->isPassword);
    }

    public function testRenameAndWithValueKeepTheIdentity(): void
    {
        $macro = (new HostMacro(new HostMacroName('foo'), 'bar', isPassword: false, id: new HostMacroId(3)))
            ->rename(new HostMacroName('baz'))
            ->withValue('qux', isPassword: true);

        self::assertSame('BAZ', $macro->name->value);
        self::assertSame('qux', $macro->value);
        self::assertTrue($macro->isPassword);
        self::assertSame(3, $macro->id?->value);
        self::assertTrue($macro->isDirect());
    }
}
