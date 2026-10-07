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

use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandLine;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandMacroTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandName;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroParentEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Host\InheritedHostMacros;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;

final class InheritedHostMacrosTest extends TestCase
{
    public function testTemplateMacrosAreTaggedWithTheirOriginAndKeepTheirId(): void
    {
        $inherited = InheritedHostMacros::resolve([$this->template(1, [$this->macro(11, 'tpl', 'value', true)])], []);

        $macro = $inherited->findByName(new HostMacroName('tpl'));
        self::assertInstanceOf(HostMacro::class, $macro);
        self::assertSame(HostMacroParentEnum::Template, $macro->parent);
        self::assertSame(11, $macro->id?->value);
        self::assertSame('value', $macro->value);
        self::assertTrue($macro->isPassword);
    }

    public function testTheTemplateNearestToTheHostWins(): void
    {
        $inherited = InheritedHostMacros::resolve([
            $this->template(1, [$this->macro(11, 'shared', 'from-nearest')]),
            $this->template(2, [$this->macro(21, 'shared', 'from-farther'), $this->macro(22, 'farther-only', 'x')]),
        ], []);

        self::assertSame('from-nearest', $inherited->findByName(new HostMacroName('shared'))?->value);
        self::assertSame('x', $inherited->findByName(new HostMacroName('farther-only'))?->value);
        self::assertCount(2, $inherited->toList());
    }

    public function testCheckCommandMacrosOnlyFillNamesNoTemplateDefines(): void
    {
        $command = $this->command(CommandTypeEnum::Check, '$USER1$/check -a $_HOSTSHARED$ -b $_HOSTCMDONLY$', [
            new CommandMacro(new CommandMacroId(31), 'CMDONLY', CommandMacroTypeEnum::Host),
        ]);

        $inherited = InheritedHostMacros::resolve([$this->template(1, [$this->macro(11, 'shared', 'from-template')])], [$command]);

        // A name both define resolves to the template's definition, value included.
        $shared = $inherited->findByName(new HostMacroName('shared'));
        self::assertSame(HostMacroParentEnum::Template, $shared?->parent);
        self::assertSame('from-template', $shared->value);
        self::assertSame(11, $shared->id?->value);
        $commandMacro = $inherited->findByName(new HostMacroName('cmdonly'));
        self::assertInstanceOf(HostMacro::class, $commandMacro);
        self::assertSame(HostMacroParentEnum::Command, $commandMacro->parent);
        self::assertSame(31, $commandMacro->id?->value);
        self::assertSame('', $commandMacro->value);
        self::assertFalse($commandMacro->isPassword);
    }

    public function testACommandMacroNeverRecordedIsStillInheritedWithoutId(): void
    {
        $inherited = InheritedHostMacros::resolve([], [$this->command(CommandTypeEnum::Check, '$USER1$/check -a $_HOSTFOO$')]);

        $macro = $inherited->findByName(new HostMacroName('foo'));
        self::assertInstanceOf(HostMacro::class, $macro);
        self::assertNull($macro->id);
    }

    public function testReservedSnmpMacrosAreNeverInheritedFromTheCommand(): void
    {
        $inherited = InheritedHostMacros::resolve(
            [],
            [$this->command(CommandTypeEnum::Check, '$USER1$/check -C $_HOSTSNMPCOMMUNITY$ -v $_HOSTSNMPVERSION$ -a $_HOSTFOO$')],
        );

        self::assertSame(['FOO'], array_map(static fn (HostMacro $macro): string => $macro->name->value, $inherited->toList()));
    }

    public function testServiceMacrosOfTheCheckCommandAreNeverInherited(): void
    {
        $inherited = InheritedHostMacros::resolve([], [$this->command(CommandTypeEnum::Check, '$USER1$/check -a $_HOSTFOO$ -p $_SERVICEPORT$')]);

        self::assertSame(['FOO'], array_map(static fn (HostMacro $macro): string => $macro->name->value, $inherited->toList()));
    }

    public function testTheFirstCommandDeclaringANameWins(): void
    {
        $inherited = InheritedHostMacros::resolve([], [
            $this->command(CommandTypeEnum::Check, '$USER1$/first -a $_HOSTSHARED$', [
                new CommandMacro(new CommandMacroId(1), 'SHARED', CommandMacroTypeEnum::Host),
            ]),
            $this->command(CommandTypeEnum::Check, '$USER1$/second -a $_HOSTSHARED$ -b $_HOSTSECONDONLY$', [
                new CommandMacro(new CommandMacroId(2), 'SHARED', CommandMacroTypeEnum::Host),
            ]),
        ]);

        self::assertSame(1, $inherited->findByName(new HostMacroName('shared'))?->id?->value);
        self::assertNotNull($inherited->findByName(new HostMacroName('secondonly')));
    }

    public function testANonCheckCommandAmongOthersIsSkipped(): void
    {
        $inherited = InheritedHostMacros::resolve([], [
            $this->command(CommandTypeEnum::Notification, '$USER1$/notify -a $_HOSTNOTIFY$'),
            $this->command(CommandTypeEnum::Check, '$USER1$/check -a $_HOSTCHECK$'),
        ]);

        self::assertSame(['CHECK'], array_map(static fn (HostMacro $macro): string => $macro->name->value, $inherited->toList()));
    }

