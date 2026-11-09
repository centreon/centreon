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

namespace Tests\App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate;

use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroParentEnum;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateName;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;

final class ServiceTemplateTest extends TestCase
{
    public function testItHasNoMacrosByDefault(): void
    {
        $template = new ServiceTemplate(new ServiceTemplateId(1), new ServiceTemplateName('tpl'));

        self::assertSame([], $template->macros->toArray());
        self::assertSame([], $template->macrosAsInherited());
    }

    public function testItExposesItsOwnMacrosAsInheritedFromATemplate(): void
    {
        $template = $this->template(new ServiceMacro(
            new ServiceMacroName('foo'),
            'bar',
            isPassword: true,
            description: 'a description',
            id: new ServiceMacroId(4),
        ));

        $macros = $template->macrosAsInherited();

        self::assertCount(1, $macros);
        self::assertSame(ServiceMacroParentEnum::Template, $macros[0]->parent);
        self::assertSame(4, $macros[0]->id?->value);
        self::assertSame('FOO', $macros[0]->name->value);
        self::assertSame('bar', $macros[0]->value);
        self::assertTrue($macros[0]->isPassword);
        self::assertSame('a description', $macros[0]->description);
    }

    public function testItRejectsAnAlreadyInheritedMacro(): void
    {
        $template = $this->template(new ServiceMacro(
            new ServiceMacroName('foo'),
            'bar',
            isPassword: false,
            parent: ServiceMacroParentEnum::Command,
        ));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A service template only owns its own macros.');

        $template->macrosAsInherited();
    }

    private function template(ServiceMacro ...$macros): ServiceTemplate
    {
        return new ServiceTemplate(
            new ServiceTemplateId(1),
            new ServiceTemplateName('tpl'),
            macros: new Collection(array_values($macros), ServiceMacro::class),
        );
    }
}
