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
use App\ActivityLogging\Domain\Aggregate\ActivityLog;
use App\ActivityLogging\Domain\Aggregate\Actor;
use App\ActivityLogging\Domain\Aggregate\ActorId;
use App\ActivityLogging\Domain\Aggregate\TargetTypeEnum;
use App\ActivityLogging\Domain\Factory\HostActivityLogFactory;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\CheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Host\DataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\Host\ExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\Host\GeoCoordinates;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAlias;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\NotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Notifications;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SchedulingOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SnmpCommunity;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SnmpVersionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The factory spells out the legacy storage formats itself (it cannot reuse the Dbal transformer
 * from Domain), so this is the only place a drift between the two would be caught.
 */
final class HostActivityLogFactoryTest extends TestCase
{
    public function testItLogsTheHostWithoutNotificationKeysWhenItHasNoNotifications(): void
    {
        $firedAt = new \DateTimeImmutable();

        $activityLog = (new HostActivityLogFactory())->create(
            action: ActionEnum::Add,
            aggregate: $this->host(notifications: null),
            firedBy: new Actor(id: new ActorId(1)),
            firedAt: $firedAt,
        );

        self::assertSame(ActionEnum::Add, $activityLog->action);
        self::assertSame(42, $activityLog->target->id->value);
        self::assertSame('server-01', $activityLog->target->name->value);
        self::assertSame(TargetTypeEnum::Host, $activityLog->target->type);
        self::assertSame($firedAt, $activityLog->performedAt);
        self::assertSame([
            'host_name' => 'server-01',
            'host_alias' => '',
            'host_address' => '127.0.0.1',
            'host_activate' => '1',
            'nagios_server_id' => '1',
            'host_snmp_version' => '',
            'host_snmp_community' => '',
            'host_location' => '',
            'severity_id' => '',
            'ehi_notes_url' => '',
            'ehi_notes' => '',
            'ehi_action_url' => '',
            'ehi_icon_image' => '',
            'ehi_icon_image_alt' => '',
            'host_comment' => '',
            'geo_coords' => '',
            'timeperiod_tp_id' => '',
            'host_max_check_attempts' => '',
            'host_check_interval' => '',
            'host_retry_check_interval' => '',
            'host_active_checks_enabled' => '2',
            'host_passive_checks_enabled' => '2',
            'host_check_freshness' => '2',
            'host_freshness_threshold' => '',
            'host_flap_detection_enabled' => '2',
            'host_low_flap_threshold' => '',
            'host_high_flap_threshold' => '',
            'host_event_handler_enabled' => '2',
            'command_command_id2' => '',
            'command_command_id_arg2' => '',
            'host_acknowledgement_timeout' => '',
            'command_command_id' => '',
            'command_command_id_arg1' => '',
        ], $activityLog->details);
    }

    public function testItLogsEveryNotificationFieldInTheLegacyStorageFormat(): void
    {
        $notifications = new Notifications(
            enabled: TriStateEnum::True,
            contactIds: new Collection([new NotificationContactId(3), new NotificationContactId(5)], NotificationContactId::class),
            contactGroupIds: new Collection([new ContactGroupId(7)], ContactGroupId::class),
            options: [
                NotificationOptionEnum::Down,
                NotificationOptionEnum::Unreachable,
                NotificationOptionEnum::Recovery,
                NotificationOptionEnum::Flapping,
                NotificationOptionEnum::DowntimeScheduled,
            ],
            interval: 30,
            periodId: new TimePeriodId(2),
            firstDelay: 10,
            recoveryDelay: 20,
            contactAdditiveInheritance: true,
            contactGroupAdditiveInheritance: false,
        );

        $details = array_intersect_key(
            $this->createActivityLogDetails($notifications),
            array_flip([
                'host_notifications_enabled',
                'host_notification_options',
                'host_notification_interval',
                'timeperiod_tp_id2',
                'host_first_notification_delay',
                'host_recovery_notification_delay',
                'contact_additive_inheritance',
                'cg_additive_inheritance',
                'host_cs',
                'host_cgs',
            ]),
        );

        self::assertSame([
            'host_notifications_enabled' => '1',
            'host_notification_options' => 'd,u,r,f,s',
            'host_notification_interval' => '30',
            'timeperiod_tp_id2' => '2',
            'host_first_notification_delay' => '10',
            'host_recovery_notification_delay' => '20',
            'contact_additive_inheritance' => '1',
            'cg_additive_inheritance' => '0',
            'host_cs' => '3,5',
            'host_cgs' => '7',
        ], $details);
    }