    public function testANonCheckCommandContributesNothing(): void
    {
        $inherited = InheritedHostMacros::resolve([], [$this->command(CommandTypeEnum::Notification, '$USER1$/notify -a $_HOSTFOO$')]);

        self::assertSame([], $inherited->toList());
    }

    public function testFindBySourceUsesTheParentAsIdNamespace(): void
    {
        $inherited = InheritedHostMacros::resolve(
            [$this->template(1, [$this->macro(5, 'tpl', 'x')])],
            [$this->command(CommandTypeEnum::Check, '$USER1$/check -a $_HOSTCMD$', [
                new CommandMacro(new CommandMacroId(5), 'CMD', CommandMacroTypeEnum::Host),
            ])],
        );

        self::assertSame('TPL', $inherited->findBySource(HostMacroParentEnum::Template, new HostMacroId(5))?->name->value);
        self::assertSame('CMD', $inherited->findBySource(HostMacroParentEnum::Command, new HostMacroId(5))?->name->value);
        self::assertNull($inherited->findBySource(HostMacroParentEnum::Template, new HostMacroId(6)));
    }

    public function testAShadowedTemplateMacroCannotBeFoundBySource(): void
    {
        // Only the effective (nearest) definition is exposed, so only it can be addressed.
        $inherited = InheritedHostMacros::resolve([
            $this->template(1, [$this->macro(11, 'shared', 'near')]),
            $this->template(2, [$this->macro(21, 'shared', 'far')]),
        ], []);

        self::assertNull($inherited->findBySource(HostMacroParentEnum::Template, new HostMacroId(21)));
    }

    public function testWithoutRedundantDropsOnlyMacrosEquivalentToTheInheritedOne(): void
    {
        $inherited = InheritedHostMacros::resolve([$this->template(1, [
            $this->macro(11, 'same', 'v'),
            $this->macro(12, 'other-value', 'v'),
        ])], []);

        $kept = $inherited->withoutRedundant([
            new HostMacro(new HostMacroName('same'), 'v', isPassword: false),
            new HostMacro(new HostMacroName('other-value'), 'w', isPassword: false),
            new HostMacro(new HostMacroName('own'), 'v', isPassword: false),
        ]);

        self::assertSame(['OTHER-VALUE', 'OWN'], array_map(static fn (HostMacro $macro): string => $macro->name->value, $kept));
    }

    public function testNotOverriddenByKeepsOnlyTheInheritedMacrosNoDirectMacroShadows(): void
    {
        $inherited = InheritedHostMacros::resolve([$this->template(1, [
            $this->macro(11, 'shadowed', 'v'),
            $this->macro(12, 'kept', 'v'),
        ])], []);

        $remaining = $inherited->notOverriddenBy([new HostMacro(new HostMacroName('shadowed'), 'other', isPassword: false)]);

        self::assertSame(['KEPT'], array_map(static fn (HostMacro $macro): string => $macro->name->value, $remaining));
    }

    public function testEffectiveWithListsTheDirectMacrosThenTheInheritedOnesTheyDoNotShadow(): void
    {
        $inherited = InheritedHostMacros::resolve([$this->template(1, [
            $this->macro(11, 'shadowed', 'v'),
            $this->macro(12, 'kept', 'v'),
        ])], []);

        $effective = $inherited->effectiveWith([
            new HostMacro(new HostMacroName('shadowed'), 'other', isPassword: false),
            new HostMacro(new HostMacroName('own'), 'v', isPassword: false),
        ]);

        self::assertSame(
            [['SHADOWED', 'other'], ['OWN', 'v'], ['KEPT', 'v']],
            array_map(static fn (HostMacro $macro): array => [$macro->name->value, $macro->value], $effective),
        );
    }

    public function testNoneInheritsNothing(): void
    {
        self::assertSame([], InheritedHostMacros::none()->toList());
    }

    /**
     * @param list<HostMacro> $macros
     */
    private function template(int $id, array $macros): HostTemplate
    {
        return new HostTemplate(new HostTemplateId($id), new HostTemplateName('tpl-' . $id), new Collection($macros, HostMacro::class));
    }

    private function macro(int $id, string $name, string $value, bool $isPassword = false): HostMacro
    {
        return new HostMacro(new HostMacroName($name), $value, $isPassword, new HostMacroId($id));
    }

    /**
     * @param list<CommandMacro> $knownMacros
     */
    private function command(CommandTypeEnum $type, string $line, array $knownMacros = []): Command
    {
        $command = new Command(
            new CommandId(1),
            new CommandName('cmd'),
            $type,
            new CommandLine($line),
            isShellEnabled: false,
            isActivated: true,
            isFromMonitoringConnector: false,
            connector: null,
            comment: null,
        );
        $command->setStoredMacros($knownMacros);

        return $command;
    }
}
