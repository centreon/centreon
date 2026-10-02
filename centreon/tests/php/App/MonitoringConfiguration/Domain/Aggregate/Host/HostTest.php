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

use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\CheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Host\DataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\Host\ExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAlias;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Notifications;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SchedulingOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SnmpCommunity;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SnmpVersionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;
use Tests\App\Shared\Double\FakeVault;

final class HostTest extends TestCase
{
    public function testItRejectsAHostNamedAsBothParentAndChild(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->host(parentHostIds: [7], childHostIds: [7]);
    }

    public function testItAcceptsUnrelatedParentsAndChildren(): void
    {
        $host = $this->host(parentHostIds: [7], childHostIds: [8]);

        self::assertCount(1, $host->parentHostIds);
        self::assertCount(1, $host->childHostIds);
    }

    public function testItEnablesTheHost(): void
    {
        $host = $this->host(activated: false);

        $host->enable();

        self::assertTrue($host->activated);
    }

    public function testItDisablesTheHost(): void
    {
        $host = $this->host(activated: true);

        $host->disable();

        self::assertFalse($host->activated);
    }

    public function testGetVaultUuidReturnsNullWhenNothingIsVaulted(): void
    {
        $host = $this->host(snmpCommunity: new SnmpCommunity('public'));

        self::assertNull($host->getVaultUuid(new FakeVault()));
    }

    public function testGetVaultUuidReturnsTheSnmpCommunityEntryWhenVaulted(): void
    {
        $vault = new FakeVault();
        $vault->extractedUuids['secret::vault::monitoring/hosts/uuid-1::_HOSTSNMPCOMMUNITY'] = 'uuid-1';
        $host = $this->host(snmpCommunity: new SnmpCommunity('secret::vault::monitoring/hosts/uuid-1::_HOSTSNMPCOMMUNITY'));

        self::assertSame('uuid-1', $host->getVaultUuid($vault));
    }

    public function testGetVaultUuidFallsBackToTheFirstVaultedPasswordMacro(): void
    {
        $vault = new FakeVault();
        $vault->extractedUuids['secret::vault::monitoring/hosts/uuid-2::_HOSTTOKEN'] = 'uuid-2';
        $macro = new HostMacro(
            new HostMacroName('token'),
            'secret::vault::monitoring/hosts/uuid-2::_HOSTTOKEN',
            isPassword: true,
        );
        $host = $this->host(snmpCommunity: new SnmpCommunity('public'), macros: [$macro]);

        self::assertSame('uuid-2', $host->getVaultUuid($vault));
    }

    public function testGetVaultUuidIgnoresANonPasswordMacroEvenIfItLooksLikeAVaultPath(): void
    {
        $vault = new FakeVault();
        $vault->extractedUuids['secret::vault::monitoring/hosts/uuid-3::_HOSTNOTASECRET'] = 'uuid-3';
        $macro = new HostMacro(
            new HostMacroName('notasecret'),
            'secret::vault::monitoring/hosts/uuid-3::_HOSTNOTASECRET',
            isPassword: false,
        );
        $host = $this->host(macros: [$macro]);

        self::assertNull($host->getVaultUuid($vault));
    }

    public function testGetVaultUuidPrefersTheSnmpCommunityOverMacros(): void
    {
        $vault = new FakeVault();
        $vault->extractedUuids['secret::vault::monitoring/hosts/uuid-4::_HOSTSNMPCOMMUNITY'] = 'uuid-4';
        $vault->extractedUuids['secret::vault::monitoring/hosts/uuid-5::_HOSTTOKEN'] = 'uuid-5';
        $macro = new HostMacro(
            new HostMacroName('token'),
            'secret::vault::monitoring/hosts/uuid-5::_HOSTTOKEN',
            isPassword: true,
        );
        $host = $this->host(
            snmpCommunity: new SnmpCommunity('secret::vault::monitoring/hosts/uuid-4::_HOSTSNMPCOMMUNITY'),
            macros: [$macro],
        );

        self::assertSame('uuid-4', $host->getVaultUuid($vault));
    }

    public function testWithKeepsTheIdentityAndEverythingWhenNothingIsProvided(): void
    {
        $original = $this->persistedHost();

        $copy = $original->with();

        self::assertNotSame($original, $copy);
        self::assertSame(12, $copy->id()->value);
        self::assertTrue($copy->hasSameConfigurationAs($original));
        self::assertSame($original->activated, $copy->activated);
        self::assertSame($original->templateIds, $copy->templateIds);
    }

