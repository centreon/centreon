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
use App\MonitoringConfiguration\Domain\Aggregate\HostAlias;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\HostMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\HostMacroParentEnum;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateCheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateDataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateMacro;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateNotifications;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateSchedulingOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\SnmpCommunity;
use App\MonitoringConfiguration\Domain\Aggregate\SnmpVersionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateLockedException;
use App\Shared\Domain\Aggregate\TriStateEnum;
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
        $template = $this->template(macros: [new HostTemplateMacro(new HostMacroName('foo'), 'bar', isPassword: true, id: new HostMacroId(4))]);

        $macros = $template->macrosAsInherited();

        self::assertCount(1, $macros);
        self::assertSame(HostMacroParentEnum::Template, $macros[0]->parent);
        self::assertSame(4, $macros[0]->id?->value);
        self::assertSame('FOO', $macros[0]->name->value);
        self::assertSame('bar', $macros[0]->value);
        self::assertTrue($macros[0]->isPassword);
    }

    public function testItLoadsLazyMacrosOnlyWhenRead(): void
    {
        $calls = new \ArrayObject();
        $template = new HostTemplate(
            new HostTemplateId(1),
            new HostTemplateName('tpl'),
            new HostTemplateCheckOptions(macros: new Collection(static function () use ($calls): array {
                $calls->append(true);

                return [];
            }, HostTemplateMacro::class)),
        );

        self::assertCount(0, $calls);
        $template->macrosAsInherited();
        self::assertCount(1, $calls);
    }

    public function testNoVaultEntryWhenNothingIsVaulted(): void
    {
        $template = $this->template(snmp: 'public', macros: [new HostTemplateMacro(new HostMacroName('PWD'), 'plain', isPassword: true)]);

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
            new HostTemplateMacro(new HostMacroName('USER'), 'admin', isPassword: false),
            new HostTemplateMacro(new HostMacroName('PWD'), 'secret::vault::monitoring/hosts/uuid-macro::_HOSTPWD', isPassword: true),
        ]);

        self::assertSame('uuid-macro', $template->getVaultUuid(new FakeVault()));
    }

    public function testANonPasswordMacroHoldingAVaultPathIsIgnored(): void
    {
        $template = $this->template(macros: [new HostTemplateMacro(new HostMacroName('NOTE'), 'secret::vault::monitoring/hosts/uuid-x::_HOSTNOTE', isPassword: false)]);

        self::assertNull($template->getVaultUuid(new FakeVault()));
    }

    public function testACopyCarriesTheSourceConfigurationWithItsOwnNameSecretsAndMacros(): void
    {
        $extendedInformations = new HostTemplateExtendedInformations(note: 'a note', iconId: new MediaId(8));
        $schedulingOptions = new HostTemplateSchedulingOptions(maxCheckAttempts: 3);
        $dataProcessing = new HostTemplateDataProcessing(acknowledgmentTimeout: 4);
        $notifications = new HostTemplateNotifications(enabled: TriStateEnum::True);
        $source = new HostTemplate(
            new HostTemplateId(1),
            new HostTemplateName('source'),
            new HostTemplateCheckOptions(
                new CommandId(5),
                ['-w', '80'],
                new Collection([new HostTemplateMacro(new HostMacroName('OLD'), 'old', isPassword: false)], HostTemplateMacro::class),
            ),
            alias: new HostAlias('An alias'),
            isLocked: false,
            parentTemplateIds: new Collection([new HostTemplateId(3), new HostTemplateId(2)], HostTemplateId::class),
            snmpCommunity: new SnmpCommunity('old-community'),
            snmpVersion: SnmpVersionEnum::TwoC,
            timezoneId: new TimezoneId(6),
            severityId: new HostSeverityId(7),
            extendedInformations: $extendedInformations,
            schedulingOptions: $schedulingOptions,
            dataProcessing: $dataProcessing,
            notifications: $notifications,
            categoryIds: new Collection([new HostCategoryId(9)], HostCategoryId::class),
            linkedServiceTemplateIds: new Collection([new ServiceTemplateId(10)], ServiceTemplateId::class),
        );
        $macros = new Collection([new HostTemplateMacro(new HostMacroName('NEW'), 'new', isPassword: false)], HostTemplateMacro::class);

        $copy = $source->duplicate(new HostTemplateName('copy'), new SnmpCommunity('new-community'), $macros);

        self::assertSame('copy', $copy->name->value);
        self::assertSame('new-community', $copy->snmpCommunity?->value);
        self::assertSame($macros, $copy->checkOptions->macros);
        self::assertSame(5, $copy->checkOptions->checkCommandId?->value);
        self::assertSame(['-w', '80'], $copy->checkOptions->args);
        self::assertSame('An alias', $copy->alias?->value);
        self::assertSame([3, 2], array_map(static fn (HostTemplateId $id): int => $id->value, $copy->parentTemplateIds->toArray()));
        self::assertSame(SnmpVersionEnum::TwoC, $copy->snmpVersion);
        self::assertSame(6, $copy->timezoneId?->value);
        self::assertSame(7, $copy->severityId?->value);
        self::assertSame($extendedInformations, $copy->extendedInformations);
        self::assertSame($schedulingOptions, $copy->schedulingOptions);
        self::assertSame($dataProcessing, $copy->dataProcessing);
        self::assertSame($notifications, $copy->notifications);
        self::assertSame($source->categoryIds, $copy->categoryIds);
        self::assertSame($source->linkedServiceTemplateIds, $copy->linkedServiceTemplateIds);
        self::assertFalse($copy->isLocked);
        $this->expectException(MissingIdException::class);
        $copy->id();
    }

    public function testALockedTemplateCannotBeDuplicated(): void
    {
        $locked = new HostTemplate(new HostTemplateId(1), new HostTemplateName('locked'), isLocked: true);

        $this->expectException(HostTemplateLockedException::class);

        $locked->duplicate(new HostTemplateName('copy'), null, new Collection([], HostTemplateMacro::class));
    }

    public function testAnUnlockedTemplatePassesTheLockCheck(): void
    {
        $this->expectNotToPerformAssertions();

        new HostTemplate(new HostTemplateId(1), new HostTemplateName('free'))->assertUnlocked();
    }

    /**
     * @param list<HostTemplateMacro> $macros
     */
    private function template(?string $snmp = null, array $macros = []): HostTemplate
    {
        return new HostTemplate(
            new HostTemplateId(1),
            new HostTemplateName('tpl'),
            new HostTemplateCheckOptions(macros: new Collection($macros, HostTemplateMacro::class)),
            snmpCommunity: $snmp !== null ? new SnmpCommunity($snmp) : null,
        );
    }
}
