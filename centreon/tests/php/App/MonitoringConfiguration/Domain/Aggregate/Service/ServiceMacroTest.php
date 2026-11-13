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

use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroParentEnum;
use PHPUnit\Framework\TestCase;

final class ServiceMacroTest extends TestCase
{
    public function testItHoldsItsFields(): void
    {
        $macro = new ServiceMacro(new ServiceMacroName('token'), 'value', isPassword: false, description: 'API token');

        self::assertSame('TOKEN', $macro->name->value);
        self::assertSame('value', $macro->value);
        self::assertFalse($macro->isPassword);
        self::assertSame('API token', $macro->description);
        self::assertNull($macro->id);
        self::assertNull($macro->parent);
        self::assertFalse($macro->isInherited());
    }

    public function testItHoldsItsIdAndItsParent(): void
    {
        $macro = new ServiceMacro(
            new ServiceMacroName('token'),
            'value',
            isPassword: false,
            id: new ServiceMacroId(9),
            parent: ServiceMacroParentEnum::Command,
        );

        self::assertSame(9, $macro->id?->value);
        self::assertSame(ServiceMacroParentEnum::Command, $macro->parent);
        self::assertTrue($macro->isInherited());
    }

    public function testItRejectsANonPositiveId(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServiceMacroId(0);
    }

    public function testInheritedFromKeepsTheMacroAndTagsTheParent(): void
    {
        $macro = new ServiceMacro(new ServiceMacroName('token'), 'v', isPassword: true, description: 'API token', id: new ServiceMacroId(4));

        $inherited = $macro->inheritedFrom(ServiceMacroParentEnum::Template);

        self::assertSame(ServiceMacroParentEnum::Template, $inherited->parent);
        self::assertSame(4, $inherited->id?->value);
        self::assertSame('TOKEN', $inherited->name->value);
        self::assertSame('v', $inherited->value);
        self::assertTrue($inherited->isPassword);
        self::assertSame('API token', $inherited->description);
    }

    public function testItRejectsAValueLongerThan4096(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServiceMacro(new ServiceMacroName('big'), str_repeat('a', 4097), isPassword: false);
    }

    public function testItAllowsANullDescription(): void
    {
        $macro = new ServiceMacro(new ServiceMacroName('secret'), 's3cr3t', isPassword: true);

        self::assertNull($macro->description);
        self::assertTrue($macro->isPassword);
    }

    public function testItAcceptsADescriptionAtTheByteLimit(): void
    {
        $macro = new ServiceMacro(
            new ServiceMacroName('big'),
            'v',
            isPassword: false,
            description: str_repeat('a', ServiceMacro::MAX_DESCRIPTION_LENGTH),
        );

        self::assertSame(ServiceMacro::MAX_DESCRIPTION_LENGTH, mb_strlen((string) $macro->description, '8bit'));
    }

    public function testItRejectsADescriptionExceedingTheByteLimit(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        // A single multibyte character is 2 bytes: MAX_DESCRIPTION_LENGTH such characters are within the
        // character limit but twice the byte budget of the TEXT column.
        new ServiceMacro(
            new ServiceMacroName('big'),
            'v',
            isPassword: false,
            description: str_repeat('é', ServiceMacro::MAX_DESCRIPTION_LENGTH),
        );
    }

    public function testNamesAreComparedUpperCased(): void
    {
        $macro = new ServiceMacro(new ServiceMacroName('foo'), 'bar', isPassword: false);

        self::assertTrue($macro->hasSameNameAs(new ServiceMacro(new ServiceMacroName('FOO'), 'other', isPassword: true)));
        self::assertFalse($macro->hasSameNameAs(new ServiceMacro(new ServiceMacroName('food'), 'bar', isPassword: false)));
    }

    /**
     * Identity (id), origin (parent) and description are not compared: an own macro equivalent to the macro it
     * shadows is redundant whatever row it lives in.
     */
    public function testEquivalenceComparesNameValueAndPasswordOnly(): void
    {
        $own = new ServiceMacro(new ServiceMacroName('foo'), 'bar', isPassword: false, description: 'own', id: new ServiceMacroId(1));
        $inherited = new ServiceMacro(new ServiceMacroName('FOO'), 'bar', isPassword: false, description: 'template', id: new ServiceMacroId(9), parent: ServiceMacroParentEnum::Template);

        self::assertTrue($own->isEquivalentTo($inherited));
    }

