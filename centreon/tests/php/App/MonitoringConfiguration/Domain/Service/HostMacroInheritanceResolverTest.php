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

namespace Tests\App\MonitoringConfiguration\Domain\Service;

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroName;
use App\MonitoringConfiguration\Domain\Service\HostMacroInheritanceResolver;
use PHPUnit\Framework\TestCase;

final class HostMacroInheritanceResolverTest extends TestCase
{
    private HostMacroInheritanceResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new HostMacroInheritanceResolver();
    }

    public function testItKeepsAMacroThatIsNotInherited(): void
    {
        $own = new HostMacro(new HostMacroName('own'), 'value', isPassword: false);

        $kept = $this->resolver->keepOverridesOnly([$own], []);

        self::assertSame([$own], $kept);
    }

    public function testItDropsAMacroIdenticalToAnInheritedOne(): void
    {
        $submitted = new HostMacro(new HostMacroName('community'), 'public', isPassword: false);
        $inherited = new HostMacro(new HostMacroName('community'), 'public', isPassword: false);

        self::assertSame([], $this->resolver->keepOverridesOnly([$submitted], [$inherited]));
    }

    public function testItKeepsAMacroThatOverridesTheInheritedValue(): void
    {
        $submitted = new HostMacro(new HostMacroName('community'), 'private', isPassword: false);
        $inherited = new HostMacro(new HostMacroName('community'), 'public', isPassword: false);

        self::assertSame([$submitted], $this->resolver->keepOverridesOnly([$submitted], [$inherited]));
    }

    public function testItKeepsAMacroWhenOnlyThePasswordFlagDiffers(): void
    {
        $submitted = new HostMacro(new HostMacroName('token'), 'same', isPassword: true);
        $inherited = new HostMacro(new HostMacroName('token'), 'same', isPassword: false);

        self::assertSame([$submitted], $this->resolver->keepOverridesOnly([$submitted], [$inherited]));
    }

    public function testItReindexesTheKeptMacrosAsAList(): void
    {
        $dropped = new HostMacro(new HostMacroName('a'), 'x', isPassword: false);
        $kept = new HostMacro(new HostMacroName('b'), 'y', isPassword: false);

        $result = $this->resolver->keepOverridesOnly(
            [$dropped, $kept],
            [new HostMacro(new HostMacroName('a'), 'x', isPassword: false)],
        );

        self::assertSame([$kept], $result);
    }
}
