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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host;

use App\MonitoringConfiguration\Domain\Aggregate\Host\CheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroParentEnum;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\HostCheckOptionsOutputTransformer;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\HostMacroTransformer;
use PHPUnit\Framework\TestCase;

final class HostCheckOptionsOutputTransformerTest extends TestCase
{
    public function testItOutputsTheEffectiveMacrosGivenAsExtraData(): void
    {
        $own = new HostMacro(new HostMacroName('OWN'), 'stored', false, new HostMacroId(1));
        $inherited = new HostMacro(new HostMacroName('INHERITED'), 'secret', true, new HostMacroId(2), HostMacroParentEnum::Template);

        $output = (new HostCheckOptionsOutputTransformer(new HostMacroTransformer()))->transform(
            new CheckOptions(null, [], [new HostMacro(new HostMacroName('NOT_LISTED'), 'ignored', false)]),
            ['commands' => [], 'macros' => [$own, $inherited]],
        );

        self::assertCount(2, $output->macros);
        self::assertSame(1, $output->macros[0]->id);
        self::assertSame('OWN', $output->macros[0]->name);
        self::assertSame('stored', $output->macros[0]->value);
        self::assertNull($output->macros[0]->parent);
        self::assertSame(2, $output->macros[1]->id);
        self::assertSame('INHERITED', $output->macros[1]->name);
        self::assertNull($output->macros[1]->value, 'A password macro value is never echoed.');
        self::assertSame('template', $output->macros[1]->parent);
    }

    public function testItRequiresTheMacros(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new HostCheckOptionsOutputTransformer(new HostMacroTransformer()))->transform(new CheckOptions(null), ['commands' => []]);
    }
}
