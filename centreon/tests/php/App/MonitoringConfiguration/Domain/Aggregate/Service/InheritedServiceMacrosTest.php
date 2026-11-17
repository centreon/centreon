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

use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandLine;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandMacroTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandName;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Service\InheritedServiceMacros;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroParentEnum;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateName;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;

final class InheritedServiceMacrosTest extends TestCase
{
    public function testTemplateMacrosAreTaggedWithTheirOriginAndKeepTheirId(): void
    {
        $inherited = InheritedServiceMacros::resolve([$this->template(1, [$this->macro(11, 'tpl', 'value', true)])], []);

        $macro = $this->onlyMacro($inherited->effectiveWith([]));
        self::assertSame(ServiceMacroParentEnum::Template, $macro->parent);
        self::assertSame(11, $macro->id?->value);
        self::assertSame('TPL', $macro->name->value);
        self::assertSame('value', $macro->value);
        self::assertTrue($macro->isPassword);
    }

    public function testTheTemplateNearestToTheServiceWins(): void
    {
        $inherited = InheritedServiceMacros::resolve([
            $this->template(1, [$this->macro(11, 'shared', 'from-nearest')]),
            $this->template(2, [$this->macro(21, 'shared', 'from-farther'), $this->macro(22, 'farther-only', 'x')]),
        ], []);

        self::assertSame(
            [['SHARED', 'from-nearest', 11], ['FARTHER-ONLY', 'x', 22]],
            $this->describe($inherited->effectiveWith([])),
        );
    }

    public function testCheckCommandMacrosOnlyFillNamesNoTemplateDefines(): void
    {
        $command = $this->command(CommandTypeEnum::Check, '$USER1$/check -a $_SERVICESHARED$ -b $_SERVICECMDONLY$', [
            new CommandMacro(new CommandMacroId(31), 'CMDONLY', CommandMacroTypeEnum::Service),
        ]);

        $inherited = InheritedServiceMacros::resolve([$this->template(1, [$this->macro(11, 'shared', 'from-template')])], [$command]);

        [$shared, $commandMacro] = $inherited->effectiveWith([]);
        self::assertSame(ServiceMacroParentEnum::Template, $shared->parent);
        self::assertSame('from-template', $shared->value);
        self::assertSame(11, $shared->id?->value);
        self::assertSame('CMDONLY', $commandMacro->name->value);
        self::assertSame(ServiceMacroParentEnum::Command, $commandMacro->parent);
        self::assertSame(31, $commandMacro->id?->value);
        self::assertSame('', $commandMacro->value);
        self::assertFalse($commandMacro->isPassword);
    }

    public function testACommandMacroNeverRecordedIsStillInheritedWithoutId(): void
    {
        $inherited = InheritedServiceMacros::resolve([], [$this->command(CommandTypeEnum::Check, '$USER1$/check -a $_SERVICEFOO$')]);

        $macro = $this->onlyMacro($inherited->effectiveWith([]));
        self::assertSame('FOO', $macro->name->value);
        self::assertNull($macro->id);
    }

    public function testHostMacrosOfTheCheckCommandAreNeverInherited(): void
    {
        $inherited = InheritedServiceMacros::resolve([], [$this->command(CommandTypeEnum::Check, '$USER1$/check -a $_HOSTFOO$ -p $_SERVICEPORT$')]);

        self::assertSame(['PORT'], array_map(static fn (ServiceMacro $macro): string => $macro->name->value, $inherited->effectiveWith([])));
    }

    /**
     * As legacy: the SNMP community and version are not custom macros a service inherits from its check command.
     */
    public function testTheSnmpServiceMacrosOfTheCheckCommandAreNeverInherited(): void
    {
        $inherited = InheritedServiceMacros::resolve(
            [],
            [$this->command(CommandTypeEnum::Check, '$USER1$/check -C $_SERVICESNMPCOMMUNITY$ -v $_SERVICESNMPVERSION$ -p $_SERVICEPORT$')],
        );

        self::assertSame(['PORT'], array_map(static fn (ServiceMacro $macro): string => $macro->name->value, $inherited->effectiveWith([])));
    }

    /**
     * Only the check command's SNMP macros are left out: a template macro of the same name is a custom macro,
     * inherited like any other.
     */
    public function testATemplateMacroNamedLikeAnSnmpSettingIsStillInherited(): void
    {
        $inherited = InheritedServiceMacros::resolve(
            [$this->template(1, [$this->macro(11, 'snmpcommunity', 'public')])],
            [$this->command(CommandTypeEnum::Check, '$USER1$/check -C $_SERVICESNMPCOMMUNITY$')],
        );

        self::assertSame(
            [['SNMPCOMMUNITY', 'public', 11, ServiceMacroParentEnum::Template]],
            array_map(
                static fn (ServiceMacro $macro): array => [$macro->name->value, $macro->value, $macro->id?->value, $macro->parent],
                $inherited->effectiveWith([]),
            ),
        );
    }

