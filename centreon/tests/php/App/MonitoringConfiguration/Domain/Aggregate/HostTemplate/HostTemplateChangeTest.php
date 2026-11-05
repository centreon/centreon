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
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateAlias;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateChange;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateCheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateCheckOptionsChange;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateDataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateDataProcessingChange;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateExtendedInformationsChange;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateNotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateNotifications;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateNotificationsChange;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateSchedulingOptions;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateSchedulingOptionsChange;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateSnmpCommunity;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateSnmpVersionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateLockedException;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\App\Shared\Double\FakeVault;

final class HostTemplateChangeTest extends TestCase
{
    public function testALockedTemplateRefusesEvenAnEmptyChange(): void
    {
        $template = new HostTemplate(new HostTemplateId(1), new HostTemplateName('locked'), isLocked: true);

        $this->expectException(HostTemplateLockedException::class);

        $template->withChange(new HostTemplateChange());
    }

    public function testAStoredValueOutOfTheBoundsSurvivesAChangeOfAnotherField(): void
    {
        $template = $this->template(schedulingOptions: new HostTemplateSchedulingOptions(maxCheckAttempts: 0));

        $template = $template->withChange(new HostTemplateChange(alias: new HostTemplateAlias('New alias')));

        self::assertSame('New alias', $template->alias?->value);
        self::assertSame(0, $template->schedulingOptions->maxCheckAttempts);
    }

    /**
     * @param \Closure(): HostTemplateChange $change
     */
    #[DataProvider('provideValuesOutOfTheBounds')]
    public function testAValueOutOfTheBoundsProvidedIsRefused(\Closure $change): void
    {
        $template = $this->template();

        $this->expectException(\InvalidArgumentException::class);

        $template->withChange($change());
    }

    /**
     * @return iterable<string, array{\Closure(): HostTemplateChange}>
     */
    public static function provideValuesOutOfTheBounds(): iterable
    {
        yield 'max check attempts 0' => [static fn (): HostTemplateChange => new HostTemplateChange(schedulingOptions: new HostTemplateSchedulingOptionsChange(maxCheckAttempts: 0))];

        yield 'retry check interval 0' => [static fn (): HostTemplateChange => new HostTemplateChange(schedulingOptions: new HostTemplateSchedulingOptionsChange(retryCheckInterval: 0))];

        yield 'acknowledgment timeout 0' => [static fn (): HostTemplateChange => new HostTemplateChange(dataProcessing: new HostTemplateDataProcessingChange(acknowledgmentTimeout: 0))];

        yield 'freshness threshold -1' => [static fn (): HostTemplateChange => new HostTemplateChange(dataProcessing: new HostTemplateDataProcessingChange(freshnessThreshold: -1))];

        yield 'low flap threshold 101' => [static fn (): HostTemplateChange => new HostTemplateChange(dataProcessing: new HostTemplateDataProcessingChange(lowFlapThreshold: 101))];

        yield 'high flap threshold -1' => [static fn (): HostTemplateChange => new HostTemplateChange(dataProcessing: new HostTemplateDataProcessingChange(highFlapThreshold: -1))];

        yield 'note 513' => [static fn (): HostTemplateChange => new HostTemplateChange(extendedInformations: new HostTemplateExtendedInformationsChange(note: str_repeat('n', 513)))];

        yield 'note url 2049' => [static fn (): HostTemplateChange => new HostTemplateChange(extendedInformations: new HostTemplateExtendedInformationsChange(noteUrl: str_repeat('u', 2049)))];

        yield 'comment 65536' => [static fn (): HostTemplateChange => new HostTemplateChange(extendedInformations: new HostTemplateExtendedInformationsChange(comment: str_repeat('c', 65536)))];

        yield 'blank note' => [static fn (): HostTemplateChange => new HostTemplateChange(extendedInformations: new HostTemplateExtendedInformationsChange(note: '  '))];

        yield 'notification interval -1' => [static fn (): HostTemplateChange => new HostTemplateChange(notifications: new HostTemplateNotificationsChange(interval: -1))];

        yield 'recovery delay 2147483648' => [static fn (): HostTemplateChange => new HostTemplateChange(notifications: new HostTemplateNotificationsChange(recoveryDelay: HostTemplate::MAX_INTEGER + 1))];
    }