    public function testADifferentValueIsNotEquivalent(): void
    {
        $inherited = new ServiceMacro(new ServiceMacroName('foo'), 'bar', isPassword: false, parent: ServiceMacroParentEnum::Template);

        self::assertFalse((new ServiceMacro(new ServiceMacroName('foo'), 'baz', isPassword: false))->isEquivalentTo($inherited));
    }

    public function testAnEmptyValueDiffersFromASetOne(): void
    {
        $inherited = new ServiceMacro(new ServiceMacroName('foo'), 'bar', isPassword: false, parent: ServiceMacroParentEnum::Template);

        self::assertFalse((new ServiceMacro(new ServiceMacroName('foo'), '', isPassword: false))->isEquivalentTo($inherited));
    }

    public function testAChangeOfPasswordFlagAloneIsNotEquivalent(): void
    {
        $inherited = new ServiceMacro(new ServiceMacroName('foo'), 'bar', isPassword: false, parent: ServiceMacroParentEnum::Template);

        self::assertFalse((new ServiceMacro(new ServiceMacroName('foo'), 'bar', isPassword: true))->isEquivalentTo($inherited));
    }

    public function testValuesAreComparedInTheirRawStoredForm(): void
    {
        // Two references are different values even if they might resolve to the same secret.
        $inherited = new ServiceMacro(new ServiceMacroName('pwd'), 'secret::vault::monitoring/services/a::_SERVICEPWD', isPassword: true, parent: ServiceMacroParentEnum::Template);
        $own = new ServiceMacro(new ServiceMacroName('pwd'), 'secret::vault::monitoring/services/b::_SERVICEPWD', isPassword: true);

        self::assertFalse($own->isEquivalentTo($inherited));
    }

    public function testAMacroOfAnotherNameIsNotEquivalent(): void
    {
        $inherited = new ServiceMacro(new ServiceMacroName('foo'), 'bar', isPassword: false, parent: ServiceMacroParentEnum::Template);

        self::assertFalse((new ServiceMacro(new ServiceMacroName('other'), 'bar', isPassword: false))->isEquivalentTo($inherited));
    }

    public function testItIsIdentifiedByItsParentAndId(): void
    {
        $inherited = new ServiceMacro(new ServiceMacroName('foo'), 'bar', isPassword: false, id: new ServiceMacroId(3), parent: ServiceMacroParentEnum::Template);
        $own = new ServiceMacro(new ServiceMacroName('foo'), 'bar', isPassword: false, id: new ServiceMacroId(3));

        self::assertTrue($inherited->isIdentifiedBy(ServiceMacroParentEnum::Template, new ServiceMacroId(3)));
        self::assertFalse($inherited->isIdentifiedBy(ServiceMacroParentEnum::Command, new ServiceMacroId(3)));
        self::assertFalse($inherited->isIdentifiedBy(null, new ServiceMacroId(3)));
        self::assertFalse($inherited->isIdentifiedBy(ServiceMacroParentEnum::Template, new ServiceMacroId(4)));
        self::assertTrue($own->isIdentifiedBy(null, new ServiceMacroId(3)));
        self::assertFalse($own->isIdentifiedBy(ServiceMacroParentEnum::Template, new ServiceMacroId(3)));
    }

    public function testAMacroWithoutIdIsIdentifiedByNoId(): void
    {
        $macro = new ServiceMacro(new ServiceMacroName('foo'), 'bar', isPassword: false);

        self::assertFalse($macro->isIdentifiedBy(null, new ServiceMacroId(1)));
    }

    public function testRenameKeepsEverythingButTheName(): void
    {
        $macro = (new ServiceMacro(new ServiceMacroName('foo'), 'bar', isPassword: true, description: 'kept', id: new ServiceMacroId(3)))
            ->rename(new ServiceMacroName('baz'));

        self::assertSame(
            ['BAZ', 'bar', true, 'kept', 3, null],
            [$macro->name->value, $macro->value, $macro->isPassword, $macro->description, $macro->id?->value, $macro->parent],
        );
    }

    public function testWithValueKeepsEverythingButTheValueAndThePasswordFlag(): void
    {
        $macro = (new ServiceMacro(new ServiceMacroName('foo'), 'bar', isPassword: false, description: 'kept', id: new ServiceMacroId(3), parent: ServiceMacroParentEnum::Command))
            ->withValue('qux', isPassword: true);

        self::assertSame(
            ['FOO', 'qux', true, 'kept', 3, ServiceMacroParentEnum::Command],
            [$macro->name->value, $macro->value, $macro->isPassword, $macro->description, $macro->id?->value, $macro->parent],
        );
    }
}
