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
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroChange;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroParentEnum;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateName;
use App\MonitoringConfiguration\Domain\Exception\DuplicateServiceMacroNameException;
use App\MonitoringConfiguration\Domain\Exception\ServiceMacroNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\ServiceMacroPasswordUnmaskedException;
use App\MonitoringConfiguration\Domain\Exception\ServiceMacroValueRequiredException;
use App\MonitoringConfiguration\Domain\Service\ServiceMacroChangesResolver;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;

final class ServiceMacroChangesResolverTest extends TestCase
{
    private const TEMPLATE_SECRET = 'secret::vault::monitoring/services/tpl-uuid::_SERVICETPLPWD';
    private const SERVICE_SECRET = 'secret::vault::monitoring/services/service-uuid::_SERVICEPWD';

    private ServiceMacroChangesResolver $resolver;

    private InheritedServiceMacros $inherited;

    protected function setUp(): void
    {
        $this->resolver = new ServiceMacroChangesResolver();

        // Template macros: TPLVAL (11, plain), TPLPWD (12, password), SHARED (13, 'x');
        // command macros: CMD (31), UNRECORDED (no row), SHARED (shadowed by the template).
        $command = new Command(
            new CommandId(1),
            new CommandName('check'),
            CommandTypeEnum::Check,
            new CommandLine('$USER1$/check -a $_SERVICECMD$ -b $_SERVICEUNRECORDED$ -c $_SERVICESHARED$'),
            isShellEnabled: false,
            isActivated: true,
            isFromMonitoringConnector: false,
            connector: null,
            comment: null,
        );
        $command->setStoredMacros([new CommandMacro(new CommandMacroId(31), 'CMD', CommandMacroTypeEnum::Service)]);

        $this->inherited = InheritedServiceMacros::resolve([
            new ServiceTemplate(new ServiceTemplateId(1), new ServiceTemplateName('tpl'), macros: new Collection([
                new ServiceMacro(new ServiceMacroName('tplval'), 'inherited', isPassword: false, id: new ServiceMacroId(11)),
                new ServiceMacro(new ServiceMacroName('tplpwd'), self::TEMPLATE_SECRET, isPassword: true, id: new ServiceMacroId(12)),
                new ServiceMacro(new ServiceMacroName('shared'), 'x', isPassword: false, id: new ServiceMacroId(13)),
            ], ServiceMacro::class)),
        ], [$command]);
    }

    public function testANewMacroBecomesADirectMacro(): void
    {
        $macros = $this->resolver->resolve([$this->change('own', 'value')], [], $this->inherited);

        self::assertCount(1, $macros);
        self::assertFalse($macros[0]->isInherited());
        self::assertNull($macros[0]->id);
        self::assertSame('value', $macros[0]->value);
    }

    public function testAnEmptyStringIsAValidValue(): void
    {
        $macros = $this->resolver->resolve([$this->change('own', '', isPassword: true)], [], $this->inherited);

        self::assertSame('', $macros[0]->value);
        self::assertTrue($macros[0]->isPassword);
    }

    public function testAPasswordKeepsItsStoredValueWhenNullIsSent(): void
    {
        $current = [$this->direct(5, 'pwd', self::SERVICE_SECRET, isPassword: true)];

        $macros = $this->resolver->resolve([$this->change('pwd', null, isPassword: true, id: 5)], $current, $this->inherited);

        self::assertSame(self::SERVICE_SECRET, $macros[0]->value);
        self::assertSame(5, $macros[0]->id?->value);
    }

    public function testADirectPasswordRenamedKeepsItsValueAndId(): void
    {
        $current = [$this->direct(5, 'pwd', self::SERVICE_SECRET, isPassword: true)];

        $macros = $this->resolver->resolve([$this->change('renamed', null, isPassword: true, id: 5)], $current, $this->inherited);

        self::assertSame('RENAMED', $macros[0]->name->value);
        self::assertSame(self::SERVICE_SECRET, $macros[0]->value);
        self::assertSame(5, $macros[0]->id?->value);
    }

    public function testADirectMacroIsUpdatedInPlace(): void
    {
        $current = [$this->direct(5, 'own', 'old')];

        $macros = $this->resolver->resolve([$this->change('own', 'new', id: 5)], $current, $this->inherited);

        self::assertSame('new', $macros[0]->value);
        self::assertSame(5, $macros[0]->id?->value);
    }

    public function testADirectMacroNotSubmittedIsRemoved(): void
    {
        $current = [$this->direct(5, 'kept', 'x'), $this->direct(6, 'removed', 'y')];

        $macros = $this->resolver->resolve([$this->change('kept', 'x', id: 5)], $current, $this->inherited);

        self::assertSame(['KEPT'], $this->names($macros));
    }

