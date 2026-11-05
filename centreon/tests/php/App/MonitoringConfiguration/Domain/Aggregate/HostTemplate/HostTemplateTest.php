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

use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAlias;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroParentEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SnmpCommunity;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateLockedException;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Exception\MissingIdException;
use PHPUnit\Framework\TestCase;
use Tests\App\Shared\Double\FakeVault;

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

    public function testNoVaultEntryWhenNothingIsVaulted(): void
    {
        $template = $this->template(snmp: 'public', macros: [new HostMacro(new HostMacroName('PWD'), 'plain', isPassword: true)]);

        self::assertNull($template->getVaultUuid(new FakeVault()));
    }

    public function testTheVaultEntryOfAVaultedSnmpCommunity(): void
    {
        $template = $this->template(snmp: 'secret::vault::monitoring/hosts/uuid-snmp::_HOSTSNMPCOMMUNITY');

        self::assertSame('uuid-snmp', $template->getVaultUuid(new FakeVault()));
    }

    public function testTheVaultEntryOfAVaultedPasswordMacroAlone(): void
    {
        $template = $this->template(macros: [
            new HostMacro(new HostMacroName('USER'), 'admin', isPassword: false),
            new HostMacro(new HostMacroName('PWD'), 'secret::vault::monitoring/hosts/uuid-macro::_HOSTPWD', isPassword: true),
        ]);

        self::assertSame('uuid-macro', $template->getVaultUuid(new FakeVault()));
    }

    public function testANonPasswordMacroHoldingAVaultPathIsIgnored(): void
    {
        $template = $this->template(macros: [new HostMacro(new HostMacroName('NOTE'), 'secret::vault::monitoring/hosts/uuid-x::_HOSTNOTE', isPassword: false)]);

        self::assertNull($template->getVaultUuid(new FakeVault()));
    }

    public function testACopyCarriesTheSourceConfigurationWithItsOwnNameSecretsAndMacros(): void
    {
        $source = new HostTemplate(
            new HostTemplateId(1),
            new HostTemplateName('source'),
            new Collection([new HostMacro(new HostMacroName('OLD'), 'old', isPassword: false)], HostMacro::class),
            checkCommandId: new CommandId(5),
            alias: new HostAlias('An alias'),
            iconId: new MediaId(8),
            parentTemplateIds: new Collection([new HostTemplateId(3), new HostTemplateId(2)], HostTemplateId::class),
            snmpCommunity: new SnmpCommunity('old-community'),
        );
        $macros = new Collection([new HostMacro(new HostMacroName('NEW'), 'new', isPassword: false)], HostMacro::class);

        $copy = $source->duplicate(new HostTemplateName('copy'), new SnmpCommunity('new-community'), $macros);

        self::assertSame('copy', $copy->name->value);
        self::assertSame('new-community', $copy->snmpCommunity?->value);
        self::assertSame($macros, $copy->macros);
        self::assertSame(5, $copy->checkCommandId?->value);
        self::assertSame('An alias', $copy->alias?->value);
        self::assertSame(8, $copy->iconId?->value);
        self::assertSame([3, 2], array_map(static fn (HostTemplateId $id): int => $id->value, $copy->parentTemplateIds->toArray()));
        self::assertFalse($copy->isLocked);
        $this->expectException(MissingIdException::class);
        $copy->id();
    }

    public function testALockedTemplateCannotBeDuplicated(): void
    {
        $locked = new HostTemplate(new HostTemplateId(1), new HostTemplateName('locked'), isLocked: true);

        $this->expectException(HostTemplateLockedException::class);

        $locked->duplicate(new HostTemplateName('copy'), null, new Collection([], HostMacro::class));
    }

    public function testAnUnlockedTemplatePassesTheLockCheck(): void
    {
        $this->expectNotToPerformAssertions();

        new HostTemplate(new HostTemplateId(1), new HostTemplateName('free'))->assertUnlocked();
    }

    /**
     * @param list<HostMacro> $macros
     */
    private function template(?string $snmp = null, array $macros = []): HostTemplate
    {
        return new HostTemplate(
            new HostTemplateId(1),
            new HostTemplateName('tpl'),
            new Collection($macros, HostMacro::class),
            snmpCommunity: $snmp !== null ? new SnmpCommunity($snmp) : null,
        );
    }
}
