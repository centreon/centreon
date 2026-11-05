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

namespace Tests\App\ActivityLogging\Domain\Factory;

use App\ActivityLogging\Domain\Aggregate\ActionEnum;
use App\ActivityLogging\Domain\Aggregate\Actor;
use App\ActivityLogging\Domain\Aggregate\ActorId;
use App\ActivityLogging\Domain\Aggregate\TargetTypeEnum;
use App\ActivityLogging\Domain\Factory\HostTemplateActivityLogFactory;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateAlias;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateCheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateDataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateMacro;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateNotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateNotifications;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateSchedulingOptions;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateSnmpCommunity;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateSnmpVersionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HostTemplateActivityLogFactoryTest extends TestCase
{
    /**
     * Add is both a creation and a duplicate.
     */
    #[DataProvider('provideActionsWithDetails')]
    public function testItLogsEveryFieldOfTheTemplate(ActionEnum $action): void
    {
        $log = new HostTemplateActivityLogFactory()->create($action, $this->fullTemplate(), new Actor(new ActorId(3)), new \DateTimeImmutable());

        self::assertSame($action, $log->action);
        self::assertSame(TargetTypeEnum::HostTemplate, $log->target->type);
        self::assertSame(12, $log->target->id->value);
        self::assertSame('generic', $log->target->name->value);
        self::assertSame(
            [
                'host_name' => 'generic',
                'host_alias' => 'Generic',
                'host_snmp_version' => '2c',
                'host_snmp_community' => 'public',
                'host_location' => '4',
                'severity_id' => '5',
                'ehi_notes_url' => 'https://notes.example.com',
                'ehi_notes' => 'A note',
                'ehi_action_url' => 'https://action.example.com',
                'ehi_icon_image' => '6',
                'ehi_icon_image_alt' => 'An icon',
                'host_comment' => 'A comment',
                'timeperiod_tp_id' => '7',
                'host_max_check_attempts' => '3',
                'host_check_interval' => '5',
                'host_retry_check_interval' => '1',
                'host_active_checks_enabled' => '1',
                'host_passive_checks_enabled' => '0',
                'host_check_freshness' => '1',
                'host_freshness_threshold' => '60',
                'host_flap_detection_enabled' => '2',
                'host_low_flap_threshold' => '10',
                'host_high_flap_threshold' => '40',
                'host_event_handler_enabled' => '0',
                'command_command_id2' => '8',
                'command_command_id_arg2' => '!restart',
                'host_acknowledgement_timeout' => '15',
                'command_command_id' => '9',
                'command_command_id_arg1' => '!-w!80#BR#90',
                'host_notifications_enabled' => '1',
                'host_notification_options' => 'd,r',
                'host_notification_interval' => '30',
                'timeperiod_tp_id2' => '10',
                'host_first_notification_delay' => '2',
                'host_recovery_notification_delay' => '',
                'contact_additive_inheritance' => '1',
                'cg_additive_inheritance' => '0',
                'host_cs' => '20,21',
                'host_cgs' => '22',
            ],
            $log->details,
        );
    }

    public function testAnUnsetValueIsLoggedAsAnEmptyString(): void
    {
        $log = new HostTemplateActivityLogFactory()->create(
            ActionEnum::Add,
            new HostTemplate(new HostTemplateId(12), new HostTemplateName('bare')),
            new Actor(new ActorId(3)),
            new \DateTimeImmutable(),
        );

        self::assertSame('', $log->details['host_alias']);
        self::assertSame('', $log->details['ehi_notes']);
        self::assertSame('2', $log->details['host_active_checks_enabled']);
        self::assertArrayNotHasKey('host_notifications_enabled', $log->details);
    }

    public function testADeletionIsLoggedWithoutDetails(): void
    {
        $log = new HostTemplateActivityLogFactory()->create(ActionEnum::Delete, $this->fullTemplate(), new Actor(new ActorId(3)), new \DateTimeImmutable());

        self::assertSame(ActionEnum::Delete, $log->action);
        self::assertSame([], $log->details);
    }

    /**
     * @return iterable<string, array{ActionEnum}>
     */
    public static function provideActionsWithDetails(): iterable
    {
        yield 'add' => [ActionEnum::Add];

        yield 'update' => [ActionEnum::Update];
    }

    private function fullTemplate(): HostTemplate
    {
        return new HostTemplate(
            new HostTemplateId(12),
            new HostTemplateName('generic'),
            new HostTemplateCheckOptions(
                new CommandId(9),
                ['-w', "80\n90"],
                new Collection([new HostTemplateMacro(new HostTemplateMacroName('PORT'), '443', false)], HostTemplateMacro::class),
            ),
            alias: new HostTemplateAlias('Generic'),
            parentTemplateIds: new Collection([new HostTemplateId(30)], HostTemplateId::class),
            snmpCommunity: new HostTemplateSnmpCommunity('public'),
            snmpVersion: HostTemplateSnmpVersionEnum::TwoC,
            timezoneId: new TimezoneId(4),
            severityId: new HostSeverityId(5),
            extendedInformations: new HostTemplateExtendedInformations(
                noteUrl: 'https://notes.example.com',
                note: 'A note',
                actionUrl: 'https://action.example.com',
                iconId: new MediaId(6),
                altIcon: 'An icon',
                comment: 'A comment',
            ),
            schedulingOptions: new HostTemplateSchedulingOptions(
                checkTimeperiodId: new TimePeriodId(7),
                maxCheckAttempts: 3,
                normalCheckInterval: 5,
                retryCheckInterval: 1,
                activeCheckEnabled: TriStateEnum::True,
                passiveCheckEnabled: TriStateEnum::False,
            ),
            dataProcessing: new HostTemplateDataProcessing(
                checkFreshness: TriStateEnum::True,
                eventHandlerEnabled: TriStateEnum::False,
                acknowledgmentTimeout: 15,
                freshnessThreshold: 60,
                lowFlapThreshold: 10,
                highFlapThreshold: 40,
                eventHandlerCommandId: new CommandId(8),
                eventHandlerArgs: ['restart'],
            ),
            notifications: new HostTemplateNotifications(
                enabled: TriStateEnum::True,
                contactIds: new Collection([new NotificationContactId(20), new NotificationContactId(21)], NotificationContactId::class),
                contactGroupIds: new Collection([new ContactGroupId(22)], ContactGroupId::class),
                options: [HostTemplateNotificationOptionEnum::Down, HostTemplateNotificationOptionEnum::Recovery],
                interval: 30,
                periodId: new TimePeriodId(10),
                firstDelay: 2,
                contactAdditiveInheritance: true,
            ),
            categoryIds: new Collection([new HostCategoryId(31)], HostCategoryId::class),
            linkedServiceTemplateIds: new Collection([new ServiceTemplateId(32)], ServiceTemplateId::class),
        );
    }
}