    public function testAnInheritedPasswordRenamedBecomesDirectCarryingTheInheritedRawValue(): void
    {
        // The raw template reference is carried over; moving it under the service's vault entry
        // is the caller's job (ServiceMacroSecretsSynchronizer).
        $macros = $this->resolver->resolve(
            [$this->change('mypwd', null, isPassword: true, id: 12, parent: ServiceMacroParentEnum::Template)],
            [],
            $this->inherited,
        );

        self::assertCount(1, $macros);
        self::assertFalse($macros[0]->isInherited());
        self::assertNull($macros[0]->id);
        self::assertSame('MYPWD', $macros[0]->name->value);
        self::assertSame(self::TEMPLATE_SECRET, $macros[0]->value);
    }

    public function testAnInheritedMacroWithANewValueBecomesDirect(): void
    {
        $macros = $this->resolver->resolve(
            [$this->change('tplval', 'overridden', id: 11, parent: ServiceMacroParentEnum::Template)],
            [],
            $this->inherited,
        );

        self::assertFalse($macros[0]->isInherited());
        self::assertNull($macros[0]->id);
        self::assertSame('overridden', $macros[0]->value);
    }

    public function testAnInheritedMacroWhosePasswordFlagAloneChangesBecomesDirect(): void
    {
        // is_password alone is an override.
        $macros = $this->resolver->resolve(
            [$this->change('tplval', 'inherited', isPassword: true, id: 11, parent: ServiceMacroParentEnum::Template)],
            [],
            $this->inherited,
        );

        self::assertCount(1, $macros);
        self::assertTrue($macros[0]->isPassword);
    }

    public function testAnUntouchedInheritedMacroEchoedBackStaysInherited(): void
    {
        // No direct copy is materialised.
        $macros = $this->resolver->resolve([
            $this->change('tplval', 'inherited', id: 11, parent: ServiceMacroParentEnum::Template),
            $this->change('tplpwd', null, isPassword: true, id: 12, parent: ServiceMacroParentEnum::Template),
            $this->change('cmd', '', id: 31, parent: ServiceMacroParentEnum::Command),
        ], [], $this->inherited);

        self::assertSame([], $macros);
    }

    public function testADirectOverrideSetBackToTheInheritedValueCollapses(): void
    {
        $current = [$this->direct(5, 'tplval', 'overridden')];

        $macros = $this->resolver->resolve([$this->change('tplval', 'inherited', id: 5)], $current, $this->inherited);

        self::assertSame([], $macros);
    }

    public function testANewMacroMatchingAnInheritedOneIsNotAnOverride(): void
    {
        // A matching name alone never makes an override.
        $macros = $this->resolver->resolve([
            $this->change('tplval', 'inherited'),
            $this->change('cmd', ''),
        ], [], $this->inherited);

        self::assertSame([], $macros);
    }

    public function testANewMacroDifferingFromAnInheritedOneOverridesIt(): void
    {
        $macros = $this->resolver->resolve([$this->change('cmd', 'service-value')], [], $this->inherited);

        self::assertSame(['CMD'], $this->names($macros));
    }

    public function testACommandMacroIsResolvedByNameEvenWithAStaleId(): void
    {
        // on_demand_macro_command ids are rewritten on every command save: never an error.
        $macros = $this->resolver->resolve([
            $this->change('cmd', '', id: 999, parent: ServiceMacroParentEnum::Command),
            $this->change('unrecorded', 'set', id: 998, parent: ServiceMacroParentEnum::Command),
        ], [], $this->inherited);

        self::assertSame(['UNRECORDED'], $this->names($macros));
    }

    public function testAnEmptyCommandOnlyMacroStaysInheritedWhateverIdIsSent(): void
    {
        $macros = $this->resolver->resolve([
            $this->change('cmd', '', id: 999, parent: ServiceMacroParentEnum::Command),
            $this->change('unrecorded', '', id: 31, parent: ServiceMacroParentEnum::Command),
        ], [], $this->inherited);

        self::assertSame([], $macros);
    }

    public function testAnEmptyValueOverridesATemplateMacroTheCommandAlsoDeclares(): void
    {
        // The template wins over the command for SHARED, so '' differs from what is inherited.
        $macros = $this->resolver->resolve([$this->change('shared', '')], [], $this->inherited);

        self::assertSame(['SHARED'], $this->names($macros));
        self::assertSame('', $macros[0]->value);
        self::assertFalse($macros[0]->isInherited());
    }

    public function testAnEmptyValueSentAsTheCommandMacroStillOverridesTheTemplate(): void
    {
        // Resolved by name, so the outcome is the same as for a new macro.
        $macros = $this->resolver->resolve(
            [$this->change('shared', '', id: 31, parent: ServiceMacroParentEnum::Command)],
            [],
            $this->inherited,
        );

        self::assertSame(['SHARED'], $this->names($macros));
        self::assertNull($macros[0]->id);
    }

