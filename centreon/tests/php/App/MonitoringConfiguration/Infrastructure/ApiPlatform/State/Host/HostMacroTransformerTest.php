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

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroParentEnum;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\HostMacroInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\HostMacroTransformer;
use PHPUnit\Framework\TestCase;

final class HostMacroTransformerTest extends TestCase
{
    public function testItNeverEchoesAPasswordValue(): void
    {
        $output = (new HostMacroTransformer())->transform(
            new HostMacro(new HostMacroName('pwd'), 'secret::vault::x', isPassword: true, id: new HostMacroId(3), parent: HostMacroParentEnum::Template),
        );

        self::assertSame(3, $output->id);
        self::assertSame('PWD', $output->name);
        self::assertNull($output->value);
        self::assertTrue($output->isPassword);
        self::assertSame('template', $output->parent);
    }

    public function testADirectMacroHasNoParent(): void
    {
        $output = (new HostMacroTransformer())->transform(new HostMacro(new HostMacroName('foo'), 'bar', isPassword: false, id: new HostMacroId(4)));

        self::assertSame('bar', $output->value);
        self::assertNull($output->parent);
    }

    public function testItTurnsAnInputIntoAChange(): void
    {
        $change = (new HostMacroTransformer())->toChange(new HostMacroInput('pwd', null, isPassword: true, id: 3, parent: 'command'));

        self::assertSame('PWD', $change->name->value);
        self::assertNull($change->value);
        self::assertTrue($change->isPassword);
        self::assertSame(3, $change->id?->value);
        self::assertSame(HostMacroParentEnum::Command, $change->parent);
    }
}