    /**
     * The block a client left out: Default tri-state, and empty strings where legacy logs an
     * unset field.
     */
    public function testItLogsAnEmptyNotificationsBlock(): void
    {
        $details = $this->createActivityLogDetails(new Notifications(
            enabled: TriStateEnum::UseDefault,
            contactIds: new Collection([], NotificationContactId::class),
            contactGroupIds: new Collection([], ContactGroupId::class),
        ));

        self::assertSame('2', $details['host_notifications_enabled']);
        self::assertSame('', $details['host_notification_options']);
        self::assertSame('', $details['host_notification_interval']);
        self::assertSame('', $details['timeperiod_tp_id2']);
        self::assertSame('', $details['host_cs']);
        self::assertSame('', $details['host_cgs']);
    }

    public function testItLogsTheNoneOptionAsN(): void
    {
        $details = $this->createActivityLogDetails(new Notifications(
            enabled: TriStateEnum::False,
            contactIds: new Collection([], NotificationContactId::class),
            contactGroupIds: new Collection([], ContactGroupId::class),
            options: [NotificationOptionEnum::None],
        ));

        self::assertSame('0', $details['host_notifications_enabled']);
        self::assertSame('n', $details['host_notification_options']);
    }

    public function testItLogsEveryPropertyOfACompleteHostInTheLegacyStorageFormat(): void
    {
        $host = $this->host(
            null,
            alias: new HostAlias('front server'),
            snmpVersion: SnmpVersionEnum::TwoC,
            snmpCommunity: new SnmpCommunity('secret::vault::monitoring/hosts/uuid-1::_HOSTSNMPCOMMUNITY'),
            timezoneId: new TimezoneId(4),
            severityId: new HostSeverityId(6),
            extendedInformations: new ExtendedInformations(
                noteUrl: 'https://notes.example.com',
                note: 'a note',
                actionUrl: 'https://actions.example.com',
                iconId: new MediaId(8),
                altIcon: 'server icon',
                comment: 'a comment',
                geoCoordinates: new GeoCoordinates('48.85', '2.35'),
            ),
            schedulingOptions: new SchedulingOptions(
                checkTimeperiodId: new TimePeriodId(9),
                maxCheckAttempts: 3,
                normalCheckInterval: 5,
                retryCheckInterval: 1,
                activeCheckEnabled: TriStateEnum::True,
                passiveCheckEnabled: TriStateEnum::False,
            ),
            dataProcessing: new DataProcessing(
                checkFreshness: TriStateEnum::True,
                flapDetectionEnabled: TriStateEnum::False,
                eventHandlerEnabled: TriStateEnum::True,
                acknowledgmentTimeout: 15,
                freshnessThreshold: 120,
                lowFlapThreshold: 20,
                highFlapThreshold: 40,
                eventHandlerCommandId: new CommandId(11),
                eventHandlerArgs: ['restart', 'now'],
            ),
            checkOptions: new CheckOptions(new CommandId(10), ['-w', "80\n90"]),
        );

        $details = $this->create(ActionEnum::Add, $host)->details;

        self::assertSame([
            'host_alias' => 'front server',
            'nagios_server_id' => '1',
            'host_snmp_version' => '2c',
            'host_snmp_community' => 'secret::vault::monitoring/hosts/uuid-1::_HOSTSNMPCOMMUNITY',
            'host_location' => '4',
            'severity_id' => '6',
            'ehi_notes_url' => 'https://notes.example.com',
            'ehi_notes' => 'a note',
            'ehi_action_url' => 'https://actions.example.com',
            'ehi_icon_image' => '8',
            'ehi_icon_image_alt' => 'server icon',
            'host_comment' => 'a comment',
            'geo_coords' => '48.85,2.35',
            'timeperiod_tp_id' => '9',
            'host_max_check_attempts' => '3',
            'host_check_interval' => '5',
            'host_retry_check_interval' => '1',
            'host_active_checks_enabled' => '1',
            'host_passive_checks_enabled' => '0',
            'host_check_freshness' => '1',
            'host_freshness_threshold' => '120',
            'host_flap_detection_enabled' => '0',
            'host_low_flap_threshold' => '20',
            'host_high_flap_threshold' => '40',
            'host_event_handler_enabled' => '1',
            'command_command_id2' => '11',
            'command_command_id_arg2' => '!restart!now',
            'host_acknowledgement_timeout' => '15',
            'command_command_id' => '10',
            'command_command_id_arg1' => '!-w!80#BR#90',
        ], array_diff_key($details, array_flip(['host_name', 'host_address', 'host_activate'])));
    }