    public function testTheTemplateValueOnANameTheCommandAlsoDeclaresStaysInherited(): void
    {
        $macros = $this->resolver->resolve(
            [$this->change('shared', 'x', id: 999, parent: ServiceMacroParentEnum::Command)],
            [],
            $this->inherited,
        );

        self::assertSame([], $macros);
    }

    public function testOnlyTheLastOfADirectMacroSentTwiceIsKept(): void
    {
        $current = [$this->direct(5, 'foo', 'old')];

        // Id 5 under no parent refers to a single macro: the last change sent for it wins.
        $macros = $this->resolver->resolve([
            $this->change('foo', 'a', id: 5),
            $this->change('bar', 'b', id: 5),
        ], $current, $this->inherited);

        self::assertSame(['BAR'], $this->names($macros));
        self::assertSame(5, $macros[0]->id?->value);
        self::assertSame('b', $macros[0]->value);
    }

    public function testOnlyTheLastOfAnInheritedMacroSentTwiceIsKept(): void
    {
        // Under two names, so not a duplicate: template macro 11 is one macro, the last change sent for it wins.
        $macros = $this->resolver->resolve([
            $this->change('first', 'a', id: 11, parent: ServiceMacroParentEnum::Template),
            $this->change('second', 'b', id: 11, parent: ServiceMacroParentEnum::Template),
        ], [], $this->inherited);

        self::assertSame([['SECOND', 'b', null]], $this->describe($macros));
    }

    /**
     * Two submitted macros of the same name are refused, compared upper-cased, whatever they refer to: the
     * service would otherwise hold one of them only.
     */
    public function testTwoNewMacrosOfTheSameNameAreRefused(): void
    {
        try {
            $this->resolver->resolve([
                $this->change('foo', 'a'),
                $this->change('bar', 'b'),
                $this->change('FOO', 'c'),
                $this->change('Bar', 'd'),
            ], [], $this->inherited);
            self::fail('Expected DuplicateServiceMacroNameException');
        } catch (DuplicateServiceMacroNameException $exception) {
            self::assertSame(['checkOptions' => ['FOO', 'BAR']], $exception->criteria);
        }
    }

    public function testACommandMacroSentTwiceUnderItsNameIsADuplicate(): void
    {
        $this->expectException(DuplicateServiceMacroNameException::class);

        $this->resolver->resolve([
            $this->change('cmd', 'first', id: 31, parent: ServiceMacroParentEnum::Command),
            $this->change('cmd', 'last', id: 99, parent: ServiceMacroParentEnum::Command),
        ], [], $this->inherited);
    }

    public function testTheSameInheritedMacroSentTwiceUnderItsNameIsADuplicate(): void
    {
        $this->expectException(DuplicateServiceMacroNameException::class);

        $this->resolver->resolve([
            $this->change('tplval', 'first', id: 11, parent: ServiceMacroParentEnum::Template),
            $this->change('tplval', 'second', id: 11, parent: ServiceMacroParentEnum::Template),
        ], [], $this->inherited);
    }

    /**
     * The submitted macros are compared, before any of them is dropped as equivalent to the inherited one.
     */
    public function testADuplicateIsRefusedEvenWhenOneOfThemRepeatsTheInheritedMacro(): void
    {
        $this->expectException(DuplicateServiceMacroNameException::class);

        $this->resolver->resolve([
            $this->change('tplval', 'inherited'),
            $this->change('tplval', 'other'),
        ], [], $this->inherited);
    }

    public function testDuplicatesAreReportedBeforeAnUnknownMacro(): void
    {
        $this->expectException(DuplicateServiceMacroNameException::class);

        $this->resolver->resolve([
            $this->change('foo', 'a', id: 404),
            $this->change('foo', 'b'),
        ], [], $this->inherited);
    }

    public function testTheSameIdUnderAnotherParentIsAnotherMacro(): void
    {
        // The parent is the id namespace: direct macro 11 and template macro 11 are two macros.
        $current = [$this->direct(11, 'own', 'old')];

        $macros = $this->resolver->resolve([
            $this->change('own', 'new', id: 11),
            $this->change('tplval', 'overridden', id: 11, parent: ServiceMacroParentEnum::Template),
        ], $current, $this->inherited);

        self::assertSame(['OWN', 'TPLVAL'], $this->names($macros));
    }

    public function testNewMacrosAreNeverMergedTogether(): void
    {
        // Without an id, a change refers to no stored macro: none is dropped as a repeat.
        $macros = $this->resolver->resolve([
            $this->change('one', 'a'),
            $this->change('two', 'b'),
        ], [], $this->inherited);

        self::assertSame(['ONE', 'TWO'], $this->names($macros));
    }