    public function testWithReplacesTheProvidedPropertiesOnly(): void
    {
        $original = $this->persistedHost();

        $changed = $original->with(
            name: new HostName('server-02'),
            pollerId: new PollerId(9),
            snmpVersion: SnmpVersionEnum::TwoC,
            activated: false,
        );

        self::assertSame('server-02', $changed->name->value);
        self::assertSame(9, $changed->pollerId->value);
        self::assertSame(SnmpVersionEnum::TwoC, $changed->snmpVersion);
        self::assertFalse($changed->activated);
        self::assertSame('127.0.0.1', $changed->address->value);
        self::assertSame('server-01', $original->name->value);
    }

    public function testWithClearsAnOptionalPropertySetToNull(): void
    {
        $original = $this->persistedHost()->with(alias: new HostAlias('front'), timezoneId: new TimezoneId(4), notifications: Notifications::default());

        $changed = $original->with(alias: null, timezoneId: null, notifications: null);

        self::assertNull($changed->alias);
        self::assertNull($changed->timezoneId);
        self::assertNull($changed->notifications);
    }

    public function testWithRequiresAPersistedHost(): void
    {
        $this->expectException(\Throwable::class);

        $this->host()->with(name: new HostName('server-02'));
    }

    public function testSameConfigurationIgnoresTheActivation(): void
    {
        $original = $this->persistedHost();

        self::assertTrue($original->hasSameConfigurationAs($original->with(activated: false)));
    }

    public function testSameConfigurationDetectsAScalarChange(): void
    {
        $original = $this->persistedHost();

        self::assertFalse($original->hasSameConfigurationAs($original->with(address: new HostAddress('10.0.0.1'))));
        self::assertFalse($original->hasSameConfigurationAs($original->with(timezoneId: new TimezoneId(1))));
    }

    public function testSameConfigurationDetectsASubObjectChange(): void
    {
        $original = $this->persistedHost();

        self::assertFalse($original->hasSameConfigurationAs($original->with(schedulingOptions: new SchedulingOptions(maxCheckAttempts: 3))));
        self::assertFalse($original->hasSameConfigurationAs($original->with(dataProcessing: new DataProcessing(TriStateEnum::True))));
        self::assertFalse($original->hasSameConfigurationAs($original->with(checkOptions: new CheckOptions(new CommandId(5)))));
        self::assertFalse($original->hasSameConfigurationAs($original->with(extendedInformations: new ExtendedInformations(note: 'a'))));
        self::assertFalse($original->hasSameConfigurationAs($original->with(notifications: Notifications::default())));
    }

    public function testSameConfigurationTreatsEqualOptionalSubObjectsAsSame(): void
    {
        $original = $this->persistedHost()->with(extendedInformations: new ExtendedInformations(note: 'a'), notifications: Notifications::default());

        $copy = $original->with(extendedInformations: new ExtendedInformations(note: 'a'), notifications: Notifications::default());

        self::assertTrue($original->hasSameConfigurationAs($copy));
    }

    /**
     * @param list<int> $parentHostIds
     * @param list<int> $childHostIds
     * @param list<HostMacro> $macros
     */
    private function host(
        array $parentHostIds = [],
        array $childHostIds = [],
        bool $activated = true,
        ?SnmpCommunity $snmpCommunity = null,
        array $macros = [],
    ): Host {
        return new Host(
            id: null,
            name: new HostName('server-01'),
            alias: null,
            address: new HostAddress('127.0.0.1'),
            activated: $activated,
            pollerId: new PollerId(1),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
            parentHostIds: $this->hostIds($parentHostIds),
            childHostIds: $this->hostIds($childHostIds),
            snmpCommunity: $snmpCommunity,
            checkOptions: new CheckOptions(null, macros: $macros),
        );
    }

    /**
     * @param list<int> $ids
     *
     * @return Collection<HostId>
     */
    private function hostIds(array $ids): Collection
    {
        return new Collection(array_map(static fn (int $id): HostId => new HostId($id), $ids), HostId::class);
    }

    private function persistedHost(): Host
    {
        return new Host(
            id: new HostId(12),
            name: new HostName('server-01'),
            alias: null,
            address: new HostAddress('127.0.0.1'),
            activated: true,
            pollerId: new PollerId(1),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
        );
    }
}