    public function testRemovingTheCommandKeepsTheArguments(): void
    {
        $template = $this->template(checkOptions: new HostTemplateCheckOptions(new CommandId(5), ['-w']));

        $template = $template->withChange(new HostTemplateChange(checkOptions: new HostTemplateCheckOptionsChange(checkCommandId: null)));

        self::assertNull($template->checkOptions->checkCommandId);
        self::assertSame(['-w'], $template->checkOptions->args);
    }

    public function testArgumentsAreAcceptedWithoutACommand(): void
    {
        $template = $this->template(checkOptions: new HostTemplateCheckOptions());

        $template = $template->withChange(new HostTemplateChange(checkOptions: new HostTemplateCheckOptionsChange(args: ['-w'])));

        self::assertNull($template->checkOptions->checkCommandId);
        self::assertSame(['-w'], $template->checkOptions->args);
    }

    public function testAKeyLeftOutKeepsItsValueAndNullClearsIt(): void
    {
        $template = $this->template(
            extendedInformations: new HostTemplateExtendedInformations(note: 'A note', comment: 'A comment'),
            schedulingOptions: new HostTemplateSchedulingOptions(maxCheckAttempts: 3, normalCheckInterval: 5),
        );

        $template = $template->withChange(new HostTemplateChange(
            snmpVersion: null,
            extendedInformations: new HostTemplateExtendedInformationsChange(note: null),
            schedulingOptions: new HostTemplateSchedulingOptionsChange(maxCheckAttempts: null),
        ));

        self::assertNull($template->snmpVersion);
        self::assertSame('Alias', $template->alias?->value);
        self::assertNotNull($template->extendedInformations);
        self::assertNull($template->extendedInformations->note);
        self::assertSame('A comment', $template->extendedInformations->comment);
        self::assertNull($template->schedulingOptions->maxCheckAttempts);
        self::assertSame(5, $template->schedulingOptions->normalCheckInterval);
    }

    public function testClearingEveryExtendedTextLeavesNone(): void
    {
        $template = $this->template(extendedInformations: new HostTemplateExtendedInformations(note: 'A note'));

        $template = $template->withChange(new HostTemplateChange(extendedInformations: new HostTemplateExtendedInformationsChange(note: null)));

        self::assertNull($template->extendedInformations);
    }

    public function testTheTemplateBeforeTheChangeIsLeftAsItWas(): void
    {
        $template = $this->template(schedulingOptions: new HostTemplateSchedulingOptions(maxCheckAttempts: 3));

        $changed = $template->withChange(new HostTemplateChange(
            name: new HostTemplateName('renamed'),
            schedulingOptions: new HostTemplateSchedulingOptionsChange(maxCheckAttempts: 4),
            notifications: new HostTemplateNotificationsChange(interval: 7),
        ));

        self::assertSame('tpl', $template->name->value);
        self::assertSame(3, $template->schedulingOptions->maxCheckAttempts);
        self::assertSame(30, $template->notifications?->interval);
        self::assertSame(4, $changed->schedulingOptions->maxCheckAttempts);
        self::assertFalse($changed->hasSameConfigurationAs($template));
    }

    public function testTheSameValuesResentChangeNothing(): void
    {
        $template = $this->template();

        $changed = $template->withChange(new HostTemplateChange(
            name: new HostTemplateName('tpl'),
            alias: new HostTemplateAlias('Alias'),
            snmpVersion: HostTemplateSnmpVersionEnum::TwoC,
            extendedInformations: new HostTemplateExtendedInformationsChange(note: ' kept as stored '),
            notifications: new HostTemplateNotificationsChange(enabled: TriStateEnum::True, interval: 30),
        ));

        self::assertTrue($changed->hasSameConfigurationAs($template));
    }

    /**
     * @param \Closure(): HostTemplateChange $change
     */
    #[DataProvider('provideConfigurationChanges')]
    public function testEachConfigurationFieldCounts(\Closure $change): void
    {
        $template = $this->template();

        $changed = $template->withChange($change());

        self::assertFalse($changed->hasSameConfigurationAs($template));
    }