    public function testAnUnknownDirectOrTemplateIdIsRejected(): void
    {
        try {
            $this->resolver->resolve([
                $this->change('a', 'x', id: 404),
                $this->change('b', 'x', id: 405, parent: ServiceMacroParentEnum::Template),
            ], [], $this->inherited);
            self::fail('Expected ServiceMacroNotFoundException');
        } catch (ServiceMacroNotFoundException $exception) {
            self::assertSame(['checkOptions' => [404, 405]], $exception->criteria);
        }
    }

    public function testATemplateIdIsNotADirectId(): void
    {
        // The parent is the id namespace: id 11 exists on the template, not on the service.
        $this->expectException(ServiceMacroNotFoundException::class);

        $this->resolver->resolve([$this->change('tplval', 'x', id: 11)], [], $this->inherited);
    }

    public function testKeepingTheStoredValueOfANonPasswordMacroIsRejected(): void
    {
        // The stored macro is not a password, so its value is echoed and must be resent.
        $current = [$this->direct(5, 'plain', 'x')];

        $this->expectException(ServiceMacroValueRequiredException::class);

        $this->resolver->resolve([$this->change('plain', null, isPassword: true, id: 5)], $current, $this->inherited);
    }

    public function testKeepingTheStoredValueOfACommandMacroIsRejected(): void
    {
        $this->expectException(ServiceMacroValueRequiredException::class);

        $this->resolver->resolve(
            [$this->change('cmd', null, isPassword: true, id: 31, parent: ServiceMacroParentEnum::Command)],
            [],
            $this->inherited,
        );
    }

    public function testADirectPasswordCannotBecomePlain(): void
    {
        $current = [$this->direct(5, 'pwd', self::SERVICE_SECRET, isPassword: true)];

        $this->expectException(ServiceMacroPasswordUnmaskedException::class);

        $this->resolver->resolve([$this->change('pwd', 'clear', id: 5)], $current, $this->inherited);
    }

    public function testAnInheritedPasswordCannotBecomePlain(): void
    {
        $this->expectException(ServiceMacroPasswordUnmaskedException::class);

        $this->resolver->resolve(
            [$this->change('tplpwd', 'clear', id: 12, parent: ServiceMacroParentEnum::Template)],
            [],
            $this->inherited,
        );
    }

    public function testAnInheritedPasswordCannotBeOverriddenByANewPlainMacro(): void
    {
        // Without its id, the same name still shadows the inherited password.
        $this->expectException(ServiceMacroPasswordUnmaskedException::class);

        $this->resolver->resolve([$this->change('tplpwd', 'clear')], [], $this->inherited);
    }

    public function testAnInheritedPasswordCannotBeOverriddenByARenamedPlainMacro(): void
    {
        $current = [$this->direct(5, 'plain', 'x')];

        $this->expectException(ServiceMacroPasswordUnmaskedException::class);

        $this->resolver->resolve([$this->change('tplpwd', 'x', id: 5)], $current, $this->inherited);
    }

    public function testAPlainMacroAlreadyShadowingAnInheritedPasswordCanBeEchoedBack(): void
    {
        // Legacy data: the override predates the rule, and resending it introduces nothing.
        $current = [$this->direct(5, 'tplpwd', 'clear')];

        $macros = $this->resolver->resolve([$this->change('tplpwd', 'clear', id: 5)], $current, $this->inherited);

        self::assertSame(['TPLPWD'], $this->names($macros));
        self::assertFalse($macros[0]->isPassword);
    }

    public function testAPlainMacroCanBecomeAPassword(): void
    {
        $current = [$this->direct(5, 'plain', 'x')];

        $macros = $this->resolver->resolve([$this->change('plain', 'x', isPassword: true, id: 5)], $current, $this->inherited);

        self::assertTrue($macros[0]->isPassword);
    }

    private function change(
        string $name,
        ?string $value,
        bool $isPassword = false,
        ?int $id = null,
        ?ServiceMacroParentEnum $parent = null,
    ): ServiceMacroChange {
        return new ServiceMacroChange(
            new ServiceMacroName($name),
            $value,
            $isPassword,
            $id !== null ? new ServiceMacroId($id) : null,
            $parent,
        );
    }

    /**
     * @param list<ServiceMacro> $macros
     *
     * @return list<array{string, string, ?int}>
     */
    private function describe(array $macros): array
    {
        return array_map(static fn (ServiceMacro $macro): array => [$macro->name->value, $macro->value, $macro->id?->value], $macros);
    }

    private function direct(int $id, string $name, string $value, bool $isPassword = false): ServiceMacro
    {
        return new ServiceMacro(new ServiceMacroName($name), $value, $isPassword, id: new ServiceMacroId($id));
    }

    /**
     * @param list<ServiceMacro> $macros
     *
     * @return list<string>
     */
    private function names(array $macros): array
    {
        return array_map(static fn (ServiceMacro $macro): string => $macro->name->value, $macros);
    }
}
