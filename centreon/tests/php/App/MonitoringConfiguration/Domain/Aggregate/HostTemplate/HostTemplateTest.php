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

namespace Tests\App\MonitoringConfiguration\Domain\Aggregate\HostTemplate;

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroParentEnum;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;

final class HostTemplateTest extends TestCase
{
    public function testItHasNoMacrosByDefault(): void
    {
        self::assertSame([], (new HostTemplate(new HostTemplateId(1), new HostTemplateName('tpl')))->macrosAsInherited());
    }

    public function testItExposesItsOwnMacrosAsInheritedFromATemplate(): void
    {
        $template = new HostTemplate(
            new HostTemplateId(1),
            new HostTemplateName('tpl'),
            new Collection([new HostMacro(new HostMacroName('foo'), 'bar', isPassword: false, id: new HostMacroId(4))], HostMacro::class),
        );

        $macros = $template->macrosAsInherited();

        self::assertCount(1, $macros);
        self::assertSame(HostMacroParentEnum::Template, $macros[0]->parent);
        self::assertSame(4, $macros[0]->id?->value);
    }

    public function testItLoadsLazyMacrosOnlyWhenRead(): void
    {
        $calls = new \ArrayObject();
        $template = new HostTemplate(
            new HostTemplateId(1),
            new HostTemplateName('tpl'),
            new Collection(static function () use ($calls): array {
                $calls->append(true);

                return [];
            }, HostMacro::class),
        );

        self::assertCount(0, $calls);
        $template->macrosAsInherited();
        self::assertCount(1, $calls);
    }
}