    /**
     * @return iterable<string, array{\Closure(): HostTemplateChange}>
     */
    public static function provideConfigurationChanges(): iterable
    {
        yield 'name' => [static fn (): HostTemplateChange => new HostTemplateChange(name: new HostTemplateName('other'))];

        yield 'alias' => [static fn (): HostTemplateChange => new HostTemplateChange(alias: new HostTemplateAlias('Other'))];

        yield 'SNMP version' => [static fn (): HostTemplateChange => new HostTemplateChange(snmpVersion: HostTemplateSnmpVersionEnum::Three)];

        yield 'SNMP community' => [static fn (): HostTemplateChange => new HostTemplateChange(snmpCommunity: new HostTemplateSnmpCommunity('other'))];

        yield 'timezone' => [static fn (): HostTemplateChange => new HostTemplateChange(timezoneId: new TimezoneId(9))];

        yield 'severity' => [static fn (): HostTemplateChange => new HostTemplateChange(severityId: null)];

        yield 'check command' => [static fn (): HostTemplateChange => new HostTemplateChange(checkOptions: new HostTemplateCheckOptionsChange(checkCommandId: new CommandId(6)))];

        yield 'arguments' => [static fn (): HostTemplateChange => new HostTemplateChange(checkOptions: new HostTemplateCheckOptionsChange(args: ['-c']))];

        yield 'an extended text' => [static fn (): HostTemplateChange => new HostTemplateChange(extendedInformations: new HostTemplateExtendedInformationsChange(note: 'kept as stored'))];

        yield 'a scheduling option' => [static fn (): HostTemplateChange => new HostTemplateChange(schedulingOptions: new HostTemplateSchedulingOptionsChange(activeCheckEnabled: TriStateEnum::False))];

        yield 'a data processing option' => [static fn (): HostTemplateChange => new HostTemplateChange(dataProcessing: new HostTemplateDataProcessingChange(eventHandlerArgs: ['x']))];

        yield 'a notification setting' => [static fn (): HostTemplateChange => new HostTemplateChange(notifications: new HostTemplateNotificationsChange(contactAdditiveInheritance: true))];
    }

    public function testContactsComparedAsSets(): void
    {
        $contacts = static fn (int ...$ids): Collection => new Collection(array_map(static fn (int $id): NotificationContactId => new NotificationContactId($id), $ids), NotificationContactId::class);

        $notifications = new HostTemplateNotifications(contactIds: $contacts(1, 2), contactGroupIds: new Collection([new ContactGroupId(3)], ContactGroupId::class));

        self::assertTrue($notifications->equals(new HostTemplateNotifications(contactIds: $contacts(2, 1), contactGroupIds: new Collection([new ContactGroupId(3)], ContactGroupId::class))));
        self::assertFalse($notifications->equals(new HostTemplateNotifications(contactIds: $contacts(2), contactGroupIds: new Collection([new ContactGroupId(3)], ContactGroupId::class))));
        self::assertFalse($notifications->equals(new HostTemplateNotifications(contactIds: $contacts(1, 2))));
    }

    public function testTheVaultEntryIsReleasedOnlyWhenNoSecretReferencesItAnyMore(): void
    {
        $vaulted = new HostTemplateSnmpCommunity('secret::vault::monitoring/hosts/uuid-1::_HOSTSNMPCOMMUNITY');
        $before = $this->template(snmpCommunity: $vaulted);
        $kept = $before->withChange(new HostTemplateChange());
        $cleared = $before->withChange(new HostTemplateChange(snmpCommunity: null));

        self::assertFalse($kept->releasesVaultEntryOf($before, new FakeVault()));
        self::assertTrue($cleared->releasesVaultEntryOf($before, new FakeVault()));
    }

    private function template(
        ?HostTemplateCheckOptions $checkOptions = null,
        ?HostTemplateExtendedInformations $extendedInformations = null,
        ?HostTemplateSchedulingOptions $schedulingOptions = null,
        ?HostTemplateSnmpCommunity $snmpCommunity = null,
    ): HostTemplate {
        return new HostTemplate(
            new HostTemplateId(1),
            new HostTemplateName('tpl'),
            $checkOptions ?? new HostTemplateCheckOptions(new CommandId(5)),
            alias: new HostTemplateAlias('Alias'),
            snmpCommunity: $snmpCommunity ?? new HostTemplateSnmpCommunity('public'),
            snmpVersion: HostTemplateSnmpVersionEnum::TwoC,
            timezoneId: new TimezoneId(4),
            severityId: new HostSeverityId(8),
            extendedInformations: $extendedInformations ?? new HostTemplateExtendedInformations(note: ' kept as stored '),
            schedulingOptions: $schedulingOptions ?? new HostTemplateSchedulingOptions(checkTimeperiodId: new TimePeriodId(2), maxCheckAttempts: 3),
            dataProcessing: new HostTemplateDataProcessing(acknowledgmentTimeout: 15),
            notifications: new HostTemplateNotifications(enabled: TriStateEnum::True, options: [HostTemplateNotificationOptionEnum::Down], interval: 30),
        );
    }
}
