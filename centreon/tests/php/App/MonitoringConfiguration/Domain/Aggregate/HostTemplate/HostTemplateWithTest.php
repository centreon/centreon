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

use App\MonitoringConfiguration\Domain\Aggregate\CheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\DataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\ExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\HostAlias;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Notifications;
use App\MonitoringConfiguration\Domain\Aggregate\SchedulingOptions;
use App\MonitoringConfiguration\Domain\Aggregate\SnmpCommunity;
use App\MonitoringConfiguration\Domain\Aggregate\SnmpVersionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateLockedException;
use App\Shared\Domain\Aggregate\TriStateEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\App\Shared\Double\FakeVault;

final class HostTemplateWithTest extends TestCase
{
    public function testALockedTemplateRefusesEvenAnEmptyChange(): void
    {
        $template = new HostTemplate(new HostTemplateId(1), new HostTemplateName('locked'), isLocked: true);

        $this->expectException(HostTemplateLockedException::class);

        $template->with();
    }

    /**
     * @param \Closure(HostTemplate): HostTemplate $change
     */
    #[DataProvider('provideNumbersTooLargeForTheirColumn')]
    public function testANumberTooLargeForItsColumnIsRefused(\Closure $change): void
    {
        $template = $this->template();

        $this->expectException(\InvalidArgumentException::class);

        $change($template);
    }

    /**
     * @return iterable<string, array{\Closure(HostTemplate): HostTemplate}>
     */
    public static function provideNumbersTooLargeForTheirColumn(): iterable
    {
        yield 'max check attempts' => [static fn (HostTemplate $template): HostTemplate => $template->with(
            schedulingOptions: $template->schedulingOptions->with(maxCheckAttempts: HostTemplate::MAX_INTEGER + 1),
        )];

        yield 'acknowledgment timeout' => [static fn (HostTemplate $template): HostTemplate => $template->with(
            dataProcessing: $template->dataProcessing->with(acknowledgmentTimeout: HostTemplate::MAX_INTEGER + 1),
        )];

        yield 'recovery delay' => [static fn (HostTemplate $template): HostTemplate => $template->with(
            notifications: Notifications::default()->with(recoveryDelay: HostTemplate::MAX_INTEGER + 1),
        )];
    }

    public function testAPropertyLeftOutKeepsItsValueAndNullClearsIt(): void
    {
        $template = $this->template()->with(snmpVersion: null);

        self::assertNull($template->snmpVersion);
        self::assertSame('Alias', $template->alias?->value);
        self::assertSame(4, $template->timezoneId?->value);
    }

    public function testTheTemplateBeforeTheChangeIsLeftAsItWas(): void
    {
        $template = $this->template();

        $changed = $template->with(
            name: new HostTemplateName('renamed'),
            schedulingOptions: $template->schedulingOptions->with(maxCheckAttempts: 4),
        );

        self::assertSame('tpl', $template->name->value);
        self::assertSame(3, $template->schedulingOptions->maxCheckAttempts);
        self::assertSame(4, $changed->schedulingOptions->maxCheckAttempts);
        self::assertSame(1, $changed->id()->value);
        self::assertFalse($changed->hasSameConfigurationAs($template));
    }

    public function testTheSameValuesResentChangeNothing(): void
    {
        $template = $this->template();

        $changed = $template->with(
            name: new HostTemplateName('tpl'),
            alias: new HostAlias('Alias'),
            snmpVersion: SnmpVersionEnum::TwoC,
            extendedInformations: new ExtendedInformations(note: 'A note'),
            notifications: Notifications::default()->with(enabled: TriStateEnum::True, options: [NotificationOptionEnum::Down], interval: 30),
        );

        self::assertTrue($changed->hasSameConfigurationAs($template));
    }

    /**
     * @param \Closure(HostTemplate): HostTemplate $change
     */
    #[DataProvider('provideConfigurationChanges')]
    public function testEachConfigurationFieldCounts(\Closure $change): void
    {
        $template = $this->template();

        self::assertFalse($change($template)->hasSameConfigurationAs($template));
    }

    /**
     * @return iterable<string, array{\Closure(HostTemplate): HostTemplate}>
     */
    public static function provideConfigurationChanges(): iterable
    {
        yield 'name' => [static fn (HostTemplate $template): HostTemplate => $template->with(name: new HostTemplateName('other'))];

        yield 'alias' => [static fn (HostTemplate $template): HostTemplate => $template->with(alias: new HostAlias('Other'))];

        yield 'SNMP version' => [static fn (HostTemplate $template): HostTemplate => $template->with(snmpVersion: SnmpVersionEnum::Three)];

        yield 'SNMP community' => [static fn (HostTemplate $template): HostTemplate => $template->with(snmpCommunity: new SnmpCommunity('other'))];

        yield 'timezone' => [static fn (HostTemplate $template): HostTemplate => $template->with(timezoneId: new TimezoneId(9))];

        yield 'severity' => [static fn (HostTemplate $template): HostTemplate => $template->with(severityId: null)];

        yield 'check command' => [static fn (HostTemplate $template): HostTemplate => $template->with(checkOptions: $template->checkOptions->with(checkCommandId: new CommandId(6)))];

        yield 'arguments' => [static fn (HostTemplate $template): HostTemplate => $template->with(checkOptions: $template->checkOptions->with(args: ['-c']))];

        yield 'an extended text' => [static fn (HostTemplate $template): HostTemplate => $template->with(extendedInformations: new ExtendedInformations(note: 'another note'))];

        yield 'a scheduling option' => [static fn (HostTemplate $template): HostTemplate => $template->with(schedulingOptions: $template->schedulingOptions->with(activeCheckEnabled: TriStateEnum::False))];

        yield 'a data processing option' => [static fn (HostTemplate $template): HostTemplate => $template->with(dataProcessing: $template->dataProcessing->with(eventHandlerArgs: ['x']))];

        yield 'a notification setting' => [static fn (HostTemplate $template): HostTemplate => $template->with(notifications: $template->notifications?->with(contactAdditiveInheritance: true))];
    }

    public function testTheVaultEntryIsReleasedOnlyWhenNoSecretReferencesItAnyMore(): void
    {
        $vaulted = new SnmpCommunity('secret::vault::monitoring/hosts/uuid-1::_HOSTSNMPCOMMUNITY');
        $before = $this->template(snmpCommunity: $vaulted);
        $kept = $before->with();
        $cleared = $before->with(snmpCommunity: null);

        self::assertFalse($kept->releasesVaultEntryOf($before, new FakeVault()));
        self::assertTrue($cleared->releasesVaultEntryOf($before, new FakeVault()));
    }

    private function template(?SnmpCommunity $snmpCommunity = null): HostTemplate
    {
        return new HostTemplate(
            new HostTemplateId(1),
            new HostTemplateName('tpl'),
            new CheckOptions(new CommandId(5)),
            alias: new HostAlias('Alias'),
            snmpCommunity: $snmpCommunity ?? new SnmpCommunity('public'),
            snmpVersion: SnmpVersionEnum::TwoC,
            timezoneId: new TimezoneId(4),
            severityId: new HostSeverityId(8),
            extendedInformations: new ExtendedInformations(note: 'A note'),
            schedulingOptions: new SchedulingOptions(checkTimeperiodId: new TimePeriodId(2), maxCheckAttempts: 3),
            dataProcessing: new DataProcessing(acknowledgmentTimeout: 15),
            notifications: Notifications::default()->with(enabled: TriStateEnum::True, options: [NotificationOptionEnum::Down], interval: 30),
        );
    }
}