    public function testANonCheckCommandContributesNothing(): void
    {
        $inherited = InheritedServiceMacros::resolve([], [$this->command(CommandTypeEnum::Notification, '$USER1$/notify $_SERVICEFOO$')]);

        self::assertSame([], $inherited->effectiveWith([]));
    }

    public function testEffectiveWithListsTheOwnMacrosThenTheInheritedOnesTheyDoNotShadow(): void
    {
        $inherited = InheritedServiceMacros::resolve([$this->template(1, [
            $this->macro(11, 'shadowed', 'v'),
            $this->macro(12, 'kept', 'v'),
        ])], []);

        $effective = $inherited->effectiveWith([
            new ServiceMacro(new ServiceMacroName('shadowed'), 'other', isPassword: false, id: new ServiceMacroId(1)),
            new ServiceMacro(new ServiceMacroName('own'), 'v', isPassword: false, id: new ServiceMacroId(2)),
        ]);

        self::assertSame(
            [['SHADOWED', 'other', 1], ['OWN', 'v', 2], ['KEPT', 'v', 12]],
            $this->describe($effective),
        );
    }

    public function testFindByNameReturnsTheInheritedMacroOfThatName(): void
    {
        $inherited = InheritedServiceMacros::resolve([$this->template(1, [$this->macro(11, 'tpl', 'v')])], []);

        self::assertSame(11, $inherited->findByName(new ServiceMacroName('TPL'))?->id?->value);
        self::assertNull($inherited->findByName(new ServiceMacroName('other')));
    }

    /**
     * The parent is the id namespace: the same id under another origin is another macro.
     */
    public function testFindBySourceMatchesTheOriginAndTheId(): void
    {
        $command = $this->command(CommandTypeEnum::Check, '$USER1$/check -a $_SERVICECMD$', [
            new CommandMacro(new CommandMacroId(11), 'CMD', CommandMacroTypeEnum::Service),
        ]);
        $inherited = InheritedServiceMacros::resolve([$this->template(1, [$this->macro(11, 'tpl', 'v')])], [$command]);

        self::assertSame('TPL', $inherited->findBySource(ServiceMacroParentEnum::Template, new ServiceMacroId(11))?->name->value);
        self::assertSame('CMD', $inherited->findBySource(ServiceMacroParentEnum::Command, new ServiceMacroId(11))?->name->value);
        self::assertNull($inherited->findBySource(ServiceMacroParentEnum::Template, new ServiceMacroId(12)));
    }

    /**
     * A macro equivalent to the inherited macro of its name (name, value, password flag) is not kept; one that
     * differs in value or flag, or has no inherited counterpart, is kept, in its order.
     */
    public function testWithoutRedundantDropsTheMacrosEquivalentToTheInheritedOnes(): void
    {
        $inherited = InheritedServiceMacros::resolve([$this->template(1, [
            $this->macro(11, 'same', 'v'),
            $this->macro(12, 'other-value', 'v'),
            $this->macro(13, 'other-flag', 'v'),
        ])], []);

        $kept = $inherited->withoutRedundant([
            new ServiceMacro(new ServiceMacroName('same'), 'v', isPassword: false),
            new ServiceMacro(new ServiceMacroName('other-value'), 'w', isPassword: false),
            new ServiceMacro(new ServiceMacroName('other-flag'), 'v', isPassword: true),
            new ServiceMacro(new ServiceMacroName('own'), 'v', isPassword: false),
        ]);

        self::assertSame(['OTHER-VALUE', 'OTHER-FLAG', 'OWN'], array_map(static fn (ServiceMacro $macro): string => $macro->name->value, $kept));
    }

    /**
     * @param list<ServiceMacro> $macros
     */
    private function template(int $id, array $macros): ServiceTemplate
    {
        return new ServiceTemplate(
            new ServiceTemplateId($id),
            new ServiceTemplateName('tpl-' . $id),
            macros: new Collection($macros, ServiceMacro::class),
        );
    }

    private function macro(int $id, string $name, string $value, bool $isPassword = false): ServiceMacro
    {
        return new ServiceMacro(new ServiceMacroName($name), $value, $isPassword, id: new ServiceMacroId($id));
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

    /**
     * @param list<ServiceMacro> $macros
     */
    private function onlyMacro(array $macros): ServiceMacro
    {
        self::assertCount(1, $macros);

        return $macros[0];
    }

    /**
     * @param list<ServiceMacro> $macros
     *
     * @return list<array{string, string, ?int}>
     */
    private function describe(array $macros): array
    {
        return array_map(
            static fn (ServiceMacro $macro): array => [$macro->name->value, $macro->value, $macro->id?->value],
            $macros,
        );
    }
}