    public function testItDoesNotLogTheMacrosNorTheRelationsOfTheHost(): void
    {
        $host = $this->host(
            null,
            checkOptions: new CheckOptions(null, macros: [
                new HostMacro(new HostMacroName('TOKEN'), 'plain-secret', isPassword: true),
            ]),
            templateIds: new Collection([new HostTemplateId(3)], HostTemplateId::class),
            hostGroupIds: new Collection([new HostGroupId(4)], HostGroupId::class),
        );

        $details = $this->create(ActionEnum::Add, $host)->details;

        self::assertNotContains('plain-secret', $details);
        self::assertArrayNotHasKey('host_hgs', $details);
        self::assertArrayNotHasKey('host_templates', $details);
        self::assertArrayNotHasKey('host_macros', $details);
    }

    /**
     * Legacy records the properties of a host when it is created only: deleting, enabling or
     * disabling one logs the bare action.
     */
    #[DataProvider('actionsWithoutDetails')]
    public function testItLogsNoDetailsForAnActionOtherThanACreation(ActionEnum $action): void
    {
        $activityLog = $this->create($action, $this->host(null));

        self::assertSame($action, $activityLog->action);
        self::assertSame('server-01', $activityLog->target->name->value);
        self::assertSame([], $activityLog->details);
    }

    /**
     * @return iterable<string, array{ActionEnum}>
     */
    public static function actionsWithoutDetails(): iterable
    {
        yield 'delete' => [ActionEnum::Delete];

        yield 'enable' => [ActionEnum::Enable];

        yield 'disable' => [ActionEnum::Disable];
    }

    private function create(ActionEnum $action, Host $host): ActivityLog
    {
        return (new HostActivityLogFactory())->create(
            action: $action,
            aggregate: $host,
            firedBy: new Actor(id: new ActorId(1)),
            firedAt: new \DateTimeImmutable(),
        );
    }

    /**
     * @return array<string, string>
     */
    private function createActivityLogDetails(Notifications $notifications): array
    {
        return (new HostActivityLogFactory())->create(
            action: ActionEnum::Add,
            aggregate: $this->host($notifications),
            firedBy: new Actor(id: new ActorId(1)),
            firedAt: new \DateTimeImmutable(),
        )->details;
    }

    /**
     * @param ?Collection<HostTemplateId> $templateIds
     * @param ?Collection<HostGroupId> $hostGroupIds
     */
    private function host(
        ?Notifications $notifications,
        ?HostAlias $alias = null,
        ?SnmpVersionEnum $snmpVersion = null,
        ?SnmpCommunity $snmpCommunity = null,
        ?TimezoneId $timezoneId = null,
        ?HostSeverityId $severityId = null,
        ?ExtendedInformations $extendedInformations = null,
        SchedulingOptions $schedulingOptions = new SchedulingOptions(),
        DataProcessing $dataProcessing = new DataProcessing(),
        CheckOptions $checkOptions = new CheckOptions(null),
        ?Collection $templateIds = null,
        ?Collection $hostGroupIds = null,
    ): Host {
        $host = new Host(
            id: null,
            name: new HostName('server-01'),
            alias: $alias,
            address: new HostAddress('127.0.0.1'),
            activated: true,
            pollerId: new PollerId(1),
            templateIds: $templateIds ?? new Collection([], HostTemplateId::class),
            hostGroupIds: $hostGroupIds ?? new Collection([], HostGroupId::class),
            snmpVersion: $snmpVersion,
            snmpCommunity: $snmpCommunity,
            timezoneId: $timezoneId,
            severityId: $severityId,
            extendedInformations: $extendedInformations,
            schedulingOptions: $schedulingOptions,
            dataProcessing: $dataProcessing,
            checkOptions: $checkOptions,
            notifications: $notifications,
        );

        $reflection = new \ReflectionProperty(AggregateRoot::class, 'id');
        $reflection->setValue($host, new HostId(42));

        return $host;
    }
}
